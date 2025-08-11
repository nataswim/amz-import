<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.example.com
 * @since      1.0.0
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Amazon_Product_Importer
 * @subpackage Amazon_Product_Importer/public
 * @author     Your Name <https://mycreanet.fr>
 */
class Amazon_Product_Importer_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The options from the database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $options    Plugin options.
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->options = get_option( 'amazon_product_importer_options', array() );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Amazon_Product_Importer_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Amazon_Product_Importer_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( 
			$this->plugin_name, 
			plugin_dir_url( __FILE__ ) . 'css/public.css', 
			array(), 
			$this->version, 
			'all' 
		);

		// Enqueue additional styles conditionally
		if ( $this->has_amazon_products_on_page() ) {
			wp_enqueue_style(
				$this->plugin_name . '-products',
				plugin_dir_url( __FILE__ ) . 'css/amazon-products.css',
				array( $this->plugin_name ),
				$this->version,
				'all'
			);
		}

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Amazon_Product_Importer_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Amazon_Product_Importer_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( 
			$this->plugin_name, 
			plugin_dir_url( __FILE__ ) . 'js/public.js', 
			array( 'jquery' ), 
			$this->version, 
			false 
		);

		// Localize script with AJAX data
		wp_localize_script( $this->plugin_name, 'amazonImporterPublic', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'amazon_importer_public_nonce' ),
			'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'analytics' => isset( $this->options['enable_analytics'] ) ? $this->options['enable_analytics'] : false,
			'priceUpdateInterval' => isset( $this->options['price_update_interval'] ) ? $this->options['price_update_interval'] : 300000,
			'strings' => array(
				'loading' => __( 'Chargement...', 'amazon-product-importer' ),
				'error' => __( 'Une erreur est survenue', 'amazon-product-importer' ),
				'priceUpdated' => __( 'Prix mis à jour', 'amazon-product-importer' ),
				'unavailable' => __( 'Produit non disponible', 'amazon-product-importer' ),
			)
		));

	}

	/**
	 * Initialize shortcodes
	 *
	 * @since    1.0.0
	 */
	public function init_shortcodes() {
		add_shortcode( 'amazon_product', array( $this, 'amazon_product_shortcode' ) );
		add_shortcode( 'amazon_price', array( $this, 'amazon_price_shortcode' ) );
		add_shortcode( 'amazon_button', array( $this, 'amazon_button_shortcode' ) );
		add_shortcode( 'amazon_gallery', array( $this, 'amazon_gallery_shortcode' ) );
	}

	/**
	 * Amazon product shortcode
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes
	 * @return   string            HTML output
	 */
	public function amazon_product_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'asin' => '',
			'id' => '',
			'template' => 'default',
			'show_price' => 'true',
			'show_button' => 'true',
			'show_image' => 'true',
			'show_description' => 'true',
			'image_size' => 'medium',
			'target' => '_blank',
			'class' => ''
		), $atts, 'amazon_product' );

		// Get product by ASIN or WooCommerce ID
		$product = $this->get_amazon_product( $atts );
		
		if ( ! $product ) {
			return $this->render_error_message( __( 'Produit Amazon non trouvé', 'amazon-product-importer' ) );
		}

		// Load template
		return $this->load_template( 'shortcodes/amazon-product.php', array(
			'product' => $product,
			'atts' => $atts
		) );
	}

	/**
	 * Amazon price shortcode
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes
	 * @return   string            HTML output
	 */
	public function amazon_price_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'asin' => '',
			'id' => '',
			'format' => 'full', // full, current, savings
			'currency' => 'EUR',
			'class' => ''
		), $atts, 'amazon_price' );

		$product = $this->get_amazon_product( $atts );
		
		if ( ! $product ) {
			return '';
		}

		$price_data = $this->get_product_price_data( $product );
		
		return $this->format_price_display( $price_data, $atts );
	}

	/**
	 * Amazon button shortcode
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes
	 * @return   string            HTML output
	 */
	public function amazon_button_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'asin' => '',
			'id' => '',
			'text' => __( 'Acheter sur Amazon', 'amazon-product-importer' ),
			'class' => 'amazon-buy-button',
			'target' => '_blank',
			'style' => 'default'
		), $atts, 'amazon_button' );

		$product = $this->get_amazon_product( $atts );
		
		if ( ! $product ) {
			return '';
		}

		$amazon_url = $this->get_amazon_affiliate_url( $product );
		
		return sprintf(
			'<a href="%s" class="%s" target="%s" rel="nofollow noopener" data-asin="%s">%s</a>',
			esc_url( $amazon_url ),
			esc_attr( $atts['class'] ),
			esc_attr( $atts['target'] ),
			esc_attr( $this->get_product_asin( $product ) ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Amazon gallery shortcode
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes
	 * @return   string            HTML output
	 */
	public function amazon_gallery_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'asin' => '',
			'id' => '',
			'size' => 'medium',
			'columns' => 3,
			'limit' => 6,
			'lightbox' => 'true',
			'class' => ''
		), $atts, 'amazon_gallery' );

		$product = $this->get_amazon_product( $atts );
		
		if ( ! $product ) {
			return '';
		}

		$gallery_images = $this->get_product_gallery_images( $product, $atts );
		
		return $this->load_template( 'shortcodes/amazon-gallery.php', array(
			'images' => $gallery_images,
			'atts' => $atts
		) );
	}

	/**
	 * Add Amazon badge to product title
	 *
	 * @since    1.0.0
	 * @param    string    $title    Product title
	 * @param    int       $id       Product ID
	 * @return   string              Modified title
	 */
	public function add_amazon_badge_to_title( $title, $id = null ) {
		if ( ! is_admin() && $this->is_amazon_product( $id ) ) {
			$badge = $this->get_amazon_badge();
			return $title . ' ' . $badge;
		}
		return $title;
	}

	/**
	 * Add Amazon information to product content
	 *
	 * @since    1.0.0
	 * @param    string    $content    Product content
	 * @return   string                Modified content
	 */
	public function add_amazon_info_to_content( $content ) {
		global $post;

		if ( is_singular( 'product' ) && $this->is_amazon_product( $post->ID ) ) {
			$amazon_info = $this->get_amazon_product_info( $post->ID );
			$content .= $amazon_info;
		}

		return $content;
	}

	/**
	 * Modify WooCommerce product price display
	 *
	 * @since    1.0.0
	 * @param    string    $price    Price HTML
	 * @param    object    $product  WooCommerce product
	 * @return   string              Modified price HTML
	 */
	public function modify_amazon_product_price( $price, $product ) {
		if ( $this->is_amazon_product( $product->get_id() ) ) {
			$amazon_price_data = $this->get_product_price_data( $product );
			$last_updated = get_post_meta( $product->get_id(), '_amazon_price_updated', true );
			
			if ( $last_updated ) {
				$price .= sprintf(
					'<small class="amazon-price-updated">%s %s</small>',
					__( 'Prix mis à jour le', 'amazon-product-importer' ),
					date_i18n( get_option( 'date_format' ), strtotime( $last_updated ) )
				);
			}
		}
		return $price;
	}

	/**
	 * Add Amazon meta information to product
	 *
	 * @since    1.0.0
	 */
	public function display_amazon_product_meta() {
		global $post;

		if ( is_singular( 'product' ) && $this->is_amazon_product( $post->ID ) ) {
			echo $this->load_template( 'public/amazon-product-meta.php', array(
				'product_id' => $post->ID,
				'amazon_data' => $this->get_amazon_meta_data( $post->ID )
			) );
		}
	}

	/**
	 * Handle AJAX price update request
	 *
	 * @since    1.0.0
	 */
	public function ajax_update_prices() {
		check_ajax_referer( 'amazon_importer_public_nonce', 'nonce' );

		$asins = isset( $_POST['asins'] ) ? array_map( 'sanitize_text_field', $_POST['asins'] ) : array();
		
		if ( empty( $asins ) ) {
			wp_send_json_error( __( 'Aucun ASIN fourni', 'amazon-product-importer' ) );
		}

		$price_updates = array();
		
		foreach ( $asins as $asin ) {
			$product_id = $this->get_product_id_by_asin( $asin );
			
			if ( $product_id ) {
				$price_data = $this->get_fresh_price_data( $asin );
				if ( $price_data ) {
					$price_updates[ $asin ] = $price_data;
				}
			}
		}

		wp_send_json_success( $price_updates );
	}

	/**
	 * Handle AJAX variation data request
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_variation() {
		check_ajax_referer( 'amazon_importer_public_nonce', 'nonce' );

		$variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
		
		if ( ! $variation_id ) {
			wp_send_json_error( __( 'ID de variation invalide', 'amazon-product-importer' ) );
		}

		$variation = wc_get_product( $variation_id );
		
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			wp_send_json_error( __( 'Variation non trouvée', 'amazon-product-importer' ) );
		}

		$variation_data = array(
			'price' => $this->get_variation_price_data( $variation ),
			'image' => $this->get_variation_image_data( $variation ),
			'availability' => $this->get_variation_availability( $variation ),
			'buy_url' => $this->get_amazon_affiliate_url( $variation )
		);

		wp_send_json_success( $variation_data );
	}

	/**
	 * Handle AJAX event tracking
	 *
	 * @since    1.0.0
	 */
	public function ajax_track_event() {
		check_ajax_referer( 'amazon_importer_public_nonce', 'nonce' );

		$event = isset( $_POST['event'] ) ? sanitize_text_field( $_POST['event'] ) : '';
		$data = isset( $_POST['data'] ) ? array_map( 'sanitize_text_field', $_POST['data'] ) : array();

		if ( empty( $event ) ) {
			wp_send_json_error( __( 'Événement invalide', 'amazon-product-importer' ) );
		}

		// Log the event
		$this->log_user_event( $event, $data );

		// Trigger custom action for extensibility
		do_action( 'amazon_importer_track_event', $event, $data );

		wp_send_json_success();
	}

	/**
	 * Get Amazon product by ASIN or WooCommerce ID
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes
	 * @return   object|false     WooCommerce product or false
	 */
	private function get_amazon_product( $atts ) {
		if ( ! empty( $atts['id'] ) ) {
			return wc_get_product( intval( $atts['id'] ) );
		}

		if ( ! empty( $atts['asin'] ) ) {
			$product_id = $this->get_product_id_by_asin( $atts['asin'] );
			if ( $product_id ) {
				return wc_get_product( $product_id );
			}
		}

		return false;
	}

	/**
	 * Get product ID by ASIN
	 *
	 * @since    1.0.0
	 * @param    string    $asin    Amazon ASIN
	 * @return   int|false          Product ID or false
	 */
	private function get_product_id_by_asin( $asin ) {
		global $wpdb;

		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_amazon_asin' 
			AND meta_value = %s 
			LIMIT 1",
			$asin
		) );

		return $product_id ? intval( $product_id ) : false;
	}

	/**
	 * Check if product is imported from Amazon
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID
	 * @return   bool                  True if Amazon product
	 */
	private function is_amazon_product( $product_id ) {
		if ( ! $product_id ) {
			return false;
		}

		$amazon_asin = get_post_meta( $product_id, '_amazon_asin', true );
		return ! empty( $amazon_asin );
	}

	/**
	 * Get Amazon badge HTML
	 *
	 * @since    1.0.0
	 * @return   string    Badge HTML
	 */
	private function get_amazon_badge() {
		return '<span class="amazon-product-badge">' . __( 'Amazon', 'amazon-product-importer' ) . '</span>';
	}

	/**
	 * Get Amazon affiliate URL
	 *
	 * @since    1.0.0
	 * @param    object    $product    WooCommerce product
	 * @return   string               Amazon URL with affiliate tag
	 */
	private function get_amazon_affiliate_url( $product ) {
		$asin = $this->get_product_asin( $product );
		$region = get_post_meta( $product->get_id(), '_amazon_region', true ) ?: 'com';
		$associate_tag = isset( $this->options['associate_tag'] ) ? $this->options['associate_tag'] : '';

		$base_url = "https://www.amazon.{$region}/dp/{$asin}";
		
		if ( $associate_tag ) {
			$base_url .= "?tag=" . urlencode( $associate_tag );
		}

		return $base_url;
	}

	/**
	 * Get product ASIN
	 *
	 * @since    1.0.0
	 * @param    object    $product    WooCommerce product
	 * @return   string               ASIN
	 */
	private function get_product_asin( $product ) {
		return get_post_meta( $product->get_id(), '_amazon_asin', true );
	}

	/**
	 * Load template file
	 *
	 * @since    1.0.0
	 * @param    string    $template    Template path
	 * @param    array     $args        Template arguments
	 * @return   string                 Template output
	 */
	private function load_template( $template, $args = array() ) {
		// Extract args to variables
		extract( $args );

		// Try theme template first
		$theme_template = locate_template( array(
			'amazon-product-importer/' . $template,
			$template
		) );

		if ( $theme_template ) {
			$template_file = $theme_template;
		} else {
			$template_file = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/' . $template;
		}

		if ( file_exists( $template_file ) ) {
			ob_start();
			include $template_file;
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Check if current page has Amazon products
	 *
	 * @since    1.0.0
	 * @return   bool    True if page has Amazon products
	 */
	private function has_amazon_products_on_page() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for shortcodes
		if ( has_shortcode( $post->post_content, 'amazon_product' ) ||
			 has_shortcode( $post->post_content, 'amazon_price' ) ||
			 has_shortcode( $post->post_content, 'amazon_button' ) ) {
			return true;
		}

		// Check if it's a WooCommerce product page with Amazon product
		if ( is_singular( 'product' ) && $this->is_amazon_product( $post->ID ) ) {
			return true;
		}

		// Check if it's a shop page with Amazon products
		if ( is_shop() || is_product_category() || is_product_tag() ) {
			return $this->has_amazon_products_in_query();
		}

		return false;
	}

	/**
	 * Check if current query has Amazon products
	 *
	 * @since    1.0.0
	 * @return   bool    True if query has Amazon products
	 */
	private function has_amazon_products_in_query() {
		global $wpdb;

		$query = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_amazon_asin'
			AND p.post_type = 'product'
			AND p.post_status = 'publish'
			LIMIT 1"
		);

		return intval( $query ) > 0;
	}

	/**
	 * Render error message
	 *
	 * @since    1.0.0
	 * @param    string    $message    Error message
	 * @return   string               Error HTML
	 */
	private function render_error_message( $message ) {
		return sprintf(
			'<div class="amazon-notice notice-error">%s</div>',
			esc_html( $message )
		);
	}

	/**
	 * Get product price data
	 *
	 * @since    1.0.0
	 * @param    object    $product    WooCommerce product
	 * @return   array                Price data
	 */
	private function get_product_price_data( $product ) {
		return array(
			'current' => $product->get_price_html(),
			'regular' => $product->get_regular_price(),
			'sale' => $product->get_sale_price(),
			'currency' => get_woocommerce_currency_symbol()
		);
	}

	/**
	 * Format price display
	 *
	 * @since    1.0.0
	 * @param    array    $price_data    Price data
	 * @param    array    $atts          Display attributes
	 * @return   string                  Formatted price HTML
	 */
	private function format_price_display( $price_data, $atts ) {
		$format = $atts['format'];
		$class = ! empty( $atts['class'] ) ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';

		switch ( $format ) {
			case 'current':
				return sprintf( '<span%s>%s</span>', $class, $price_data['current'] );
			
			case 'savings':
				if ( ! empty( $price_data['sale'] ) && $price_data['regular'] > $price_data['sale'] ) {
					$savings = $price_data['regular'] - $price_data['sale'];
					return sprintf( '<span%s>%s%s</span>', $class, $savings, $price_data['currency'] );
				}
				return '';
			
			default:
				return sprintf( '<div class="amazon-product-price%s">%s</div>', $class, $price_data['current'] );
		}
	}

	/**
	 * Log user event
	 *
	 * @since    1.0.0
	 * @param    string    $event    Event name
	 * @param    array     $data     Event data
	 */
	private function log_user_event( $event, $data ) {
		if ( ! isset( $this->options['enable_analytics'] ) || ! $this->options['enable_analytics'] ) {
			return;
		}

		$log_data = array(
			'event' => $event,
			'data' => $data,
			'timestamp' => current_time( 'mysql' ),
			'user_ip' => $this->get_user_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'referrer' => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : ''
		);

		// Use WordPress transients for temporary storage
		$logs = get_transient( 'amazon_importer_event_logs' ) ?: array();
		$logs[] = $log_data;

		// Keep only last 100 events in memory
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		set_transient( 'amazon_importer_event_logs', $logs, DAY_IN_SECONDS );

		// Trigger action for external analytics
		do_action( 'amazon_importer_log_event', $log_data );
	}

	/**
	 * Get user IP address
	 *
	 * @since    1.0.0
	 * @return   string    User IP
	 */
	private function get_user_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	/**
	 * Get Amazon meta data for product
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID
	 * @return   array                 Amazon meta data
	 */
	private function get_amazon_meta_data( $product_id ) {
		return array(
			'asin' => get_post_meta( $product_id, '_amazon_asin', true ),
			'region' => get_post_meta( $product_id, '_amazon_region', true ),
			'last_updated' => get_post_meta( $product_id, '_amazon_last_updated', true ),
			'price_updated' => get_post_meta( $product_id, '_amazon_price_updated', true ),
			'availability' => get_post_meta( $product_id, '_amazon_availability', true ),
			'brand' => get_post_meta( $product_id, '_amazon_brand', true )
		);
	}

}