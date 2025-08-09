/**
 * Amazon Product Importer - Public JavaScript
 *
 * Fonctionnalités JavaScript pour la partie publique du plugin
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/public/js
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * Objet principal du plugin Amazon Product Importer
     */
    window.AmazonProductImporter = window.AmazonProductImporter || {};

    /**
     * Configuration et variables globales
     */
    const API = {
        // Configuration
        config: {
            animationDuration: 300,
            priceUpdateInterval: 300000, // 5 minutes
            trackingEnabled: true,
            lazyLoadImages: true,
            cacheTimeout: 3600000 // 1 heure
        },

        // Cache local
        cache: new Map(),

        // Éléments DOM
        elements: {},

        /**
         * Initialisation du plugin
         */
        init: function() {
            this.bindEvents();
            this.initShortcodes();
            this.initBadges();
            this.initPriceTracking();
            this.initLazyLoading();
            this.initVariationHandlers();
            this.setupAnalytics();
            
            // Debug mode
            if (amazonImporterPublic && amazonImporterPublic.debug) {
                console.log('Amazon Product Importer: Initialized');
            }
        },

        /**
         * Liaison des événements
         */
        bindEvents: function() {
            $(document).ready(() => this.onDocumentReady());
            $(window).on('load', () => this.onWindowLoad());
            $(window).on('resize', this.debounce(() => this.onWindowResize(), 250));
            
            // Événements spécifiques au plugin
            $(document).on('click', '.amazon-buy-button', this.handleBuyButtonClick.bind(this));
            $(document).on('click', '.amazon-product-badge', this.handleBadgeClick.bind(this));
            $(document).on('change', '.amazon-variation-select', this.handleVariationChange.bind(this));
            $(document).on('mouseenter', '.amazon-product-shortcode', this.handleProductHover.bind(this));
        },

        /**
         * Actions à l'initialisation du DOM
         */
        onDocumentReady: function() {
            this.cacheElements();
            this.enhanceAmazonLinks();
            this.initTooltips();
            this.initProgressiveEnhancement();
        },

        /**
         * Actions au chargement complet de la page
         */
        onWindowLoad: function() {
            this.optimizeImages();
            this.initIntersectionObserver();
            this.preloadCriticalAssets();
        },

        /**
         * Actions au redimensionnement de la fenêtre
         */
        onWindowResize: function() {
            this.adjustResponsiveElements();
            this.recalculateLayouts();
        },

        /**
         * Cache des éléments DOM fréquemment utilisés
         */
        cacheElements: function() {
            this.elements = {
                body: $('body'),
                amazonProducts: $('.amazon-product-shortcode'),
                amazonBadges: $('.amazon-product-badge'),
                buyButtons: $('.amazon-buy-button'),
                priceElements: $('.amazon-product-price'),
                variationSelects: $('.amazon-variation-select')
            };
        },

        /**
         * Gestion des shortcodes Amazon
         */
        initShortcodes: function() {
            this.elements.amazonProducts.each(function(index, element) {
                const $product = $(element);
                const productData = $product.data();
                
                // Ajouter des classes pour l'animation
                $product.addClass('amazon-fade-in');
                
                // Initialiser les données du produit
                API.setupProductData($product, productData);
                
                // Ajouter les fonctionnalités interactives
                API.addProductInteractions($product);
            });
        },

        /**
         * Configuration des données produit
         */
        setupProductData: function($product, data) {
            // Stocker les données importantes
            $product.data('amazon-asin', data.asin || '');
            $product.data('amazon-price', data.price || '');
            $product.data('amazon-availability', data.availability || '');
            
            // Ajouter des attributs pour l'accessibilité
            $product.attr('role', 'article');
            $product.attr('aria-label', 'Produit Amazon: ' + (data.title || 'Produit sans titre'));
        },

        /**
         * Ajout d'interactions aux produits
         */
        addProductInteractions: function($product) {
            // Bouton de partage
            this.addShareButton($product);
            
            // Favoris/Wishlist
            this.addWishlistButton($product);
            
            // Comparaison de prix
            this.addPriceComparison($product);
        },

        /**
         * Gestion des badges Amazon
         */
        initBadges: function() {
            this.elements.amazonBadges.each(function() {
                const $badge = $(this);
                
                // Animation au survol
                $badge.on('mouseenter', function() {
                    $(this).addClass('amazon-badge-hover');
                }).on('mouseleave', function() {
                    $(this).removeClass('amazon-badge-hover');
                });
                
                // Tooltip informatif
                if ($badge.data('info')) {
                    API.addTooltip($badge, $badge.data('info'));
                }
            });
        },

        /**
         * Gestion du tracking des prix
         */
        initPriceTracking: function() {
            if (!this.config.trackingEnabled) return;

            // Vérification périodique des prix
            setInterval(() => {
                this.updatePrices();
            }, this.config.priceUpdateInterval);

            // Tracking des interactions
            this.trackUserInteractions();
        },

        /**
         * Mise à jour des prix
         */
        updatePrices: function() {
            const asins = [];
            
            this.elements.priceElements.each(function() {
                const asin = $(this).closest('.amazon-product-shortcode').data('amazon-asin');
                if (asin && asins.indexOf(asin) === -1) {
                    asins.push(asin);
                }
            });

            if (asins.length > 0) {
                this.fetchPriceUpdates(asins);
            }
        },

        /**
         * Récupération des mises à jour de prix
         */
        fetchPriceUpdates: function(asins) {
            const data = {
                action: 'amazon_update_prices',
                asins: asins,
                nonce: amazonImporterPublic.nonce
            };

            $.ajax({
                url: amazonImporterPublic.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.updatePriceElements(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    if (amazonImporterPublic.debug) {
                        console.error('Erreur de mise à jour des prix:', error);
                    }
                }
            });
        },

        /**
         * Mise à jour des éléments de prix dans le DOM
         */
        updatePriceElements: function(priceData) {
            Object.keys(priceData).forEach(asin => {
                const $products = $(`.amazon-product-shortcode[data-amazon-asin="${asin}"]`);
                const newPrice = priceData[asin];
                
                $products.each(function() {
                    const $product = $(this);
                    const $priceElement = $product.find('.amazon-product-price');
                    const currentPrice = $priceElement.data('price');
                    
                    if (currentPrice !== newPrice.current) {
                        API.animatePriceChange($priceElement, newPrice);
                    }
                });
            });
        },

        /**
         * Animation du changement de prix
         */
        animatePriceChange: function($element, newPrice) {
            $element.addClass('price-updating');
            
            setTimeout(() => {
                $element.html(this.formatPrice(newPrice));
                $element.removeClass('price-updating').addClass('price-updated');
                
                setTimeout(() => {
                    $element.removeClass('price-updated');
                }, 2000);
            }, this.config.animationDuration);
        },

        /**
         * Formatage du prix
         */
        formatPrice: function(priceData) {
            let html = '';
            
            if (priceData.old && priceData.old !== priceData.current) {
                html += `<span class="old-price">${priceData.old}</span>`;
            }
            
            html += `<span class="current-price">${priceData.current}</span>`;
            
            if (priceData.savings) {
                html += `<span class="savings">Économisez ${priceData.savings}</span>`;
            }
            
            return html;
        },

        /**
         * Gestion des variations de produit
         */
        initVariationHandlers: function() {
            this.elements.variationSelects.on('change', this.handleVariationChange.bind(this));
        },

        /**
         * Gestion du changement de variation
         */
        handleVariationChange: function(event) {
            const $select = $(event.target);
            const $product = $select.closest('.amazon-product-shortcode');
            const variationId = $select.val();
            
            if (variationId) {
                this.loadVariationData($product, variationId);
            }
        },

        /**
         * Chargement des données de variation
         */
        loadVariationData: function($product, variationId) {
            const data = {
                action: 'amazon_get_variation',
                variation_id: variationId,
                nonce: amazonImporterPublic.nonce
            };

            $product.addClass('amazon-loading');

            $.ajax({
                url: amazonImporterPublic.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.updateProductWithVariation($product, response.data);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError($product, 'Erreur de chargement de la variation');
                },
                complete: () => {
                    $product.removeClass('amazon-loading');
                }
            });
        },

        /**
         * Mise à jour du produit avec les données de variation
         */
        updateProductWithVariation: function($product, variationData) {
            // Mise à jour du prix
            if (variationData.price) {
                const $priceElement = $product.find('.amazon-product-price');
                $priceElement.html(this.formatPrice(variationData.price));
            }

            // Mise à jour de l'image
            if (variationData.image) {
                const $image = $product.find('.product-image');
                this.updateProductImage($image, variationData.image);
            }

            // Mise à jour de la disponibilité
            if (variationData.availability) {
                this.updateAvailability($product, variationData.availability);
            }

            // Mise à jour du lien d'achat
            if (variationData.buy_url) {
                $product.find('.amazon-buy-button').attr('href', variationData.buy_url);
            }
        },

        /**
         * Mise à jour de l'image du produit
         */
        updateProductImage: function($image, imageData) {
            const img = new Image();
            img.onload = function() {
                $image.fadeOut(API.config.animationDuration, function() {
                    $image.attr('src', imageData.url);
                    $image.attr('alt', imageData.alt || '');
                    $image.fadeIn(API.config.animationDuration);
                });
            };
            img.src = imageData.url;
        },

        /**
         * Gestion des clics sur les boutons d'achat
         */
        handleBuyButtonClick: function(event) {
            const $button = $(event.target);
            const $product = $button.closest('.amazon-product-shortcode');
            const asin = $product.data('amazon-asin');
            
            // Analytics
            this.trackEvent('buy_button_click', {
                asin: asin,
                price: $product.find('.amazon-product-price').data('price'),
                position: $product.index()
            });

            // Animation du bouton
            $button.addClass('button-clicked');
            setTimeout(() => $button.removeClass('button-clicked'), 500);

            // Pas de preventDefault - laisser le lien s'ouvrir
        },

        /**
         * Gestion des clics sur les badges
         */
        handleBadgeClick: function(event) {
            event.preventDefault();
            const $badge = $(event.target);
            
            // Afficher les informations du badge
            this.showBadgeInfo($badge);
        },

        /**
         * Gestion du survol des produits
         */
        handleProductHover: function(event) {
            const $product = $(event.target).closest('.amazon-product-shortcode');
            
            // Précharger les données si nécessaire
            this.preloadProductData($product);
        },

        /**
         * Chargement paresseux des images
         */
        initLazyLoading: function() {
            if (!this.config.lazyLoadImages || !('IntersectionObserver' in window)) {
                return;
            }

            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            img.classList.remove('lazy-load');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            $('.amazon-product-shortcode img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        },

        /**
         * Initialisation de l'Intersection Observer
         */
        initIntersectionObserver: function() {
            if (!('IntersectionObserver' in window)) return;

            const productObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const $product = $(entry.target);
                        this.trackProductView($product);
                    }
                });
            }, {
                threshold: 0.5
            });

            this.elements.amazonProducts.each(function() {
                productObserver.observe(this);
            });
        },

        /**
         * Amélioration progressive
         */
        initProgressiveEnhancement: function() {
            // Ajouter des classes pour indiquer que JS est activé
            this.elements.body.addClass('amazon-js-enabled');

            // Améliorer l'accessibilité
            this.enhanceAccessibility();

            // Ajouter des raccourcis clavier
            this.addKeyboardShortcuts();
        },

        /**
         * Amélioration de l'accessibilité
         */
        enhanceAccessibility: function() {
            // ARIA labels pour les boutons
            this.elements.buyButtons.each(function() {
                const $button = $(this);
                const productTitle = $button.closest('.amazon-product-shortcode')
                    .find('.product-title').text();
                
                $button.attr('aria-label', `Acheter ${productTitle} sur Amazon`);
            });

            // Navigation au clavier
            this.elements.amazonProducts.attr('tabindex', '0');
        },

        /**
         * Ajout de raccourcis clavier
         */
        addKeyboardShortcuts: function() {
            $(document).on('keydown', (event) => {
                // Échapper pour fermer les modales
                if (event.key === 'Escape') {
                    this.closeModals();
                }
            });
        },

        /**
         * Tracking des événements
         */
        trackEvent: function(eventName, data = {}) {
            if (!this.config.trackingEnabled) return;

            // Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    event_category: 'Amazon Product Importer',
                    ...data
                });
            }

            // Analytics personnalisées
            if (amazonImporterPublic.analytics) {
                $.ajax({
                    url: amazonImporterPublic.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'amazon_track_event',
                        event: eventName,
                        data: data,
                        nonce: amazonImporterPublic.nonce
                    }
                });
            }
        },

        /**
         * Tracking des vues de produit
         */
        trackProductView: function($product) {
            const asin = $product.data('amazon-asin');
            if (asin && !$product.data('view-tracked')) {
                $product.data('view-tracked', true);
                this.trackEvent('product_view', { asin: asin });
            }
        },

        /**
         * Gestion du cache
         */
        setCache: function(key, data, ttl = this.config.cacheTimeout) {
            this.cache.set(key, {
                data: data,
                timestamp: Date.now(),
                ttl: ttl
            });
        },

        getCache: function(key) {
            const cached = this.cache.get(key);
            if (cached && (Date.now() - cached.timestamp) < cached.ttl) {
                return cached.data;
            }
            this.cache.delete(key);
            return null;
        },

        /**
         * Fonction de debounce
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Fonction de throttle
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Utilitaires diverses
         */
        showError: function($element, message) {
            const $error = $('<div class="amazon-notice notice-error"></div>').text(message);
            $element.prepend($error);
            setTimeout(() => $error.fadeOut(), 5000);
        },

        showSuccess: function($element, message) {
            const $success = $('<div class="amazon-notice notice-success"></div>').text(message);
            $element.prepend($success);
            setTimeout(() => $success.fadeOut(), 3000);
        },

        addTooltip: function($element, content) {
            $element.attr('title', content);
            // Ici, vous pourriez intégrer une bibliothèque de tooltips plus avancée
        },

        /**
         * Nettoyage lors de la destruction
         */
        destroy: function() {
            $(document).off('.amazon-product-importer');
            $(window).off('.amazon-product-importer');
            this.cache.clear();
        }
    };

    // Exposition de l'API globalement
    window.AmazonProductImporter.Public = API;

    // Initialisation automatique
    $(document).ready(() => {
        API.init();
    });

})(jQuery);

/**
 * Compatibilité sans jQuery (optionnel)
 */
if (typeof jQuery === 'undefined') {
    console.warn('Amazon Product Importer: jQuery n\'est pas chargé. Certaines fonctionnalités peuvent ne pas fonctionner.');
}