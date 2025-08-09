(function($) {
    'use strict';

    /**
     * Amazon Import Interface
     */
    var AmazonImportInterface = {
        
        currentPage: 1,
        isSearching: false,
        isImporting: false,
        importQueue: [],

        init: function() {
            this.bindEvents();
            this.initSearchForm();
        },

        bindEvents: function() {
            // Search form submission
            $('#amazon-search-form').on('submit', this.handleSearch.bind(this));
            
            // Import buttons
            $(document).on('click', '.import-button', this.handleImport.bind(this));
            
            // Pagination
            $(document).on('click', '.amazon-pagination a', this.handlePagination.bind(this));
            
            // Clear results
            $('#clear-results').on('click', this.clearResults.bind(this));
            
            // Bulk import
            $('#bulk-import-selected').on('click', this.handleBulkImport.bind(this));
            
            // Select all checkbox
            $('#select-all-products').on('change', this.handleSelectAll.bind(this));
            
            // Individual product checkboxes
            $(document).on('change', '.product-checkbox', this.updateBulkImportButton.bind(this));
        },

        initSearchForm: function() {
            // Initialize search type toggle
            $('input[name="search_type"]').on('change', function() {
                var searchType = $(this).val();
                if (searchType === 'asin') {
                    $('#search-keywords').attr('placeholder', 'B08N5WRWNW, B07FZ8S74R, ...');
                    $('#search-category').closest('tr').hide();
                } else {
                    $('#search-keywords').attr('placeholder', 'iPhone, Samsung Galaxy, ...');
                    $('#search-category').closest('tr').show();
                }
            });
        },

        handleSearch: function(e) {
            e.preventDefault();
            
            if (this.isSearching) {
                return;
            }
            
            var $form = $(e.target);
            var searchType = $form.find('input[name="search_type"]:checked').val();
            var keywords = $form.find('#search-keywords').val().trim();
            var category = $form.find('#search-category').val();
            
            if (!keywords) {
                this.showMessage(amazon_importer_ajax.strings.enter_keywords, 'error');
                return;
            }
            
            this.currentPage = 1;
            this.performSearch(searchType, keywords, category);
        },

        performSearch: function(searchType, keywords, category, page) {
            page = page || 1;
            
            this.isSearching = true;
            this.showLoading(true);
            this.clearResults();
            
            var data = {
                action: 'api_search_products',
                nonce: amazon_importer_ajax.nonce,
                search_type: searchType,
                keywords: keywords,
                category: category,
                page: page,
                items_per_page: 20
            };

            $.post(amazon_importer_ajax.ajax_url, data)
                .done(this.handleSearchResponse.bind(this))
                .fail(this.handleSearchError.bind(this))
                .always(function() {
                    this.isSearching = false;
                    this.showLoading(false);
                }.bind(this));
        },

        handleSearchResponse: function(response) {
            if (response.success && response.data.items && response.data.items.length > 0) {
                this.displayResults(response.data);
                this.updateSearchStats(response.data);
            } else {
                var message = response.data && response.data.error ? 
                    response.data.error : 
                    amazon_importer_ajax.strings.no_results;
                this.showMessage(message, 'info');
            }
        },

        handleSearchError: function() {
            this.showMessage('Erreur lors de la recherche. Veuillez réessayer.', 'error');
        },

        displayResults: function(data) {
            var $resultsContainer = $('#amazon-results');
            var $grid = $('#amazon-products-grid');
            
            $grid.empty();
            
            if (data.items && data.items.length > 0) {
                data.items.forEach(function(item) {
                    var productCard = this.createProductCard(item);
                    $grid.append(productCard);
                }.bind(this));
                
                this.createPagination(data);
                $resultsContainer.show();
            }
        },

        createProductCard: function(item) {
            var imageUrl = item.image || amazon_importer_ajax.default_image;
            var price = item.price ? item.price : 'Prix non disponible';
            var isImported = item.is_imported || false;
            
            var cardHtml = `
                <div class="amazon-product-card" data-asin="${item.asin}">
                    <div class="product-checkbox-wrapper">
                        <input type="checkbox" class="product-checkbox" value="${item.asin}" ${isImported ? 'disabled' : ''}>
                    </div>
                    <div class="amazon-product-image">
                        <img src="${imageUrl}" alt="${item.title}" loading="lazy">
                    </div>
                    <div class="amazon-product-title">${item.title}</div>
                    <div class="amazon-product-asin">ASIN: ${item.asin}</div>
                    <div class="amazon-product-price">${price}</div>
                    <div class="amazon-product-actions">
                        ${this.createImportButton(item.asin, isImported)}
                    </div>
                    ${isImported ? '<div class="import-status">✓ Déjà importé</div>' : ''}
                </div>
            `;
            
            return $(cardHtml);
        },

        createImportButton: function(asin, isImported) {
            if (isImported) {
                return '<button class="import-button imported" disabled>Déjà importé</button>';
            }
            
            return `<button class="import-button" data-asin="${asin}">Importer</button>`;
        },

        createPagination: function(data) {
            if (!data.pagination) return;
            
            var pagination = data.pagination;
            var paginationHtml = '<div class="amazon-pagination">';
            
            if (pagination.has_previous) {
                paginationHtml += `<a href="#" data-page="${pagination.current_page - 1}">« Précédent</a>`;
            }
            
            for (var i = Math.max(1, pagination.current_page - 2); 
                 i <= Math.min(pagination.total_pages, pagination.current_page + 2); 
                 i++) {
                var activeClass = i === pagination.current_page ? ' current' : '';
                paginationHtml += `<a href="#" data-page="${i}" class="page-number${activeClass}">${i}</a>`;
            }
            
            if (pagination.has_next) {
                paginationHtml += `<a href="#" data-page="${pagination.current_page + 1}">Suivant »</a>`;
            }
            
            paginationHtml += '</div>';
            
            $('#amazon-results').append(paginationHtml);
        },

        handlePagination: function(e) {
            e.preventDefault();
            
            if (this.isSearching) return;
            
            var page = parseInt($(e.target).data('page'));
            var $form = $('#amazon-search-form');
            var searchType = $form.find('input[name="search_type"]:checked').val();
            var keywords = $form.find('#search-keywords').val().trim();
            var category = $form.find('#search-category').val();
            
            this.currentPage = page;
            this.performSearch(searchType, keywords, category, page);
        },

        handleImport: function(e) {
            e.preventDefault();
            
            if (this.isImporting) return;
            
            var $button = $(e.target);
            var asin = $button.data('asin');
            
            this.importProduct(asin, $button);
        },

        importProduct: function(asin, $button) {
            $button.prop('disabled', true)
                   .addClass('importing')
                   .text('Importation...');
            
            var data = {
                action: 'api_import_product',
                nonce: amazon_importer_ajax.nonce,
                asin: asin
            };

            $.post(amazon_importer_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $button.removeClass('importing')
                               .addClass('imported')
                               .text('Importé');
                        
                        // Disable checkbox
                        $button.closest('.amazon-product-card')
                               .find('.product-checkbox')
                               .prop('disabled', true);
                        
                        this.showMessage(
                            `Produit ${asin} importé avec succès!`, 
                            'success'
                        );
                        
                        // Update import statistics
                        this.updateImportStats();
                        
                    } else {
                        $button.removeClass('importing')
                               .prop('disabled', false)
                               .text('Importer');
                        
                        var message = response.data && response.data.error ? 
                            response.data.error : 
                            'Erreur lors de l\'importation';
                        this.showMessage(message, 'error');
                    }
                }.bind(this))
                .fail(function() {
                    $button.removeClass('importing')
                           .prop('disabled', false)
                           .text('Importer');
                    
                    this.showMessage('Erreur lors de l\'importation', 'error');
                }.bind(this));
        },

        handleBulkImport: function(e) {
            e.preventDefault();
            
            var selectedAsins = this.getSelectedAsins();
            
            if (selectedAsins.length === 0) {
                this.showMessage('Veuillez sélectionner au moins un produit', 'error');
                return;
            }
            
            if (!confirm(`Importer ${selectedAsins.length} produit(s) sélectionné(s)?`)) {
                return;
            }
            
            this.startBulkImport(selectedAsins);
        },

        startBulkImport: function(asins) {
            this.importQueue = asins.slice(); // Copy array
            this.updateBulkProgress(0, this.importQueue.length);
            this.processBulkImportQueue();
        },

        processBulkImportQueue: function() {
            if (this.importQueue.length === 0) {
                this.completeBulkImport();
                return;
            }
            
            var asin = this.importQueue.shift();
            var $button = $(`.import-button[data-asin="${asin}"]`);
            
            // Update progress
            var completed = this.importQueue.length;
            var total = completed + this.importQueue.length;
            this.updateBulkProgress(total - completed, total);
            
            // Import single product
            this.importProduct(asin, $button);
            
            // Continue with next item after delay
            setTimeout(this.processBulkImportQueue.bind(this), 1000);
        },

        updateBulkProgress: function(completed, total) {
            var percentage = Math.round((completed / total) * 100);
            
            $('#bulk-progress-bar .amazon-progress-fill').css('width', percentage + '%');
            $('#bulk-progress-text').text(`${completed}/${total} produits importés`);
            
            if (completed === 0) {
                $('#bulk-progress-wrap').show();
            }
        },

        completeBulkImport: function() {
            setTimeout(function() {
                $('#bulk-progress-wrap').hide();
                this.showMessage('Importation en lot terminée!', 'success');
                this.updateBulkImportButton();
            }.bind(this), 1000);
        },

        getSelectedAsins: function() {
            var asins = [];
            $('.product-checkbox:checked:not(:disabled)').each(function() {
                asins.push($(this).val());
            });
            return asins;
        },

        handleSelectAll: function(e) {
            var isChecked = $(e.target).is(':checked');
            $('.product-checkbox:not(:disabled)').prop('checked', isChecked);
            this.updateBulkImportButton();
        },

        updateBulkImportButton: function() {
            var selectedCount = this.getSelectedAsins().length;
            var $button = $('#bulk-import-selected');
            
            if (selectedCount > 0) {
                $button.prop('disabled', false)
                       .text(`Importer ${selectedCount} produit(s)`);
            } else {
                $button.prop('disabled', true)
                       .text('Importer la sélection');
            }
        },

        updateSearchStats: function(data) {
            if (data.pagination) {
                var stats = `${data.pagination.total_items} résultat(s) trouvé(s)`;
                $('#search-stats').text(stats).show();
            }
        },

        updateImportStats: function() {
            // This could fetch and display import statistics
            // For now, just increment a counter if it exists
            var $counter = $('#import-counter');
            if ($counter.length) {
                var current = parseInt($counter.text()) || 0;
                $counter.text(current + 1);
            }
        },

        clearResults: function() {
            $('#amazon-results').hide();
            $('#amazon-products-grid').empty();
            $('#search-stats').hide();
            this.updateBulkImportButton();
        },

        showLoading: function(show) {
            if (show) {
                $('#amazon-loading').show();
                $('#search-button').prop('disabled', true);
            } else {
                $('#amazon-loading').hide();
                $('#search-button').prop('disabled', false);
            }
        },

        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $(`<div class="amazon-message ${type}">${message}</div>`);
            
            $('#amazon-messages').append($message);
            
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AmazonImportInterface.init();
    });

})(jQuery);