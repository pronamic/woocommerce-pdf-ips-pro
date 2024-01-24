<?php
/**
 * Plugin Name: WooCommerce PDF Invoices & Packing Slips Professional
 * Plugin URI: http://www.wpovernight.com
 * Description: Extended functionality for the WooCommerce PDF Invoices & Packing Slips plugin
 * Version: 1.4.5
 * Author: Ewout Fernhout
 * Author URI: http://www.wpovernight.com
 * License: GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 * Text Domain: wpo_wcpdf_pro
*/

if ( !class_exists( 'WooCommerce_PDF_IPS_Pro' ) ) {

	class WooCommerce_PDF_IPS_Pro {
		public static $plugin_path;

		/**
		 * Constructor
		 */
		public function __construct() {
			self::$plugin_path = trailingslashit(dirname(__FILE__));

			// Init updater data
			$this->item_name	= 'WooCommerce PDF Invoices & Packing Slips Professional';
			$this->file			= __FILE__;
			$this->license_slug	= 'wpo_wcpdf_pro_license';
			$this->version		= '1.4.5';
			$this->author		= 'Ewout Fernhout';

			// load the localisation & classes
			add_action( 'plugins_loaded', array( $this, 'translations' ) ); // or use init?
			add_action( 'plugins_loaded', array( $this, 'load_classes' ) );

			// Load the updater
			add_action( 'init', array( $this, 'load_updater' ), 0 );

			// run lifecycle methods
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				add_action( 'wp_loaded', array( $this, 'do_install' ) );
			}
		}

		/**
		 * Run the updater scripts from the WPO Sidekick
		 * @return void
		 */
		public function load_updater() {
			// Check if sidekick is loaded
			if (class_exists('WPO_Updater')) {
				$this->updater = new WPO_Updater( $this->item_name, $this->file, $this->license_slug, $this->version, $this->author );
			}
		}

		/**
		 * Load the translation / textdomain files
		 * 
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present
		 */
		public function translations() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'wpo_wcpdf_pro' );
			$dir    = trailingslashit( WP_LANG_DIR );

			/**
			 * Frontend/global Locale. Looks in:
			 *
			 * 		- WP_LANG_DIR/woocommerce-pdf-invoices-packing-slips/wpo_wcpdf_pro-LOCALE.mo
			 * 	 	- WP_LANG_DIR/plugins/wpo_wcpdf_pro-LOCALE.mo
			 * 	 	- woocommerce-pdf-invoices-packing-slips/languages/wpo_wcpdf_pro-LOCALE.mo (which if not found falls back to:)
			 * 	 	- WP_LANG_DIR/plugins/wpo_wcpdf_pro-LOCALE.mo
			 *
			 * WP_LANG_DIR defaults to wp-content/languages
			 */
			load_textdomain( 'wpo_wcpdf_pro', $dir . 'woocommerce-pdf-ips-pro/wpo_wcpdf_pro-' . $locale . '.mo' );
			load_textdomain( 'wpo_wcpdf_pro', $dir . 'plugins/wpo_wcpdf_pro-' . $locale . '.mo' );
			load_plugin_textdomain( 'wpo_wcpdf_pro', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
		}

		/**
		 * Load the main plugin classes and functions
		 */
		public function includes() {
			include_once( 'includes/wcpdf-pro-settings.php' );
			include_once( 'includes/wcpdf-pro-writepanels.php' );
			include_once( 'includes/wcpdf-pro-functions.php' );
		}
		

		/**
		 * Instantiate classes when woocommerce is activated
		 */
		public function load_classes() {
			if ( $this->is_woocommerce_activated() ) {
				$this->includes();
				$this->settings = new WooCommerce_PDF_IPS_Pro_Settings();
				$this->writepanels = new WooCommerce_PDF_IPS_Pro_Writepanels();
				$this->functions = new WooCommerce_PDF_IPS_Pro_Functions();
			} else {
				// display notice instead
				add_action( 'admin_notices', array ( $this, 'need_woocommerce' ) );
			}

		}

		/**
		 * Check if woocommerce is activated
		 */
		public function is_woocommerce_activated() {
			$blog_plugins = get_option( 'active_plugins', array() );
			$site_plugins = get_site_option( 'active_sitewide_plugins', array() );

			if ( in_array( 'woocommerce/woocommerce.php', $blog_plugins ) || isset( $site_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			} else {
				return false;
			}
		}
		
		/**
		 * WooCommerce not active notice.
		 *
		 * @return string Fallack notice.
		 */
		 
		public function need_woocommerce() {
			$error = sprintf( __( 'WooCommerce PDF Invoices & Packing Slips Professional requires %sWooCommerce%s to be installed & activated!' , 'wpo_wcpdf_pro' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>' );

			$message = '<div class="error"><p>' . $error . '</p></div>';
		
			echo $message;
		}

		/** Lifecycle methods *******************************************************
		 * Because register_activation_hook only runs when the plugin is manually
		 * activated by the user, we're checking the current version against the
		 * version stored in the database
		****************************************************************************/

		/**
		 * Handles version checking
		 */
		public function do_install() {
			$version_setting = 'wpo_wcpdf_ips_version';
			$installed_version = get_option( $version_setting );

			// installed version lower than plugin version?
			if ( version_compare( $installed_version, $this->version, '<' ) ) {

				if ( ! $installed_version ) {
					$this->install();
				} else {
					$this->upgrade( $installed_version );
				}

				// new version number
				update_option( $version_setting, $this->version );
			}
		}


		/**
		 * Plugin install method. Perform any installation tasks here
		 */
		protected function install() {
			// stub
		}

		/**
		 * Plugin upgrade method.  Perform any required upgrades here
		 *
		 * @param string $installed_version the currently installed ('old') version
		 */
		protected function upgrade( $installed_version ) {
			$settings_key = 'wpo_wcpdf_pro_settings';
			// 1.4.0 - set default for new settings
			if ( version_compare( $installed_version, '1.4.0', '<' ) ) {
				$current_settings = get_option( $settings_key );
				$new_defaults = array(
					'enable_proforma'	=> 1,
				);
				
				$new_settings = array_merge($current_settings, $new_defaults);

				update_option( $settings_key, $new_settings );
			}
		}

		/**
		 * Return/Show document number 
		 */
		public function get_number( $document_type, $order_id = '' ) {
			global $wpo_wcpdf;
			if ( empty( $order_id ) ) {
				$order_id = $wpo_wcpdf->export->order->id;
			}
			$number = $this->functions->get_number( $document_type, $order_id );
			return $number;
		}
		public function number( $document_type, $order_id = '' ) {
			global $wpo_wcpdf;
			if ( empty( $order_id ) ) {
				$order_id = $wpo_wcpdf->export->order->id;
			}
			echo $this->get_number( $document_type, $order_id );
		}

		/**
		 * Return/Show document date 
		 */
		public function get_date( $document_type, $order_id = '' ) {
			global $wpo_wcpdf;
			if ( empty( $order_id ) ) {
				$order_id = $wpo_wcpdf->export->order->id;
			}

			// name conversion for settings and meta compatibility (credit-note = credit_note)
			$document_type = str_replace('-', '_', $document_type);

			// get document date from post meta
			// try parent first (=original proforma invoice for credit notes)
			if ( $document_type != 'credit_note' && get_post_type( $order_id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $order_id ) ) {
				$date = get_post_meta( $parent_order_id, '_wcpdf_'.$document_type.'_date', true );
			} else {
				$date = get_post_meta( $order_id, '_wcpdf_'.$document_type.'_date', true );
			}

			if ( !empty($date) ) {
				$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
			} else {
				$formatted_date = false;
			}

			return $formatted_date;
		}
		public function date( $document_type ) {
			echo $this->get_date( $document_type );
		}


	} // class WooCommerce_PDF_IPS_Pro
} // class_exists

// Load main plugin class
$wpo_wcpdf_pro = new WooCommerce_PDF_IPS_Pro();

/**
 * WPOvernight updater admin notice
 */
if ( ! class_exists( 'WPO_Updater' ) && ! function_exists( 'wpo_updater_notice' ) ) {

	if ( ! empty( $_GET['hide_wpo_updater_notice'] ) ) {
		update_option( 'wpo_updater_notice', 'hide' );
	}

	/**
	 * Display a notice if the "WP Overnight Sidekick" plugin hasn't been installed.
	 * @return void
	 */
	function wpo_updater_notice() {
		$wpo_updater_notice = get_option( 'wpo_updater_notice' );

		$blog_plugins = get_option( 'active_plugins', array() );
		$site_plugins = get_site_option( 'active_sitewide_plugins', array() );
		$plugin = 'wpovernight-sidekick/wpovernight-sidekick.php';

		if ( in_array( $plugin, $blog_plugins ) || isset( $site_plugins[$plugin] ) || $wpo_updater_notice == 'hide' ) {
			return;
		}

		echo '<div class="updated fade"><p>Install the <strong>WP Overnight Sidekick</strong> plugin to receive updates for your WP Overnight plugins - check your order confirmation email for more information. <a href="'.add_query_arg( 'hide_wpo_updater_notice', 'true' ).'">Hide this notice</a></p></div>' . "\n";
	}

	add_action( 'admin_notices', 'wpo_updater_notice' );
}
