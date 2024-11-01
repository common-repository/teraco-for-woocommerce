<?php

/*
Plugin Name: Teraco for WooCommerce
Plugin URI: https://wordpress.org/plugins/teraco-for-woocommerce/
Description: Acquire and retain customers using account credits, gift cards, promotions, and points.
Version: 1.0.0
Author: Teraco
Author URI: http://teraco.giftpal.in
License: GPL2

Teraco for WooCommerce is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

Teraco for WooCommerce is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with Teraco for WooCommerce. If not, see https://www.gnu.org/licenses/old-licenses/gpl-2.0.html.

*/



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_TERACO_MIN_PHP_VER', '7.0.0' );
define( 'WC_TERACO_MIN_WOOC_VER', '3.0.0' );

if ( ! function_exists( 'teraco_compatibility_tests' ) ) {
	function teraco_compatibility_tests() {
		return ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) //woocommerce is installed and active
		       && version_compare( phpversion(), WC_TERACO_MIN_PHP_VER, '>=' )
		       && defined( 'WC_VERSION' )
		       && version_compare( WC_VERSION, WC_TERACO_MIN_WOOC_VER, '>=' );
	}
}


if ( ! function_exists( 'teraco_init_woo_gateway' ) ) {

	function teraco_init_woo_gateway() {
		if ( ! teraco_compatibility_tests() ) {
			return;
		}
		include_once 'includes/woocommerce-teraco-constants.php';
		include_once 'includes/woocommerce-teraco-configs.php';
		include_once 'includes/woocommerce-teraco-core.php';
		include_once 'includes/woocommerce-teraco-currency.php';
		include_once 'includes/woocommerce-teraco-metadata.php';
		include_once 'includes/woocommerce-teraco-transactions.php';
		include_once 'includes/woocommerce-teraco-admin-view.php';
		include_once 'includes/woocommerce-teraco-user-view.php';
		include_once 'includes/woocommerce-teraco-payment-gateway.php';


		//Localisation
		load_plugin_textdomain( 'woocommerce_teraco', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
	}

	add_action( 'plugins_loaded', 'teraco_init_woo_gateway' );
	add_action( 'init', 'WC_Teraco_Currency::init' );
	add_action( 'init', 'WC_Teraco_Admin::init' );
	add_action( 'init', 'WC_Teraco_User::init' );
}

if ( ! function_exists( 'teraco_plugin_add_settings_link' ) ) {
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'teraco_plugin_add_settings_link' );

	function teraco_plugin_add_settings_link( $existing_links ) {
		$settings_link = array( '<a href="admin.php?page=wc-settings&tab=checkout&section=teraco">' . __( 'Settings' ) . '</a>', );
		return array_merge( $existing_links, $settings_link );
	}
}


/**
 * Register Gateway
 */
if ( ! function_exists( 'teraco_register_woo_gateway' ) ) {
	function teraco_register_woo_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Teraco';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'teraco_register_woo_gateway' );
}
