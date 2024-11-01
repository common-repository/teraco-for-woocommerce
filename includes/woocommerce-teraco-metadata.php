<?php

if ( ! class_exists( 'WC_Teraco_Metadata' ) ) {
	class WC_Teraco_Metadata {

		private static function get_order_involved_metadata( WC_Order $order, $key ) {
			$value_string = $order->get_meta( $key, true );
			return base64_decode( $value_string );
		}

		private static function update_order_involved_metadata( WC_Order $order, $key, $value ) {
			$order->add_meta_data( $key, base64_encode( $value ), true );
			$order->save();
		}

		public static function get_order_transactions( WC_Order $order ) {
			$partial_payment_string = self::get_order_involved_metadata( $order, WC_Teraco_Metadata_Constants::TRANSACTIONS_METADATA_KEY );
			if ( isset( $partial_payment_string ) || ! empty ( $partial_payment_string ) ) {
				$partial_payment_object_array = json_decode( $partial_payment_string, true );
			}
			return $partial_payment_object_array ?? array();
		}

		public static function get_order_transactions_in_keys( WC_Order $order, $include ) {
			$filtered_objects_array = self::get_order_transactions( $order );
			foreach ( $include as $key => $value ) {
				$filtered_objects_array = array_filter( $filtered_objects_array, function ( $object ) use ( $key, $value ) {
					return isset( $object[ $key ] ) && $object[ $key ] === $value;
				} );
			}
			return $filtered_objects_array ?? array();
		}

		static function filter_balance_transactions( $object ) {
			return ( ! in_array( $object[ WC_Teraco_Metadata_Constants::TRANSACTION_STATUS ],
				array(
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_VOIDED,
					WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE,
				) ) );
		}

		static function transaction_value_sum( $carry, $transaction_object ) {
		   return $carry + $transaction_object[ WC_Teraco_Metadata_Constants::TRANSACTION_VALUE ];
		}


		/**
		 * The structure of the $payment_object array should be as the following:
		 * payment_object = array(
		 * 'transaction_id'   => /transaction id for this payment/, this is a required key and the function will ignore arrays that don't have this key. the values must be unique
		 * 'payment_method'   => 'teraco',
		 * 'value'            => /amount/,
		 * 'type'             => /'PAYMENT'|'REFUND'/,
		 * 'note'             => /some note/,
		 * 'raw'              => / the raw object of the transaction result or other metadata to be stored/,
		 * 'status'           => /'CAPTURED'/'PENDING'/'PENDING_TO_CAPTURE'/'PENDING_TO_VOID'/'CAPTURED_TO_REFUND'/'REFUNDED'/'VOIDED'
		 * );
		 */
		public static function add_order_transaction( WC_Order $order, $payment_object ) {

			$payments_metadata_string = self::get_order_involved_metadata( $order, WC_Teraco_Metadata_Constants::TRANSACTIONS_METADATA_KEY );
			$payments_array = json_decode( $payments_metadata_string, true );

			if ( ! isset( $payments_array ) ) {
				$payments_array = array();
			}
			$transaction_id = $payment_object[ WC_Teraco_Metadata_Constants::TRANSACTION_ID ];
			if ( isset( $transaction_id ) ) {
				$payments_array[ $transaction_id ] = $payment_object;
				self::update_order_involved_metadata( $order, WC_Teraco_Metadata_Constants::TRANSACTIONS_METADATA_KEY, json_encode( $payments_array ) );
			}


		}


		public static function get_transactions_total( WC_Order $order ) {
			$order_transactions_array = array_filter( self::get_order_transactions( $order ),
				'WC_Teraco_Metadata::filter_balance_transactions' );
			return round( array_reduce( $order_transactions_array, 'WC_Teraco_Metadata::transaction_value_sum', 0 ), 2 );
			
		}


		public static function get_payments_total( WC_Order $order ) {
			$order_transactions_array = array_filter( self::get_order_transactions_in_keys( $order,
				array( WC_Teraco_Metadata_Constants::TRANSACTION_TYPE => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_PAYMENT ) ),
				'WC_Teraco_Metadata::filter_balance_transactions' );
			return round( array_reduce( $order_transactions_array, 'WC_Teraco_Metadata::transaction_value_sum', 0 ), 2 );
		}


		public static function get_refunds_total( $order ) {
			$order_transactions_array = array_filter( self::get_order_transactions_in_keys( $order,
				array( WC_Teraco_Metadata_Constants::TRANSACTION_TYPE => WC_Teraco_Metadata_Constants::TRANSACTION_TYPE_REFUND ) ),
				'WC_Teraco_Metadata::filter_balance_transactions' );

			return round( array_reduce( $order_transactions_array, 'WC_Teraco_Metadata::transaction_value_sum', 0 ), 2 );
		}

		public static function get_original_total( WC_Order $order ) {
			$original_total = $order->get_meta( WC_Teraco_Metadata_Constants::ORIGINAL_TOTAL_METADATA_KEY, true );
			if ( ! isset( $original_total ) || empty( $original_total ) ) {
				$original_total = $order->get_total();
			}
			return $original_total;
		}

		public static function is_original_total_set( WC_Order $order ) {
			return $order->meta_exists( WC_Teraco_Metadata_Constants::ORIGINAL_TOTAL_METADATA_KEY );
		}

		public static function set_original_total( WC_Order $order, $value ) {
			$a = $order->add_meta_data( WC_Teraco_Metadata_Constants::ORIGINAL_TOTAL_METADATA_KEY, $value, true );
		}

		public static function set_original_status( WC_Order $order, $status ) {
			$order->add_meta_data( WC_Teraco_Metadata_Constants::ORIGINAL_STATUS_METADATA_KEY, $status, true );
		}

		public static function get_original_status( WC_Order $order ) {
			return $order->get_meta( WC_Teraco_Metadata_Constants::ORIGINAL_STATUS_METADATA_KEY, true );
		}

		public static function get_balance( WC_Order $order ) {
			return ( self::get_original_total( $order ) - self::get_transactions_total( $order ) );
		}

		public static function get_number_of_failed_transactions( $order ) {
			$pending_to_void_transactions    = self::get_order_transactions_in_keys( $order,
				array( WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_VOID ) );
			$pending_to_capture_transactions = self::get_order_transactions_in_keys( $order,
				array( WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_PENDING_TO_CAPTURE ) );
			$captured_to_refund_transactions = self::get_order_transactions_in_keys( $order,
				array( WC_Teraco_Metadata_Constants::TRANSACTION_STATUS => WC_Teraco_Metadata_Constants::TRANSACTION_STATUS_CAPTURED_TO_REFUND ) );

			return count( $pending_to_capture_transactions ) + count( $pending_to_void_transactions ) + count( $captured_to_refund_transactions );
		}
	}
}


