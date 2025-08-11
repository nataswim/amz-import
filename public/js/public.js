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
                        this.processPriceUpdates(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Amazon Product Importer: Price update failed', error);
                }
            });
        },

        /**
         * Traitement des mises à jour de prix
         */
        processPriceUpdates: function(updates) {
            Object.keys(updates).forEach(asin => {
                const priceData = updates[asin];
                const $products = $(`.amazon-product-shortcode[data-amazon-asin="${asin}"]`);
                
                $products.each(function() {
                    const $product = $(this);
                    const $priceElement = $product.find('.amazon-product-price');
                    
                    if ($priceElement.length && priceData.price) {
                        const currentPrice = $priceElement.text();
                        
                        if (currentPrice !== priceData.formatted_price) {
                            $priceElement.fadeOut(200, function() {
                                $(this).text(priceData.formatted_price)
                                       .addClass('amazon-price-updated')
                                       .fadeIn(200);
                                
                                setTimeout(() => {
                                    $(this).removeClass('amazon-price-updated');
                                }, 2000);
                            });
                        }
                    }
                });
            });
        },

        /**
         * Gestion du lazy loading des images
         */
        initLazyLoading: function() {
            if (!this.config.lazyLoadImages) return;

            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('data-src');
                        
                        if (src) {
                            img.src = src;
                            img.classList.remove('amazon-lazy');
                            img.classList.add('amazon-loaded');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('.amazon-lazy').forEach(img => {
                imageObserver.observe(img);
            });
        },

        /**
         * Gestion des variations de produits
         */
        initVariationHandlers: function() {
            this.elements.variationSelects.on('change', function() {
                const $select = $(this);
                const variationId = $select.val();
                const $product = $select.closest('.amazon-product-shortcode');
                
                if (variationId) {
                    API.loadVariationData($product, variationId);
                }
            });
        },

        /**
         * Chargement des données de variation
         */
        loadVariationData: function($product, variationId) {
            const asin = $product.data('amazon-asin');
            const data = {
                action: 'amazon_get_variation',
                asin: asin,
                variation_id: variationId,
                nonce: amazonImporterPublic.nonce
            };

            $.ajax({
                url: amazonImporterPublic.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: () => {
                    $product.addClass('amazon-loading');
                },
                success: (response) => {
                    if (response.success) {
                        this.updateProductDisplay($product, response.data);
                    }
                },
                complete: () => {
                    $product.removeClass('amazon-loading');
                },
                error: (xhr, status, error) => {
                    console.error('Amazon Product Importer: Variation loading failed', error);
                }
            });
        },

        /**
         * Mise à jour de l'affichage du produit
         */
        updateProductDisplay: function($product, data) {
            // Mise à jour du prix
            if (data.price) {
                $product.find('.amazon-product-price').text(data.price);
            }

            // Mise à jour de l'image
            if (data.image) {
                $product.find('.amazon-product-image img').attr('src', data.image);
            }

            // Mise à jour de la disponibilité
            if (data.availability) {
                $product.find('.amazon-availability').text(data.availability);
            }

            // Animation de mise à jour
            $product.addClass('amazon-updated');
            setTimeout(() => {
                $product.removeClass('amazon-updated');
            }, 1000);
        },

        /**
         * Gestion des clics sur les boutons d'achat
         */
        handleBuyButtonClick: function(e) {
            const $button = $(e.currentTarget);
            const asin = $button.data('asin');
            const affiliateTag = $button.data('affiliate-tag');
            
            // Tracking de l'événement
            this.trackEvent('buy_button_click', {
                asin: asin,
                affiliate_tag: affiliateTag
            });

            // Laisser le comportement par défaut se produire
            return true;
        },

        /**
         * Gestion des clics sur les badges
         */
        handleBadgeClick: function(e) {
            const $badge = $(e.currentTarget);
            const badgeType = $badge.data('badge-type');
            
            this.trackEvent('badge_click', {
                badge_type: badgeType
            });
        },

        /**
         * Gestion des changements de variation
         */
        handleVariationChange: function(e) {
            const $select = $(e.currentTarget);
            const variationId = $select.val();
            const asin = $select.closest('.amazon-product-shortcode').data('amazon-asin');
            
            this.trackEvent('variation_change', {
                asin: asin,
                variation_id: variationId
            });
        },

        /**
         * Gestion du survol des produits
         */
        handleProductHover: function(e) {
            const $product = $(e.currentTarget);
            const asin = $product.data('amazon-asin');
            
            // Précharger les données du produit si nécessaire
            this.preloadProductData(asin);
        },

        /**
         * Préchargement des données produit
         */
        preloadProductData: function(asin) {
            if (this.cache.has(asin)) return;

            const data = {
                action: 'amazon_preload_product',
                asin: asin,
                nonce: amazonImporterPublic.nonce
            };

            $.ajax({
                url: amazonImporterPublic.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.cache.set(asin, response.data);
                        
                        // Nettoyer le cache après timeout
                        setTimeout(() => {
                            this.cache.delete(asin);
                        }, this.config.cacheTimeout);
                    }
                }
            });
        },

        /**
         * Ajout de boutons de partage
         */
        addShareButton: function($product) {
            if ($product.find('.amazon-share-button').length) return;

            const productUrl = $product.data('product-url');
            const productTitle = $product.data('product-title');
            
            if (productUrl) {
                const $shareButton = $('<button class="amazon-share-button" title="Partager ce produit">')
                    .append('<span class="dashicons dashicons-share"></span>')
                    .on('click', (e) => {
                        e.preventDefault();
                        this.shareProduct(productUrl, productTitle);
                    });

                $product.find('.amazon-product-actions').append($shareButton);
            }
        },

        /**
         * Partage de produit
         */
        shareProduct: function(url, title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(err => console.log('Partage annulé', err));
            } else {
                // Fallback : copier dans le presse-papiers
                navigator.clipboard.writeText(url).then(() => {
                    this.showNotification(amazonImporterPublic.strings.linkCopied || 'Lien copié!');
                });
            }
        },

        /**
         * Ajout de boutons wishlist
         */
        addWishlistButton: function($product) {
            if ($product.find('.amazon-wishlist-button').length) return;

            const asin = $product.data('amazon-asin');
            
            if (asin) {
                const $wishlistButton = $('<button class="amazon-wishlist-button" title="Ajouter à la wishlist">')
                    .append('<span class="dashicons dashicons-heart"></span>')
                    .on('click', (e) => {
                        e.preventDefault();
                        this.toggleWishlist(asin, $wishlistButton);
                    });

                $product.find('.amazon-product-actions').append($wishlistButton);
            }
        },

        /**
         * Gestion de la wishlist
         */
        toggleWishlist: function(asin, $button) {
            const data = {
                action: 'amazon_toggle_wishlist',
                asin: asin,
                nonce: amazonImporterPublic.nonce
            };

            $.ajax({
                url: amazonImporterPublic.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        $button.toggleClass('amazon-in-wishlist', response.data.in_wishlist);
                        this.showNotification(response.data.message);
                    }
                }
            });
        },

        /**
         * Ajout de tooltips
         */
        addTooltip: function($element, content) {
            $element.attr('title', content);
            
            // Tooltip amélioré si disponible
            if (typeof tippy !== 'undefined') {
                tippy($element[0], {
                    content: content,
                    theme: 'amazon-importer'
                });
            }
        },

        /**
         * Amélioration progressive
         */
        initProgressiveEnhancement: function() {
            // Améliorer les liens Amazon existants
            $('a[href*="amazon."]').each(function() {
                const $link = $(this);
                const href = $link.attr('href');
                
                // Ajouter des attributs pour le tracking
                if (href.includes('/dp/') || href.includes('/gp/product/')) {
                    $link.addClass('amazon-external-link');
                    $link.attr('rel', 'nofollow sponsored');
                    $link.attr('target', '_blank');
                }
            });
        },

        /**
         * Optimisation des images
         */
        optimizeImages: function() {
            $('.amazon-product-image img').each(function() {
                const img = this;
                
                // Ajouter des attributs pour l'optimisation
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
                
                if (!img.hasAttribute('decoding')) {
                    img.setAttribute('decoding', 'async');
                }
            });
        },

        /**
         * Intersection Observer pour les animations
         */
        initIntersectionObserver: function() {
            const animationObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('amazon-animate-in');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.amazon-product-shortcode').forEach(el => {
                animationObserver.observe(el);
            });
        },

        /**
         * Préchargement des ressources critiques
         */
        preloadCriticalAssets: function() {
            const asins = [];
            
            $('.amazon-product-shortcode').each(function() {
                const asin = $(this).data('amazon-asin');
                if (asin && asins.indexOf(asin) === -1) {
                    asins.push(asin);
                }
            });

            // Précharger les 3 premiers produits
            asins.slice(0, 3).forEach(asin => {
                this.preloadProductData(asin);
            });
        },

        /**
         * Ajustements responsifs
         */
        adjustResponsiveElements: function() {
            const isMobile = window.innerWidth < 768;
            
            $('.amazon-product-shortcode').each(function() {
                const $product = $(this);
                $product.toggleClass('amazon-mobile-layout', isMobile);
            });
        },

        /**
         * Recalcul des layouts
         */
        recalculateLayouts: function() {
            // Recalculer les hauteurs des grilles de produits
            $('.amazon-products-grid').each(function() {
                const $grid = $(this);
                const items = $grid.find('.amazon-product-shortcode');
                
                // Reset heights
                items.css('height', 'auto');
                
                // Égaliser les hauteurs si nécessaire
                if (items.length > 1) {
                    const maxHeight = Math.max(...items.map(function() {
                        return $(this).outerHeight();
                    }).get());
                    
                    items.css('height', maxHeight + 'px');
                }
            });
        },

        /**
         * Configuration des analytics
         */
        setupAnalytics: function() {
            if (!amazonImporterPublic.analytics) return;

            // Initialiser le tracking des interactions
            this.trackUserInteractions();
        },

        /**
         * Tracking des interactions utilisateur
         */
        trackUserInteractions: function() {
            // Track scroll depth sur les produits
            const productElements = document.querySelectorAll('.amazon-product-shortcode');
            const scrollObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const asin = entry.target.getAttribute('data-amazon-asin');
                        this.trackEvent('product_viewed', { asin: asin });
                        scrollObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            productElements.forEach(el => scrollObserver.observe(el));
        },

        /**
         * Tracking d'événements
         */
        trackEvent: function(eventName, properties = {}) {
            if (!amazonImporterPublic.analytics) return;

            const eventData = {
                action: 'amazon_track_event',
                event_name: eventName,
                properties: properties,
                nonce: amazonImporterPublic.nonce
            };

            // Envoi en mode "fire and forget"
            navigator.sendBeacon(amazonImporterPublic.ajaxUrl, new URLSearchParams(eventData));
        },

        /**
         * Affichage de notifications
         */
        showNotification: function(message, type = 'info', duration = 3000) {
            const $notification = $('<div class="amazon-notification">')
                .addClass(`amazon-notification-${type}`)
                .text(message)
                .appendTo('body');

            $notification.fadeIn(300);

            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        },

        /**
         * Fonction utilitaire de debounce
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Exposition de l'API publique
    window.AmazonProductImporter.Public = API;

    // Initialisation automatique
    $(document).ready(function() {
        API.init();
    });

})(jQuery);