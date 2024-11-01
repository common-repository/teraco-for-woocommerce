=== Teraco for WooCommerce ===
Contributors: Teraco integrations
Tags: woocommerce, giftcard
Requires at least: 4.7
Tested up to: 5.0.3
Stable tag: trunk
Requires PHP: 7.0 or later
License: GPLv2 or later
WC requires at least: 3.0
WC tested up to: 3.5.3
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Acquire and retain customers using Account Credits, Gift Cards, Promotions, and Points.

== Description ==
Teraco for WooCommerce allows Teraco’s Gift Cards to be redeemed in your WooCommerce checkout. You can view and track GiftCard redemptions and also issue GiftCard refunds from your WooCommerce dashboard.
Teraco is a modern platform for digital account credits, Gift Cards, Promotions, and Points—made for customer acquisition and retention.
To learn more, visit [Teraco](http://web.giftpal.in/).

== How to Get Started ==
* Get a Teraco account [here](http://dev.teraco.giftpal.in).
* Install plugin
* First complete the basic on-boarding process of Gift cards.
* Get access key by creating an application(API) and add the access key in plugins.

== How to Create Giftcard ==
* Create a campaign with preloaded value flag true.
* Create Giftcard style, will use for sending your brand image along with Giftcard via email in case of refund.
* Create Gift cards within created campaign.
* Distribute your WooCommerce-ready Gift Cards codes to customers

To connect the plugin with your Teraco account to process Gift Cards, you will need to enter your Teraco API key (Key_Secrate) in the plugin settings as detailed below.

== Features ==
The following features are supported in the current version (1.0.0) of this plugin:

* Pay for an order by a Gift Card.
* Split payment on an order using a Gift Card and another payment method.
* Pay for an order using more than one Gift Card.
* Cancel an order by the customer after attempting to pay with a Gift Card, if the order balance exceeds the value of the Card.
* Issue full order refund by the store admin when an order is paid by a Gift Card.
* Detailed log of all the transactions on an order for the store admin.
* Use Gift Cards with Teraco Attached Promotions including Promotions with Redemption Rules.
The current version of this plugin has been tested to work seamlessly with Stripe, PayPal Standard, and CardConnect WooCommerce plugins.

== Installation ==
= Dependencies =
This requires the following dependencies.
* PHP 7.0 or later.
* Wordpress 4.7, or later.
* WooCommerce 3.0, or later.
In order for this plugin to work, the WooComerce wordpress plugin must be installed and activated.

= Install and activate the plugin =

Option 1 - Install automatically through your WordPress dashboard:
1. Go to `Plugins` > `Add new` and use the search field to search for `Teraco for WooCommerce`
2. Click `Install Now` and then `Activate`
Option 2 - Upload the plugin file:
1. In your WordPress dashboard, go to `Plugins` > `Add new` > `Upload Plugin`.
2. Click `Choose File`, select the plugin `zip`, and click `Install Now`.
3. Click `Activate Plugin` when prompted.
Option 3 - By SFTP:
1. Copy the `woocommerce-Teraco` folder into the `wp-content/plugins` directory of your Wordpress instance.
2. Activate the plugin through the `Plugins` menu in WordPress.
= Set Up Teraco Payment Gateway =
In order to connect with the Teraco API, the plugin needs valid Teraco API keys(Key_Secrate). You can generate API keys in your  [Teraco dashboard](http://dev.teraco.giftpal.in) fill basic on-boarding detail, select Giftcard Management from dropdown list at top right corner, go to `Application`, and then click on `Add Application`.
Once you have created an API key, enter it in the Teraco for WooCommerce settings page. The settings are in the `WooCommerce` > `Settings` > `Checkout` tab where you can scroll down to the bottom of the page under `Payment Gateways ` and find `Gift Code` by Teraco.
Alternatively, you can directly go to the settings page by clicking on the `Settings` link under Teraco for WooCommerce plugin name on Wordpress `Plugins` page.
== WooCommerce Coupons ==
To avoid confusion on the checkout page, if you are using Teraco Gift Cards, we strongly recommend that you disable WooCommerce coupons. You can disable WooCommerce default coupons by going to `WooCommerce` >` Settings` > `Checkout` tab > `Checkout` options and unchecking the checkbox `Enable the use of coupons`.

== Screenshots ==
1. Create API keys(Key_Secrate).
2. Create Campaigns.
3. Create and download Gift Card codes from Teraco to send to your customers.
4. Upload Styles.
5. After installing Teraco for WooCommerce, Teraco codes are redeemable in your WooCommerce checkout.
6. View Teraco Gift Card redemption details in your WordPress dashboard.
7. Teraco for WooCommerce also supports refunds.
8. Track individual card details and transactions in Teraco.

== Changelog ==
= 2.0.0 =
* Support for Teraco conditional Promotions with redemption rules.
= 1.0.2 =
* Improvements to readme & assets for display in WordPress directory listing
= 1.0.1 =
* Bumping up the version to submit to WordPress.
= 1.0.0-beta.1 =
* Tested with WooCommerce 3.3.5
* Test with Stripe, PayPal Standard, and CardConnect
= 1.0.0-alpha =
* Initial Release