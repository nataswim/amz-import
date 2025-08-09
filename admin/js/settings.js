/**
 * Amazon Product Importer - Settings JavaScript
 *
 * Handles the settings page functionality including form validation,
 * API testing, and configuration management.
 *
 * @package Amazon_Product_Importer
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Settings Controller
     */
    var SettingsController = {

        // Configuration
        config: {
            ajaxUrl: amazon_settings_data.ajax_url,
            nonce: amazon_settings_data.nonce,
            currentTab: amazon_settings_data.current_tab,
            strings: amazon_settings_data.strings,
            validationDelay: 500,
            autoSaveDelay: 3000,
            apiTestTimeout: 30000
        },

        // State management
        state: {
            isDirty: false,
            isValidating: false,
            isTesting: false,
            isSaving: false,
            validationTimer: null,
            autoSaveTimer: null,
            originalValues: {},
            validationErrors: {},
            unsavedChanges: new Set()
        },

        // DOM element cache
        elements: {
            $form: null,
            $tabs: null,
            $submitButton: null,
            $resetButton: null,
            $testApiButton: null,
            $exportButton: null,
            $importButton: null,
            $fileInput: null,
            $confirmModal: null
        },

        /**
         * Initialize the settings controller
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeComponents();
            this.loadInitialState();
            this.setupFormValidation();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.$form = $('#amazon-settings-form');
            this.elements.$tabs = $('.amazon-settings-tabs .nav-tab');
            this.elements.$submitButton = $('#submit');
            this.elements.$resetButton = $('#amazon-reset-form-btn');
            this.elements.$testApiButton = $('#amazon-test-api-btn');
            this.elements.$exportButton = $('#amazon-export-settings-btn');
            this.elements.$importButton = $('#amazon-import-settings-btn');
            this.elements.$fileInput = $('#amazon-import-settings-file');
            this.elements.$confirmModal = $('#amazon-confirm-modal');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            this.elements.$form.on('submit', function(e) {
                e.preventDefault();
                self.handleFormSubmit();
            });

            // Input change tracking
            this.elements.$form.on('change input', 'input, select, textarea', function() {
                self.handleFieldChange($(this));
            });

            // Tab navigation
            this.elements.$tabs.on('click', function(e) {
                e.preventDefault();
                self.switchTab($(this));
            });

            // API connection test
            this.elements.$testApiButton.on('click', function() {
                self.testApiConnection();
            });

            // Reset form
            this.elements.$resetButton.on('click', function() {
                self.resetForm();
            });

            // Export settings
            this.elements.$exportButton.on('click', function() {
                self.exportSettings();
            });

            // Import settings
            this.elements.$importButton.on('click', function() {
                self.elements.$fileInput.click();
            });

            // File input change
            this.elements.$fileInput.on('change', function() {
                self.importSettings(this.files[0]);
            });

            // Reset to defaults
            $('#amazon-reset-settings-btn').on('click', function() {
                self.resetToDefaults();
            });

            // Force sync
            $('#amazon-force-sync-btn').on('click', function() {
                self.forceSyncNow();
            });

            // Modal events
            $('.amazon-modal-close, .amazon-modal-cancel').on('click', function() {
                self.closeModal();
            });

            $('.amazon-modal-confirm').on('click', function() {
                self.handleModalConfirm();
            });

            // Field validation on blur
            this.elements.$form.on('blur', 'input, select, textarea', function() {
                self.validateField($(this));
            });

            // Real-time validation for specific fields
            $('#amazon_product_importer_access_key_id, #amazon_product_importer_secret_access_key').on('input', function() {
                self.scheduleValidation($(this));
            });

            // Dependency management
            $('#amazon_product_importer_auto_sync_enabled').on('change', function() {
                self.toggleSyncDependencies($(this).is(':checked'));
            });

            $('#amazon_product_importer_auto_categories').on('change', function() {
                self.toggleCategoryDependencies($(this).is(':checked'));
            });

            $('#amazon_product_importer_import_images').on('change', function() {
                self.toggleImageDependencies($(this).is(':checked'));
            });

            // Advanced field interactions
            $('#amazon_product_importer_min_price, #amazon_product_importer_max_price').on('change', function() {
                self.validatePriceRange();
            });

            $('#amazon_product_importer_category_min_depth, #amazon_product_importer_category_max_depth').on('change', function() {
                self.validateDepthRange();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                self.handleKeyboardShortcuts(e);
            });

            // Browser navigation warning
            $(window).on('beforeunload', function() {
                if (self.state.isDirty) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // Auto-save functionality
            this.elements.$form.on('change', 'input, select, textarea', function() {
                self.scheduleAutoSave();
            });

            // Preview updates
            $('#amazon_product_importer_default_status, #amazon_product_importer_default_visibility').on('change', function() {
                self.updateImportPreview();
            });

            // Marketplace change
            $('#amazon_product_importer_marketplace').on('change', function() {
                self.updateRegionOptions($(this).val());
            });
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            this.initializeTooltips();
            this.initializeColorPickers();
            this.initializeDatePickers();
            this.initializeConditionalFields();
            this.setupFieldDependencies();
        },

        /**
         * Load initial state
         */
        loadInitialState: function() {
            this.captureOriginalValues();
            this.validateAllFields();
            this.updateUI();
            this.checkApiStatus();
        },

        /**
         * Setup form validation
         */
        setupFormValidation: function() {
            // Add validation rules for each field type
            this.addValidationRules();
            
            // Initialize validation indicators
            this.initializeValidationIndicators();
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function() {
            if (this.state.isSaving) return;

            // Validate all fields before submission
            if (!this.validateAllFields()) {
                this.showValidationSummary();
                return;
            }

            this.state.isSaving = true;
            this.showSaveProgress();

            var formData = this.elements.$form.serialize();
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData + '&action=amazon_save_settings&nonce=' + this.config.nonce,
                success: function(response) {
                    this.handleSaveSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleSaveError(error);
                }.bind(this),
                complete: function() {
                    this.state.isSaving = false;
                    this.hideSaveProgress();
                }.bind(this)
            });
        },

        /**
         * Handle field changes
         */
        handleFieldChange: function($field) {
            var fieldName = $field.attr('name');
            var currentValue = this.getFieldValue($field);
            var originalValue = this.state.originalValues[fieldName];

            // Track dirty state
            if (currentValue !== originalValue) {
                this.state.unsavedChanges.add(fieldName);
                this.state.isDirty = true;
            } else {
                this.state.unsavedChanges.delete(fieldName);
                this.state.isDirty = this.state.unsavedChanges.size > 0;
            }

            // Update UI indicators
            this.updateFieldIndicator($field);
            this.updateSaveButtonState();

            // Real-time validation for critical fields
            if (this.isCriticalField(fieldName)) {
                this.scheduleValidation($field);
            }
        },

        /**
         * Switch tabs with validation
         */
        switchTab: function($tab) {
            var targetTab = $tab.attr('href').split('tab=')[1];
            
            if (this.state.isDirty) {
                this.showModal('confirm', {
                    title: 'Unsaved Changes',
                    message: 'You have unsaved changes. Do you want to save before switching tabs?',
                    confirmText: 'Save & Switch',
                    cancelText: 'Switch Without Saving',
                    callback: function(confirmed) {
                        if (confirmed) {
                            this.handleFormSubmit();
                        }
                        this.navigateToTab(targetTab);
                    }.bind(this)
                });
            } else {
                this.navigateToTab(targetTab);
            }
        },

        /**
         * Navigate to specific tab
         */
        navigateToTab: function(tab) {
            window.location.href = window.location.pathname + '?page=amazon-product-importer-settings&tab=' + tab;
        },

        /**
         * Test API connection
         */
        testApiConnection: function() {
            if (this.state.isTesting) return;

            this.state.isTesting = true;
            this.showTestProgress();

            var testData = {
                access_key_id: $('#amazon_product_importer_access_key_id').val(),
                secret_access_key: $('#amazon_product_importer_secret_access_key').val(),
                associate_tag: $('#amazon_product_importer_associate_tag').val(),
                marketplace: $('#amazon_product_importer_marketplace').val(),
                region: $('#amazon_product_importer_region').val()
            };

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                timeout: this.config.apiTestTimeout,
                data: {
                    action: 'amazon_test_api_connection',
                    nonce: this.config.nonce,
                    ...testData
                },
                success: function(response) {
                    this.handleTestSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    this.handleTestError(error, status);
                }.bind(this),
                complete: function() {
                    this.state.isTesting = false;
                    this.hideTestProgress();
                }.bind(this)
            });
        },

        /**
         * Reset form to original values
         */
        resetForm: function() {
            if (!this.state.isDirty) return;

            this.showModal('confirm', {
                title: 'Reset Form',
                message: 'This will reset all fields to their saved values. Continue?',
                confirmText: 'Reset',
                cancelText: 'Cancel',
                callback: function(confirmed) {
                    if (confirmed) {
                        this.performFormReset();
                    }
                }.bind(this)
            });
        },

        /**
         * Perform actual form reset
         */
        performFormReset: function() {
            // Reset all form fields to original values
            for (var fieldName in this.state.originalValues) {
                var $field = $('[name="' + fieldName + '"]');
                this.setFieldValue($field, this.state.originalValues[fieldName]);
            }

            // Clear dirty state
            this.state.isDirty = false;
            this.state.unsavedChanges.clear();
            this.state.validationErrors = {};

            // Update UI
            this.updateUI();
            this.showNotification('info', 'Form has been reset to saved values.');
        },

        /**
         * Export settings
         */
        exportSettings: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_export_settings',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.downloadFile(response.data.filename, response.data.content);
                        this.showNotification('success', 'Settings exported successfully.');
                    } else {
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Failed to export settings.');
                }
            });
        },

        /**
         * Import settings from file
         */
        importSettings: function(file) {
            if (!file) return;

            if (file.type !== 'application/json') {
                this.showNotification('error', 'Please select a valid JSON file.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    this.validateAndImportSettings(settings);
                } catch (error) {
                    this.showNotification('error', 'Invalid JSON file format.');
                }
            }.bind(this);

            reader.readAsText(file);
        },

        /**
         * Validate and import settings
         */
        validateAndImportSettings: function(settings) {
            // Validate settings structure
            if (!this.validateImportedSettings(settings)) {
                this.showNotification('error', 'Invalid settings format.');
                return;
            }

            this.showModal('confirm', {
                title: 'Import Settings',
                message: 'This will overwrite your current settings. Are you sure you want to continue?',
                confirmText: 'Import',
                cancelText: 'Cancel',
                callback: function(confirmed) {
                    if (confirmed) {
                        this.applyImportedSettings(settings);
                    }
                }.bind(this)
            });
        },

        /**
         * Apply imported settings
         */
        applyImportedSettings: function(settings) {
            // Apply each setting to the form
            for (var key in settings) {
                var $field = $('[name="' + key + '"]');
                if ($field.length) {
                    this.setFieldValue($field, settings[key]);
                }
            }

            // Mark as dirty and validate
            this.state.isDirty = true;
            this.validateAllFields();
            this.updateUI();
            
            this.showNotification('success', 'Settings imported successfully. Don\'t forget to save.');
        },

        /**
         * Reset to default values
         */
        resetToDefaults: function() {
            this.showModal('confirm', {
                title: 'Reset to Defaults',
                message: 'This will reset ALL settings to their default values. This action cannot be undone. Continue?',
                confirmText: 'Reset to Defaults',
                cancelText: 'Cancel',
                callback: function(confirmed) {
                    if (confirmed) {
                        this.performDefaultReset();
                    }
                }.bind(this)
            });
        },

        /**
         * Force sync now
         */
        forceSyncNow: function() {
            this.showModal('confirm', {
                title: 'Force Sync',
                message: 'This will immediately sync all Amazon products with current data. This may take some time. Continue?',
                confirmText: 'Start Sync',
                cancelText: 'Cancel',
                callback: function(confirmed) {
                    if (confirmed) {
                        this.startForcedSync();
                    }
                }.bind(this)
            });
        },

        /**
         * Start forced sync
         */
        startForcedSync: function() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'amazon_force_sync',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', 'Sync started successfully. Check the sync status page for progress.');
                    } else {
                        this.showNotification('error', response.data.message);
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('error', 'Failed to start sync.');
                }
            });
        },

        /**
         * Validate individual field
         */
        validateField: function($field) {
            var fieldName = $field.attr('name');
            var value = this.getFieldValue($field);
            var isValid = true;
            var errorMessage = '';

            // Apply validation rules based on field type
            switch (fieldName) {
                case 'amazon_product_importer_access_key_id':
                case 'amazon_product_importer_secret_access_key':
                    if (value && !/^[A-Z0-9]{16,}$/i.test(value)) {
                        isValid = false;
                        errorMessage = 'Invalid format. Must be alphanumeric, minimum 16 characters.';
                    }
                    break;

                case 'amazon_product_importer_associate_tag':
                    if (value && (value.length > 128 || !/^[a-zA-Z0-9\-_]+$/.test(value))) {
                        isValid = false;
                        errorMessage = 'Invalid associate tag format.';
                    }
                    break;

                case 'amazon_product_importer_api_rate_limit':
                    var rateLimit = parseInt(value);
                    if (value && (isNaN(rateLimit) || rateLimit < 1 || rateLimit > 10000)) {
                        isValid = false;
                        errorMessage = 'Rate limit must be between 1 and 10,000.';
                    }
                    break;

                case 'amazon_product_importer_cache_duration':
                    var duration = parseInt(value);
                    if (value && (isNaN(duration) || duration < 5 || duration > 1440)) {
                        isValid = false;
                        errorMessage = 'Cache duration must be between 5 and 1440 minutes.';
                    }
                    break;
            }

            // Update validation state
            if (isValid) {
                delete this.state.validationErrors[fieldName];
                this.clearFieldError($field);
            } else {
                this.state.validationErrors[fieldName] = errorMessage;
                this.showFieldError($field, errorMessage);
            }

            return isValid;
        },

        /**
         * Validate all fields
         */
        validateAllFields: function() {
            var isValid = true;
            
            this.elements.$form.find('input, select, textarea').each(function() {
                if (!this.validateField($(this))) {
                    isValid = false;
                }
            }.bind(this));

            // Cross-field validation
            isValid = this.validatePriceRange() && isValid;
            isValid = this.validateDepthRange() && isValid;

            return isValid;
        },

        /**
         * Validate price range
         */
        validatePriceRange: function() {
            var minPrice = parseFloat($('#amazon_product_importer_min_price').val()) || 0;
            var maxPrice = parseFloat($('#amazon_product_importer_max_price').val()) || Infinity;

            if (minPrice > maxPrice) {
                this.showFieldError($('#amazon_product_importer_max_price'), 'Maximum price must be greater than minimum price.');
                return false;
            }

            this.clearFieldError($('#amazon_product_importer_min_price'));
            this.clearFieldError($('#amazon_product_importer_max_price'));
            return true;
        },

        /**
         * Validate depth range
         */
        validateDepthRange: function() {
            var minDepth = parseInt($('#amazon_product_importer_category_min_depth').val()) || 0;
            var maxDepth = parseInt($('#amazon_product_importer_category_max_depth').val()) || Infinity;

            if (minDepth > maxDepth) {
                this.showFieldError($('#amazon_product_importer_category_max_depth'), 'Maximum depth must be greater than minimum depth.');
                return false;
            }

            this.clearFieldError($('#amazon_product_importer_category_min_depth'));
            this.clearFieldError($('#amazon_product_importer_category_max_depth'));
            return true;
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S: Save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.handleFormSubmit();
            }

            // Ctrl/Cmd + R: Reset
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.resetForm();
            }

            // Escape: Close modals
            if (e.key === 'Escape') {
                this.closeModal();
            }
        },

        /**
         * Toggle sync dependencies
         */
        toggleSyncDependencies: function(enabled) {
            var $dependencies = $('#amazon_product_importer_sync_interval, #amazon_product_importer_sync_price, #amazon_product_importer_sync_stock, #amazon_product_importer_sync_title, #amazon_product_importer_sync_description, #amazon_product_importer_sync_images');
            
            $dependencies.prop('disabled', !enabled);
            $dependencies.closest('tr').toggleClass('amazon-disabled', !enabled);
        },

        /**
         * Toggle category dependencies
         */
        toggleCategoryDependencies: function(enabled) {
            var $dependencies = $('#amazon_product_importer_category_min_depth, #amazon_product_importer_category_max_depth');
            
            $dependencies.prop('disabled', !enabled);
            $dependencies.closest('tr').toggleClass('amazon-disabled', !enabled);
        },

        /**
         * Toggle image dependencies
         */
        toggleImageDependencies: function(enabled) {
            var $dependencies = $('#amazon_product_importer_max_images, #amazon_product_importer_thumbnail_size');
            
            $dependencies.prop('disabled', !enabled);
            $dependencies.closest('tr').toggleClass('amazon-disabled', !enabled);
        },

        /**
         * Update region options based on marketplace
         */
        updateRegionOptions: function(marketplace) {
            var regionMapping = {
                'www.amazon.com': 'us-east-1',
                'www.amazon.ca': 'us-east-1',
                'www.amazon.co.uk': 'eu-west-1',
                'www.amazon.de': 'eu-west-1',
                'www.amazon.fr': 'eu-west-1',
                'www.amazon.it': 'eu-west-1',
                'www.amazon.es': 'eu-west-1',
                'www.amazon.co.jp': 'ap-northeast-1',
                'www.amazon.com.au': 'ap-southeast-2',
                'www.amazon.in': 'ap-south-1'
            };

            var recommendedRegion = regionMapping[marketplace];
            if (recommendedRegion) {
                $('#amazon_product_importer_region').val(recommendedRegion);
                this.showNotification('info', `Region automatically updated to ${recommendedRegion} for ${marketplace}`);
            }
        },

        /**
         * Update import preview
         */
        updateImportPreview: function() {
            var status = $('#amazon_product_importer_default_status').val();
            var visibility = $('#amazon_product_importer_default_visibility').val();

            $('.amazon-preview-status-value').text(status);
            $('.amazon-preview-visibility-value').text(visibility);
        },

        // UI Helper Methods
        showNotification: function(type, message) {
            var $notification = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            
            $('.amazon-settings-container').before($notification);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showModal: function(type, options) {
            var $modal = this.elements.$confirmModal;
            
            $modal.find('#amazon-confirm-title').text(options.title);
            $modal.find('#amazon-confirm-message').text(options.message);
            $modal.find('.amazon-modal-confirm').text(options.confirmText);
            $modal.find('.amazon-modal-cancel').text(options.cancelText);
            
            $modal.data('callback', options.callback);
            $modal.show();
            $('body').addClass('amazon-modal-open');
        },

        closeModal: function() {
            this.elements.$confirmModal.hide();
            $('body').removeClass('amazon-modal-open');
        },

        handleModalConfirm: function() {
            var callback = this.elements.$confirmModal.data('callback');
            if (callback) {
                callback(true);
            }
            this.closeModal();
        },

        showFieldError: function($field, message) {
            $field.addClass('amazon-field-error');
            
            var $error = $field.siblings('.amazon-field-error-message');
            if ($error.length === 0) {
                $error = $('<span class="amazon-field-error-message"></span>');
                $field.after($error);
            }
            $error.text(message);
        },

        clearFieldError: function($field) {
            $field.removeClass('amazon-field-error');
            $field.siblings('.amazon-field-error-message').remove();
        },

        showSaveProgress: function() {
            this.elements.$submitButton.prop('disabled', true).text('Saving...');
        },

        hideSaveProgress: function() {
            this.elements.$submitButton.prop('disabled', false).text('Save Settings');
        },

        showTestProgress: function() {
            this.elements.$testApiButton.find('.amazon-test-text').hide();
            this.elements.$testApiButton.find('.amazon-test-loading').show();
            this.elements.$testApiButton.prop('disabled', true);
        },

        hideTestProgress: function() {
            this.elements.$testApiButton.find('.amazon-test-text').show();
            this.elements.$testApiButton.find('.amazon-test-loading').hide();
            this.elements.$testApiButton.prop('disabled', false);
        },

        updateFieldIndicator: function($field) {
            var fieldName = $field.attr('name');
            var hasChanges = this.state.unsavedChanges.has(fieldName);
            
            $field.toggleClass('amazon-field-changed', hasChanges);
        },

        updateSaveButtonState: function() {
            this.elements.$submitButton.prop('disabled', !this.state.isDirty || Object.keys(this.state.validationErrors).length > 0);
        },

        updateUI: function() {
            // Update all field indicators
            this.elements.$form.find('input, select, textarea').each(function() {
                this.updateFieldIndicator($(this));
            }.bind(this));

            // Update button states
            this.updateSaveButtonState();
            
            // Update dependency states
            this.toggleSyncDependencies($('#amazon_product_importer_auto_sync_enabled').is(':checked'));
            this.toggleCategoryDependencies($('#amazon_product_importer_auto_categories').is(':checked'));
            this.toggleImageDependencies($('#amazon_product_importer_import_images').is(':checked'));
        },

        // Utility Methods
        getFieldValue: function($field) {
            if ($field.is(':checkbox')) {
                return $field.is(':checked') ? '1' : '0';
            }
            return $field.val();
        },

        setFieldValue: function($field, value) {
            if ($field.is(':checkbox')) {
                $field.prop('checked', value === '1' || value === true);
            } else {
                $field.val(value);
            }
            $field.trigger('change');
        },

        captureOriginalValues: function() {
            this.elements.$form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var fieldName = $field.attr('name');
                if (fieldName) {
                    this.state.originalValues[fieldName] = this.getFieldValue($field);
                }
            }.bind(this));
        },

        isCriticalField: function(fieldName) {
            var criticalFields = [
                'amazon_product_importer_access_key_id',
                'amazon_product_importer_secret_access_key',
                'amazon_product_importer_associate_tag',
                'amazon_product_importer_marketplace',
                'amazon_product_importer_region'
            ];
            return criticalFields.includes(fieldName);
        },

        scheduleValidation: function($field) {
            clearTimeout(this.state.validationTimer);
            this.state.validationTimer = setTimeout(function() {
                this.validateField($field);
            }.bind(this), this.config.validationDelay);
        },

        scheduleAutoSave: function() {
            if (!this.state.isDirty) return;
            
            clearTimeout(this.state.autoSaveTimer);
            this.state.autoSaveTimer = setTimeout(function() {
                if (this.state.isDirty && Object.keys(this.state.validationErrors).length === 0) {
                    this.autoSave();
                }
            }.bind(this), this.config.autoSaveDelay);
        },

        autoSave: function() {
            // Implement auto-save functionality if needed
        },

        downloadFile: function(filename, content) {
            var blob = new Blob([content], { type: 'application/json' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        // Placeholder methods for complex features
        initializeTooltips: function() {
            $('[title]').tooltip();
        },

        initializeColorPickers: function() {
            $('.color-picker').wpColorPicker();
        },

        initializeDatePickers: function() {
            $('.date-picker').datepicker();
        },

        initializeConditionalFields: function() {
            // Initialize conditional field logic
        },

        setupFieldDependencies: function() {
            // Setup field dependency relationships
        },

        addValidationRules: function() {
            // Add custom validation rules
        },

        initializeValidationIndicators: function() {
            // Initialize validation UI indicators
        },

        checkApiStatus: function() {
            // Check current API connection status
        },

        handleSaveSuccess: function(response) {
            if (response.success) {
                this.state.isDirty = false;
                this.state.unsavedChanges.clear();
                this.captureOriginalValues();
                this.updateUI();
                this.showNotification('success', 'Settings saved successfully.');
            } else {
                this.showNotification('error', response.data.message);
            }
        },

        handleSaveError: function(error) {
            this.showNotification('error', 'Failed to save settings. Please try again.');
        },

        handleTestSuccess: function(response) {
            if (response.success) {
                $('#amazon-api-test-result').html('<div class="notice notice-success"><p>API connection successful!</p></div>').show();
            } else {
                $('#amazon-api-test-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
            }
        },

        handleTestError: function(error, status) {
            var message = status === 'timeout' ? 'API test timed out. Please check your credentials and try again.' : 'API test failed. Please check your credentials.';
            $('#amazon-api-test-result').html('<div class="notice notice-error"><p>' + message + '</p></div>').show();
        },

        showValidationSummary: function() {
            var errorCount = Object.keys(this.state.validationErrors).length;
            this.showNotification('error', `Please fix ${errorCount} validation error(s) before saving.`);
        },

        validateImportedSettings: function(settings) {
            // Validate imported settings structure
            return typeof settings === 'object' && settings !== null;
        },

        performDefaultReset: function() {
            // Reset all fields to default values
            this.showNotification('info', 'Resetting to default values...');
            // Implementation would reset each field to its default value
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof amazon_settings_data !== 'undefined') {
            SettingsController.init();
        }
    });

    // Expose to global scope for debugging
    window.AmazonSettingsController = SettingsController;

})(jQuery);