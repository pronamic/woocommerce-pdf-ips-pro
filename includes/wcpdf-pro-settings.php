<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WooCommerce_PDF_IPS_Pro_Settings' ) ) {

	class WooCommerce_PDF_IPS_Pro_Settings {
		public function __construct() {
			$this->pro_settings = get_option( 'wpo_wcpdf_pro_settings' );
			add_action( 'admin_enqueue_scripts', array( &$this, 'load_scripts_styles' ) ); // Load scripts
			add_action( 'admin_init', array( &$this, 'init_settings' ) ); // Registers settings
			add_action( 'wpo_wcpdf_settings_tabs', array( &$this, 'settings_tab' ) );
			add_action( 'admin_notices', array( &$this, 'pro_template_check' ) );
			add_action( 'admin_notices', array( &$this, 'wc_version_check' ) );
			add_filter( 'wpo_wcpdf_wc_emails', array( &$this, 'add_custom_emails' ) );

			add_action( 'wp_ajax_wcpdf_i18n_get_translations', array($this, 'get_translations' ));
			add_action( 'wp_ajax_wcpdf_i18n_save_translations', array($this, 'save_translations' ));
		}

		public function get_translations () {
			check_ajax_referer( 'wcpdf_i18n_translations', 'security' );
			if (empty($_POST)) {
				die();
			}
			extract($_POST);

			// $icl_get_languages = 'a:3:{s:2:"en";a:8:{s:2:"id";s:1:"1";s:6:"active";s:1:"1";s:11:"native_name";s:7:"English";s:7:"missing";s:1:"0";s:15:"translated_name";s:7:"English";s:13:"language_code";s:2:"en";s:16:"country_flag_url";s:43:"http://yourdomain/wpmlpath/res/flags/en.png";s:3:"url";s:23:"http://yourdomain/about";}s:2:"fr";a:8:{s:2:"id";s:1:"4";s:6:"active";s:1:"0";s:11:"native_name";s:9:"Français";s:7:"missing";s:1:"0";s:15:"translated_name";s:6:"French";s:13:"language_code";s:2:"fr";s:16:"country_flag_url";s:43:"http://yourdomain/wpmlpath/res/flags/fr.png";s:3:"url";s:29:"http://yourdomain/fr/a-propos";}s:2:"it";a:8:{s:2:"id";s:2:"27";s:6:"active";s:1:"0";s:11:"native_name";s:8:"Italiano";s:7:"missing";s:1:"0";s:15:"translated_name";s:7:"Italian";s:13:"language_code";s:2:"it";s:16:"country_flag_url";s:43:"http://yourdomain/wpmlpath/res/flags/it.png";s:3:"url";s:26:"http://yourdomain/it/circa";}}';
			// $icl_get_languages = unserialize($icl_get_languages);
			$icl_get_languages = icl_get_languages('skip_missing=0');
			$input_type = strtolower($input_type);

			$translations = get_option( 'wpo_wcpdf_translations' );

			printf( '<div id="%s-translations" class="translations">', $input_attributes['id'])
			?>
				<ul>
					<?php foreach ( $icl_get_languages as $lang => $data ) {
						$translation_id = $data['language_code'].'_'.$input_attributes['id'];
						printf('<li><a href="#%s">%s</a></li>', $translation_id, $data['native_name']);
					}
					?>
				</ul>
				<?php foreach ( $icl_get_languages as $lang => $data ) {
					$translation_id = $data['language_code'].'_'.$input_attributes['id'];
					$value = isset($translations[$input_attributes['name']][$data['language_code']]) ? $translations[$input_attributes['name']][$data['language_code']] : '';
					printf( '<div id="%s">', $translation_id );
					switch ( $input_type ) {
						case 'textarea':
							printf( '<textarea cols="%1$s" rows="%2$s" data-language="%3$s">%4$s</textarea>', $input_attributes['cols'], $input_attributes['rows'], $data['language_code'], $value);
							break;
						case 'input':
							printf( '<input type="text" size="%1$s" value="%2$s" data-language="%3$s"/>', $input_attributes['size'], $value, $data['language_code'] );
							break;
					}
					$spinner = '<div class="spinner"></div>';
					printf('<div><button class="wpo-wcpdf-i18n-translations-save button button-primary">%s</button>%s</div>', __( 'Save translations', 'wpo_wcpdf_pro' ), $spinner);
					echo '</div>';
				}
				?>
			
			</div>
			<?php

			die();
		}
		public function save_translations () {
			check_ajax_referer( 'wcpdf_i18n_translations', 'security' );
			if (empty($_POST)) {
				die();
			}
			extract($_POST);

			$translations = get_option( 'wpo_wcpdf_translations' );
			$translations[$setting] = $strings;
			update_option( 'wpo_wcpdf_translations', $translations );

			die();
		}

		/**
		 * Scripts & styles for settings page
		 */
		public function load_scripts_styles ( $hook ) {
			global $wpo_wcpdf;

			if( !isset($wpo_wcpdf->settings->options_page_hook) || $hook != $wpo_wcpdf->settings->options_page_hook ) {
				return;				
			} 

			wp_enqueue_script(
				'wcpdf-file-upload-js',
				plugins_url( 'js/file-upload.js', dirname(__FILE__) ),
				array(),
				'1.3.9'
			);

			if (class_exists('SitePress') || class_exists('Polylang')) {
				wp_enqueue_style(
					'wcpdf-i18n',
					plugins_url( 'css/wcpdf-i18n.css', dirname(__FILE__) ),
					array(),
					'1.3.9'
				);
				wp_enqueue_script(
					'wcpdf-i18n-settings',
					plugins_url( 'js/wcpdf-i18n-settings.js', dirname(__FILE__) ),
					array( 'jquery', 'jquery-ui-tabs' ),
					'1.3.9'
				);
				wp_localize_script(
					'wcpdf-i18n-settings',
					'wpo_wcpdf_i18n',
					array(  
						'ajaxurl'        => admin_url( 'admin-ajax.php' ), // URL to WordPress ajax handling page
						'nonce'          => wp_create_nonce('wcpdf_i18n_translations'),
						'translate_text' => __( 'Translate', 'wpo_wcpdf_pro' ),
						// 'icon'		=> plugins_url( 'images/wpml-icon.png', dirname(__FILE__) ),
					)
				);
			}

			wp_enqueue_media();
		}

		/**
		 * Warning for missing pro templates
		 */
		public function pro_template_check () {
			if ( isset($_GET['page']) && $_GET['page'] == 'wpo_wcpdf_options_page' ) {
				global $wpo_wcpdf;

				// check if template is not 'Simple' (templates are bundled) & pro templates don't exist
				if ( $wpo_wcpdf->export->template_path != $wpo_wcpdf->export->template_default_base_path . 'Simple' && ( !file_exists( $wpo_wcpdf->export->template_path . '/proforma.php' ) || !file_exists( $wpo_wcpdf->export->template_path . '/credit-note.php' ) ) ) {
					$pro_template_folder = str_replace( ABSPATH, '', WooCommerce_PDF_IPS_Pro::$plugin_path . 'templates/Simple/' ); 
					?>
					<div class="error">
						<p>
						<?php _e("<b>Warning!</b> Your WooCommerce PDF Invoices & Packing Slips template folder does not contain templates for credit notes and/or proforma invoices.", 'wpo_wcpdf_pro');?> <br />
						<?php printf( __("If you are using WP Overnight premium templates, please update to the latest version. Otherwise copy the template files located in %s and adapt them to your own template.", 'wpo_wcpdf_pro'), '<code>'.$pro_template_folder.'</code>'); ?><br />
						</p>
					</div>
					<?php
				} // file_exists check
			}
		}

		/**
		 * Check if WooCommerce version is up to date for credit notes
		 */
		public function wc_version_check () {
			if ( isset($_GET['page']) && $_GET['page'] == 'wpo_wcpdf_options_page' ) {
				if ( version_compare( WOOCOMMERCE_VERSION, '2.2.7', '<' ) ) {
					?>
					<div class="error">
						<p>
						<?php printf(__("<b>Important note:</b> WooCommerce 2.2.7 or newer is required to print credit notes. You are currently using WooCommerce %s", 'wpo_wcpdf_pro'), WOOCOMMERCE_VERSION); ?> <br />
						</p>
					</div>
					<?php
				}
			}
		}

		/**
		 * Register settings
		 */
		public function init_settings() {
			global $wpo_wcpdf;
			$option = 'wpo_wcpdf_pro_settings';

			// Create option in wp_options.
			if ( false === get_option( $option ) ) {
				$this->default_settings( $option );
			}

			/**
			 * Attachments section.
			 */
			add_settings_section(
				'attachments',
				__( 'Attachments', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'section_options_callback' ),
				$option
			);


			add_settings_field(
				'static_file',
				__( 'Static files', 'wpo_wcpdf_pro' ),
				array( &$this, 'multiple_file_upload_callback' ),
				$option,
				'attachments',
				array(
					'menu'							=> $option,
					'id'							=> 'static_file',
					'uploader_title'				=> __( 'Select a file to attach', 'wpo_wcpdf_pro' ),
					'uploader_button_text'			=> __( 'Set file', 'wpo_wcpdf_pro' ),
					'remove_button_text'			=> __( 'Remove file', 'wpo_wcpdf_pro' ),
				)
			);

			$wc_emails = array(
				'new_order'			=> __( 'Admin New Order email' , 'wpo_wcpdf' ),
				'processing'		=> __( 'Customer Processing Order email' , 'wpo_wcpdf' ),
				'completed'			=> __( 'Customer Completed Order email' , 'wpo_wcpdf' ),
				'customer_invoice'	=> __( 'Customer Invoice email' , 'wpo_wcpdf' ),
			);

			$attachment_documents = array (
				'proforma'			=> __( 'Proforma' , 'wpo_wcpdf_pro' ),
				'packing-slip'		=> __( 'Packing Slip' , 'wpo_wcpdf_pro' ),
				'credit-note'		=> __( 'Credit Note' , 'wpo_wcpdf_pro' ),
				'static'			=> __( 'Static files' , 'wpo_wcpdf_pro' )
			);

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				// disable credit notes for old versions
				unset( $attachment_documents['credit-note'] );
			}


			add_settings_field(
				'pro_attach',
				__( 'Pro attachment settings', 'wpo_wcpdf_pro' ),
				array( &$this, 'checkbox_table_callback' ),
				$option,
				'attachments',
				array(
					'menu'		=> $option,
					'id'		=> 'pro_attach',
					'rows' 		=> apply_filters( 'wpo_wcpdf_wc_emails', $wc_emails ),
					'columns'	=> $attachment_documents,
					'description' => __( 'Please note that the more files you attach, the longer the processing time. This is especially relevant for the emails that are sent at checkout!' , 'wpo_wcpdf_pro' ),
				)
			);

			/**
			 * Credit notes section
			 */
			
			add_settings_section(
				'credit_notes',
				__( 'Credit Notes / Refunds', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'section_options_callback' ),
				$option
			);

			add_settings_field(
				'subtract_refunded_qty',
				__( 'Subtract refunded item quantities from packing slip', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'subtract_refunded_qty',
				)
			);

			add_settings_field(
				'positive_credit_note',
				__( 'Use positive prices', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'positive_credit_note',
					'description'	=> __( 'Prices in Credit Notes are negative by default, but some countries (like Germany) require positive prices.', 'wpo_wcpdf_pro' ),
				)
			);

			add_settings_field(
				'credit_note_number',
				__( 'Numbering system', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'radio_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'credit_note_number',
					'options' 		=> array(
						'main'		=> __( 'Main invoice numbering' , 'wpo_wcpdf_pro' ),
						'separate'	=> __( 'Separate credit note numbering' , 'wpo_wcpdf_pro' ),
					),
				)
			);

			add_settings_field(
				'credit_note_original_invoice_number',
				__( 'Show original invoice number', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'credit_note_original_invoice_number',
				)
			);

			add_settings_field(
				'credit_note_date',
				__( 'Display credit note date', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'credit_note_date',
				)
			);

			add_settings_field(
				'next_credit_note_number',
				__( 'Next number (without prefix/suffix etc.)', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'text_element_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'			=> $option,
					'id'			=> 'next_credit_note_number',
					'size'			=> '10',
					'description'	=> __( 'This is the number that will be used for the next Credit Note that is generated (manually or automatically)' , 'wpo_wcpdf_pro' ),
				)
			);

			add_settings_field(
				'credit_note_number_formatting',
				__( 'Number format', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'invoice_number_formatting_callback' ),
				$option,
				'credit_notes',
				array(
					'menu'					=> $option,
					'id'					=> 'credit_note_number_formatting',
					'fields'				=> array(
						'prefix'			=> array(
							'title'			=> __( 'Prefix' , 'wpo_wcpdf' ),
							'size'			=> 20,
							'description'	=> __( 'to use the order year and/or month, use [order_year] or [order_month] respectively' , 'wpo_wcpdf' ),
						),
						'suffix'			=> array(
							'title'			=> __( 'Suffix' , 'wpo_wcpdf' ),
							'size'			=> 20,
							'description'	=> '',
						),
						'padding'			=> array(
							'title'			=> __( 'Padding' , 'wpo_wcpdf' ),
							'size'			=> 2,
							'description'	=> __( 'enter the number of digits here - enter "6" to display 42 as 000042' , 'wpo_wcpdf' ),
						),
					),
				)
			);

			/**
			 * Proforma section
			 */
			
			add_settings_section(
				'proforma',
				__( 'Proforma Invoices', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'section_options_callback' ),
				$option
			);

			add_settings_field(
				'enable_proforma',
				__( 'Enable Proforma Invoices', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'proforma',
				array(
					'menu'			=> $option,
					'id'			=> 'enable_proforma',
				)
			);

			add_settings_field(
				'proforma_number',
				__( 'Numbering system', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'radio_element_callback' ),
				$option,
				'proforma',
				array(
					'menu'			=> $option,
					'id'			=> 'proforma_number',
					'options' 		=> array(
						'main'		=> __( 'Main invoice numbering' , 'wpo_wcpdf_pro' ),
						'separate'	=> __( 'Separate proforma invoice numbering' , 'wpo_wcpdf_pro' ),
					),
				)
			);

			add_settings_field(
				'proforma_date',
				__( 'Display proforma invoice date', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'proforma',
				array(
					'menu'			=> $option,
					'id'			=> 'proforma_date',
				)
			);

			add_settings_field(
				'next_proforma_number',
				__( 'Next number (without prefix/suffix etc.)', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'text_element_callback' ),
				$option,
				'proforma',
				array(
					'menu'			=> $option,
					'id'			=> 'next_proforma_number',
					'size'			=> '10',
				)
			);

			add_settings_field(
				'proforma_number_formatting',
				__( 'Number format', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'invoice_number_formatting_callback' ),
				$option,
				'proforma',
				array(
					'menu'					=> $option,
					'id'					=> 'proforma_number_formatting',
					'fields'				=> array(
						'prefix'			=> array(
							'title'			=> __( 'Prefix' , 'wpo_wcpdf' ),
							'size'			=> 20,
							// 'description'	=> __( 'to use the order year and/or month, use [order_year] or [order_month] respectively' , 'wpo_wcpdf' ),
						),
						'suffix'			=> array(
							'title'			=> __( 'Suffix' , 'wpo_wcpdf' ),
							'size'			=> 20,
							'description'	=> '',
						),
						'padding'			=> array(
							'title'			=> __( 'Padding' , 'wpo_wcpdf' ),
							'size'			=> 2,
							// 'description'	=> __( 'enter the number of digits here - enter "6" to display 42 as 000042' , 'wpo_wcpdf' ),
						),
					),
				)
			);

			/**
			 * Address customization section
			 */
			
			add_settings_section(
				'address_customization',
				__( 'Address customization', 'wpo_wcpdf_pro' ),
				array( &$this, 'custom_address_fields_section_callback' ),
				$option
			);

			add_settings_field(
				'billing_address',
				__( 'Billing address', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'textarea_element_callback' ),
				$option,
				'address_customization',
				array(
					'menu'			=> $option,
					'id'			=> 'billing_address',
					'width'			=> '42',
					'height'		=> '8',
				)
			);


			add_settings_field(
				'shipping_address',
				__( 'Shipping address', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'textarea_element_callback' ),
				$option,
				'address_customization',
				array(
					'menu'			=> $option,
					'id'			=> 'shipping_address',
					'width'			=> '42',
					'height'		=> '8',
				)
			);

			add_settings_field(
				'remove_whitespace',
				__( 'Remove empty lines', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'address_customization',
				array(
					'menu'			=> $option,
					'id'			=> 'remove_whitespace',
					'description'	=> __( 'Enable this option if you want to remove empty lines left over from empty address/placeholder replacements', 'wpo_wcpdf_pro' ),
				)
			);

			add_settings_field(
				'placeholders_allow_line_breaks',
				__( 'Allow line breaks within custom fields', 'wpo_wcpdf_pro' ),
				array( &$wpo_wcpdf->settings, 'checkbox_element_callback' ),
				$option,
				'address_customization',
				array(
					'menu'			=> $option,
					'id'			=> 'placeholders_allow_line_breaks',
				)
			);

			// Register settings.
			register_setting( $option, $option, array( &$wpo_wcpdf->settings, 'validate_options' ) );
		}

		/**
		 * Set default settings.
		 */
		public function default_settings( $option ) {
			switch ( $option ) {
				case 'wpo_wcpdf_pro_settings':
					$default = array(
						'credit_note_number'	=> 'separate',
						'proforma_number'		=> 'separate',
					);
					break;
				default:
					$default = array();
					break;
			}

			if ( false === get_option( $option ) ) {
				add_option( $option, $default );
			} else {
				update_option( $option, $default );
			}
		}


		/**
		 * add Pro settings tab to the PDF Invoice settings page
		 * @param  array $tabs slug => Title
		 * @return array $tabs with Pro
		 */
		public function settings_tab( $tabs ) {
			$tabs['pro'] = __('Pro','wpo_wcpdf_pro');
			return $tabs;
		}

		public function checkbox_table_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];

			$options = get_option( $menu );

			$rows = $args['rows'];
			$columns = $args['columns'];

			?>
			<table style="">
				<tr>
					<td style="padding:0 10px 5px 0;">&nbsp;</td>
					<?php foreach ( $columns as $column => $title ) { ?>
					<td style="padding:0 10px 5px 0;"><?php echo $title; ?></td>
					<?php } ?>
				</tr>
				<tr>
					<td style="padding: 0;">
						<?php foreach ($rows as $row) {
							echo $row.'<br/>';
						} ?>
					</td>
					<?php foreach ( $columns as $column => $title ) { ?>
					<td style="text-align:center; padding: 0;">
						<?php foreach ( $rows as $row => $title ) {
							$current = ( isset( $options[$id.'_'.$column][$row] ) ) ? $options[$id.'_'.$column][$row] : '';
							$name = sprintf('%1$s[%2$s_%3$s][%4$s]', $menu, $id, $column, $row);
							printf( '<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s /><br/>', $name, checked( 1, $current, false ) );
						} ?>
					</td>
					<?php } ?>
				</tr>
			</table>

			<?php
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		}

		/**
		 * File upload callback.
		 *
		 * @param  array $args Field arguments.
		 */
		public function file_upload_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				$current = $options[$id];
			} else {
				$current = array(
					'id'		=> '',
					'filename'	=> '',
				);
			}

			$uploader_title = $args['uploader_title'];
			$uploader_button_text = $args['uploader_button_text'];
			$remove_button_text = $args['remove_button_text'];

			printf( '<input id="%1$s_id" name="%2$s[%1$s][id]" value="%3$s" type="hidden"  />', $id, $menu, $current['id'] );
			printf( '<input id="%1$s_filename" name="%2$s[%1$s][filename]" size="50" value="%3$s" readonly="readonly" />', $id, $menu, $current['filename'] );
			if ( !empty($current['id']) ) {
				printf('<span class="button remove_file_button" data-input_id="%1$s">%2$s</span>', $id, $remove_button_text );
			}
			printf( '<span class="button upload_file_button %4$s" data-uploader_title="%1$s" data-uploader_button_text="%2$s" data-remove_button_text="%3$s" data-input_id="%4$s">%2$s</span>', $uploader_title, $uploader_button_text, $remove_button_text, $id );
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		}

		/**
		 * Multiple file upload callback.
		 *
		 * @param  array $args Field arguments.
		 */
		public function multiple_file_upload_callback( $args ) {
			$menu = $args['menu'];
			$id = $args['id'];
			$options = get_option( $menu );
		
			if ( isset( $options[$id] ) ) {
				// convert old single static file to array
				if ( isset( $options[$id]['id'] ) ) {
					$current = array( $options[$id] );
				} else {
					$current = $options[$id];
				}
			}

			$uploader_title = $args['uploader_title'];
			$uploader_button_text = $args['uploader_button_text'];
			$remove_button_text = $args['remove_button_text'];

			for ($i=0; $i < 3; $i++) {
				$file_id = isset($current[$i]) ? $current[$i]['id'] : '';
				$filename = isset($current[$i]) ? $current[$i]['filename'] : '';

				printf( '<input id="%1$s_%2$s_id" name="%3$s[%1$s][%2$s][id]" value="%4$s" type="hidden" />', $id, $i, $menu, $file_id );
				printf( '<input id="%1$s_%2$s_filename" name="%3$s[%1$s][%2$s][filename]" size="50" value="%4$s" readonly="readonly" />', $id, $i, $menu, $filename );
				if ( !empty($file_id) ) {
					printf('<span class="button remove_file_button" data-input_id="%1$s_%2$s">%3$s</span>', $id, $i, $remove_button_text );
				}
				printf( '<span class="button upload_file_button %4$s" data-uploader_title="%1$s" data-uploader_button_text="%2$s" data-remove_button_text="%3$s" data-input_id="%4$s_%5$s">%2$s</span><br/>', $uploader_title, $uploader_button_text, $remove_button_text, $id, $i );
			}
		
			// Displays option description.
			if ( isset( $args['description'] ) ) {
				printf( '<p class="description">%s</p>', $args['description'] );
			}
		}

		/**
		 * Address customization callback.
		 *
		 * @return void.
		 */
		public function custom_address_fields_section_callback() {
			echo __( 'Here you can modify the way the shipping and billing address are formatted in the PDF documents as well as add custom fields to them.', 'wpo_wcpdf_pro').'<br/>';
			echo __( 'You can use the following placeholders in addition to regular text and html tags (like h1, h2, b):', 'wpo_wcpdf_pro').'<br/>';
			?>
			<table style="background-color:#eee;border:1px solid #aaa; margin:1em; padding:1em;">
				<tr>
					<th style="text-align:left; padding:5px 5px 0 5px;"><?php _e( 'Billing fields', 'wpo_wcpdf_pro' ); ?></th>
					<th style="text-align:left; padding:5px 5px 0 5px;"><?php _e( 'Shipping fields', 'wpo_wcpdf_pro' ); ?></th>
					<th style="text-align:left; padding:5px 5px 0 5px;"><?php _e( 'Custom fields', 'wpo_wcpdf_pro' ); ?></th>
				</tr>
				<tr>
					<td style="vertical-align:top; padding:5px;">
						[billing_address]<br/>
						[billing_first_name]<br/>
						[billing_last_name]<br/>
						[billing_company]<br/>
						[billing_address_1]<br/>
						[billing_address_2]<br/>
						[billing_city]<br/>
						[billing_postcode]<br/>
						[billing_country]<br/>
						[billing_country_code]<br/>
						[billing_state]<br/>
						[billing_state_code]<br/>
						[billing_email]<br/>
						[billing_phone]
					</td>
					<td style="vertical-align:top; padding:5px;">
						[shipping_address]<br/>
						[shipping_first_name]<br/>
						[shipping_last_name]<br/>
						[shipping_company]<br/>
						[shipping_address_1]<br/>
						[shipping_address_2]<br/>
						[shipping_city]<br/>
						[shipping_postcode]<br/>
						[shipping_country]<br/>
						[shipping_country_code]<br/>
						[shipping_state]<br/>
						[shipping_state_code]
					</td>
					<td style="vertical-align:top; padding:5px;">
						[custom_fieldname]
					</td>
				</tr>
			</table>
			<?php
			echo __( 'Leave empty to use the default formatting.', 'wpo_wcpdf_pro').'<br/>';
		}

		public function add_custom_emails ( $emails ) {
			$extra_emails = $this->get_wc_emails();

			$emails = array_merge( $emails, $extra_emails );
			return $emails;
		}

		/**
		 * get all emails registered in WooCommerce
		 * @param  boolean $remove_defaults switch to remove default woocommerce emails
		 * @return array   $emails       list of all email ids/slugs and names
		 */
		public function get_wc_emails ( $remove_defaults = true ) {
			// get emails from WooCommerce
			global $woocommerce;
			$mailer = $woocommerce->mailer();
			$wc_emails = $mailer->get_emails();

			$default_emails = array(
				'new_order',
				'customer_processing_order',
				'customer_completed_order',
				'customer_invoice',
				'customer_note',
				'customer_reset_password',
				'customer_new_account'
			);

			$emails = array();
			foreach ($wc_emails as $name => $template) {
				if ( !( $remove_defaults && in_array( $template->id, $default_emails ) ) ) {
					$emails[$template->id] = $template->title;
				}
			}

			return $emails;
		}
	
	} // end class
} // end class_exists