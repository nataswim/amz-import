/**
 * Amazon Product Importer - Import Interface JavaScript
 *
 * Handles the product search, selection, and import functionality
 *
 * @package Amazon_Product_Importer
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Import Interface Controller
     */
    var ImportInterface = {

        // Configuration
        config: {
            ajaxUrl: amazon_importer_data.ajax_url,
            nonce: amazon_importer_data.nonce,
            strings: amazon_importer_data.strings,
            searchDelay: 300,
            progressUpdateInterval: 2000,
            maxConcurrentImports: 3,
            retryDelay: 5000,
            maxRetries: 3
        },

        // State management
        state: {
            searchTimeout: null,
            progressTimer: null,
            currentBatchId: null,
            selectedProducts: new Set(),
            searchResults: [],
            currentPage: 1,
            totalPages: 1,
            isSearching: false,
            isImporting: false,
            importQueue: [],
            activeImports: new Map(),
            retryCount: new Map(),
            lastSearchParams: null
        },

        // DOM element cache
        elements: {
            $searchForm: null,
            $searchInput: null,
            $searchType: null,
            $resultsContainer: null,
            $resultsGrid: null,
            $progressContainer: null,
            $progressBar: null,
            $selectionCount: null,
            $bulkActions: null,
            $pagination: null,
            $viewToggles: null,
            $quickImportForm: null
        },

        /**
         * Initialize the import interface
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeComponents();
            this.restoreState();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.$searchForm = $('#amazon-search-form');
            this.elements.$searchInput = $('#amazon-search-term');
            this.elements.$searchType = $('#amazon-search-type');
            this.elements.$resultsContainer = $('#amazon-search-results-container');
            this.elements.$resultsGrid = $('#amazon-search-results');
            this.elements.$progressContainer = $('#amazon-import-progress-container');
            this.elements.$progressBar = $('.amazon-progress-fill');
            this.elements.$selectionCount = $('#amazon-selected-count');
            this.elements.$bulkActions = $('.amazon-import-selected-btn');
            this.elements.$pagination = $('#amazon-pagination');
            this.elements.$viewToggles = $('.amazon-view-toggle');
            this.elements.$quickImportForm = $('#amazon-quick-import-form');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Search functionality
            this.elements.$searchForm.on('submit', function(e) {
                e.preventDefault();
                self.performSearch();
            });

            // Real-time search input handling
            this.elements.$searchInput.on('input', function() {
                self.handleSearchInput();
            });

            // Search type change
            this.elements.$searchType.on('change', function() {
                self.updateSearchInputValidation();
            });

            // Product selection
            $(document).on('change', '.amazon-product-select', function() {
                self.handleProductSelection($(this));
            });

            // Select all/none
            $('.amazon-select-all-btn').on('click', function() {
                self.selectAllProducts(true);
            });

            $('.amazon-select-none-btn').on('click', function() {
                self.selectAllProducts(false);
            });

            // View mode toggles
            this.elements.$viewToggles.on('click', function() {
                self.toggleViewMode($(this).data('view'));
            });

            // Individual product actions
            $(document).on('click', '.amazon-import-product-btn', function() {
                var asin = $(this).data('asin');
                self.importSingleProduct(asin);
            });

            $(document).on('click', '.amazon-update-product-btn', function() {
                var asin = $(this).data('asin');
                self.updateSingleProduct(asin);
            });

            $(document).on('click', '.amazon-preview-product-btn', function() {
                var asin = $(this).data('asin');
                self.previewProduct(asin);
            });

            // Bulk import
            this.elements.$bulkActions.on('click', function() {
                self.importSelectedProducts();
            });

            // Quick import
            this.elements.$quickImportForm.on('submit', function(e) {
                e.preventDefault();
                self.handleQuickImport();
            });

            // Pagination
            $(document).on('click', '.amazon-page-btn', function() {
                var page = $(this).data('page');
                if (page && page !== self.state.currentPage) {
                    self.loadPage(page);
                }
            });

            // Copy ASIN
            $(document).on('click', '.amazon-copy-asin', function() {
                self.copyToClipboard($(this).data('asin'));
            });

            // Progress controls
            $('#amazon-cancel-import-btn').on('click', function() {
                self.cancelBatchImport();
            });

            $('#amazon-pause-import-btn').on('click', function() {
                self.pauseBatchImport();
            });

            $('#amazon-resume-import-btn').on('click', function() {
                self.resumeBatchImport();
            });

            // Modal events
            $('.amazon-modal-close, .amazon-modal-cancel').on('click', function() {
                self.closeModal($(this).closest('.amazon-modal'));
            });

            // Advanced options toggle
            $('#amazon-advanced-toggle-btn').on('click', function() {
                self.toggleAdvancedOptions();
            });

            // Result filters
            $('.amazon-tab-btn').on('click', function() {
                self.filterResults($(this).data('tab'));
            });

            // Search within results
            $('#amazon-results-search').on('input', function() {
                self.searchWithinResults($(this).val());
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                self.handleKeyboardShortcuts(e);
            });

            // Window events
            $(window).on('beforeunload', function() {
                if (self.state.isImporting) {
                    return self.config.strings.import_in_progress;
                }
            });
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            this.initializeTooltips();
            this.initializeProgressIndicators();
            this.updateSearchInputValidation();
        },

        /**
         * Restore previous state
         */
        restoreState: function() {
            // Restore view mode
            var viewMode = localStorage.getItem('amazon_import_view_mode') || 'grid';
            this.setViewMode(viewMode);

            // Check for ongoing imports
            this.checkOngoingImports();
        },

        /**
         * Perform product search
         */
        performSearch: function() {
            if (this.state.isSearching) return;

            var searchParams = this.getSearchParameters();
            if (!this.validateSearchParameters(searchParams)) {
                return;
            }

            this.state.isSearching = true;
            this.state.lastSearchParams = searchParams;
            this.showSearchLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_search_products',
                    nonce: this.config.nonce,
                    ...searchParams
                },
                success: function(response) {
                    this.handleSearchSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleSearchError(error);
                }.bind(this),
                complete: function() {
                    this.state.isSearching = false;
                    this.hideSearchLoading();
                }.bind(this)
            });
        },

        /**
         * Handle search input with debouncing
         */
        handleSearchInput: function() {
            clearTimeout(this.state.searchTimeout);
            
            var searchTerm = this.elements.$searchInput.val().trim();
            
            if (searchTerm.length < 2) {
                this.hideSuggestions();
                return;
            }

            this.state.searchTimeout = setTimeout(function() {
                this.showSearchSuggestions(searchTerm);
            }.bind(this), this.config.searchDelay);
        },

        /**
         * Get search parameters from form
         */
        getSearchParameters: function() {
            return {
                search_term: this.elements.$searchInput.val().trim(),
                search_type: this.elements.$searchType.val(),
                search_category: $('#amazon-search-category').val(),
                min_price: $('#amazon-min-price').val(),
                max_price: $('#amazon-max-price').val(),
                min_reviews: $('#amazon-min-reviews').val(),
                sort_by: $('#amazon-sort-by').val(),
                results_per_page: $('#amazon-results-per-page').val() || 20,
                page: this.state.currentPage
            };
        },

        /**
         * Validate search parameters
         */
        validateSearchParameters: function(params) {
            if (!params.search_term) {
                this.showNotification('error', 'Please enter a search term');
                this.elements.$searchInput.focus();
                return false;
            }

            if (params.search_type === 'asin') {
                if (!/^[A-Z0-9]{10}$/.test(params.search_term.toUpperCase())) {
                    this.showNotification('error', 'Invalid ASIN format. Must be 10 characters.');
                    this.elements.$searchInput.focus();
                    return false;
                }
            }

            return true;
        },

        /**
         * Handle successful search response
         */
        handleSearchSuccess: function(response) {
            if (response.success) {
                this.state.searchResults = response.data.products || [];
                this.state.currentPage = response.data.current_page || 1;
                this.state.totalPages = response.data.total_pages || 1;
                
                this.renderSearchResults(response.data);
                this.showResultsContainer();
                this.updatePagination();
                this.clearSelection();
                
                // Save search state
                this.saveSearchState();
            } else {
                this.showNotification('error', response.data.message || 'Search failed');
            }
        },

        /**
         * Handle search error
         */
        handleSearchError: function(error) {
            console.error('Search error:', error);
            this.showNotification('error', 'Search failed. Please try again.');
        },

        /**
         * Render search results
         */
        renderSearchResults: function(data) {
            if (!data.products || data.products.length === 0) {
                this.renderNoResults();
                return;
            }

            var html = '';
            data.products.forEach(function(product, index) {
                html += this.generateProductHTML(product, index);
            }.bind(this));

            this.elements.$resultsGrid.html(html);
            this.initializeProductElements();
            
            // Update results info
            this.updateResultsInfo(data);
        },

        /**
         * Generate HTML for a single product
         */
        generateProductHTML: function(product, index) {
            var existsLocally = product.exists_locally;
            var buttonClass = existsLocally ? 'amazon-update-product-btn' : 'amazon-import-product-btn';
            var buttonText = existsLocally ? 'Update' : 'Import';
            var buttonIcon = existsLocally ? 'dashicons-update' : 'dashicons-download';

            return `
                <div class="amazon-product-item" data-asin="${product.asin}" data-index="${index}">
                    <div class="amazon-product-checkbox">
                        <input type="checkbox" class="amazon-product-select" 
                               name="selected_products[]" value="${product.asin}" 
                               id="product_${product.asin}">
                        <label for="product_${product.asin}" class="screen-reader-text">Select this product</label>
                    </div>

                    <div class="amazon-product-image">
                        ${product.image ? `<img src="${product.image}" alt="${product.title}" loading="lazy">` : ''}
                        <div class="amazon-image-placeholder" ${product.image ? 'style="display:none;"' : ''}>
                            <span class="dashicons dashicons-format-image"></span>
                            <span>No Image</span>
                        </div>
                        
                        <div class="amazon-product-indicators">
                            ${existsLocally ? '<span class="amazon-indicator amazon-exists-indicator"><span class="dashicons dashicons-yes-alt"></span>Exists</span>' : ''}
                            ${product.prime ? '<span class="amazon-indicator amazon-prime-indicator"><span class="dashicons dashicons-star-filled"></span>Prime</span>' : ''}
                        </div>
                    </div>

                    <div class="amazon-product-details">
                        <h3 class="amazon-product-title">
                            <a href="${product.url}" target="_blank" rel="noopener">
                                ${product.title}
                                <span class="dashicons dashicons-external"></span>
                            </a>
                        </h3>

                        <div class="amazon-product-asin">
                            <span class="amazon-label">ASIN:</span>
                            <span class="amazon-value">${product.asin}</span>
                            <button type="button" class="amazon-copy-asin" data-asin="${product.asin}" title="Copy ASIN">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </div>

                        ${product.brand ? `<div class="amazon-product-brand"><span class="amazon-label">Brand:</span> <span class="amazon-value">${product.brand}</span></div>` : ''}
                        
                        ${product.price ? `<div class="amazon-product-price"><span class="amazon-price-current">${product.price}</span></div>` : ''}
                        
                        ${product.rating ? this.generateRatingHTML(product.rating, product.review_count) : ''}
                    </div>

                    <div class="amazon-product-actions">
                        <div class="amazon-primary-actions">
                            <button type="button" class="button button-primary ${buttonClass}" data-asin="${product.asin}">
                                <span class="dashicons ${buttonIcon}"></span>
                                ${buttonText}
                            </button>
                            
                            <button type="button" class="button button-secondary amazon-preview-product-btn" data-asin="${product.asin}">
                                <span class="dashicons dashicons-visibility"></span>
                                Preview
                            </button>
                        </div>
                    </div>

                    <div class="amazon-import-status" style="display: none;">
                        <div class="amazon-import-progress">
                            <div class="amazon-import-spinner"><span class="spinner is-active"></span></div>
                            <div class="amazon-import-message">Importing...</div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Generate rating HTML
         */
        generateRatingHTML: function(rating, reviewCount) {
            var ratingValue = parseFloat(rating);
            var fullStars = Math.floor(ratingValue);
            var hasHalfStar = (ratingValue - fullStars) >= 0.5;
            var emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

            var stars = '';
            for (var i = 0; i < fullStars; i++) {
                stars += '<span class="dashicons dashicons-star-filled"></span>';
            }
            if (hasHalfStar) {
                stars += '<span class="dashicons dashicons-star-half"></span>';
            }
            for (var i = 0; i < emptyStars; i++) {
                stars += '<span class="dashicons dashicons-star-empty"></span>';
            }

            return `
                <div class="amazon-product-rating">
                    <div class="amazon-rating-stars">${stars}</div>
                    <span class="amazon-rating-value">${rating}</span>
                    ${reviewCount ? `<span class="amazon-review-count">(${reviewCount})</span>` : ''}
                </div>
            `;
        },

        /**
         * Handle product selection
         */
        handleProductSelection: function($checkbox) {
            var asin = $checkbox.val();
            var isSelected = $checkbox.is(':checked');

            if (isSelected) {
                this.state.selectedProducts.add(asin);
            } else {
                this.state.selectedProducts.delete(asin);
            }

            this.updateSelectionUI();
        },

        /**
         * Select all products
         */
        selectAllProducts: function(select) {
            $('.amazon-product-select').prop('checked', select);
            
            if (select) {
                $('.amazon-product-select').each(function() {
                    this.state.selectedProducts.add($(this).val());
                }.bind(this));
            } else {
                this.state.selectedProducts.clear();
            }

            this.updateSelectionUI();
        },

        /**
         * Update selection UI
         */
        updateSelectionUI: function() {
            var count = this.state.selectedProducts.size;
            
            this.elements.$selectionCount.text(count);
            $('.amazon-selected-count').text('(' + count + ')');
            
            // Enable/disable bulk actions
            this.elements.$bulkActions.prop('disabled', count === 0);
            $('#amazon-bulk-preview-btn, #amazon-bulk-compare-btn').prop('disabled', count === 0);
            
            // Update select all button state
            var totalProducts = $('.amazon-product-select').length;
            var allSelected = count === totalProducts && totalProducts > 0;
            $('.amazon-select-all-btn').toggleClass('amazon-all-selected', allSelected);
        },

        /**
         * Import single product
         */
        importSingleProduct: function(asin) {
            if (!asin || this.state.activeImports.has(asin)) return;

            var $productItem = $(`.amazon-product-item[data-asin="${asin}"]`);
            this.showProductProgress($productItem, 'Importing...');

            this.state.activeImports.set(asin, {
                startTime: Date.now(),
                productItem: $productItem
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_import_product',
                    nonce: this.config.nonce,
                    asin: asin,
                    force_update: false
                },
                success: function(response) {
                    this.handleImportSuccess(asin, response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleImportError(asin, error);
                }.bind(this),
                complete: function() {
                    this.state.activeImports.delete(asin);
                    this.hideProductProgress($productItem);
                }.bind(this)
            });
        },

        /**
         * Update single product
         */
        updateSingleProduct: function(asin) {
            if (!confirm('Are you sure you want to update this product? This will overwrite local changes.')) {
                return;
            }

            var $productItem = $(`.amazon-product-item[data-asin="${asin}"]`);
            this.showProductProgress($productItem, 'Updating...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_import_product',
                    nonce: this.config.nonce,
                    asin: asin,
                    force_update: true
                },
                success: function(response) {
                    this.handleImportSuccess(asin, response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleImportError(asin, error);
                }.bind(this),
                complete: function() {
                    this.hideProductProgress($productItem);
                }.bind(this)
            });
        },

        /**
         * Import selected products
         */
        importSelectedProducts: function() {
            if (this.state.selectedProducts.size === 0) {
                this.showNotification('warning', 'Please select at least one product to import.');
                return;
            }

            var message = `Import ${this.state.selectedProducts.size} selected products?`;
            if (!confirm(message)) {
                return;
            }

            this.startBatchImport(Array.from(this.state.selectedProducts));
        },

        /**
         * Start batch import
         */
        startBatchImport: function(asins) {
            this.state.currentBatchId = this.generateBatchId();
            this.state.isImporting = true;
            this.state.importQueue = [...asins];

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_import_batch',
                    nonce: this.config.nonce,
                    asins: asins,
                    batch_id: this.state.currentBatchId
                },
                success: function(response) {
                    if (response.success) {
                        this.showProgressContainer();
                        this.startProgressMonitoring();
                        this.showNotification('info', response.data.message);
                    } else {
                        this.showNotification('error', response.data.message);
                        this.state.isImporting = false;
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Failed to start batch import.');
                    this.state.isImporting = false;
                }.bind(this)
            });
        },

        /**
         * Start progress monitoring
         */
        startProgressMonitoring: function() {
            this.updateBatchProgress();
            this.state.progressTimer = setInterval(function() {
                this.updateBatchProgress();
            }.bind(this), this.config.progressUpdateInterval);
        },

        /**
         * Update batch progress
         */
        updateBatchProgress: function() {
            if (!this.state.currentBatchId) return;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_get_import_status',
                    nonce: this.config.nonce,
                    batch_id: this.state.currentBatchId
                },
                success: function(response) {
                    if (response.success) {
                        this.updateProgressUI(response.data);
                        
                        if (['completed', 'cancelled', 'failed'].includes(response.data.status)) {
                            this.stopProgressMonitoring();
                            this.state.isImporting = false;
                            this.handleBatchComplete(response.data);
                        }
                    }
                }.bind(this)
            });
        },

        /**
         * Update progress UI
         */
        updateProgressUI: function(data) {
            var percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;
            
            // Update progress bar
            this.elements.$progressBar.css('width', percentage + '%');
            $('.amazon-progress-percentage').text(percentage + '%');
            
            // Update counters
            $('.amazon-progress-current').text(data.processed);
            $('.amazon-progress-total').text(data.total);
            $('.amazon-progress-success-count').text(data.success);
            $('.amazon-progress-failed-count').text(data.failed);
            
            // Update timing
            if (data.start_time) {
                var elapsed = Math.floor((Date.now() - new Date(data.start_time).getTime()) / 1000);
                $('.amazon-progress-elapsed').text(this.formatDuration(elapsed));
                
                if (data.processed > 0 && data.status === 'running') {
                    var rate = data.processed / elapsed;
                    var remainingTime = (data.total - data.processed) / rate;
                    $('.amazon-progress-eta').text(this.formatDuration(remainingTime));
                }
            }
            
            // Update current product
            if (data.current_asin) {
                $('.amazon-current-asin .amazon-current-value').text(data.current_asin);
            }
            if (data.current_product) {
                $('.amazon-current-title .amazon-current-value').text(data.current_product);
            }
        },

        /**
         * Handle import success
         */
        handleImportSuccess: function(asin, response) {
            if (response.success) {
                var $productItem = $(`.amazon-product-item[data-asin="${asin}"]`);
                this.markProductAsImported($productItem, response.data);
                this.showNotification('success', response.data.message);
            } else {
                this.handleImportError(asin, response.data.message);
            }
        },

        /**
         * Handle import error
         */
        handleImportError: function(asin, error) {
            var $productItem = $(`.amazon-product-item[data-asin="${asin}"]`);
            this.markProductAsError($productItem, error);
            
            // Implement retry logic
            var retryCount = this.state.retryCount.get(asin) || 0;
            if (retryCount < this.config.maxRetries) {
                this.state.retryCount.set(asin, retryCount + 1);
                setTimeout(function() {
                    this.importSingleProduct(asin);
                }.bind(this), this.config.retryDelay);
            } else {
                this.showNotification('error', `Failed to import ${asin}: ${error}`);
            }
        },

        /**
         * Preview product
         */
        previewProduct: function(asin) {
            this.showModal('product');
            this.loadProductPreview(asin);
        },

        /**
         * Load product preview
         */
        loadProductPreview: function(asin) {
            var $modal = $('#amazon-product-modal');
            var $body = $modal.find('.amazon-modal-body');
            
            $body.html('<div class="amazon-loading"><span class="spinner is-active"></span><p>Loading product details...</p></div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_get_product_details',
                    nonce: this.config.nonce,
                    asin: asin
                },
                success: function(response) {
                    if (response.success) {
                        $body.html(this.generateProductPreviewHTML(response.data));
                    } else {
                        $body.html('<p class="amazon-error">Failed to load product details.</p>');
                    }
                }.bind(this),
                error: function() {
                    $body.html('<p class="amazon-error">Failed to load product details.</p>');
                }
            });
        },

        /**
         * Quick import handler
         */
        handleQuickImport: function() {
            var asin = $('#amazon-quick-asin').val().trim().toUpperCase();
            var forceUpdate = $('#amazon-quick-force-update').is(':checked');
            
            if (!this.validateAsin(asin)) {
                this.showNotification('error', 'Invalid ASIN format');
                return;
            }

            var $button = this.elements.$quickImportForm.find('button[type="submit"]');
            this.setButtonLoading($button, true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_import_product',
                    nonce: this.config.nonce,
                    asin: asin,
                    force_update: forceUpdate
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', response.data.message);
                        this.elements.$quickImportForm[0].reset();
                    } else {
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Import failed. Please try again.');
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($button, false);
                }.bind(this)
            });
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    this.showNotification('success', 'Copied to clipboard');
                }.bind(this));
            } else {
                // Fallback
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    this.showNotification('success', 'Copied to clipboard');
                } catch (err) {
                    this.showNotification('error', 'Failed to copy');
                }
                document.body.removeChild(textArea);
            }
        },

        // UI Helper Methods
        showSearchLoading: function() {
            $('#amazon-search-loading').show();
            this.elements.$resultsContainer.hide();
        },

        hideSearchLoading: function() {
            $('#amazon-search-loading').hide();
        },

        showResultsContainer: function() {
            this.elements.$resultsContainer.show();
        },

        showProgressContainer: function() {
            this.elements.$progressContainer.show();
            $('html, body').animate({
                scrollTop: this.elements.$progressContainer.offset().top - 50
            }, 500);
        },

        showProductProgress: function($item, message) {
            $item.find('.amazon-import-message').text(message);
            $item.find('.amazon-import-status').show();
            $item.find('.amazon-product-actions').hide();
        },

        hideProductProgress: function($item) {
            $item.find('.amazon-import-status').hide();
            $item.find('.amazon-product-actions').show();
        },

        markProductAsImported: function($item, data) {
            $item.addClass('amazon-imported');
            $item.find('.amazon-import-product-btn')
                 .removeClass('amazon-import-product-btn')
                 .addClass('amazon-update-product-btn')
                 .html('<span class="dashicons dashicons-update"></span>Update');
        },

        markProductAsError: function($item, error) {
            $item.addClass('amazon-import-error');
            this.showNotification('error', error);
        },

        showNotification: function(type, message) {
            var $notification = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.amazon-notifications, .wrap').first().prepend($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Utility Methods
        validateAsin: function(asin) {
            return /^[A-Z0-9]{10}$/.test(asin);
        },

        generateBatchId: function() {
            return 'batch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        formatDuration: function(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var secs = Math.floor(seconds % 60);
            
            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        },

        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true).addClass('amazon-loading');
                $button.find('.amazon-quick-import-text').hide();
                $button.find('.amazon-quick-import-loading').show();
            } else {
                $button.prop('disabled', false).removeClass('amazon-loading');
                $button.find('.amazon-quick-import-text').show();
                $button.find('.amazon-quick-import-loading').hide();
            }
        },

        // State Management
        saveSearchState: function() {
            if (this.state.lastSearchParams) {
                localStorage.setItem('amazon_last_search', JSON.stringify(this.state.lastSearchParams));
            }
        },

        clearSelection: function() {
            this.state.selectedProducts.clear();
            $('.amazon-product-select').prop('checked', false);
            this.updateSelectionUI();
        },

        // Placeholder methods for features to be implemented
        loadPage: function(page) {
            this.state.currentPage = page;
            this.performSearch();
        },

        toggleViewMode: function(mode) {
            this.setViewMode(mode);
            localStorage.setItem('amazon_import_view_mode', mode);
        },

        setViewMode: function(mode) {
            this.elements.$viewToggles.removeClass('active');
            this.elements.$viewToggles.filter(`[data-view="${mode}"]`).addClass('active');
            this.elements.$resultsGrid.removeClass('amazon-view-grid amazon-view-list').addClass(`amazon-view-${mode}`);
        },

        toggleAdvancedOptions: function() {
            $('#amazon-advanced-options').slideToggle();
        },

        showModal: function(type) {
            $(`#amazon-${type}-modal`).show();
            $('body').addClass('amazon-modal-open');
        },

        closeModal: function($modal) {
            $modal.hide();
            $('body').removeClass('amazon-modal-open');
        },

        handleKeyboardShortcuts: function(e) {
            // Implement keyboard shortcuts
        },

        initializeTooltips: function() {
            $('[title]').tooltip();
        },

        initializeProgressIndicators: function() {
            // Initialize progress indicators
        },

        initializeProductElements: function() {
            // Initialize any product-specific elements after rendering
        },

        checkOngoingImports: function() {
            // Check for any ongoing imports on page load
        },

        updateSearchInputValidation: function() {
            // Update input validation based on search type
        },

        renderNoResults: function() {
            this.elements.$resultsGrid.html('<div class="amazon-no-results"><p>No products found.</p></div>');
        },

        updateResultsInfo: function(data) {
            // Update results information display
        },

        updatePagination: function() {
            // Update pagination controls
        },

        stopProgressMonitoring: function() {
            if (this.state.progressTimer) {
                clearInterval(this.state.progressTimer);
                this.state.progressTimer = null;
            }
        },

        handleBatchComplete: function(data) {
            this.showNotification('info', `Batch import completed. ${data.success} successful, ${data.failed} failed.`);
        },

        cancelBatchImport: function() {
            if (confirm('Cancel the current import batch?')) {
                // Implement batch cancellation
            }
        },

        pauseBatchImport: function() {
            // Implement batch pause
        },

        resumeBatchImport: function() {
            // Implement batch resume
        },

        showSearchSuggestions: function(term) {
            // Implement search suggestions
        },

        hideSuggestions: function() {
            $('#amazon-search-suggestions').hide();
        },

        filterResults: function(filter) {
            // Implement result filtering
        },

        searchWithinResults: function(term) {
            // Implement search within results
        },

        generateProductPreviewHTML: function(data) {
            // Generate product preview HTML
            return '<div class="amazon-product-preview">Product preview content</div>';
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof amazon_importer_data !== 'undefined') {
            ImportInterface.init();
        }
    });

    // Expose to global scope for debugging
    window.AmazonImportInterface = ImportInterface;

})(jQuery);