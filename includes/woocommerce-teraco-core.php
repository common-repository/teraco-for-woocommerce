<?php

if ( ! function_exists( 'write_log' ) ) {
	function write_log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
		} else {
			error_log( $log );
		}
	}
}

if ( ! class_exists( 'WC_TeracoEngine' ) ) {
	class WC_TeracoEngine {
		public static function api_ping( string $api_key ) {
			$response = self::call_teraco_api_with_headers( WC_Teraco_API_Constants::ENDPOINT_PING, WC_Teraco_API_Constants::HTTP_POST,$api_key);
			return self::handle_api_response( $response, WC_Teraco_API_Constants::API_RESPONSE_KEY_USER );
		}

		public static function get_giftcard_details( string $code, string $api_key ) {
			$response = self::call_teraco_api_with_headers( sprintf( WC_Teraco_API_Constants::ENDPOINT_CODE_CARD_DETAILS, $code ), WC_Teraco_API_Constants::HTTP_GET, $api_key );
			return self::handle_api_response( $response, WC_Teraco_API_Constants::API_RESPONSE_KEY_DETAILS );
		}

		public static function post_pending_transaction( string $code, int $amount, string $currency, string $userSuppliedId, string $api_key, array $metadata = [], string $pin ) {
			return self::post_transaction( $code, $amount, $currency, $userSuppliedId, $api_key, $metadata, false, true, $pin );
		}

		public static function post_dryrun_transaction( string $code, int $amount, string $currency, string $userSuppliedId, string $api_key, array $metadata = [], string $pin ) {
			return self::post_transaction( $code, $amount, $currency, $userSuppliedId, $api_key, $metadata, true, false, $pin );
		}

		private static function post_transaction( string $code, int $amount, string $currency, string $userSuppliedId, string $api_key, array $metadata, bool $dryrun, bool $pending, string $pin ) {

			$post_transaction_body = array(
				WC_Teraco_API_Constants::TRANSACTION_USER_SUPPLIED_ID => $userSuppliedId,
				WC_Teraco_API_Constants::TRANSACTION_VALUE            => $amount,
				WC_Teraco_API_Constants::TRANSACTION_CURRENCY         => $currency,
				WC_Teraco_API_Constants::TRANSACTION_CODE             => $code,
				WC_Teraco_API_Constants::TRANSACTION_PIN              => $pin, 
				 WC_Teraco_API_Constants::TRANSACTION_METADATA        => $metadata,
				WC_Teraco_API_Constants::TRANSACTION_PENDING          => $pending,
				WC_Teraco_API_Constants::TRANSACTION_NSF              => ! $dryrun
			);


            $transaction_endpoint  = $dryrun 
                 ? WC_Teraco_API_Constants::ENDPOINT_CODE_TRANSACTION_DRYRUN
		         : WC_Teraco_API_Constants::ENDPOINT_CODE_TRANSACTION;

		    $response  = $dryrun 
                ?   self::call_teraco_api_with_headers( sprintf( $transaction_endpoint, $code ), WC_Teraco_API_Constants::HTTP_POST, $api_key, $post_transaction_body )
                : self::call_teraco_api_with_headers( sprintf( $transaction_endpoint, $code ), WC_Teraco_API_Constants::HTTP_POST, $api_key, $post_transaction_body );

			return self::handle_api_response( $response, WC_Teraco_API_Constants::API_RESPONSE_KEY_TRANSACTION );

		}


		public static function refund_transaction(array $original_transaction_object_returned_from_post_method, string $userSuppliedId, string $api_key ) {
			$cardId                  = $original_transaction_object_returned_from_post_method[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ] ?? null;
			$original_transaction_id = $original_transaction_object_returned_from_post_method[ WC_Teraco_API_Constants::TRANSACTION_ID ] ?? null;

			if ( $cardId && $original_transaction_id ) {
				$refund_request_body = array(
					WC_Teraco_API_Constants::TRANSACTION_USER_SUPPLIED_ID => $userSuppliedId,
				);

				$response = self::call_teraco_api_with_headers( sprintf( WC_Teraco_API_Constants::ENDPOINT_REFUND, $cardId, $original_transaction_id ), WC_Teraco_API_Constants::HTTP_POST, $api_key, $refund_request_body );

				return self::handle_api_response( $response, WC_Teraco_API_Constants::API_RESPONSE_KEY_TRANSACTION );

			} else {
				$error_info = self::get_error_details( __FUNCTION__, func_get_args(), [], $custom_message = sprintf( "The method 'refund_transaction()' was called on a transaction object that was missing either the cardId key ('%s') or the transactionId key ('%s')", $cardId, $original_transaction_id ) );
				write_log( $error_info );
				throw new Exception( $error_info );
			}
		}

		public static function cancel_pending_transaction( array $original_transaction_object_returned_from_post_method, string $userSuppliedId, string $api_key ) {
			return self::perform_pending_transaction( $original_transaction_object_returned_from_post_method, $userSuppliedId, WC_Teraco_API_Constants::TRANSACTION_PENDING_VOID, $api_key );
		}

		public static function capture_pending_transaction( array $original_transaction_object_returned_from_post_method, string $userSuppliedId, string $api_key ) {
			return self::perform_pending_transaction( $original_transaction_object_returned_from_post_method, $userSuppliedId, WC_Teraco_API_Constants::TRANSACTION_PENDING_CAPTURE, $api_key );
		}

		private static function handle_api_response( $response, string $json_response_object_name ) {

			if ( ! is_wp_error( $response ) && ( wp_remote_retrieve_response_code( $response ) == 200 ) ) {
		
				$decoded_response = json_decode( $response[ WC_Teraco_API_Constants::API_RESPONSE_KEY_BODY ], true ) ?? [] ;
				$data = $decoded_response['data'];

				if ( isset( $data[ $json_response_object_name ] ) ) {
					return $data[ $json_response_object_name ];
				} else {
					$error_message = self::get_error_details( __FUNCTION__, func_get_args(), $response, sprintf( "A 200 OK was received, but the requested key '%s' was not found in the response body.", $json_response_object_name ) );
					write_log( $error_message );
					throw new Exception( $error_message );
				}
			} else {
                
				$calledByFunction   = self::getCallerAndCallerArgs()[0];
				$callerFunctionArgs = self::getCallerAndCallerArgs()[1];
				$error_info         = self::get_error_details( $calledByFunction, $callerFunctionArgs, $response, "Triggered by the method 'handle_api_response()' - remainder of this message pertains to the method that called it: " );
				write_log( $error_info );
				throw new Exception( $error_info );
			}

		}

		private static function call_teraco_api_with_headers( string $endpoint, string $method, string $api_key, array $body = [] ) {

			if ( WC_Teraco_API_Constants::HTTP_GET === $method ) {
				return wp_safe_remote_get( WC_Teraco_API_Configs::API_BASE_URL . $endpoint,
					array(
						WC_Teraco_API_Constants::HTTP_HEADERS => self::build_headers( $api_key ),
					)
				);
			}

			if ( WC_Teraco_API_Constants::HTTP_POST === $method ) {
				return wp_safe_remote_post( WC_Teraco_API_Configs::API_BASE_URL . $endpoint,
					array(
						WC_Teraco_API_Constants::HTTP_HEADERS => self::build_headers( $api_key ),
						WC_Teraco_API_Constants::HTTP_BODY    => json_encode( $body ),
					)
				);
			}

			write_log( self::get_error_details( __FUNCTION__, func_get_args() ) );
			return;
		}

		private static function build_headers( $api_key ) {
			return array(
				'Content-Type'  => 'application/json; charset=utf-8',
				'Authorization' => 'Bearer ' . $api_key,
			);
		}

		private static function perform_pending_transaction( array $original_transaction_object_returned_from_post_method, string $userSuppliedId, string $action, string $api_key ) {
			$cardId                  = $original_transaction_object_returned_from_post_method[ WC_Teraco_API_Constants::TRANSACTION_CARD_ID ] ?? null;
			$original_transaction_id = $original_transaction_object_returned_from_post_method[ WC_Teraco_API_Constants::TRANSACTION_ID ] ?? null;
			 $original_metadata       = $original_transaction_object_returned_from_post_method[ WC_Teraco_API_Constants::TRANSACTION_METADATA ] ?? null;

			 $body                    = array(
				WC_Teraco_API_Constants::TRANSACTION_USER_SUPPLIED_ID => $userSuppliedId,
				WC_Teraco_API_Constants::TRANSACTION_METADATA         => $original_metadata,
			);

			if ( $cardId && $original_transaction_id ) {
                
				$response = self::call_teraco_api_with_headers( sprintf( WC_Teraco_API_Constants::ENDPOINT_HANDLE_PENDING, $action , $original_transaction_id ), WC_Teraco_API_Constants::HTTP_POST, $api_key,$body );
				return self::handle_api_response( $response, WC_Teraco_API_Constants::API_RESPONSE_KEY_TRANSACTION );

			} else {

				$error_info = self::get_error_details( __FUNCTION__, func_get_args(), [], $custom_message = sprintf( "The method 'perform_pending_transaction()' was called on a transaction object that was missing either the cardId key ('%s') or the transactionId key ('%s')", $cardId, $original_transaction_id ) );
				write_log( $error_info );
				throw new Exception ( $error_info );
			}
		}

		private static function get_error_details( string $method_name, array $parameters = [], $http_response_or_wp_error = [], string $custom_message = '' ) {
			$error_info = sprintf( 'ERRORS: %s ', $custom_message );

			if ( ! empty( $http_response_or_wp_error ) ) {
				if ( is_wp_error( $http_response_or_wp_error ) ) {
					$error_info = $error_info . sprintf( 'WP_Error: %s', json_encode( $http_response_or_wp_error, true ) );
				} else {
					$error_info = $error_info . sprintf( 'HTTP response: %s - %s',
							wp_remote_retrieve_response_code( $http_response_or_wp_error ),
							wp_remote_retrieve_response_message( $http_response_or_wp_error )
						);
				}
			}

			$error_info = ( WC_Teraco_API_Configs::WE_ARE_TESTING )
				? $error_info . sprintf( " | From method %s with parameters %s",
					$method_name,
					json_encode( $parameters, true )
				)
				: '';

			return $error_info;
		}

		private static function getCallerAndCallerArgs() {
			$trace = debug_backtrace();
			$name  = $trace[2]['function'] ?? 'global';
			$args  = $trace[2]['args'] ?? [];

			return array( $name, $args );
		}

	}
}