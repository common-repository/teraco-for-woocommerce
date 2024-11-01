<?php
if ( ! class_exists( 'WC_Teraco_Transactions' ) ) {
	class WC_Teraco_Transactions {
		public static function get_teraco_api_key() {
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			if ( ! isset( $available_gateways [ WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ] ) ) {
				write_log( 'Error while looking for Teraco API key: Teraco payment gateway cannot be found.' );
				throw new Exception( __( 'Cannot find Teraco gateway.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}

			return $available_gateways [ WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ]->get_option( 'api_key' );
		}


		// public static function get_teraco_campaign_key() {
		// 	$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		// 	if ( ! isset( $available_gateways [ WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ] ) ) {
		// 		write_log( 'Error while looking for Teraco API key: Teraco payment gateway cannot be found.' );
		// 		throw new Exception( __( 'Cannot find Teraco gateway.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
		// 	}

		// 	return $available_gateways [ WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ]->get_option( 'campaign_key' );
		// }


		private static function get_teraco_transaction_metadata( $order, $currency ) {
		
			$order_items    = $order->get_items();
			$items_metadata = array();
			$counter        = 0;
			foreach ( $order_items as $item_key => $order_item ) {
				$item_product       = $order_item->get_product();
				$item_metadata_tags = array_map( "get_the_category_by_ID", $item_product->get_category_ids() );
				foreach ( $item_product->get_tag_ids() as $tag_id ) {
					array_push( $item_metadata_tags, get_tag( $tag_id )->name );
				}


				$item_metadata                 = array(
					'id'         => '' . $order_item->get_product_id(),
					'quantity'   => $order_item->get_quantity(),
					'unit_price' => WC_Teraco_Currency::teraco_currency_major_to_minor( $item_product->get_price(), $currency ),
					'tags'       => $item_metadata_tags
				);
				$items_metadata[ $counter ++ ] = $item_metadata;
			}

			$metadata = array(
					'email'  => (  WC()->version < '2.7.0' ) ? $order->billing_email : $order->get_billing_email(),   
				 'phone'  => (  WC()->version < '2.7.0' ) ? $order->billing_phone : $order->get_billing_phone(),   
				      
				'giftbit-note'        => array(
					'note' => sprintf( 'WooCommerce Order %s', $order->get_id() )
				),
				'cart'                => array(
					'total' => WC_Teraco_Currency::teraco_currency_major_to_minor( $order->get_total(), $currency ),
					'items' => $items_metadata
				),
				'_split-tender-total' => WC_Teraco_Currency::teraco_currency_major_to_minor( $order->get_total(), $currency )
			);


			return $metadata;
		}

		private static function link_cart_on_webapp( $display_text, $card_id ) {
			$link = sprintf( WC_Teraco_API_Configs::WEB_APP_CARD_DETAILS_URL, $card_id );
			return sprintf( '<a href="%s">%s</a>', $link, $display_text );
		}

		public static function get_gift_code_balance( $code, $order, $up_to_this_amount, $order_currency , $pin) {

			if ( '' === $code ) {
				throw new Exception( __( 'No gift code was provided.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}

			$teraco_api_key = self::get_teraco_api_key();
			try {
				$dryrun_result_object   = WC_TeracoEngine::post_dryrun_transaction(
					$code,
					( 0 - WC_Teraco_Currency::teraco_currency_major_to_minor( $up_to_this_amount, $order_currency ) ),
					$order_currency,
					uniqid( 'woo_' ),
					$teraco_api_key,
					self::get_teraco_transaction_metadata( $order, $order_currency ),
					$pin

				);

				$code_available_balance = 0 - $dryrun_result_object[ WC_Teraco_API_Constants::TRANSACTION_VALUE ];
        
			} catch ( Throwable $exception ) {
				throw new Exception( __( $exception, WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}
			
			$code_available_balance = WC_Teraco_Currency::teraco_currency_minor_to_major( $code_available_balance, $order_currency );

			if ( 0 == $code_available_balance ) {
				throw new Exception( __( 'The gift code does not have any value available.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}
			return $code_available_balance;
		}

		public static function get_giftcard_details( $code ) {
			$returned_value_stores        = array();
			$teraco_api_key            = self::get_teraco_api_key();
			$card_details_response_object = WC_TeracoEngine::get_giftcard_details( $code, $teraco_api_key );
			$value_stores                 = $card_details_response_object[ WC_Teraco_API_Constants::CARD_DETAILS_VALUE_STORES ];
			foreach ( $value_stores as $value_store ) {
				$returned_value_stores[ $value_store[ WC_Teraco_API_Constants::VALUE_STORE_ID ] ] = $value_store;
			}

			return $returned_value_stores;
		}

		public static function post_pending_payment_transaction( $order, $code, $amount, $currency, $pin ) {
			$teraco_api_key         = self::get_teraco_api_key();
			$transaction_result_object = WC_TeracoEngine::post_pending_transaction(
				$code,
				( 0 - WC_Teraco_Currency::teraco_currency_major_to_minor( $amount, $currency ) ),
				$currency,
				uniqid( 'woo_' ),
				$teraco_api_key,
				self::get_teraco_transaction_metadata( $order, $currency ),
				$pin
			);
     
			$transaction_id            = $transaction_result_object[ WC_Teraco_API_Constants::TRANSACTION_ID ];
			$card_id                   = $transaction_result_object[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ];
			$amount_paid = 0 - WC_Teraco_Currency::teraco_currency_minor_to_major( $transaction_result_object[ WC_Teraco_API_Constants::TRANSACTION_VALUE ], $currency );
			 
			$code_rep    = '****' . substr( $code, - 4 );
			
			$notes       = array();
			array_push( $notes, 'Gift Code ' . $code_rep );
 
			
			//create a pending payment record object and add it to the order
			$payment_transaction_object = array(
				WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD => WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME,
				WC_Teraco_Metadata_Constants::TRANSACTION_VALUE          => $amount_paid,
				WC_Teraco_Metadata_Constants::TRANSACTION_TYPE           => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				WC_Teraco_Metadata_Constants::TRANSACTION_NOTE           => $notes,
				WC_Teraco_Metadata_Constants::TRANSACTION_ID             => $transaction_id,
				WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT     => $transaction_result_object,
				WC_Teraco_Metadata_Constants::TRANSACTION_STATUS         => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING
			);

    
			$order->add_order_note( sprintf( __( 'Pending charge of %s on gift code %s - Transaction ID: %s',
				WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
				wc_price( $amount_paid ),
				$code_rep,
				self::link_cart_on_webapp( $transaction_id, $card_id ) ) );
			WC_Teraco_Metadata::add_order_transaction( $order, $payment_transaction_object );
			$order->save();
			return $transaction_result_object;
		}

		public static function capture_pending_payment_transaction( $order, $pending_transaction_object ) {
			$teraco_api_key           = self::get_teraco_api_key();
			$original_transaction_object = $pending_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT ];
			$capture_result              = WC_TeracoEngine::capture_pending_transaction( $original_transaction_object, uniqid( 'woo_' ), $teraco_api_key );


			$card_id = $capture_result[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ];

			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_ORIGINAL_TRANSACTION ] = $original_transaction_object;
			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT ]           = $capture_result;
			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ]               = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED;
			WC_Teraco_Metadata::add_order_transaction( $order, $pending_transaction_object );
			$order->add_order_note( sprintf( __( 'Finalized payment of %s on gift code ****%s - Transaction ID: %s - Original Pending Transaction ID: %s', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					wc_price( $pending_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_VALUE ] ),
					$original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_CODE_LAST_FOUR ],
					self::link_cart_on_webapp( $capture_result[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id ),
					self::link_cart_on_webapp( $original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id ) )
			);

			return $capture_result;
		}

		public static function cancel_pending_payment_transaction( $order, $pending_transaction_object ) {
			$teraco_api_key = self::get_teraco_api_key();

			$original_transaction_object = $pending_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT ];
			$void_result                 = WC_TeracoEngine::cancel_pending_transaction( $original_transaction_object, uniqid( 'woo_' ), $teraco_api_key );
			$card_id                     = $void_result[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ];

			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_ORIGINAL_TRANSACTION ] = $original_transaction_object;
			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT ]           = $void_result;
			$pending_transaction_object [ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ]               = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_VOIDED;
			
			WC_Teraco_Metadata::add_order_transaction( $order, $pending_transaction_object );
			$order->add_order_note( sprintf( __( 'Cancelled payment of %s on gift code ****%s - Transaction ID: %s - Original Pending Transaction ID: %s', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					wc_price( $pending_transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_VALUE ] ),
					$original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_CODE_LAST_FOUR ],
					self::link_cart_on_webapp( $void_result[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id ),
					self::link_cart_on_webapp( $original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id )
				)
			);
			return $void_result;
		}

		public static function refund_captured_transaction( $order, $transaction_object ) {
			$teraco_api_key = self::get_teraco_api_key();
			//$teraco_campaign_key = self::get_teraco_campaign_key();

			$original_transaction_object = $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT ];


			$refund_result_object        = WC_TeracoEngine::refund_transaction( $original_transaction_object, uniqid( 'woo_' ), $teraco_api_key ); //,$teraco_campaign_key
			$refund_value                = WC_Teraco_Currency::teraco_currency_minor_to_major( $refund_result_object[ WC_Teraco_API_Constants::TRANSACTION_VALUE ], get_option( 'woocommerce_currency' ) ); //todo: fix this. currency should come from the transaction object
			$card_id                     = $refund_result_object[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ];
			$refund_object               = array(
				WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD          => WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME,
				WC_Teraco_Metadata_Constants::TRANSACTION_VALUE                   => 0 - $refund_value,
				WC_Teraco_Metadata_Constants::TRANSACTION_TYPE                    => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_REFUND,
				WC_Teraco_Metadata_Constants::TRANSACTION_NOTE                    => array( sprintf( __( 'Gift Code ****%s', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), $refund_result_object[ WC_Teraco_API_Constants::TRANSACTION_CODE_LAST_FOUR ] ) ),
				WC_Teraco_Metadata_Constants::TRANSACTION_ID                      => $refund_result_object[ WC_Teraco_API_Constants::TRANSACTION_ID ],
				WC_Teraco_Metadata_Constants::TRANSACTION_ORIGINAL_TRANSACTION_ID => $original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_ID ],
				WC_Teraco_Metadata_Constants::TRANSACTION_RAW_OBJECT              => $refund_result_object,
				WC_Teraco_Metadata_Constants::TRANSACTION_STATUS                  => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED,
			);
			WC_Teraco_Metadata::add_order_transaction( $order, $refund_object );
			$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_REFUNDED;
			WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );

			

			$order->add_order_note( sprintf( __( 'Refunded %s to gift code ****%s - Refund Transaction ID: %s - Original Transaction ID: %s', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					wc_price( $refund_value ),
					$original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_CODE_LAST_FOUR ],
					self::link_cart_on_webapp( $refund_result_object[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id ),
					self::link_cart_on_webapp( $original_transaction_object[ WC_Teraco_API_Constants::TRANSACTION_ID ], $card_id )
				)
			);

			return $refund_result_object;
		}

		public static function refund_third_party_transaction( $order, $transaction_object ) {
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$payment_gateway    = $available_gateways [ $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ] ];
			if ( isset( $payment_gateway ) ) //todo: test whether the gateway supports refund
			{
				write_log( sprintf( 'attempting a third-party gateway refund via %s', $payment_gateway->get_method_title() ) );
				$refund_value = $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_VALUE ];
				$result       = $payment_gateway->process_refund( $order->get_id(), $refund_value, __( 'Full refund requested via Teraco.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
				if ( ! is_wp_error( $result ) ) {
					write_log( sprintf( 'Refund via %s result: %s', $payment_gateway->get_method_title(), $result ) );
					$refund_object = array(
						WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD          => $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ],
						WC_Teraco_Metadata_Constants::TRANSACTION_ID                      => 'refund_of_' . $order->get_transaction_id(),
						WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD          => $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ],
						WC_Teraco_Metadata_Constants::TRANSACTION_VALUE                   => 0 - $refund_value,
						WC_Teraco_Metadata_Constants::TRANSACTION_TYPE                    => 
						WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_REFUND,
						WC_Teraco_Metadata_Constants::TRANSACTION_ORIGINAL_TRANSACTION_ID => $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_ID ],
						WC_Teraco_Metadata_Constants::TRANSACTION_NOTE                    => array( sprintf( __( 'Refund for %s ', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), $order->get_transaction_id() ) ),
						WC_Teraco_Metadata_Constants::TRANSACTION_STATUS                  => 
						WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED,
					);
					WC_Teraco_Metadata::add_order_transaction( $order, $refund_object );
					$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_REFUNDED;
					WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );


				} else {
					write_log( implode( " ", array(
						'WP_Error codes:',
						implode( ' ', $result->get_error_codes() ),
						'WP_Error messages:',
						implode( ' ', $result->get_error_messages() ),
					) ) );
					throw new Exception( implode( ' ', $result->get_error_messages() ) );
				}
			} else {
				throw new Exception( sprintf( __( 'Cannot find the original payment gateway %s for refund.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ),
					$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ] ) );
			}
		}

		public static function post_third_party_captured_transaction( $order, $payment_method, $amount, $transaction_id ) {
			$partial_payment_object = array(
				WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD => $payment_method,
				WC_Teraco_Metadata_Constants::TRANSACTION_VALUE          => $amount,
				WC_Teraco_Metadata_Constants::TRANSACTION_TYPE           => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				WC_Teraco_Metadata_Constants::TRANSACTION_NOTE           => array( '' ),
				WC_Teraco_Metadata_Constants::TRANSACTION_ID             => $transaction_id,
				WC_Teraco_Metadata_Constants::TRANSACTION_STATUS         => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED,
			);

			WC_Teraco_Metadata::add_order_transaction( $order, $partial_payment_object );

		}

		public static function capture_all_pending_transactions( $order ) {
			$all_pending_transactions    = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );
			$total_captured_transactions = 0;
			foreach ( $all_pending_transactions as $transaction_id => $transaction_object ) {
				//first save that we are going to capture. this help us recover if this fails.
				$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE;
				WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );
			}

			//reload all the PENDING_TO_CAPTURE transactions to make sure we include anything remaining from past attempts.
			$all_pending_to_capture_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );


			try {
				foreach ( $all_pending_to_capture_transactions as $transaction_id => $transaction_object ) {
					self::capture_pending_payment_transaction( $order, $transaction_object );
					$total_captured_transactions ++;
				}
			} catch ( Throwable $exception ) {
				write_log( 'Error in capturing some codes: ' . $exception->getMessage() );
				throw new Exception( 'Error occurred in finalizing some gift code payments. Please contact the store.' );
			}

			return $total_captured_transactions;
		}

		public static function cancel_all_pending_transactions( $order ) {

			$all_pending_payment_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );
			$total_voided_transactions        = 0;

			//first save that we are going to capture. this help us recover if this fails.
			foreach ( $all_pending_payment_transactions as $transaction_id => $transaction_object ) {
				$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_VOID;
				WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );
			}

			//reload the list of PENDING_TO_VOID to include the ones that might have failed previously.
			$all_pending_to_void_payment_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_VOID,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );

			try {
				foreach ( $all_pending_to_void_payment_transactions as $transaction_id => $transaction_object ) {
					self::cancel_pending_payment_transaction( $order, $transaction_object );
					$total_voided_transactions ++;
				}
			} catch ( Throwable $exception ) {
				write_log( 'Error in voiding some codes: ' . $exception->getMessage() );
				throw new Exception( __( 'Error occurred in canceling some pending gift code payments. Please contact the store.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}


			return $total_voided_transactions;
		}

		public static function refund_all_transactions( $order ) {
			//if there has been any pending transaction that has failed to capture, we should just void it...
			$all_failed_to_capture_teraco_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );


			

			//... so we change their statuses to PENDING so that they'll get voided in the next block.
			foreach ( $all_failed_to_capture_teraco_transactions as $transaction_id => $transaction_object ) {
				$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING;
				WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );
			}

			$total_number_of_voids_and_refunds = self::cancel_all_pending_transactions( $order );

			$all_captured_payment_teraco_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );
 
			foreach ( $all_captured_payment_teraco_transactions as $transaction_id => $transaction_object ) {
				$transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ] = WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED_TO_REFUND;
				WC_Teraco_Metadata::add_order_transaction( $order, $transaction_object );
			}

			//reloading the list of payments to be refunded ensures that anything failed before will also be considered
			$all_captured_payments_to_be_refunded = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED_TO_REFUND,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );

				

			try {
				foreach ( $all_captured_payments_to_be_refunded as $transaction_id => $transaction_object ) {
					$payment_method = $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ];
					if ( $payment_method === WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ) {
						self::refund_captured_transaction( $order, $transaction_object );
					} else {
						self::refund_third_party_transaction( $order, $transaction_object );
					}
					$total_number_of_voids_and_refunds ++;
				}
			} catch ( Throwable $exception ) {
				write_log( 'Error in refunding some codes: ' . $exception->getMessage() );
				throw new Exception( __( 'Error occurred in refunding some gift code payments. Please contact the store.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ) );
			}
			return $total_number_of_voids_and_refunds;
		}

		public static function retry_all_failed_transactions( $order ) {
			$number_of_fixes = 0;

			$pending_to_void_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_VOID,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );

			foreach ( $pending_to_void_transactions as $transaction_id => $transaction_object ) {
				self::cancel_pending_payment_transaction( $order, $transaction_object );
				$number_of_fixes ++;
				$order->add_order_note( sprintf( __( 'Fixed transaction %s which had originally failed at cancelling.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), $transaction_id ) );
			}

			$pending_to_capture_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );

			foreach ( $pending_to_capture_transactions as $transaction_id => $transaction_object ) {
				self::capture_pending_payment_transaction( $order, $transaction_object );
				$number_of_fixes ++;
				$order->add_order_note( sprintf( __( 'Fixed transaction %s which had originally failed at finalizing.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), $transaction_id ) );
			}

			$captured_to_refund_transactions = WC_Teraco_Metadata::get_order_transactions_in_keys( $order,
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED_TO_REFUND,
					WC_Teraco_Metadata_Constants::TRANSACTION_TYPE   => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT,
				) );

			foreach ( $captured_to_refund_transactions as $transaction_id => $transaction_object ) {
				if ( $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_PAYMENT_METHOD ] === WC_Teraco_Plugin_Constants::TERACO_PAYMENT_METHOD_NAME ) {
					self::refund_captured_transaction( $order, $transaction_object );
				} else {
					self::refund_third_party_transaction( $order, $transaction_object );
				}
				$number_of_fixes ++;
				$order->add_order_note( sprintf( __( 'Fixed transaction %s which had originally failed at refund.', WC_Teraco_Plugin_Constants::TERACO_NAMESPACE ), $transaction_id ) );
			}
			$order->save();//this is required for persisting the order notes; otherwise they will be lost.

			return $number_of_fixes;
		}

	}
}
