/**
 * Amazon Product Importer Admin JavaScript
 *
 * @package Amazon_Product_Importer
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Main Admin object
     */
    var AmazonProductImporter = {
        
        // Configuration
        config: {
            ajaxUrl: amazon_importer_data.ajax_url,
            nonce: amazon_importer_data.nonce,
            strings: amazon_importer_data.strings,
            searchDelay: 500,
            progressRefreshInterval: 5000,
            maxRetries: 3
        },

        // State management
        state: {
            searchTimeout: null,
            currentBatchId: null,
            progressTimer: null,
            selectedProducts: [],
            currentView: 'grid',
            isImporting: false,
            retryCount: 0
        },

        // Cache for DOM elements
        cache: {
            $body: null,
            $searchForm: null,
            $searchResults: null,
            $progressContainer: null,
            $modals: {}
        },

        /**
         * Initialize the admin interface
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initComponents();
            this.loadInitialState();
        },

        /**
         * Cache frequently used DOM elements
         */
        cacheElements: function() {
            this.cache.$body = $('body');
            this.cache.$searchForm = $('#amazon-search-form');
            this.cache.$searchResults = $('#amazon-search-results');
            this.cache.$progressContainer = $('#amazon-import-progress-container');
            this.cache.$modals.product = $('#amazon-product-modal');
            this.cache.$modals.confirm = $('#amazon-import-confirm-modal');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Search functionality
            this.cache.$searchForm.on('submit', function(e) {
                e.preventDefault();
                self.handleSearch();
            });

            // Real-time search suggestions
            $('#amazon-search-term').on('input', function() {
                self.handleSearchInput($(this).val());
            });

            // Search type change
            $('#amazon-search-type').on('change', function() {
                self.handleSearchTypeChange($(this).val());
            });

            // Advanced options toggle
            $('#amazon-advanced-toggle-btn').on('click', function() {
                self.toggleAdvancedOptions();
            });

            // Product selection
            $(document).on('change', '.amazon-product-select', function() {
                self.handleProductSelection($(this));
            });

            // Select all/none buttons
            $('.amazon-select-all-btn').on('click', function() {
                self.selectAllProducts(true);
            });

            $('.amazon-select-none-btn, .amazon-deselect-all-btn').on('click', function() {
                self.selectAllProducts(false);
            });

            // View mode toggle
            $('.amazon-view-toggle').on('click', function() {
                self.toggleViewMode($(this).data('view'));
            });

            // Product actions
            $(document).on('click', '.amazon-import-product-btn', function() {
                self.importSingleProduct($(this).data('asin'));
            });

            $(document).on('click', '.amazon-update-product-btn', function() {
                self.updateSingleProduct($(this).data('asin'));
            });

            $(document).on('click', '.amazon-preview-product-btn', function() {
                self.previewProduct($(this).data('asin'));
            });

            // Bulk actions
            $('#amazon-import-selected-btn').on('click', function() {
                self.importSelectedProducts();
            });

            // Quick import
            $('#amazon-quick-import-form').on('submit', function(e) {
                e.preventDefault();
                self.handleQuickImport();
            });

            // Pagination
            $(document).on('click', '.amazon-page-btn', function() {
                var page = $(this).data('page');
                if (page) {
                    self.loadPage(page);
                }
            });

            // Jump to page
            $('.amazon-jump-btn').on('click', function() {
                var page = $('#amazon-jump-to-page').val();
                if (page) {
                    self.loadPage(parseInt(page));
                }
            });

            // Modal handling
            $('.amazon-modal-close, .amazon-modal-cancel').on('click', function() {
                self.closeModals();
            });

            // Action menu toggles
            $(document).on('click', '.amazon-action-menu-toggle', function(e) {
                e.stopPropagation();
                self.toggleActionMenu($(this));
            });

            // Copy ASIN functionality
            $(document).on('click', '.amazon-copy-asin', function() {
                self.copyAsin($(this).data('asin'));
            });

            // Progress controls
            $('#amazon-cancel-import-btn').on('click', function() {
                self.cancelImport();
            });

            $('#amazon-pause-import-btn').on('click', function() {
                self.pauseImport();
            });

            $('#amazon-resume-import-btn').on('click', function() {
                self.resumeImport();
            });

            // Global click handler for closing menus
            $(document).on('click', function() {
                $('.amazon-action-menu-content').hide();
            });

            // Form validation
            this.cache.$searchForm.find('input, select').on('blur', function() {
                self.validateField($(this));
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
        initComponents: function() {
            this.initTooltips();
            this.initSortables();
            this.initDatePickers();
            this.initColorPickers();
        },

        /**
         * Load initial state
         */
        loadInitialState: function() {
            // Restore last search if available
            var lastSearch = localStorage.getItem('amazon_last_search');
            if (lastSearch) {
                try {
                    var searchData = JSON.parse(lastSearch);
                    $('#amazon-search-term').val(searchData.term);
                    $('#amazon-search-type').val(searchData.type);
                } catch (e) {
                    // Ignore parsing errors
                }
            }

            // Check for ongoing imports
            this.checkOngoingImports();
        },

        /**
         * Handle search form submission
         */
        handleSearch: function() {
            var searchData = this.getSearchFormData();
            
            if (!this.validateSearchData(searchData)) {
                return;
            }

            this.showSearchLoading();
            this.saveSearchState(searchData);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_search_products',
                    nonce: this.config.nonce,
                    search_term: searchData.term,
                    search_type: searchData.type,
                    search_category: searchData.category,
                    min_price: searchData.minPrice,
                    max_price: searchData.maxPrice,
                    sort_by: searchData.sortBy,
                    page: searchData.page || 1
                },
                success: function(response) {
                    this.handleSearchSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleSearchError(error);
                }.bind(this),
                complete: function() {
                    this.hideSearchLoading();
                }.bind(this)
            });
        },

        /**
         * Handle search input for suggestions
         */
        handleSearchInput: function(value) {
            clearTimeout(this.state.searchTimeout);
            
            if (value.length < 3) {
                this.hideSuggestions();
                return;
            }

            this.state.searchTimeout = setTimeout(function() {
                this.showSearchSuggestions(value);
            }.bind(this), this.config.searchDelay);
        },

        /**
         * Handle search type change
         */
        handleSearchTypeChange: function(type) {
            var $searchInput = $('#amazon-search-term');
            var $placeholder = $searchInput.attr('placeholder');

            if (type === 'asin') {
                $searchInput.attr('placeholder', 'B01234567X');
                $searchInput.attr('pattern', '^[A-Z0-9]{10}$');
                $searchInput.attr('maxlength', '10');
            } else {
                $searchInput.attr('placeholder', this.config.strings.search_keywords_placeholder || 'Enter keywords...');
                $searchInput.removeAttr('pattern');
                $searchInput.removeAttr('maxlength');
            }
        },

        /**
         * Toggle advanced search options
         */
        toggleAdvancedOptions: function() {
            var $options = $('#amazon-advanced-options');
            var $toggle = $('#amazon-advanced-toggle-btn');
            
            if ($options.is(':visible')) {
                $options.slideUp();
                $toggle.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            } else {
                $options.slideDown();
                $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        },

        /**
         * Handle product selection
         */
        handleProductSelection: function($checkbox) {
            var asin = $checkbox.val();
            var isChecked = $checkbox.is(':checked');
            
            if (isChecked) {
                if (this.state.selectedProducts.indexOf(asin) === -1) {
                    this.state.selectedProducts.push(asin);
                }
            } else {
                var index = this.state.selectedProducts.indexOf(asin);
                if (index > -1) {
                    this.state.selectedProducts.splice(index, 1);
                }
            }
            
            this.updateSelectionUI();
        },

        /**
         * Select all or none products
         */
        selectAllProducts: function(select) {
            $('.amazon-product-select').prop('checked', select);
            
            if (select) {
                this.state.selectedProducts = [];
                $('.amazon-product-select').each(function() {
                    this.state.selectedProducts.push($(this).val());
                }.bind(this));
            } else {
                this.state.selectedProducts = [];
            }
            
            this.updateSelectionUI();
        },

        /**
         * Update selection UI
         */
        updateSelectionUI: function() {
            var count = this.state.selectedProducts.length;
            
            $('#amazon-selected-count').text(count);
            $('.amazon-selected-count').text('(' + count + ')');
            
            // Enable/disable bulk action buttons
            var $bulkButtons = $('#amazon-import-selected-btn, #amazon-bulk-preview-btn, #amazon-bulk-compare-btn');
            $bulkButtons.prop('disabled', count === 0);
            
            // Update select all button state
            var totalProducts = $('.amazon-product-select').length;
            var allSelected = count === totalProducts && totalProducts > 0;
            $('.amazon-select-all-btn').toggleClass('amazon-all-selected', allSelected);
        },

        /**
         * Toggle view mode
         */
        toggleViewMode: function(mode) {
            this.state.currentView = mode;
            
            $('.amazon-view-toggle').removeClass('active');
            $('.amazon-view-toggle[data-view="' + mode + '"]').addClass('active');
            
            this.cache.$searchResults.removeClass('amazon-view-grid amazon-view-list')
                                   .addClass('amazon-view-' + mode);
            
            // Save preference
            localStorage.setItem('amazon_view_mode', mode);
        },

        /**
         * Import single product
         */
        importSingleProduct: function(asin) {
            if (!asin) return;

            var $button = $('.amazon-import-product-btn[data-asin="' + asin + '"]');
            var $productItem = $button.closest('.amazon-product-item');
            
            this.showProductImporting($productItem);
            
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
                    if (response.success) {
                        this.showProductImported($productItem, response.data);
                        this.showNotification('success', response.data.message);
                    } else {
                        this.showProductError($productItem, response.data.message);
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showProductError($productItem, this.config.strings.import_error);
                    this.showNotification('error', this.config.strings.import_error);
                }.bind(this),
                complete: function() {
                    this.hideProductImporting($productItem);
                }.bind(this)
            });
        },

        /**
         * Update single product
         */
        updateSingleProduct: function(asin) {
            if (!asin) return;

            if (!confirm(this.config.strings.confirm_update)) {
                return;
            }

            var $button = $('.amazon-update-product-btn[data-asin="' + asin + '"]');
            var $productItem = $button.closest('.amazon-product-item');
            
            this.showProductImporting($productItem);
            
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
                    if (response.success) {
                        this.showProductImported($productItem, response.data);
                        this.showNotification('success', response.data.message);
                    } else {
                        this.showProductError($productItem, response.data.message);
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showProductError($productItem, this.config.strings.import_error);
                    this.showNotification('error', this.config.strings.import_error);
                }.bind(this),
                complete: function() {
                    this.hideProductImporting($productItem);
                }.bind(this)
            });
        },

        /**
         * Preview product details
         */
        previewProduct: function(asin) {
            if (!asin) return;

            this.showModal('product');
            this.loadProductDetails(asin);
        },

        /**
         * Import selected products
         */
        importSelectedProducts: function() {
            if (this.state.selectedProducts.length === 0) {
                this.showNotification('warning', this.config.strings.no_selection);
                return;
            }

            var message = this.config.strings.confirm_batch_import.replace('%d', this.state.selectedProducts.length);
            if (!confirm(message)) {
                return;
            }

            this.startBatchImport(this.state.selectedProducts);
        },

        /**
         * Handle quick import
         */
        handleQuickImport: function() {
            var asin = $('#amazon-quick-asin').val().trim().toUpperCase();
            var forceUpdate = $('#amazon-quick-force-update').is(':checked');
            
            if (!this.validateAsin(asin)) {
                this.showNotification('error', 'Invalid ASIN format');
                return;
            }

            var $form = $('#amazon-quick-import-form');
            var $button = $form.find('button[type="submit"]');
            
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
                        $form[0].reset();
                    } else {
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', this.config.strings.import_error);
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($button, false);
                }.bind(this)
            });
        },

        /**
         * Start batch import
         */
        startBatchImport: function(asins) {
            this.state.currentBatchId = this.generateBatchId();
            this.state.isImporting = true;
            
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
                    } else {
                        this.showNotification('error', response.data.message);
                        this.state.isImporting = false;
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', this.config.strings.import_error);
                    this.state.isImporting = false;
                }.bind(this)
            });
        },

        /**
         * Start progress monitoring
         */
        startProgressMonitoring: function() {
            this.state.progressTimer = setInterval(function() {
                this.updateProgress();
            }.bind(this), this.config.progressRefreshInterval);
        },

        /**
         * Update progress
         */
        updateProgress: function() {
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
                        
                        if (response.data.status === 'completed' || response.data.status === 'cancelled') {
                            this.stopProgressMonitoring();
                            this.state.isImporting = false;
                        }
                    }
                }.bind(this)
            });
        },

        /**
         * Stop progress monitoring
         */
        stopProgressMonitoring: function() {
            if (this.state.progressTimer) {
                clearInterval(this.state.progressTimer);
                this.state.progressTimer = null;
            }
        },

        /**
         * Cancel import
         */
        cancelImport: function() {
            if (!confirm(this.config.strings.cancel_import)) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_cancel_import',
                    nonce: this.config.nonce,
                    batch_id: this.state.currentBatchId
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('info', response.data.message);
                        this.stopProgressMonitoring();
                        this.state.isImporting = false;
                    }
                }.bind(this)
            });
        },

        /**
         * Copy ASIN to clipboard
         */
        copyAsin: function(asin) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(asin).then(function() {
                    this.showNotification('success', 'ASIN copied to clipboard');
                }.bind(this));
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = asin;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    this.showNotification('success', 'ASIN copied to clipboard');
                } catch (err) {
                    this.showNotification('error', 'Failed to copy ASIN');
                }
                document.body.removeChild(textArea);
            }
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + A: Select all products
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && this.cache.$searchResults.is(':visible')) {
                e.preventDefault();
                this.selectAllProducts(true);
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                this.closeModals();
            }
            
            // Enter: Trigger search when search field is focused
            if (e.key === 'Enter' && $('#amazon-search-term').is(':focus')) {
                e.preventDefault();
                this.handleSearch();
            }
        },

        /**
         * Show notification
         */
        showNotification: function(type, message) {
            var $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notifications
            $('.amazon-notification').remove();
            
            $notification.addClass('amazon-notification').prependTo('.wrap');
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Show modal
         */
        showModal: function(modalType) {
            var $modal = this.cache.$modals[modalType];
            if ($modal) {
                $modal.show();
                this.cache.$body.addClass('amazon-modal-open');
            }
        },

        /**
         * Close all modals
         */
        closeModals: function() {
            $('.amazon-modal').hide();
            this.cache.$body.removeClass('amazon-modal-open');
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true);
                $button.find('.amazon-search-text, .amazon-quick-import-text').hide();
                $button.find('.amazon-search-spinner, .amazon-quick-import-loading').show();
            } else {
                $button.prop('disabled', false);
                $button.find('.amazon-search-text, .amazon-quick-import-text').show();
                $button.find('.amazon-search-spinner, .amazon-quick-import-loading').hide();
            }
        },

        /**
         * Validate ASIN format
         */
        validateAsin: function(asin) {
            return /^[A-Z0-9]{10}$/.test(asin);
        },

        /**
         * Generate unique batch ID
         */
        generateBatchId: function() {
            return 'batch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Get search form data
         */
        getSearchFormData: function() {
            return {
                term: $('#amazon-search-term').val().trim(),
                type: $('#amazon-search-type').val(),
                category: $('#amazon-search-category').val(),
                minPrice: $('#amazon-min-price').val(),
                maxPrice: $('#amazon-max-price').val(),
                sortBy: $('#amazon-sort-by').val(),
                page: 1
            };
        },

        /**
         * Validate search data
         */
        validateSearchData: function(data) {
            if (!data.term) {
                this.showNotification('error', 'Please enter a search term');
                return false;
            }

            if (data.type === 'asin' && !this.validateAsin(data.term.toUpperCase())) {
                this.showNotification('error', 'Invalid ASIN format');
                return false;
            }

            return true;
        },

        /**
         * Save search state
         */
        saveSearchState: function(data) {
            localStorage.setItem('amazon_last_search', JSON.stringify({
                term: data.term,
                type: data.type
            }));
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[title]').tooltip();
        },

        /**
         * Initialize other components as needed
         */
        initSortables: function() {
            // Implement if needed
        },

        initDatePickers: function() {
            // Implement if needed
        },

        initColorPickers: function() {
            // Implement if needed
        },

        // Placeholder methods for UI updates
        showSearchLoading: function() {
            $('#amazon-search-loading').show();
        },

        hideSearchLoading: function() {
            $('#amazon-search-loading').hide();
        },

        handleSearchSuccess: function(response) {
            // Handle successful search response
            console.log('Search successful', response);
        },

        handleSearchError: function(error) {
            // Handle search error
            console.error('Search error', error);
        },

        showProgressContainer: function() {
            this.cache.$progressContainer.show();
        },

        updateProgressUI: function(data) {
            // Update progress UI with data
            console.log('Progress update', data);
        },

        showProductImporting: function($item) {
            $item.find('.amazon-import-status').show();
        },

        hideProductImporting: function($item) {
            $item.find('.amazon-import-status').hide();
        },

        showProductImported: function($item, data) {
            // Mark product as imported
            $item.addClass('amazon-imported');
        },

        showProductError: function($item, message) {
            // Show error state
            $item.addClass('amazon-import-error');
        },

        loadProductDetails: function(asin) {
            // Load product details for modal
        },

        checkOngoingImports: function() {
            // Check for any ongoing imports on page load
        },

        toggleActionMenu: function($toggle) {
            var $menu = $toggle.siblings('.amazon-action-menu-content');
            $('.amazon-action-menu-content').not($menu).hide();
            $menu.toggle();
        },

        loadPage: function(page) {
            // Load specific page of results
            var searchData = this.getSearchFormData();
            searchData.page = page;
            // Re-run search with new page
        },

        hideSuggestions: function() {
            $('#amazon-search-suggestions').hide();
        },

        showSearchSuggestions: function(value) {
            // Show search suggestions
        },

        validateField: function($field) {
            // Validate individual form field
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        AmazonProductImporter.init();
    });

    // Expose to global scope for debugging
    window.AmazonProductImporter = AmazonProductImporter;

})(jQuery);