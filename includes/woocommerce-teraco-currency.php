<?php
if ( ! class_exists( 'WC_Teraco_Currency' ) ) {

	class WC_Teraco_Currency {

		//currencies with conversion factor other than 100
		private static $currency_major_to_minor_factor = null;

		public static function init() {
			if ( null == self::$currency_major_to_minor_factor ) {
				//zero decimal currencies
				$currency_table ['BIF'] =
				$currency_table ['CLP'] =
				$currency_table ['DJF'] =
				$currency_table ['GNF'] =
				$currency_table ['JPY'] =
				$currency_table ['KMF'] =
				$currency_table ['KRW'] =
				$currency_table ['MGA'] =
				$currency_table ['PYG'] =
				$currency_table ['RWF'] =
				$currency_table ['VND'] =
				$currency_table ['VUV'] =
				$currency_table ['XAF'] =
				$currency_table ['XOF'] =
				$currency_table ['XPF'] = 1;

				//add others as we support more. any currency not in this list will be treated with the default of 100
			}
		}

		public static function teraco_currency_major_to_minor( $value, string $currency ) {
			$major_to_minor_factor = self::$currency_major_to_minor_factor[ strtoupper( $currency ) ] ?? 100;
			$minor_value           = round( $value, 2 ) * $major_to_minor_factor;
			return $minor_value;
		}

		public static function teraco_currency_minor_to_major( $value, string $currency ) {
			$major_to_minor_factor = self::$currency_major_to_minor_factor[ strtoupper( $currency ) ] ?? 100;
			$major_value           = round( $value / $major_to_minor_factor, 2 );
			return $major_value;
		}

	}
}


