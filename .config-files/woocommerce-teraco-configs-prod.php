<?php
if ( ! class_exists( 'WC_Teraco_API_Configs' ) ) {
	class WC_Teraco_API_Configs {

		const API_BASE_URL = 'http://dev.api.teraco.giftpal.in/api/v1/woocommerce/giftcards';
		const WEB_APP_CARD_DETAILS_URL = 'http://dev.teraco.giftpal.in/app/management/giftcards/list';
		

		const API_VERSION = '1.0';
		const PLUGIN_VERSION = '1.0';

		const WE_ARE_TESTING = false;
		const FAILURE_RATE = 20; //percent

	}
}
