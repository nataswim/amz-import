/**
 * Amazon Product Importer - Import Interface JavaScript
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/admin/js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * Import Interface Controller
     */
    const ImportInterface = {
        
        // Configuration
        config: {
            searchDelay: 500,
            maxRetries: 3,
            batchSize: 5,
            currentPage: 1,
            itemsPerPage: 20,
            searchTimeout: null,
            importInProgress: false,
            selectedProducts: new Set(),
            searchResults: [],
            importQueue: [],
            importProgress: {
                current: 0,
                total: 0,
                success: 0,
                failed: 0
            }
        },

        // DOM Elements
        elements: {
            searchInput: null,
            searchButton: null,
            searchType: null,
            resultsContainer: null,
            progressModal: null,
            bulkImportModal: null,
            historyModal: null
        },

        /**
         * Initialize the import interface
         */
        init() {
            this.bindElements();
            this.bindEvents();
            this.initializeModals();
            this.loadSearchHistory();
            
            // Check if API is configured
            if (!amazon_importer_ajax.api_configured) {
                this.showApiConfigurationWarning();
            }

            console.log('Amazon Import Interface initialized');
        },

        /**
         * Bind DOM elements
         */
        bindElements() {
            this.elements = {
                searchInput: $('#amazon-search-query'),
                bulkAsinInput: $('#amazon-bulk-asin'),
                searchButton: $('#amazon-search-btn'),
                searchType: $('input[name="search_type"]'),
                resultsContainer: $('#amazon-search-results'),
                resultsHeader: $('#results-header'),
                resultsCount: $('#results-count'),
                searchStatus: $('#search-status'),
                progressModal: $('#import-progress-modal'),
                bulkImportModal: $('#bulk-import-modal'),
                historyModal: $('#import-history-modal'),
                advancedFilters: $('#advanced-filters'),
                categoryFilter: $('#amazon-category-filter'),
                sortBy: $('#amazon-sort-by'),
                itemCount: $('#amazon-item-count')
            };
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Search functionality
            this.elements.searchButton.on('click', (e) => this.handleSearch(e));
            this.elements.searchInput.on('keypress', (e) => {
                if (e.which === 13) this.handleSearch(e);
            });
            this.elements.searchInput.on('input', () => this.handleSearchInputChange());

            // Search type changes
            this.elements.searchType.on('change', (e) => this.handleSearchTypeChange(e));

            // Filter changes
            this.elements.categoryFilter.on('change', () => this.handleFilterChange());
            this.elements.sortBy.on('change', () => this.handleFilterChange());
            this.elements.itemCount.on('change', () => this.handleFilterChange());

            // Advanced filters toggle
            $('#advanced-filters-toggle').on('click', () => this.toggleAdvancedFilters());

            // View toggle
            $('.view-btn').on('click', (e) => this.handleViewToggle(e));

            // Bulk actions
            $('#select-all-btn').on('click', () => this.selectAllProducts());
            $('#import-selected-btn').on('click', () => this.importSelectedProducts());

            // Load more results
            $('#load-more-btn').on('click', () => this.loadMoreResults());

            // Modal controls
            $('.modal-close, .modal-overlay').on('click', (e) => this.closeModal(e));
            $('#bulk-import-btn').on('click', () => this.openBulkImportModal());
            $('#import-history-btn').on('click', () => this.openImportHistoryModal());

            // Bulk import functionality
            $('#csv-upload-btn').on('click', () => $('#csv-file-input').click());
            $('#csv-file-input').on('change', (e) => this.handleCsvFileSelect(e));
            $('#wishlist-import-btn').on('click', () => this.handleWishlistImport());

            // Import progress controls
            $('#cancel-import-btn').on('click', () => this.cancelImport());
            $('#close-modal-btn').on('click', () => this.closeProgressModal());

            // Prevent form submission on Enter in search
            $(document).on('keypress', '#amazon-search-query, #amazon-bulk-asin', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    ImportInterface.handleSearch(e);
                }
            });

            // Product card interactions
            $(document).on('click', '.product-card', (e) => this.handleProductCardClick(e));
            $(document).on('change', '.product-checkbox', (e) => this.handleProductSelection(e));
            $(document).on('click', '.import-single-btn', (e) => this.handleSingleImport(e));

            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        },

        /**
         * Handle search functionality
         */
        async handleSearch(e) {
            e.preventDefault();

            if (this.config.importInProgress) {
                this.showNotification('warning', amazon_importer_ajax.strings.import_in_progress);
                return;
            }

            const searchType = this.elements.searchType.filter(':checked').val();
            let searchQuery = '';

            if (searchType === 'bulk_asin') {
                searchQuery = this.elements.bulkAsinInput.val().trim();
            } else {
                searchQuery = this.elements.searchInput.val().trim();
            }

            if (!searchQuery) {
                this.showNotification('error', 'Veuillez entrer une recherche');
                return;
            }

            // Validate ASIN format for ASIN searches
            if (searchType === 'asin' && !this.validateAsin(searchQuery)) {
                this.showNotification('error', 'Format ASIN invalide (ex: B08N5WRWNW)');
                return;
            }

            if (searchType === 'bulk_asin') {
                const asins = this.parseAsinList(searchQuery);
                if (asins.length === 0) {
                    this.showNotification('error', 'Aucun ASIN valide trouvé');
                    return;
                }
            }

            // Reset pagination
            this.config.currentPage = 1;
            this.config.searchResults = [];

            await this.performSearch(searchQuery, searchType);
        },

        /**
         * Perform the actual search
         */
        async performSearch(query, searchType, loadMore = false) {
            try {
                this.setSearchingState(true);
                this.hideResults();

                const searchParams = this.buildSearchParams(query, searchType);
                
                const response = await this.makeAjaxRequest('api_search_products', searchParams);

                if (response.success) {
                    if (loadMore) {
                        this.config.searchResults = [...this.config.searchResults, ...response.products];
                    } else {
                        this.config.searchResults = response.products;
                    }

                    this.displaySearchResults(this.config.searchResults, response.total_results);
                    this.saveSearchHistory(query, searchType);
                } else {
                    this.showNotification('error', response.error || 'Erreur de recherche');
                }

            } catch (error) {
                this.showNotification('error', 'Erreur de connexion: ' + error.message);
            } finally {
                this.setSearchingState(false);
            }
        },

        /**
         * Build search parameters
         */
        buildSearchParams(query, searchType) {
            const params = {
                query: query,
                search_type: searchType,
                page: this.config.currentPage,
                item_count: this.elements.itemCount.val(),
                nonce: amazon_importer_ajax.nonce
            };

            // Add filters
            const category = this.elements.categoryFilter.val();
            if (category) params.category = category;

            const sortBy = this.elements.sortBy.val();
            if (sortBy) params.sort_by = sortBy;

            // Add advanced filters if visible
            if (this.elements.advancedFilters.is(':visible')) {
                const minPrice = $('#min-price').val();
                const maxPrice = $('#max-price').val();
                const brand = $('#brand-filter').val();
                const condition = $('#condition-filter').val();

                if (minPrice) params.min_price = minPrice;
                if (maxPrice) params.max_price = maxPrice;
                if (brand) params.brand = brand;
                if (condition) params.condition = condition;
            }

            return params;
        },

        /**
         * Display search results
         */
        displaySearchResults(products, totalResults) {
            if (!products || products.length === 0) {
                this.showNoResults();
                return;
            }

            // Update results header
            this.elements.resultsCount.text(`${products.length} produit(s) trouvé(s)`);
            this.elements.resultsHeader.show();

            // Generate product cards HTML
            const productsHtml = products.map(product => this.generateProductCard(product)).join('');
            this.elements.resultsContainer.html(productsHtml);

            // Show pagination if needed
            this.updatePagination(products.length, totalResults);

            // Initialize product interactions
            this.initializeProductCards();

            // Show results
            this.showResults();
        },

        /**
         * Generate HTML for a product card
         */
        generateProductCard(product) {
            const price = this.formatPrice(product);
            const image = this.getProductImage(product);
            const title = this.truncateText(product.ItemInfo?.Title?.DisplayValue || 'Titre non disponible', 60);
            const features = this.getProductFeatures(product);

            return `
                <div class="product-card" data-asin="${product.ASIN}">
                    <div class="product-card-header">
                        <input type="checkbox" class="product-checkbox" value="${product.ASIN}">
                        <span class="product-asin">${product.ASIN}</span>
                    </div>
                    <div class="product-image">
                        <img src="${image}" alt="${title}" loading="lazy">
                    </div>
                    <div class="product-details">
                        <h3 class="product-title" title="${product.ItemInfo?.Title?.DisplayValue || ''}">${title}</h3>
                        <div class="product-price">${price}</div>
                        <div class="product-features">
                            ${features}
                        </div>
                        <div class="product-actions">
                            <button type="button" class="button button-primary import-single-btn" data-asin="${product.ASIN}">
                                <span class="dashicons dashicons-download"></span>
                                Importer
                            </button>
                            <a href="${product.DetailPageURL}" target="_blank" class="button button-secondary view-amazon-btn">
                                <span class="dashicons dashicons-external"></span>
                                Voir sur Amazon
                            </a>
                        </div>
                    </div>
                    <div class="import-status" style="display: none;">
                        <div class="import-spinner"></div>
                        <span class="import-message">Import en cours...</span>
                    </div>
                </div>
            `;
        },

        /**
         * Handle single product import
         */
        async handleSingleImport(e) {
            e.preventDefault();
            e.stopPropagation();

            const button = $(e.currentTarget);
            const asin = button.data('asin');
            const productCard = button.closest('.product-card');

            if (this.config.importInProgress) {
                this.showNotification('warning', 'Un import est déjà en cours');
                return;
            }

            try {
                // Show import status
                this.setProductImportState(productCard, 'importing');

                const response = await this.makeAjaxRequest('api_import_product', {
                    asin: asin,
                    nonce: amazon_importer_ajax.nonce
                });

                if (response.success) {
                    this.setProductImportState(productCard, 'success');
                    this.showNotification('success', `Produit ${asin} importé avec succès`);
                    
                    // Add link to edit product
                    setTimeout(() => {
                        const editLink = `<a href="/wp-admin/post.php?post=${response.product_id}&action=edit" class="button button-secondary">Modifier le produit</a>`;
                        productCard.find('.product-actions').append(editLink);
                    }, 1000);
                } else {
                    this.setProductImportState(productCard, 'error');
                    this.showNotification('error', response.error || 'Erreur lors de l\'import');
                }

            } catch (error) {
                this.setProductImportState(productCard, 'error');
                this.showNotification('error', 'Erreur de connexion: ' + error.message);
            }
        },

        /**
         * Handle bulk import of selected products
         */
        async importSelectedProducts() {
            const selectedAsins = Array.from(this.config.selectedProducts);

            if (selectedAsins.length === 0) {
                this.showNotification('warning', amazon_importer_ajax.strings.select_products);
                return;
            }

            if (this.config.importInProgress) {
                this.showNotification('warning', 'Un import est déjà en cours');
                return;
            }

            // Show progress modal
            this.openProgressModal();
            this.config.importInProgress = true;
            this.config.importQueue = [...selectedAsins];
            this.config.importProgress = {
                current: 0,
                total: selectedAsins.length,
                success: 0,
                failed: 0
            };

            await this.processBulkImport();
        },

        /**
         * Process bulk import with batch processing
         */
        async processBulkImport() {
            const batches = this.chunkArray(this.config.importQueue, this.config.batchSize);
            
            for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
                if (!this.config.importInProgress) {
                    break; // Import was cancelled
                }

                const batch = batches[batchIndex];
                await this.processBatch(batch, batchIndex + 1, batches.length);

                // Add delay between batches
                if (batchIndex < batches.length - 1) {
                    await this.delay(1000);
                }
            }

            this.finalizeBulkImport();
        },

        /**
         * Process a single batch of imports
         */
        async processBatch(asins, batchNumber, totalBatches) {
            this.updateProgressText(`Traitement du lot ${batchNumber}/${totalBatches}...`);

            const batchPromises = asins.map(asin => this.importSingleProduct(asin));
            const results = await Promise.allSettled(batchPromises);

            results.forEach((result, index) => {
                const asin = asins[index];
                this.config.importProgress.current++;

                if (result.status === 'fulfilled' && result.value.success) {
                    this.config.importProgress.success++;
                    this.addImportLogEntry(asin, 'success', 'Import réussi');
                } else {
                    this.config.importProgress.failed++;
                    const error = result.status === 'rejected' ? result.reason : result.value.error;
                    this.addImportLogEntry(asin, 'error', error);
                }

                this.updateProgressBar();
            });
        },

        /**
         * Import a single product (used in bulk import)
         */
        async importSingleProduct(asin) {
            try {
                const response = await this.makeAjaxRequest('api_import_product', {
                    asin: asin,
                    nonce: amazon_importer_ajax.nonce
                });

                return response;

            } catch (error) {
                return {
                    success: false,
                    error: error.message
                };
            }
        },

        /**
         * Finalize bulk import process
         */
        finalizeBulkImport() {
            this.config.importInProgress = false;
            
            const { success, failed, total } = this.config.importProgress;
            const successRate = Math.round((success / total) * 100);

            this.updateProgressText(`Import terminé: ${success}/${total} réussis (${successRate}%)`);
            this.updateProgressBar(100);

            // Show completion summary
            const summaryHtml = `
                <div class="import-summary">
                    <h4>Résumé de l'import</h4>
                    <p><strong>Total:</strong> ${total}</p>
                    <p><strong>Réussis:</strong> ${success}</p>
                    <p><strong>Échoués:</strong> ${failed}</p>
                    <p><strong>Taux de réussite:</strong> ${successRate}%</p>
                </div>
            `;

            $('#import-log').append(summaryHtml);

            // Show close button
            $('#cancel-import-btn').hide();
            $('#close-modal-btn').show();

            // Clear selected products
            this.config.selectedProducts.clear();
            this.updateBulkActionsState();

            // Refresh import history
            this.refreshImportHistory();
        },

        /**
         * Update progress bar and text
         */
        updateProgressBar(percentage = null) {
            if (percentage === null) {
                const { current, total } = this.config.importProgress;
                percentage = total > 0 ? Math.round((current / total) * 100) : 0;
            }

            $('#progress-fill').css('width', percentage + '%');
            $('#progress-text').text(percentage + '%');
        },

        /**
         * Update progress text
         */
        updateProgressText(text) {
            $('#progress-details').text(text);
        },

        /**
         * Add entry to import log
         */
        addImportLogEntry(asin, status, message) {
            const statusClass = status === 'success' ? 'success' : 'error';
            const icon = status === 'success' ? 'yes-alt' : 'dismiss';
            
            const logEntry = `
                <div class="log-entry ${statusClass}">
                    <span class="dashicons dashicons-${icon}"></span>
                    <strong>${asin}:</strong> ${message}
                </div>
            `;

            $('#import-log').append(logEntry);
            
            // Auto-scroll to bottom
            const logContainer = $('#import-log');
            logContainer.scrollTop(logContainer[0].scrollHeight);
        },

        /**
         * Cancel import process
         */
        cancelImport() {
            if (confirm(amazon_importer_ajax.strings.confirm_cancel)) {
                this.config.importInProgress = false;
                this.closeProgressModal();
                this.showNotification('info', 'Import annulé');
            }
        },

        /**
         * Handle search type change
         */
        handleSearchTypeChange(e) {
            const searchType = $(e.target).val();
            
            // Update UI based on search type
            if (searchType === 'bulk_asin') {
                this.elements.searchInput.hide();
                this.elements.bulkAsinInput.show().attr('placeholder', 'Entrez les ASIN séparés par des virgules ou des retours à la ligne...');
                $('#search-filters').hide();
            } else {
                this.elements.bulkAsinInput.hide();
                this.elements.searchInput.show();
                $('#search-filters').show();
                
                if (searchType === 'asin') {
                    this.elements.searchInput.attr('placeholder', 'Entrez un ASIN (ex: B08N5WRWNW)...');
                } else {
                    this.elements.searchInput.attr('placeholder', 'Entrez vos mots-clés...');
                }
            }

            // Update radio label styling
            $('.radio-label').removeClass('active');
            $(e.target).closest('.radio-label').addClass('active');
        },

        /**
         * Handle filter changes
         */
        handleFilterChange() {
            // Clear current search timeout
            if (this.config.searchTimeout) {
                clearTimeout(this.config.searchTimeout);
            }

            // Set new timeout for auto-search
            this.config.searchTimeout = setTimeout(() => {
                if (this.config.searchResults.length > 0) {
                    // Re-search with new filters
                    const searchType = this.elements.searchType.filter(':checked').val();
                    const query = searchType === 'bulk_asin' ? 
                        this.elements.bulkAsinInput.val() : 
                        this.elements.searchInput.val();
                    
                    if (query.trim()) {
                        this.performSearch(query, searchType);
                    }
                }
            }, this.config.searchDelay);
        },

        /**
         * Toggle advanced filters
         */
        toggleAdvancedFilters() {
            this.elements.advancedFilters.slideToggle();
            $('#advanced-filters-toggle').find('.dashicons').toggleClass('dashicons-admin-generic dashicons-arrow-up-alt2');
        },

        /**
         * Handle view toggle (grid/list)
         */
        handleViewToggle(e) {
            const button = $(e.currentTarget);
            const view = button.data('view');

            $('.view-btn').removeClass('active');
            button.addClass('active');

            this.elements.resultsContainer.removeClass('search-results-grid search-results-list')
                .addClass(`search-results-${view}`);
        },

        /**
         * Select/deselect all products
         */
        selectAllProducts() {
            const checkboxes = $('.product-checkbox');
            const allSelected = checkboxes.length === checkboxes.filter(':checked').length;

            if (allSelected) {
                // Deselect all
                checkboxes.prop('checked', false);
                this.config.selectedProducts.clear();
                $('#select-all-btn').text('Tout sélectionner');
            } else {
                // Select all
                checkboxes.prop('checked', true);
                checkboxes.each((index, checkbox) => {
                    this.config.selectedProducts.add($(checkbox).val());
                });
                $('#select-all-btn').text('Tout désélectionner');
            }

            this.updateBulkActionsState();
            this.updateProductCardStates();
        },

        /**
         * Handle individual product selection
         */
        handleProductSelection(e) {
            const checkbox = $(e.target);
            const asin = checkbox.val();
            const productCard = checkbox.closest('.product-card');

            if (checkbox.is(':checked')) {
                this.config.selectedProducts.add(asin);
                productCard.addClass('selected');
            } else {
                this.config.selectedProducts.delete(asin);
                productCard.removeClass('selected');
            }

            this.updateBulkActionsState();
            this.updateSelectAllButtonState();
        },

        /**
         * Update bulk actions state
         */
        updateBulkActionsState() {
            const selectedCount = this.config.selectedProducts.size;
            const importButton = $('#import-selected-btn');

            if (selectedCount > 0) {
                importButton.prop('disabled', false).text(`Importer sélectionnés (${selectedCount})`);
            } else {
                importButton.prop('disabled', true).text('Importer sélectionnés');
            }
        },

        /**
         * Update select all button state
         */
        updateSelectAllButtonState() {
            const totalCheckboxes = $('.product-checkbox').length;
            const selectedCount = this.config.selectedProducts.size;

            if (selectedCount === 0) {
                $('#select-all-btn').text('Tout sélectionner');
            } else if (selectedCount === totalCheckboxes) {
                $('#select-all-btn').text('Tout désélectionner');
            } else {
                $('#select-all-btn').text(`Sélectionner tout (${selectedCount}/${totalCheckboxes})`);
            }
        },

        /**
         * Set product import state
         */
        setProductImportState(productCard, state) {
            const statusDiv = productCard.find('.import-status');
            const actionsDiv = productCard.find('.product-actions');
            const importButton = productCard.find('.import-single-btn');

            productCard.removeClass('importing success error').addClass(state);

            switch (state) {
                case 'importing':
                    statusDiv.show();
                    actionsDiv.hide();
                    statusDiv.find('.import-message').text('Import en cours...');
                    break;
                
                case 'success':
                    statusDiv.find('.import-message').text('Importé avec succès!');
                    importButton.prop('disabled', true).text('Importé');
                    break;
                
                case 'error':
                    statusDiv.hide();
                    actionsDiv.show();
                    importButton.text('Réessayer');
                    break;
                
                default:
                    statusDiv.hide();
                    actionsDiv.show();
                    break;
            }
        },

        /**
         * Utility functions
         */
        
        // Format product price
        formatPrice(product) {
            if (product.Offers?.Listings?.[0]?.Price?.DisplayAmount) {
                return product.Offers.Listings[0].Price.DisplayAmount;
            }
            return 'Prix non disponible';
        },

        // Get product image URL
        getProductImage(product) {
            const defaultImage = amazon_importer_ajax.plugin_url + 'assets/images/no-image.png';
            
            if (product.Images?.Primary?.Medium?.URL) {
                return product.Images.Primary.Medium.URL;
            } else if (product.Images?.Primary?.Small?.URL) {
                return product.Images.Primary.Small.URL;
            }
            
            return defaultImage;
        },

        // Get product features
        getProductFeatures(product) {
            const features = product.ItemInfo?.Features?.DisplayValues || [];
            if (features.length === 0) return '<p>Aucune caractéristique disponible</p>';
            
            const maxFeatures = 3;
            const displayFeatures = features.slice(0, maxFeatures);
            
            return '<ul>' + displayFeatures.map(feature => `<li>${feature}</li>`).join('') + '</ul>';
        },

        // Truncate text
        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength).trim() + '...';
        },

        // Validate ASIN format
        validateAsin(asin) {
            return /^[A-Z0-9]{10}$/.test(asin);
        },

        // Parse ASIN list from bulk input
        parseAsinList(input) {
            const asins = input.split(/[,\n\r\s]+/)
                .map(asin => asin.trim().toUpperCase())
                .filter(asin => asin && this.validateAsin(asin));
            
            return [...new Set(asins)]; // Remove duplicates
        },

        // Chunk array into smaller arrays
        chunkArray(array, chunkSize) {
            const chunks = [];
            for (let i = 0; i < array.length; i += chunkSize) {
                chunks.push(array.slice(i, i + chunkSize));
            }
            return chunks;
        },

        // Create delay promise
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        // Make AJAX request with retry logic
        async makeAjaxRequest(action, data, retryCount = 0) {
            try {
                const response = await $.ajax({
                    url: amazon_importer_ajax.url,
                    type: 'POST',
                    data: {
                        action: action,
                        ...data
                    },
                    timeout: 30000
                });

                return response;

            } catch (error) {
                if (retryCount < this.config.maxRetries) {
                    console.log(`Request failed, retrying... (${retryCount + 1}/${this.config.maxRetries})`);
                    await this.delay(1000 * (retryCount + 1)); // Exponential backoff
                    return this.makeAjaxRequest(action, data, retryCount + 1);
                }
                
                throw new Error(error.responseJSON?.message || error.statusText || 'Erreur de connexion');
            }
        },

        /**
         * UI State Management
         */

        // Set searching state
        setSearchingState(isSearching) {
            const button = this.elements.searchButton;
            const spinner = button.find('.loading-spinner');
            const text = button.find('.button-text');
            const icon = button.find('.dashicons');

            if (isSearching) {
                button.prop('disabled', true);
                spinner.show();
                text.text('Recherche...');
                icon.hide();
                this.showSearchStatus(amazon_importer_ajax.strings.searching);
            } else {
                button.prop('disabled', false);
                spinner.hide();
                text.text('Rechercher');
                icon.show();
                this.hideSearchStatus();
            }
        },

        // Show search status
        showSearchStatus(message) {
            this.elements.searchStatus.find('.status-text').text(message);
            this.elements.searchStatus.show();
        },

        // Hide search status
        hideSearchStatus() {
            this.elements.searchStatus.hide();
        },

        // Show results
        showResults() {
            this.elements.resultsHeader.fadeIn();
            this.elements.resultsContainer.fadeIn();
        },

        // Hide results
        hideResults() {
            this.elements.resultsHeader.hide();
            this.elements.resultsContainer.hide();
            $('#results-pagination').hide();
        },

        // Show no results message
        showNoResults() {
            this.elements.resultsCount.text('0 produit trouvé');
            this.elements.resultsHeader.show();
            
            const noResultsHtml = `
                <div class="no-results">
                    <div class="no-results-icon">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <h3>Aucun résultat trouvé</h3>
                    <p>Essayez de modifier vos critères de recherche ou utilisez des mots-clés différents.</p>
                    <div class="search-suggestions">
                        <h4>Suggestions :</h4>
                        <ul>
                            <li>Vérifiez l'orthographe de vos mots-clés</li>
                            <li>Utilisez des termes plus généraux</li>
                            <li>Essayez des synonymes</li>
                            <li>Réduisez le nombre de mots-clés</li>
                        </ul>
                    </div>
                </div>
            `;
            
            this.elements.resultsContainer.html(noResultsHtml).show();
        },

        // Show notification
        showNotification(type, message, duration = 5000) {
            const notification = $(`
                <div class="notice notice-${type} is-dismissible amazon-notification">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Add to page
            $('.amazon-importer-header').after(notification);

            // Auto-dismiss
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, duration);

            // Manual dismiss
            notification.find('.notice-dismiss').on('click', () => {
                notification.fadeOut(() => notification.remove());
            });
        },

        // Show API configuration warning
        showApiConfigurationWarning() {
            const warning = `
                <div class="notice notice-warning amazon-config-warning">
                    <p>
                        <strong>Configuration requise :</strong> 
                        Veuillez configurer vos clés API Amazon dans les 
                        <a href="${amazon_importer_ajax.settings_url}">paramètres</a> 
                        avant d'importer des produits.
                    </p>
                </div>
            `;
            
            $('.amazon-import-interface').before(warning);
        },

        /**
         * Modal Management
         */

        // Initialize modals
        initializeModals() {
            // Prevent modal content clicks from closing modal
            $('.modal-content').on('click', (e) => {
                e.stopPropagation();
            });

            // Handle escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAllModals();
                }
            });
        },

        // Open progress modal
        openProgressModal() {
            this.elements.progressModal.fadeIn();
            this.resetProgressModal();
        },

        // Close progress modal
        closeProgressModal() {
            this.elements.progressModal.fadeOut();
        },

        // Reset progress modal
        resetProgressModal() {
            $('#progress-fill').css('width', '0%');
            $('#progress-text').text('0%');
            $('#progress-details').text('Préparation...');
            $('#import-log').empty();
            $('#cancel-import-btn').show();
            $('#close-modal-btn').hide();
        },

        // Open bulk import modal
        openBulkImportModal() {
            this.elements.bulkImportModal.fadeIn();
        },

        // Open import history modal
        openImportHistoryModal() {
            this.elements.historyModal.fadeIn();
            this.loadImportHistory();
        },

        // Close modal
        closeModal(e) {
            if ($(e.target).hasClass('modal-close') || $(e.target).hasClass('modal-overlay')) {
                $(e.target).closest('.amazon-modal').fadeOut();
            }
        },

        // Close all modals
        closeAllModals() {
            $('.amazon-modal').fadeOut();
        },

        /**
         * File Upload Handling
         */

        // Handle CSV file selection
        handleCsvFileSelect(e) {
            const file = e.target.files[0];
            
            if (!file) return;

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                this.showNotification('error', 'Veuillez sélectionner un fichier CSV');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                this.showNotification('error', 'Le fichier est trop volumineux (max 5MB)');
                return;
            }

            // Show file name
            $('#csv-file-name').text(file.name);

            // Read and process CSV
            this.processCsvFile(file);
        },

        // Process CSV file
        processCsvFile(file) {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const csvContent = e.target.result;
                    const asins = this.parseCsvContent(csvContent);
                    
                    if (asins.length === 0) {
                        this.showNotification('error', 'Aucun ASIN valide trouvé dans le fichier CSV');
                        return;
                    }

                    // Show confirmation
                    const confirmed = confirm(`${asins.length} ASIN(s) trouvé(s). Commencer l'import ?`);
                    
                    if (confirmed) {
                        this.elements.bulkImportModal.fadeOut();
                        this.startBulkImportFromAsins(asins);
                    }

                } catch (error) {
                    this.showNotification('error', 'Erreur lors de la lecture du fichier CSV');
                }
            };

            reader.onerror = () => {
                this.showNotification('error', 'Erreur lors de la lecture du fichier');
            };

            reader.readAsText(file);
        },

        // Parse CSV content
        parseCsvContent(csvContent) {
            const lines = csvContent.split('\n');
            const asins = [];

            for (const line of lines) {
                const columns = line.split(',').map(col => col.trim().replace(/['"]/g, ''));
                
                // Look for ASIN in any column
                for (const column of columns) {
                    if (this.validateAsin(column)) {
                        asins.push(column);
                        break; // Only take first ASIN per line
                    }
                }
            }

            return [...new Set(asins)]; // Remove duplicates
        },

        // Start bulk import from ASIN list
        async startBulkImportFromAsins(asins) {
            this.config.selectedProducts = new Set(asins);
            await this.importSelectedProducts();
        },

        /**
         * Wishlist Import
         */

        // Handle wishlist import
        async handleWishlistImport() {
            const wishlistUrl = $('#wishlist-url-input').val().trim();
            
            if (!wishlistUrl) {
                this.showNotification('error', 'Veuillez entrer une URL de liste de souhaits');
                return;
            }

            // Validate Amazon wishlist URL
            if (!this.validateWishlistUrl(wishlistUrl)) {
                this.showNotification('error', 'URL de liste de souhaits Amazon invalide');
                return;
            }

            try {
                const response = await this.makeAjaxRequest('api_import_wishlist', {
                    wishlist_url: wishlistUrl,
                    nonce: amazon_importer_ajax.nonce
                });

                if (response.success) {
                    this.elements.bulkImportModal.fadeOut();
                    
                    if (response.asins && response.asins.length > 0) {
                        const confirmed = confirm(`${response.asins.length} produit(s) trouvé(s) dans la liste. Commencer l'import ?`);
                        
                        if (confirmed) {
                            this.startBulkImportFromAsins(response.asins);
                        }
                    } else {
                        this.showNotification('warning', 'Aucun produit trouvé dans cette liste de souhaits');
                    }
                } else {
                    this.showNotification('error', response.error || 'Erreur lors de l\'import de la liste');
                }

            } catch (error) {
                this.showNotification('error', 'Erreur de connexion: ' + error.message);
            }
        },

        // Validate wishlist URL
        validateWishlistUrl(url) {
            const wishlistPattern = /^https?:\/\/(www\.)?amazon\.[a-z.]+\/.*\/wishlist\/ls\/[A-Z0-9]+/i;
            return wishlistPattern.test(url);
        },

        /**
         * Import History
         */

        // Load import history
        async loadImportHistory() {
            try {
                const response = await this.makeAjaxRequest('api_get_import_history', {
                    nonce: amazon_importer_ajax.nonce
                });

                if (response.success) {
                    this.displayImportHistory(response.history);
                } else {
                    this.showImportHistoryError(response.error);
                }

            } catch (error) {
                this.showImportHistoryError('Erreur de chargement de l\'historique');
            }
        },

        // Display import history
        displayImportHistory(history) {
            const tbody = $('#import-history-table');
            tbody.empty();

            if (!history || history.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="5" class="no-history">
                            Aucun historique d'import disponible
                        </td>
                    </tr>
                `);
                return;
            }

            history.forEach(entry => {
                const statusClass = entry.status === 'success' ? 'success' : 'error';
                const statusIcon = entry.status === 'success' ? 'yes-alt' : 'dismiss';
                const date = new Date(entry.created_at).toLocaleString();
                
                const actionsHtml = entry.status === 'success' && entry.product_id ? 
                    `<a href="/wp-admin/post.php?post=${entry.product_id}&action=edit" class="button button-small">Modifier</a>` :
                    `<button type="button" class="button button-small retry-import" data-asin="${entry.asin}">Réessayer</button>`;

                tbody.append(`
                    <tr class="${statusClass}">
                        <td><code>${entry.asin}</code></td>
                        <td>${entry.product_title || 'N/A'}</td>
                        <td>
                            <span class="dashicons dashicons-${statusIcon}"></span>
                            ${entry.status === 'success' ? 'Réussi' : 'Échec'}
                        </td>
                        <td>${date}</td>
                        <td>${actionsHtml}</td>
                    </tr>
                `);
            });

            // Bind retry buttons
            $('.retry-import').on('click', (e) => {
                const asin = $(e.target).data('asin');
                this.retryImport(asin);
            });
        },

        // Show import history error
        showImportHistoryError(error) {
            const tbody = $('#import-history-table');
            tbody.empty().append(`
                <tr>
                    <td colspan="5" class="error">
                        Erreur: ${error}
                    </td>
                </tr>
            `);
        },

        // Refresh import history
        refreshImportHistory() {
            if (this.elements.historyModal.is(':visible')) {
                this.loadImportHistory();
            }
        },

        // Retry import
        async retryImport(asin) {
            try {
                const response = await this.makeAjaxRequest('api_import_product', {
                    asin: asin,
                    force_update: true,
                    nonce: amazon_importer_ajax.nonce
                });

                if (response.success) {
                    this.showNotification('success', `Produit ${asin} importé avec succès`);
                    this.refreshImportHistory();
                } else {
                    this.showNotification('error', response.error || 'Erreur lors de l\'import');
                }

            } catch (error) {
                this.showNotification('error', 'Erreur de connexion: ' + error.message);
            }
        },

        /**
         * Search History
         */

        // Save search to history
        saveSearchHistory(query, searchType) {
            let history = JSON.parse(localStorage.getItem('amazon_search_history') || '[]');
            
            const searchEntry = {
                query: query,
                type: searchType,
                timestamp: Date.now()
            };

            // Remove existing entry for same query
            history = history.filter(entry => !(entry.query === query && entry.type === searchType));
            
            // Add to beginning
            history.unshift(searchEntry);
            
            // Keep only last 10 searches
            history = history.slice(0, 10);
            
            localStorage.setItem('amazon_search_history', JSON.stringify(history));
            this.updateSearchSuggestions(history);
        },

        // Load search history
        loadSearchHistory() {
            const history = JSON.parse(localStorage.getItem('amazon_search_history') || '[]');
            this.updateSearchSuggestions(history);
        },

        // Update search suggestions
        updateSearchSuggestions(history) {
            // Create search suggestions dropdown (if not exists)
            if (!$('#search-suggestions').length) {
                this.elements.searchInput.after(`
                    <div id="search-suggestions" class="search-suggestions" style="display: none;">
                        <div class="suggestions-header">Recherches récentes</div>
                        <ul class="suggestions-list"></ul>
                    </div>
                `);
            }

            const suggestionsList = $('.suggestions-list');
            suggestionsList.empty();

            history.forEach(entry => {
                const timeAgo = this.timeAgo(entry.timestamp);
                suggestionsList.append(`
                    <li class="suggestion-item" data-query="${entry.query}" data-type="${entry.type}">
                        <span class="suggestion-query">${entry.query}</span>
                        <span class="suggestion-meta">${entry.type} • ${timeAgo}</span>
                    </li>
                `);
            });

            // Bind suggestion clicks
            $('.suggestion-item').on('click', (e) => {
                const query = $(e.currentTarget).data('query');
                const type = $(e.currentTarget).data('type');
                
                // Set search type
                $(`input[name="search_type"][value="${type}"]`).prop('checked', true);
                this.handleSearchTypeChange({ target: $(`input[name="search_type"][value="${type}"]`)[0] });
                
                // Set query
                if (type === 'bulk_asin') {
                    this.elements.bulkAsinInput.val(query);
                } else {
                    this.elements.searchInput.val(query);
                }
                
                // Hide suggestions
                $('#search-suggestions').hide();
                
                // Perform search
                this.handleSearch(e);
            });
        },

        // Show search suggestions
        showSearchSuggestions() {
            $('#search-suggestions').show();
        },

        // Hide search suggestions
        hideSearchSuggestions() {
            $('#search-suggestions').hide();
        },

        // Time ago helper
        timeAgo(timestamp) {
            const now = Date.now();
            const diff = now - timestamp;
            
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return 'À l\'instant';
            if (minutes < 60) return `${minutes}m`;
            if (hours < 24) return `${hours}h`;
            return `${days}j`;
        },

        /**
         * Keyboard Shortcuts
         */

        // Handle keyboard shortcuts
        handleKeyboardShortcuts(e) {
            // Ctrl/Cmd + K: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.elements.searchInput.focus();
            }

            // Escape: Close modals or clear search
            if (e.key === 'Escape') {
                if ($('.amazon-modal:visible').length > 0) {
                    this.closeAllModals();
                } else if (this.elements.searchInput.is(':focus')) {
                    this.elements.searchInput.blur().val('');
                    this.hideResults();
                }
            }

            // Ctrl/Cmd + A: Select all products (when in results)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && this.config.searchResults.length > 0) {
                e.preventDefault();
                this.selectAllProducts();
            }

            // Enter: Search or import selected
            if (e.key === 'Enter' && !$(e.target).is('input, textarea')) {
                if (this.config.selectedProducts.size > 0) {
                    this.importSelectedProducts();
                }
            }
        },

        /**
         * Pagination
         */

        // Update pagination
        updatePagination(currentResults, totalResults) {
            const hasMore = currentResults < totalResults;
            const pagination = $('#results-pagination');

            if (hasMore) {
                pagination.show();
                $('#load-more-btn').text(`Charger plus (${currentResults}/${totalResults})`);
            } else {
                pagination.hide();
            }
        },

        // Load more results
        async loadMoreResults() {
            this.config.currentPage++;
            
            const searchType = this.elements.searchType.filter(':checked').val();
            const query = searchType === 'bulk_asin' ? 
                this.elements.bulkAsinInput.val() : 
                this.elements.searchInput.val();

            await this.performSearch(query, searchType, true);
        },

        /**
         * Product Card Interactions
         */

        // Initialize product cards
        initializeProductCards() {
            // Lazy load images
            $('.product-card img').each((index, img) => {
                const $img = $(img);
                if ($img.attr('loading') === 'lazy') {
                    this.setupLazyLoading($img);
                }
            });

            // Setup tooltips
            $('.product-title').each((index, element) => {
                const $element = $(element);
                if ($element.attr('title')) {
                    this.setupTooltip($element);
                }
            });
        },

        // Handle product card clicks
        handleProductCardClick(e) {
            // Don't select if clicking on buttons or checkboxes
            if ($(e.target).is('button, input, a, .dashicons')) {
                return;
            }

            const card = $(e.currentTarget);
            const checkbox = card.find('.product-checkbox');
            
            // Toggle selection
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        },

        // Update product card states
        updateProductCardStates() {
            $('.product-card').each((index, card) => {
                const $card = $(card);
                const checkbox = $card.find('.product-checkbox');
                
                if (checkbox.is(':checked')) {
                    $card.addClass('selected');
                } else {
                    $card.removeClass('selected');
                }
            });
        },

        // Setup lazy loading for images
        setupLazyLoading($img) {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src || img.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                imageObserver.observe($img[0]);
            }
        },

        // Setup tooltip
        setupTooltip($element) {
            $element.on('mouseenter', function() {
                const title = $(this).attr('title');
                if (title) {
                    const tooltip = $(`<div class="amazon-tooltip">${title}</div>`);
                    $('body').append(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.css({
                        top: rect.top - tooltip.outerHeight() - 5,
                        left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2)
                    });
                }
            }).on('mouseleave', function() {
                $('.amazon-tooltip').remove();
            });
        },

        /**
         * Search Input Handling
         */

        // Handle search input changes
        handleSearchInputChange() {
            const query = this.elements.searchInput.val().trim();
            
            // Show suggestions if query is empty and has history
            if (!query) {
                const history = JSON.parse(localStorage.getItem('amazon_search_history') || '[]');
                if (history.length > 0) {
                    this.showSearchSuggestions();
                }
            } else {
                this.hideSearchSuggestions();
            }

            // Auto-search for ASIN format
            if (this.validateAsin(query)) {
                // Switch to ASIN search type
                $('input[name="search_type"][value="asin"]').prop('checked', true);
                this.handleSearchTypeChange({ target: $('input[name="search_type"][value="asin"]')[0] });
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if we're on the import page
        if ($('.amazon-import-interface').length > 0) {
            ImportInterface.init();
        }
    });

    // Expose to global scope for debugging
    window.AmazonImportInterface = ImportInterface;

})(jQuery);