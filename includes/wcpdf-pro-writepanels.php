<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WooCommerce_PDF_IPS_Pro_Writepanels' ) ) {

	class WooCommerce_PDF_IPS_Pro_Writepanels {
		public function __construct() {
			add_action( 'admin_notices', array( $this, 'free_version_check' ) );
			$this->pro_settings = get_option( 'wpo_wcpdf_pro_settings' );
			add_filter( 'wpo_wcpdf_meta_box_actions', array( $this, 'meta_box_actions' ) );			
			add_filter( 'wpo_wcpdf_bulk_actions', array( $this, 'bulk_actions' ) );			
			add_filter( 'wpo_wcpdf_listing_actions', array( $this, 'listing_actions' ), 10, 2 );			

			if ( class_exists( 'WooCommerce_PDF_Invoices' ) && version_compare( WooCommerce_PDF_Invoices::$version, '1.5.23', '>=' ) ) {
				add_action( 'wcpdf_invoice_number_column_end', array( $this, 'credit_note_number_column_data' ), 10, 1 );
			} else {
				add_action( 'manage_shop_order_posts_custom_column', array( $this, 'credit_note_number_column' ), 20, 2 );
			}
			add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'pro_email_order_actions' ), 90, 1 );
			add_filter( 'wpo_wcpdf_myaccount_actions', array( $this, 'my_account' ), 10, 2 );
			add_action( 'wpo_wcpdf_meta_box_end', array( $this, 'edit_numbers_dates' ), 10, 1 );
			add_action( 'save_post', array( $this,'save_numbers_dates' ) );
		}

		/**
		 * Check if free version is installed
		 */
		public function free_version_check () {
			if ( !class_exists( 'WooCommerce_PDF_Invoices' ) ) {
				?>
				<div class="error">
					<p>
					<?php printf( __( 'WooCommerce PDF Invoices & Packing Slips Professional requires the %sbase plugin (free)%s to be installed & activated!' , 'wpo_wcpdf_pro' ), '<a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">', '</a>' ); ?> <br />
					</p>
				</div>
				<?php
			}
		}

		/**
		 * Add pro buttons on PDF meta box
		 */
		public function meta_box_actions( $meta_actions ) {
			global $post_id;

			$pro_meta_actions = array();
			if ( isset( $this->pro_settings['enable_proforma'] ) ) {
				$pro_meta_actions['proforma'] = array (
					'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=proforma&order_ids=' . $post_id ), 'generate_wpo_wcpdf' ),
					'alt'		=> esc_attr__( 'PDF Proforma', 'wpo_wcpdf_pro' ),
					'title'		=> __( 'PDF Proforma', 'wpo_wcpdf_pro' ),
				);
			}

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$order = new WC_Order( $post_id);
				$refunds = $order->get_refunds();
				unset($order);

				if ( !empty( $refunds ) ) {
					$pro_meta_actions['credit-note'] = array (
						'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=credit-note&order_ids=' . $post_id ), 'generate_wpo_wcpdf' ),
						'alt'		=> esc_attr__( 'PDF Credit Note', 'wpo_wcpdf_pro' ),
						'title'		=> __( 'PDF Credit Note', 'wpo_wcpdf_pro' ),
					);				
				}
			}

			$meta_actions = array_merge( $meta_actions, $pro_meta_actions );

			return $meta_actions;
		}

		/**
		 * Add pro bulk actions
		 */
		public function bulk_actions( $bulk_actions ) {
			$pro_bulk_actions = array();

			if ( isset( $this->pro_settings['enable_proforma'] ) ) {
				$pro_bulk_actions['proforma'] = __( 'PDF Proformas', 'wpo_wcpdf_pro' );
			}

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$pro_bulk_actions['credit-note'] = __( 'PDF Credit Notes', 'wpo_wcpdf_pro' );
			}

			$bulk_actions = array_merge( $bulk_actions, $pro_bulk_actions );

			return $bulk_actions;
		}

		/**
		 * Add pro listing actions
		 */
		public function listing_actions( $listing_actions, $order) {
			$pro_listing_actions = array();

			if ( isset( $this->pro_settings['enable_proforma'] ) ) {
				$pro_listing_actions['proforma'] = array(
					'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=proforma&order_ids=' . $order->id ), 'generate_wpo_wcpdf' ),
					'img'		=> plugins_url( 'images/proforma.png' , dirname(__FILE__) ),
					'alt'		=> __( 'PDF Proforma', 'wpo_wcpdf_pro' ),
				);
			}

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$refunds = $order->get_refunds();
				if ( !empty( $refunds ) ) {
					$pro_listing_actions['credit-note'] = array(
						'url'		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=credit-note&order_ids=' . $order->id ), 'generate_wpo_wcpdf' ),
						'img'		=> plugins_url( 'images/credit-note.png' , dirname(__FILE__) ),
						'alt'		=> __( 'PDF Credit Note', 'wpo_wcpdf_pro' ),
					);
				}
			}

			$listing_actions = array_merge( $pro_listing_actions, $listing_actions );

			return $listing_actions;
		}

		/**
		 * (deprecated since 1.5.22) 
		 * @param  string $column column slug
		 */
		public function credit_note_number_column( $column ) {
			global $post, $the_order;

			if ( $column == 'pdf_invoice_number' ) {
				if ( empty( $the_order ) || $the_order->id != $post->ID ) {
					$the_order = new WC_Order( $post->ID );
				}

				if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
					$this->credit_note_number_column_data( $the_order );
				}
			}
		}

		/**
		 * Display Credit Note Number in Shop Order column (if available)
		 * @param  string $column column slug
		 */
		public function credit_note_number_column_data( $order ) {
			global $wpo_wcpdf_pro;
			$refunds = $order->get_refunds();
			foreach ($refunds as $key => $refund) {
				if ($credit_note_number = $wpo_wcpdf_pro->get_number('credit-note', $refund->id)) {
					$credit_note_numbers[] = $credit_note_number;
				}
			}

			if ( isset($credit_note_numbers) ) {
				?>
				<br/><?php _e( 'Credit Note', 'wpo_wcpdf_pro' ); ?>:<br/>
				<?php
				echo implode(', ', $credit_note_numbers);
			}
		}

		public function edit_numbers_dates ( $order_id ) {
			global $wpo_wcpdf_pro;
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$order = wc_get_order( $order_id );
				$refunds = $order->get_refunds();
				if ( !empty( $refunds ) ) {
					foreach ($refunds as $key => $refund) {
						if ( $credit_note_number = get_post_meta( $refund->id, '_wcpdf_credit_note_number', true ) ) {
							$credit_note_date = get_post_meta( $refund->id, '_wcpdf_credit_note_date', true );
							?>
							<h4><?php _e( 'Credit Note', 'wpo_wcpdf_pro' ) ?></h4>
							<p class="form-field _wcpdf_credit_note_number_field ">
								<label for="_wcpdf_credit_note_number"><?php _e( 'Credit Note Number (unformatted!)', 'wpo_wcpdf_pro' ); ?>:</label>
								<input type="text" class="short" style="" name="_wcpdf_credit_note_number[<?php echo $refund->id; ?>]" id="_wcpdf_credit_note_number" value="<?php echo $credit_note_number; ?>">
							</p>
							<p class="form-field form-field-wide">
								<label for="wcpdf_credit_note_date"><?php _e( 'Credit Note Date:', 'wpo_wcpdf_pro' ); ?></label>
								<input type="text" class="date-picker-field" name="_wcpdf_credit_note_date[<?php echo $refund->id; ?>]" id="wcpdf_credit_note_date" maxlength="10" value="<?php echo date_i18n( 'Y-m-d', strtotime( $credit_note_date ) ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />@<input type="text" class="hour" placeholder="<?php _e( 'h', 'woocommerce' ) ?>" name="_wcpdf_credit_note_date_hour[<?php echo $refund->id; ?>]" id="wcpdf_credit_note_date_hour" maxlength="2" size="2" value="<?php echo date_i18n( 'H', strtotime( $credit_note_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />:<input type="text" class="minute" placeholder="<?php _e( 'm', 'woocommerce' ) ?>" name="_wcpdf_credit_note_date_minute[<?php echo $refund->id; ?>]" id="wcpdf_credit_note_date_minute" maxlength="2" size="2" value="<?php echo date_i18n( 'i', strtotime( $credit_note_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />
							</p>
							<?php
						}
					}
				}
			}

			if ( $proforma_number = get_post_meta( $order_id, '_wcpdf_proforma_number', true ) ) {
				$proforma_date = get_post_meta( $order_id, '_wcpdf_proforma_date', true );
				?>
				<h4><?php _e( 'Proforma Invoice', 'wpo_wcpdf_pro' ) ?></h4>
				<p class="form-field _wcpdf_proforma_number_field ">
					<label for="_wcpdf_proforma_number"><?php _e( 'Proforma Invoice Number (unformatted!)', 'wpo_wcpdf_pro' ); ?>:</label>
					<input type="text" class="short" style="" name="_wcpdf_proforma_number" id="_wcpdf_proforma_number" value="<?php echo $proforma_number; ?>">
				</p>
				<p class="form-field form-field-wide">
					<label for="wcpdf_proforma_date"><?php _e( 'Proforma Invoice Date:', 'wpo_wcpdf_pro' ); ?></label>
					<input type="text" class="date-picker-field" name="_wcpdf_proforma_date" id="wcpdf_proforma_date" maxlength="10" value="<?php echo date_i18n( 'Y-m-d', strtotime( $proforma_date ) ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />@<input type="text" class="hour" placeholder="<?php _e( 'h', 'woocommerce' ) ?>" name="_wcpdf_proforma_date_hour" id="wcpdf_proforma_date_hour" maxlength="2" size="2" value="<?php echo date_i18n( 'H', strtotime( $proforma_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />:<input type="text" class="minute" placeholder="<?php _e( 'm', 'woocommerce' ) ?>" name="_wcpdf_proforma_date_minute" id="wcpdf_proforma_date_minute" maxlength="2" size="2" value="<?php echo date_i18n( 'i', strtotime( $proforma_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />
				</p>
				<?php
			}
		}

		/**
		 * Process numbers & dates from order edit screen
		 */
		public function save_numbers_dates ( $post_id ) {
			global $post_type;
			if( $post_type == 'shop_order' ) {
				// proforma
				if ( isset($_POST['_wcpdf_proforma_number']) ) {
					update_post_meta( $post_id, '_wcpdf_proforma_number', stripslashes( $_POST['_wcpdf_proforma_number'] ));
				}

				if ( isset($_POST['_wcpdf_proforma_date']) ) {
					if ( empty($_POST['_wcpdf_proforma_date']) ) {
						delete_post_meta( $post_id, '_wcpdf_proforma_date' );
					} else {
						$proforma_date = strtotime( $_POST['_wcpdf_proforma_date'] . ' ' . (int) $_POST['_wcpdf_proforma_date_hour'] . ':' . (int) $_POST['_wcpdf_proforma_date_minute'] . ':00' );
						$proforma_date = date_i18n( 'Y-m-d H:i:s', $proforma_date );
						update_post_meta( $post_id, '_wcpdf_proforma_date', $proforma_date );						
					}
				}

				// credit note
				if ( isset($_POST['_wcpdf_credit_note_number']) ) {
					foreach ($_POST['_wcpdf_credit_note_number'] as $post_id => $number) {
						update_post_meta( $post_id, '_wcpdf_credit_note_number', stripslashes( $number ) );
					}
				}

				if ( isset($_POST['_wcpdf_credit_note_date']) ) {
					foreach ($_POST['_wcpdf_credit_note_date'] as $post_id => $date) {
						if ( empty($_POST['_wcpdf_credit_note_date'][$post_id]) ) {
							delete_post_meta( $post_id, '_wcpdf_credit_note_date' );
						} else {
							$credit_note_date = strtotime( $_POST['_wcpdf_credit_note_date'][$post_id] . ' ' . (int) $_POST['_wcpdf_credit_note_date_hour'][$post_id] . ':' . (int) $_POST['_wcpdf_credit_note_date_minute'][$post_id] . ':00' );
							$credit_note_date = date_i18n( 'Y-m-d H:i:s', $credit_note_date );
							update_post_meta( $post_id, '_wcpdf_credit_note_date', $credit_note_date );						
						}
					}
				}
			}
		}

		/**
		 * Display download buttons (Proforma & Credit Note) on My Account page
		 */
		public function my_account( $actions, $order ) {
			// show proforma button if no invoice available
			if ( !isset( $actions['invoice'] ) && isset( $this->pro_settings['enable_proforma'] ) ) {
				$actions['proforma'] = array(
					'url'  => wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=proforma&order_ids=' . $order->id . '&my-account' ), 'generate_wpo_wcpdf' ),
					'name' => apply_filters( 'wpo_wcpdf_myaccount_proforma_button', __( 'Download Proforma Invoice (PDF)', 'wpo_wcpdf_pro' ) )
				);
			}

			// show credit note button when credit note is available
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2.7', '>=' ) ) {
				$refunds = $order->get_refunds();
				// if there's at least one credit note, we'll take them all...
				if ( !empty( $refunds ) && get_post_meta( $refunds[0]->id, '_wcpdf_credit_note_number', true) ) {
					$actions['credit-note'] = array(
						'url'  => wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=credit-note&order_ids=' . $order->id . '&my-account' ), 'generate_wpo_wcpdf' ),
						'name' => apply_filters( 'wpo_wcpdf_myaccount_credit_note_button', __( 'Download Credit Note (PDF)', 'wpo_wcpdf_pro' ) )
					);				
				}
			}

			return $actions;
		}
		/**
		 * Add credit note email to order actions list
		 */
		public function pro_email_order_actions ( $available_emails ) {
			global $post_id;

			$order_notification_settings = get_option( 'woocommerce_pdf_order_notification_settings' );
			if ( isset($order_notification_settings['recipient']) && !empty($order_notification_settings['recipient']) ) {
				// only add order notification action when a recipient is set!
				$available_emails[] = 'pdf_order_notification';
			}

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$order = new WC_Order( $post_id );
				$refunds = $order->get_refunds();
				if ( !empty( $refunds ) ) {
					$available_emails[] = 'customer_credit_note';
				}
			}

			return $available_emails;
		}
	} // end class
} // end class_exists