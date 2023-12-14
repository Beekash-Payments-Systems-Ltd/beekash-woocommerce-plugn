<?php
/*
Plugin Name: Beekash payment gateway plugin For WooCommerce
Description: Start accepting payments on your WooCommerce store using Beekash for WooCommerce plugin.
Tags: Beekash payment, Beekash, payment, payment gateway, online payments, pay now, buy now, e-commerce, gateway, Worldwide
Author: Beekash
Version: 1.0.0
Author URI: https://beekash.net
License: Apache or later
License URI: https://directory.fsf.org/wiki/License:Apache-2.0
Requires at least:
Tested up to: 6.2.2
Stable tag: 1.3.8
*/

if (!defined('ABSPATH')) {
	exit;
}
define( 'WC_BEEKASH_FILE', __FILE__ );
define( 'WC_BEEKASH_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'WC_BEEKASH_VERSION', '1.3.8' );


/**
 * Initialize Beekash WooCommerce payment gateway.
 */
function beekash_payment_init()
{
//	load_plugin_textdomain('beekash-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');
	if (class_exists('WC_Payment_Gateway_CC')) {
		require_once dirname(__FILE__) . '/includes/class-wc-gateway-beekash.php';
	}
	add_filter('woocommerce_payment_gateways', 'wc_add_beekash_gateway', 99);
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'beekash_payment_plugin_action_links');
}
add_action('plugins_loaded', 'beekash_payment_init', 99);
add_action("admin_enqueue_scripts", "loadbeekashpluginstyle");


function loadbeekashpluginstyle()
{
	wp_enqueue_style('beekash_style_semantic', plugins_url('assets/css/style.css', __FILE__));
}
/**
 * Add Settings link to the plugin entry in the plugins menu.
 *
 * @param array $links Plugin action links.
 *
 * @return array
 **/
function beekash_payment_plugin_action_links($links)
{
	$settings_link = array(
		'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=beekash') . '" title="' . __('View beekash WooCommerce Settings', 'beekash-payment') . '">' . __('Settings', 'beekash-payment') . '</a>',
	);
	return array_merge($settings_link, $links);
}

/**
 * Add Beekash Gateway to WooCommerce.
 *
 * @param array $methods WooCommerce payment gateways methods.
 *
 * @return array
 */
function wc_add_beekash_gateway($methods)
{
    if ( ! class_exists( 'WC_Payment_Gateway' ) )
    {
        add_action( 'admin_notices', 'beekash_payment_wc_missing_notice' );
        return;
    }
	if (class_exists('WC_Payment_Gateway_CC')) 
	{
		$methods[] = 'WC_Gateway_Beekash';
	}

	return $methods;
}

/**
 * Display a notice if WooCommerce is not installed
 */
function beekash_payment_wc_missing_notice()
{
	echo '<div class="error"><p><strong>' . sprintf(__('Beekash requires WooCommerce to be installed and active. Click %s to install WooCommerce.', 'beekash-payment'), '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539') . '" class="thickbox open-plugin-details-modal">here</a>') . '</strong></p></div>';
}
