<?php

if ( ! class_exists( 'WC_Teraco_User' ) ) {


	class WC_Teraco_User {

		private static $TERACO_TRANSACTION_STATUS_USER_VIEW = null;

		public static function init() {
			if ( self::$TERACO_TRANSACTION_STATUS_USER_VIEW == null ) {
				self::$TERACO_TRANSACTION_STATUS_USER_VIEW = array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING            => __( '[Pending]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED           => '',
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_REFUNDED           => __( '[Refunded]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_VOIDED             => __( '[Cancelled]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_VOID    => __( '[Failed at Cancelling]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE => __( '[Failed at Collecting]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED_TO_REFUND => __( '[Failed at Refunding]', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
				);
			}

			add_filter( 'woocommerce_get_formatted_order_total', 'WC_Teraco_User::get_formatted_partial_payments_and_refunds', 10, 2 );
			add_filter( 'woocommerce_get_order_item_totals', 'WC_Teraco_User::get_partial_payments_and_refunds_total', 10, 3 );
			add_action( 'woocommerce_payment_complete', 'WC_Teraco_User::payment_complete_add_last_payment_details_and_capture_all_pending', 10, 1 );
			add_filter( 'woocommerce_pay_order_button_html', 'WC_Teraco_User::pay_order_button_html_add_cancel', 10, 1 );
			add_action( 'woocommerce_order_status_cancelled', 'WC_Teraco_User::order_status_cancelled_rollback', 10, 1 );
			add_filter( 'woocommerce_order_needs_payment', 'WC_Teraco_User::order_needs_payment_not_if_already_pending', 10, 3 );

		}

		public static function get_formatted_partial_payments_and_refunds( $formatted_total, $order ) {
			if ( isset( $order ) ) {
				$formatted_total = wc_price( WC_Teraco_Metadata::get_original_total( $order ) );
			}
			return $formatted_total;
		}

		private static function get_transaction_object( $order_transaction_object ) {
			$payment_amount        = $order_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_VALUE ];
			$payment_status_string = self::$TERACO_TRANSACTION_STATUS_USER_VIEW[ $order_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] ];

			$payment_method = $order_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ];

			if ( WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME === $payment_method ) {
				$payment_method = 'Gift Card'; //remove Teraco name from customer-facing interface
			}

			$notes = $order_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_NOTE ] ?? array();
			$notes_string = implode( '<br>*', $notes);

			$transaction_type_string = ( $order_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_TYPE ] === WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT )
				? __( 'Payment', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE )
				: __( 'Refund', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE );
			$line_item_label         = sprintf( "%s %s %s<br> <small>%s </small>",
				$payment_status_string,
				$payment_method,
				$transaction_type_string,
				$notes_string );

			return array(
				'label' => $line_item_label,
				'value' => wc_price( 0 - $payment_amount ),
			);
		}

		/**
		 * Adjust the user-facing order totals summary view to add the record of gift code and other payments
		 * as well as refunds and balance.
		 */
		public static function get_partial_payments_and_refunds_total( $total_rows, $order, $tax_display ) {
			if ( isset( $order ) ) {
				$order_payments_array = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
					array( WC_Teraco_Metadata_Constants::TRANSACTION_TYPE => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT ) );
				$order_refunds_array  = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
					array( WC_Teraco_Metadata_Constants::TRANSACTION_TYPE => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_REFUND ) );

				$row_counter = 0;
				foreach ( $order_payments_array as $order_transaction_object ) { //first all the payments
					$total_rows[ 'partial_payment_' . $row_counter ++ ] = self::get_transaction_object( $order_transaction_object );
				}
				if ( count( $order_payments_array ) != 0 ) {
					$total_rows['total_paid'] = array(
						'label' => __( 'Payments Total:', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
						'value' => wc_price( 0 - WC_Teraco_Metadata::get_payments_total( $order ) ),
					);
				}
				$row_counter = 0; //reset to start counting refund rows now.
				foreach ( $order_refunds_array as $order_transaction_object ) { //now all the refunds
					$total_rows[ 'partial_refund_' . $row_counter ++ ] = self::get_transaction_object( $order_transaction_object );
				}
				if ( count( $order_refunds_array ) != 0 ) {
					$total_rows['total_refunded'] = array(
						'label' => __( 'Refunds Total:', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
						'value' => wc_price( 0 - WC_Teraco_Metadata::get_refunds_total( $order ) ),
					);
					//if we are handling refunds, remove the less informative built-in refund rows.
					foreach ( array_keys( $total_rows ) as $total_row_key ) {
						if ( substr( $total_row_key, 0, 7 ) === 'refund_' ) {
							unset( $total_rows[ $total_row_key ] );
						}
					}
				}
				if ( count( $order_payments_array ) != 0 || count( $order_refunds_array ) != 0 ) {
					$total_rows['balance'] = array(
						'label' => __( 'Balance:', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
						'value' => '<strong>' . wc_price( WC_Teraco_Metadata::get_balance( $order ) ) . '</strong>',
					);
				}
			}

			return $total_rows;
		}


		/**
		 * When the last payment is made using a payment method other than teraco, add this last payment to the metadata
		 * and adjust the payment method of the whole order to be teraco so that we receive and handle its refund requests if it happens.
		 */
		public static function payment_complete_add_last_payment_details_and_capture_all_pending( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( isset ( $order ) ) {
				if ( $order->get_payment_method() !== WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ) {
					//first we post the last transaction by the third-party gateway
					$transaction_amount = $order->get_total(); //because our payment gateway sets the order->total to be the remaining balance and this was the last payment.
					WC_Teraco_Transactions::post_third_party_captured_transaction( $order, $order->get_payment_method(), $transaction_amount, $order->get_transaction_id() );

					$order->set_total( WC_Teraco_Metadata::get_original_total( $order ) );
					$order->save();
					//now we have to capture all teraco pending transactions on the order
					try {
						WC_Teraco_Transactions::capture_all_pending_transactions( $order );
					} catch ( Throwable $exception ) {
						wc_add_notice( __( 'Unable to finalize this order due to an error. Please contact the store.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
						write_log( $exception->getMessage() );
						$order->add_order_note( __( 'Unable to finalize this order due to an error.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
						$order->set_status( 'failed', $note = 'Failed to capture some gift code payments.' );
						$order->save();

						return;
					}
				}
			}

		}

		public static function pay_order_button_html_add_cancel( $buttons_html_code ) {
			$order_id         = get_query_var( 'order-pay', 0 );
			$order            = wc_get_order( $order_id );
			$cancellation_uri = $order->get_cancel_order_url_raw();


			 $cancel_button_element             = '<a class="button alt" name="teraco_woocommerce_order_cancelled_btn" href="' . $cancellation_uri . '" id="teraco_woocommerce_checkout_cancel_order_btn">Cancel Order</a>';
		

				$cancel_button_adjust_style_script = '<script>
														adjustStyle();
														function adjustStyle() {
											                var source_elem = document.getElementById("place_order");
											                var target_elem = document.getElementById("teraco_woocommerce_checkout_cancel_order_btn");
											                cssObj = window.getComputedStyle(source_elem, null);
											                for (i = 0; i < cssObj.length; i++) { 
											                    css_obj_property_name = cssObj.item(i);
											                    css_obj_property_value = cssObj.getPropertyValue(css_obj_property_name);
											                    if (css_obj_property_name.includes("float"))
																	continue;
											                    target_elem.style[css_obj_property_name] = css_obj_property_value;
											                }
														}
												   </script>';

			$buttons_html_code = $cancel_button_element . $buttons_html_code . $cancel_button_adjust_style_script;

			return $buttons_html_code;
		}

		public static function order_status_cancelled_rollback( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( isset( $order ) ) {
				try {
					WC_Teraco_Metadata::set_original_status( $order, 'cancelled' );
					WC_Teraco_Transactions::cancel_all_pending_transactions( $order );
				} catch ( Throwable $exception ) {
					$order->add_order_note( __( 'Failed at cancelling some pending gift code payments. Please contact the store.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
					$order->save();
					write_log( 'Failed at voiding pending transactions: ' . $exception->getMessage() );
					wc_add_notice( __( 'Failed at cancelling some pending gift code payments. Please contact the store.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), 'error' );

					return;
				}
			} else {
				write_log( 'Error in handling order cancellation. Null order received by teraco_woocommerce_order_status_cancelled_rollback' );
			}
		}

		public static function order_needs_payment_not_if_already_pending( $needs_payment_already, $order, $valid_order_statuses_for_payment ) {
			$teraco_pending_paid = ( $order->get_status() === 'failed' ) && count( WC_Teraco_Metadata::get_order_transactions( $order ) ) > 0;

			return $needs_payment_already && ! $teraco_pending_paid;
		}

	}
}
