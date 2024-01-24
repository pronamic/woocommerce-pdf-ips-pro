<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WooCommerce_PDF_IPS_Pro_Functions' ) ) {

	class WooCommerce_PDF_IPS_Pro_Functions {
		public function __construct() {
			$this->pro_settings = get_option( 'wpo_wcpdf_pro_settings' );
			add_filter( 'woocommerce_email_attachments', array( $this, 'attach_static_file' ), 99, 3);
			add_filter( 'wpo_wcpdf_attach_documents', array( $this, 'attach_pro_documents' ), 10, 1 );
			add_filter( 'wpo_wcpdf_filename', array( $this, 'build_filename' ), 5, 4 );
			add_filter( 'wpo_wcpdf_template_file', array( $this, 'pro_template_files' ), 10, 2 );
			add_filter( 'wpo_wcpdf_template_name', array( $this, 'pro_template_names' ), 5, 2 );
			add_filter( 'wpo_wcpdf_billing_address', array( $this, 'billing_address_filter' ), 10, 1 );
			add_filter( 'wpo_wcpdf_shipping_address', array( $this, 'shipping_address_filter' ), 10, 1 );
			add_action( 'wpo_wcpdf_process_template_order', array( $this, 'set_numbers_dates' ), 10, 2 );
			add_filter( 'wpo_wcpdf_process_order_ids', array( $this, 'refunds_order_ids' ), 10, 2 );

			add_filter( 'wpo_wcpdf_proforma_number', array( $this, 'format_proforma_number' ), 20, 4 );
			add_filter( 'wpo_wcpdf_credit_note_number', array( $this, 'format_credit_note_number' ), 20, 4 );
			add_action( 'wpo_wcpdf_process_template', array( $this, 'positive_credit_note' ) );

			if ( isset($this->pro_settings['subtract_refunded_qty'])) {
				add_filter( 'wpo_wcpdf_order_items_data', array( $this, 'subtract_refunded_qty' ), 10, 2 );
			}
			if ( isset($this->pro_settings['credit_note_original_invoice_number'])) {
				add_filter( 'wpo_wcpdf_after_order_data', array( $this, 'original_invoice_number' ), 10, 2 );
			}

			add_filter( 'woocommerce_email_classes', array( $this, 'add_emails' ) );
			add_filter( 'wpo_wcpdf_custom_attachment_condition', array( $this, 'prevent_empty_credit_notes' ), 10, 4 );

			// register status actions to make sure triggers are pulled!
			$this->email_actions = array (
				'woocommerce_order_status_processing',
				'woocommerce_payment_complete',
			);
			$this->register_email_actions();

			// WPML compatibility functions
			if ( class_exists('SitePress') || class_exists('Polylang') ) {
				add_action( 'wpo_wcpdf_process_template_order', array( $this, 'switch_language' ), 10, 2 );
				add_action( 'wpo_wcpdf_after_pdf', array( $this, 'reset_language' ), 10, 1 );
				add_filter( 'wpo_wcpdf_meta_box_actions', array( $this, 'lang_url_box' ), 90, 1 );			
				add_filter( 'wpo_wcpdf_listing_actions', array( $this, 'lang_url' ), 90, 2 );			
				add_filter( 'wpo_wcpdf_myaccount_actions', array( $this, 'lang_url' ), 90, 2 );
			}
		}

		/**
		 * Register email actions (backwards compatible with WC 2.2 & 2.1)
		 *
		 * @access public
		 * @return void
		 */
		public function register_email_actions () {
			if ( version_compare( WOOCOMMERCE_VERSION, '2.3', '>=' ) ) {
				// use filter when possible
				add_filter( 'woocommerce_email_actions', array( $this, 'woocommerce_email_actions' ), 10, 1 );
			} else {
				// backwards compatible method
				global $woocommerce;
				foreach ( $this->email_actions as $action ) {
					add_action( $action, array( $woocommerce, 'send_transactional_email' ), 10, 10 );
				}

			}
		}

		/**
		 * Add email actions.
		 *
		 * @access public
		 * @return $email_actions
		 */
		public function woocommerce_email_actions ( $email_actions ) {
			return array_merge($email_actions, $this->email_actions);
		}

		/**
		 * Set file locations for pro document types
		 */
		public function pro_template_files( $template, $template_type ) {
			global $wpo_wcpdf;

			// bail out if file already exists in default or custom path!
			if( file_exists( $template ) ){
				return $template;
			}

			$pro_template = WooCommerce_PDF_IPS_Pro::$plugin_path . 'templates/Simple/' . $template_type . '.php';

			if( file_exists( $pro_template ) ){
				// default to bundled Simple template
				return $pro_template;
			} else {
				// unknown document type! This will inevitably throw an error unless there's another filter after this one.
				return $template;
			}
		}

		/**
		 * Filter to get template name for template type/slug
		 */
		public function pro_template_names ( $template_name, $template_type ) {
			switch ( $template_type ) {
				case 'proforma':
					$template_name = apply_filters( 'wpo_wcpdf_proforma_title', __( 'Proforma Invoice', 'wpo_wcpdf_pro' ) );
					break;
				case 'credit-note':
					$template_name = apply_filters( 'wpo_wcpdf_credit_note_title', __( 'Credit Note', 'wpo_wcpdf_pro' ) );
					break;
			}

			return $template_name;
		}

		/**
		 * Register pro document types for email attachments
		 * @param  array $documents with filename and allowed status data
		 * @return new documents
		 */
		public function attach_pro_documents( $documents ) {
			$pro_document_types = array( 'packing-slip', 'proforma', 'credit-note' );
			$pro_documents = array();

			foreach ($pro_document_types as $document_type) {
				//filter allowed statuses
				$status_setting = isset( $this->pro_settings['pro_attach_'.$document_type] ) ? array_keys( $this->pro_settings['pro_attach_'.$document_type] ) : array();
				$pro_documents[$document_type] = apply_filters( 'wpo_wcpdf_email_allowed_statuses_'.$document_type, $status_setting ); // Relevant (default) statuses: new_order, customer_invoice, customer_processing_order, customer_completed_order
			}

			$documents = array_merge($documents, $pro_documents);

			return $documents;
		}

		/**
		 * If credit notes attachment is enabled for invoice email, and an invoice email is sent when
		 * the order is not refunded, an empty credit note would otherwise be attached.
		 * This method prevents that from happening.
		 */
		public function prevent_empty_credit_notes ( $condition, $order, $status, $template_type ) {
			// only process credit notes
			if ( $template_type != 'credit-note' ) {
				return $condition;
			}

			// prevent attachment for older versions
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				return false;
			}

			// get refunds
			$refunds = $order->get_refunds();

			// only attach credit note pdf when there are refunds
			if ( empty( $refunds ) ) {
				return false;
			} else {
				return $condition;
			}
		}

		/**
		 * 
		 */
		public function build_filename( $filename, $template_type, $order_ids, $context ) {
			if ( !in_array( $template_type, array( 'credit-note', 'proforma' ) ) ) {
				// we're not processing any of the pro documents
				return $filename;
			}

			global $wpo_wcpdf, $wpo_wcpdf_pro;

			$count = count( $order_ids );

			switch ($template_type) {	
				case 'proforma':
					$name = _n( 'proforma-invoice', 'proforma-invoices', $count, 'wpo_wcpdf_pro' );
					$number = $wpo_wcpdf_pro->get_number('proforma');
					break;		
				case 'credit-note':
					$name = _n( 'credit-note', 'credit-notes', $count, 'wpo_wcpdf_pro' );
					$number = $wpo_wcpdf_pro->get_number('credit-note');
					break;
			}

			if ( $count == 1 ) {
				$suffix = $number;			
			} else {
				$suffix = date('Y-m-d'); // 2020-11-11
			}

			return sanitize_file_name( $name . '-' . $suffix . '.pdf' );
		}

		/**
		 * filters addresses when replacement placeholders configured via plugin settings!
		 */
		public function billing_address_filter( $original_address ) {
			return $this->address_replacements( $original_address, 'billing' );
		}

		public function shipping_address_filter( $original_address ) {
			return $this->address_replacements( $original_address, 'shipping' );
		}

		public function address_replacements( $original_address, $type ) {
			global $wpo_wcpdf;

			if ( !isset( $this->pro_settings[$type.'_address'] ) || empty( $this->pro_settings[$type.'_address'] ) ) {
				// nothing set, use default woocommerce formatting
				return $original_address;
			}

			// get order meta
			$order_meta = get_post_meta( $wpo_wcpdf->export->order->id );

			// flatten values
			foreach ($order_meta as $key => &$value) {
				$value = $value[0];
				if (isset($this->pro_settings['placeholders_allow_line_breaks']) && is_string($value)) {
					$value = nl2br( wptexturize( $value ) );
				}
				
			}
			// remove reference!
			unset($value);

			// get full countries & states
			$countries = new WC_Countries;
			$shipping_country	= $order_meta['_shipping_country'];
			$billing_country	= $order_meta['_billing_country'];
			$shipping_state		= $order_meta['_shipping_state'];
			$billing_state		= $order_meta['_billing_state'];

			$shipping_state_full	= ( $shipping_country && $shipping_state && isset( $countries->states[ $shipping_country ][ $shipping_state ] ) ) ? $countries->states[ $shipping_country ][ $shipping_state ] : $shipping_state;
			$billing_state_full		= ( $billing_country && $billing_state && isset( $countries->states[ $billing_country ][ $billing_state ] ) ) ? $countries->states[ $billing_country ][ $billing_state ] : $billing_state;
			$shipping_country_full	= ( $shipping_country && isset( $countries->countries[ $shipping_country ] ) ) ? $countries->countries[ $shipping_country ] : $shipping_country;
			$billing_country_full	= ( $billing_country && isset( $countries->countries[ $billing_country ] ) ) ? $countries->countries[ $billing_country ] : $billing_country;
			unset($countries);

			// add 'missing meta'
			$order_meta['shipping_address']			= $wpo_wcpdf->export->order->get_formatted_shipping_address();
			$order_meta['shipping_country_code']	= $shipping_country;
			$order_meta['shipping_state_code']		= $shipping_state;
			$order_meta['_shipping_country']		= $shipping_country_full;
			$order_meta['_shipping_state']			= $shipping_state_full;

			$order_meta['billing_address']			= $wpo_wcpdf->export->order->get_formatted_billing_address();
			$order_meta['billing_country_code']		= $billing_country;
			$order_meta['billing_state_code']		= $billing_state;
			$order_meta['_billing_country']			= $billing_country_full;
			$order_meta['_billing_state']			= $billing_state_full;

			// create placeholders list
			foreach ($order_meta as $key => $value) {
				// strip leading underscores, add brackets
				$placeholders[$key] = '['.ltrim($key,'_').']';
			}

			// print_r($placeholders);
			// print_r($order_meta);
			// die();

			// get address format
			$format = nl2br( $this->pro_settings[$type.'_address'] );

			// make an index of placeholders
			preg_match_all('/\[.*?\]/', $format, $placeholders_used);
			$placeholders_used = array_shift($placeholders_used); // we only need the first match set

			// unset empty order_meta and remove corresponding placeholder
			foreach ($order_meta as $key => $value) {
				if (empty($value)) {
					unset($order_meta[$key]);
					unset($placeholders[$key]);
				}
			}

			// make replacements
			$new_address = str_replace($placeholders, $order_meta, $format);

			// remove empty lines placeholder lines, but preserve user-defined empty lines
			if (isset($this->pro_settings['remove_whitespace'])) {
				// break formatted address into lines
				$new_address = explode("\n", $new_address);
				// loop through address lines and check if only placeholders (remove HTML formatting first)
				foreach ($new_address as $key => $address_line) {
					// strip html tags for checking
					$clean_line = trim(strip_tags($address_line));
					// clean zero-width spaces
					$clean_line = str_replace("\xE2\x80\x8B", "", $clean_line);
					// var_dump($clean_line);
					if (empty($clean_line)) {
						continue; // user defined newline!
					}
					// check without leftover placeholders
					$clean_line = str_replace($placeholders_used, '', $clean_line);

					// remove empty lines
					if (empty($clean_line)) {
						unset($new_address[$key]);
					}
				}

				// glue address lines back together
				$new_address = implode("\n", $new_address);				
			} 

			// remove leftover placeholders
			$new_address = str_replace($placeholders_used, '', $new_address);

			return $new_address;
		}

		/**
		 * Attach static file to WooCommerce emails of choice
		 * @param  array  $attachments  list of attachment paths
		 * @param  string $status       status of the order
		 * @param  object $order        order object
		 * @return array  $attachments  including static file
		 */
		public function attach_static_file( $attachments, $status, $order ) {
			if (!isset($this->pro_settings['static_file'])) {
				return $attachments;
			}

			$status_setting = isset( $this->pro_settings['pro_attach_static'] ) ? array_keys( $this->pro_settings['pro_attach_static'] ) : array();
			$allowed_statuses = apply_filters( 'wpo_wcpdf_email_allowed_statuses_static', $status_setting ); // Relevant (default) statuses: new_order, customer_invoice, customer_processing_order, customer_completed_order	

			// convert 'lazy' status name
			foreach ($allowed_statuses as $key => $order_status) {
				if ($order_status == 'completed' || $order_status == 'processing') {
					$allowed_statuses[$key] = "customer_" . $order_status . "_order";
				}
			}

			// convert old single static file to array
			if ( isset( $this->pro_settings['static_file']['id'] ) ) {
				$static_files = array( $this->pro_settings['static_file'] );
			} else {
				$static_files = $this->pro_settings['static_file'];
			}

			// fake $template_type for attachment condition filter
			$template_type = 'static_file';
			// use this filter to add an extra condition - return false to disable the file attachment
			$attach_file = apply_filters('wpo_wcpdf_custom_attachment_condition', true, $order, $status, $template_type );

			if ( in_array( $status, $allowed_statuses ) && $attach_file ) {
				foreach ($static_files as $static_file) {
					if ( isset( $static_file['id'] ) ) {
						$file_path = get_attached_file( $static_file['id'] );
						if ( file_exists( $file_path ) ) {
							$attachments[] = $file_path;
						}
					}
				}
			}

			return $attachments;
		}

		/**
		 * Set number and date for pro documents
		 * @param  string $template_type
		 * @param  int    $order_id
		 * @return void
		 */
		public function set_numbers_dates( $template_type, $order_id ) {
			// check if we're processing one of the pro document types
			if ( !in_array( $template_type, array( 'proforma', 'credit-note' ) ) ) {
				return;
			}

			// name conversion for settings and meta compatibility (credit-note = credit_note)
			$template_type = str_replace('-', '_', $template_type);

			// get document date
			$date = get_post_meta( $order_id, '_wcpdf_'.$template_type.'_date', true );
			if ( empty($date) ) {
				// first time this document is created for this order
				// set document date
				$date = current_time('mysql');
				update_post_meta( $order_id, '_wcpdf_'.$template_type.'_date', $date );
				
			}

			// get document number
			$number = get_post_meta( $order_id, '_wcpdf_'.$template_type.'_number', true );
			if ( isset( $this->pro_settings[$template_type.'_number'] ) && empty( $number ) ) {
				// numbering system switch
				switch ($this->pro_settings[$template_type.'_number']) {
					case 'main':
						// making direct DB call to avoid caching issues
						global $wpdb;
						$next_invoice_number = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'wpo_wcpdf_next_invoice_number' ) );
						$next_invoice_number = apply_filters( 'wpo_wcpdf_next_invoice_number', $next_invoice_number, $order_id );

						// set document number
						$document_number = isset( $next_invoice_number ) ? $next_invoice_number : 1;

						// increase wpo_wcpdf_next_invoice_number
						$update_args = array(
							'option_value'	=> $document_number + 1,
							'autoload'		=> 'yes',
						);
						$result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => 'wpo_wcpdf_next_invoice_number' ) );
						break;
					case 'separate':
						// set document number
						$document_number = isset( $this->pro_settings['next_'.$template_type.'_number'] ) ? $this->pro_settings['next_'.$template_type.'_number'] : 1;

						// increment next document number setting
						$this->pro_settings = get_option( 'wpo_wcpdf_pro_settings' );
						$this->pro_settings['next_'.$template_type.'_number'] += 1;
						update_option( 'wpo_wcpdf_pro_settings', $this->pro_settings );
						break;
				}

				update_post_meta( $order_id, '_wcpdf_'.$template_type.'_number', $document_number );
				update_post_meta( $order_id, '_wcpdf_formatted_'.$template_type.'_number', $this->get_number( $template_type, $order_id ) );
			}
		}

		/**
		 * Get the formatted document number for a template type
		 * @param  string $template_type
		 * @param  int    $order_id
		 * @return formatted document number
		 */
		public function get_number( $template_type, $order_id = '' ) {
			global $wpo_wcpdf;
			// name conversion for settings and meta compatibility (credit-note = credit_note)
			$template_type = str_replace('-', '_', $template_type);

			// get number from post meta
			// try parent first (=original proforma invoice for credit notes)
			if ( $template_type != 'credit_note' && get_post_type( $order_id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $order_id ) ) {
				$number = get_post_meta( $parent_order_id, '_wcpdf_'.$template_type.'_number', true );
			} else {
				$number = get_post_meta( $order_id, '_wcpdf_'.$template_type.'_number', true );
			}

			// prepare filter data & filter
			if ( $number ) {
				// check if we have already loaded this order
				if ( $wpo_wcpdf->export->order->id == $order_id ) {
					$order_number = $wpo_wcpdf->export->order->get_order_number();
					$order_date = $wpo_wcpdf->export->order->order_date;
				} else {
					$order = new WC_Order( $order_id );
					$order_number = $order->get_order_number();
					$order_date = $order->order_date;
				}

				return apply_filters( 'wpo_wcpdf_'.$template_type.'_number', $number, $order_number, $order_id, $order_date );
			} else {
				// no number for this order
				return false;
			}
		}

		public function refunds_order_ids($order_ids, $template_type) {
			if ($template_type == 'credit-note') {
				$refunds_order_ids = array();
				foreach ($order_ids as $order_id) {
					$order = new WC_Order( $order_id );
					$refunds = $order->get_refunds();
					foreach ($refunds as $key => $refund) {
						$refunds_order_ids[] = $refund->id;
					}
				}
				// die(print_r($refunds_order_ids,true));
				return $refunds_order_ids;
			} else {
				return $order_ids;
			}
		}

		/**
		 * Format proforma invoice & credit note numbers
		 * @param  int    $number       the plain, unformatted number
		 * @param  string $order_number WooCommerce order number
		 * @param  int    $order_id     Order ID
		 * @param  string $order_date   mysql order date
		 * @return string               Fotmatted number
		 */
		public function format_proforma_number( $number, $order_number, $order_id, $order_date ) {
			return $this->format_number( 'proforma', $number, $order_number, $order_id, $order_date );
		}

		public function format_credit_note_number( $number, $order_number, $order_id, $order_date ) {
			return $this->format_number( 'credit-note', $number, $order_number, $order_id, $order_date );
		}

		/**
		 * Universal number formatting function
		 */
		public function format_number( $template_type, $number, $order_number, $order_id, $order_date ) {
			// name conversion for settings and meta compatibility (credit-note = credit_note)
			$template_type = str_replace('-', '_', $template_type);

			// get format settings
			$order_year = date_i18n( 'Y', strtotime( $order_date ) );
			$order_month = date_i18n( 'm', strtotime( $order_date ) );
			
			$formats['prefix'] = isset($this->pro_settings[$template_type.'_number_formatting_prefix'])?$this->pro_settings[$template_type.'_number_formatting_prefix']:'';
			$formats['suffix'] = isset($this->pro_settings[$template_type.'_number_formatting_suffix'])?$this->pro_settings[$template_type.'_number_formatting_suffix']:'';
			$formats['padding'] = isset($this->pro_settings[$template_type.'_number_formatting_padding'])?$this->pro_settings[$template_type.'_number_formatting_padding']:'';

			// Replacements
			foreach ($formats as $key => $value) {
				$value = str_replace('[order_year]', $order_year, $value);
				$value = str_replace('[order_month]', $order_month, $value);
				$formats[$key] = $value;
			}

			// Padding
			if ( ctype_digit( (string)$formats['padding'] ) ) {
				$number = sprintf('%0'.$formats['padding'].'d', $number);
			}

			$formatted_number = $formats['prefix'] . $number . $formats['suffix'] ;

			return $formatted_number;
		}

		public function subtract_refunded_qty ( $items_data, $order ) {
			global $wpo_wcpdf;
			if ( $wpo_wcpdf->export->template_type == 'packing-slip' ) {

				foreach ($items_data as $key => &$item) {
					// item_id is required! (introduced in 1.5.3 of main plugin)
					if ( isset( $item['item_id'] ) ) {
						$refunded_qty = $order->get_qty_refunded_for_item( $item['item_id'] );
						if ( version_compare( WOOCOMMERCE_VERSION, '2.6', '>=' ) ) {
							$item['quantity'] = $item['quantity'] + $refunded_qty;
						} else {
							$item['quantity'] = $item['quantity'] - $refunded_qty;
						}

					}

					if ( $item['quantity'] == 0 ) {
						//remove 0 qty items
						unset( $items_data[$key] );
					}
				}
			}
			return $items_data;
		}

		/**
		 * Show positive prices on credit note following user settings
		 */
		public function positive_credit_note ( $template_type ) {
			if ( $template_type == 'credit-note' && isset( $this->pro_settings['positive_credit_note'] ) ) {
				add_filter( 'wc_price', array( $this, 'woocommerce_positive_prices' ), 10, 3 );
			}
		}

		public function woocommerce_positive_prices ( $formatted_price, $price, $args ) {
			$formatted_price = str_replace('<span class="amount">-', '<span class="amount">', $formatted_price);
			return $formatted_price;
		}

		public function original_invoice_number ($template_type, $order) {
			global $wpo_wcpdf;
			if ($template_type == 'credit-note') {
				?>
				<tr class="invoice-number">
					<th><?php _e( 'Original Invoice Number:', 'wpo_wcpdf_pro' ); ?></th>
					<td><?php $wpo_wcpdf->invoice_number(); ?></td>
				</tr>
				<?php
			}
		}

		public function add_emails ( $email_classes ) {
			// add our custom email classes to the list of email classes that WooCommerce loads
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$email_classes['WC_Email_Customer_Credit_Note'] = include( 'email-customer-credit-note.php' );
			}
			$email_classes['WC_Email_PDF_Order_Notification'] = include( 'email-pdf-order-notification.php' );
			return $email_classes;
		}

		/**
		 * WPML compatibility helper function: set wpml language before pdf creation
		 */
		public function switch_language( $template_type, $order_id ) {
			global $sitepress, $polylang, $locale, $wp_locale, $woocommerce, $wpo_wcpdf, $wpo_wcpdf_pro;

			// WPML specific
			if (class_exists('SitePress')) {
				$order_lang = get_post_meta( $order_id, 'wpml_language', true );
				if ( empty( $order_lang ) && $template_type == 'credit-note' ) {
					if ( $parent_order_id = wp_get_post_parent_id( $order_id ) ) {
						$order_lang = get_post_meta( $parent_order_id, 'wpml_language', true );
					}
				}
				if ( $order_lang == '' ) {
					$order_lang = $sitepress->get_default_language();
				}
				$order_lang = apply_filters( 'wpo_wcpdf_wpml_language', $order_lang, $order_id, $template_type );

				$this->order_lang = $order_lang;

				// filters to ensure correct locale
				add_filter( 'plugin_locale', array( $this, 'set_locale_for_emails' ), 10, 2 );
				add_filter( 'icl_current_string_language', array( $this, 'wpml_admin_string_language' ), 9, 2);

				$this->previous_language = $sitepress->get_current_language();
				$sitepress->switch_lang( $order_lang );
			// Polylang specific
			} elseif (class_exists('Polylang')) {
				if (!function_exists('pll_get_post_language')) {
					return;
				}
				// use parent order id for refunds
				if ( get_post_type( $order_id ) == 'shop_order_refund' && $parent_order_id = wp_get_post_parent_id( $order_id ) ) {
					$order_id = $parent_order_id;
				}
				$order_locale = pll_get_post_language( $order_id, 'locale' );
				$order_lang = pll_get_post_language( $order_id, 'slug' );
				if ( $order_lang == '' ) {
					$order_locale = pll_default_language( 'locale' );
					$order_lang = pll_default_language( 'slug' );
				}
				$this->order_locale = $order_locale;
				$this->order_lang = $order_lang;
				$this->previous_language = pll_current_language( 'locale' );
				unload_textdomain( 'default' );
			}

			// unload text domains
			unload_textdomain( 'woocommerce' );
			unload_textdomain( 'wpo_wcpdf' );
			unload_textdomain( 'wpo_wcpdf_pro' );

			if (class_exists('Polylang')) {
				// set locale to order locale
				$locale = apply_filters( 'locale', $this->order_locale );
				$polylang->curlang->locale = $this->order_locale;

				// load Polylang translated string
				static $cache; // Polylang string translations cache object to avoid loading the same translations object several times
				// Cache object not found. Create one...
				if ( empty( $cache ) ) {
					$cache = new PLL_Cache();
				}

				if (false === $mo = $cache->get( $this->order_locale ) ) {
					$mo = new PLL_MO();
					$mo->import_from_db( $GLOBALS['polylang']->model->get_language( $this->order_locale ) );
					$GLOBALS['l10n']['pll_string'] = &$mo;
					// Add to cache
					$cache->set( $this->order_locale, $mo );
				}
				
				load_default_textdomain( $this->order_locale );
			}

			// reload text domains
			$woocommerce->load_plugin_textdomain();
			$wpo_wcpdf->translations();
			$wpo_wcpdf_pro->translations();
			global $wp_locale;
			$wp_locale = new WP_Locale();


			// filter admin texts to explicitly call icl_t for each admin string
			$this->translate_admin_texts();
		}

		public function set_locale_for_emails( $locale, $domain ) {
			$pdf_text_domains = apply_filters( 'wpo_wcpdf_plugin_text_domains', array(
				'woocommerce',
				'wpo_wcpdf',
				'wpo_wcpdf_pro',
				'woocommerce-payment-discounts',
			) );
			if ( in_array( $domain, $pdf_text_domains ) && !empty( $this->order_lang ) ) {
				global $sitepress;
				$locale = $sitepress->get_locale( $this->order_lang );
			}
			return $locale;
		}

		/**
		 * Filter admin texts for string translations
		 */
		public function translate_admin_texts () {
			add_filter( 'wpo_wcpdf_shop_name', array( $this, 'wpml_shop_name_text' ), 9, 1 );
			add_filter( 'wpo_wcpdf_shop_address', array( $this, 'wpml_shop_address_text' ), 9, 1 );
			add_filter( 'wpo_wcpdf_footer', array( $this, 'wpml_footer_text' ), 9, 1 );
			add_filter( 'wpo_wcpdf_extra_1', array( $this, 'wpml_extra_1_text' ), 9, 1 );
			add_filter( 'wpo_wcpdf_extra_2', array( $this, 'wpml_extra_2_text' ), 9, 1 );
			add_filter( 'wpo_wcpdf_extra_3', array( $this, 'wpml_extra_3_text' ), 9, 1 );
		}

		/**
		 * Get string translations
		 */
		public function wpml_shop_name_text ($shop_name) {
			return $this->get_string_translation( 'shop_name', $shop_name );
		}
		public function wpml_shop_address_text ($shop_address) {
			return $this->get_string_translation( 'shop_address', $shop_address );
		}
		public function wpml_footer_text ($footer) {
			return $this->get_string_translation( 'footer', $footer );
		}
		public function wpml_extra_1_text ($extra_1) {
			return $this->get_string_translation( 'extra_1', $extra_1 );
		}
		public function wpml_extra_2_text ($extra_2) {
			return $this->get_string_translation( 'extra_2', $extra_2 );
		}
		public function wpml_extra_3_text ($extra_3) {
			return $this->get_string_translation( 'extra_3', $extra_3 );
		}

		/**
		 * Get string translation for string name, using $woocommerce_wpml helper function
		 */
		public function get_string_translation ($string_name, $default) {
			global $wpo_wcpdf, $woocommerce_wpml, $sitepress;
			// check internal settings first
			$translations = get_option( 'wpo_wcpdf_translations' );
			$internal_string = 'wpo_wcpdf_template_settings['.$string_name.']';

			if ( !empty($translations[$internal_string][$this->order_lang]) ) {
				return wpautop( wptexturize( $translations[$internal_string][$this->order_lang] ) );
			}

			// fall back to string translations
			if (class_exists('SitePress')) {
				$full_string_name = '[wpo_wcpdf_template_settings]'.$string_name;
				if ( isset($woocommerce_wpml->emails) && method_exists( $woocommerce_wpml->emails, 'wcml_get_email_string_info' ) ) {
					$string_data = $woocommerce_wpml->emails->wcml_get_email_string_info( $full_string_name );
					if($string_data) {
						$string = icl_t($string_data[0]->context, $full_string_name ,$string_data[0]->value);
						return wpautop( wptexturize( $string ) );
					}
				}
			} elseif (class_exists('Polylang') && function_exists('pll_translate_string')) {
				// we don't rely on $default, it has been filtered throught wpautop &
				// wptexturize when the apply_filter function was invoked
				$string = pll_translate_string( $wpo_wcpdf->settings->template_settings[$string_name], $this->order_locale );
				return wpautop( wptexturize( $string ) );
			}

			// no translations found
			return $default;
		}

		public function wpml_admin_string_language ( $current_language, $name ) {
			if ( !empty( $this->order_lang ) ) {
				return $this->order_lang;
			} else {
				return $current_language;
			}
		}

		/**
		 * WPML compatibility helper function: set wpml language to default after PDF creation
		 */
		public function reset_language() {
			global $sitepress;
			// WPML specific
			if (class_exists('SitePress')) {
				remove_filter( 'icl_current_string_language', array( $this, 'wpml_admin_string_language' ) );

				$sitepress->switch_lang( $this->previous_language );
			}
			// Polylang?
		}

		/**
		 * WPML compatibility helper function: Add 'lang' parameter to urls of admin actions
		 */
		public function lang_url ( $actions, $order ) {
			global $sitepress;
			$order_id = is_object($order) ? $order->id : $order;

			// WPML specific
			if (class_exists('SitePress')) {
				$order_lang = get_post_meta( $order_id, 'wpml_language', true );
				if ( $order_lang == '' ) {
					$order_lang = $sitepress->get_default_language();
				}
			// Polylang specific
			} elseif (class_exists('Polylang')) {
				$order_lang = pll_get_post_language( $order_id, 'slug' );
				if ( $order_lang == '' ) {
					$order_lang = pll_default_language( 'slug' );
				}
			}

			foreach ( $actions as $template_type => &$action ) {
				if ( isset( $action['url'] ) ) {
					$order_lang = apply_filters( 'wpo_wcpdf_wpml_language', $order_lang, $order_id, $template_type );
					$action['url'] = add_query_arg( 'lang', $order_lang, $action['url'] );
				}
			}

			return $actions;
		}

		public function lang_url_box ( $meta_actions ) {
			global $post_id;
			return $this->lang_url( $meta_actions, $post_id );
		}

	} // end class
} // end class_exists