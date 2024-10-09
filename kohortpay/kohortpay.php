<?php
/*
Plugin Name: WooCommerce KohortPay Referral Program
Plugin URI: https://docs.kohortpay.com/plateformes-e-commerce/woocommerce
Description: Extends WooCommerce with a referral program using cashback.
Version: 1.3.0
Author: KohortPay
Author URI: http://www.kohortpay.com/
Copyright: © 2024-2034 KohortPay.
License: MIT
License URI: https://github.com/kohortpay/module-woocommerce?tab=MIT-1-ov-file#readme
Text Domain: kohortpay
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit();
}

// Define constants
define('KOHORTPAY_MODULE_VERSION', '1.3.0');
define('KOHORTPAY_MODULE_DIR', plugin_dir_path(__FILE__));
define('KOHORTPAY_MODULE_URL', plugin_dir_url(__FILE__));

// Initialize the module
function kohortpay_module_init()
{
  // Check if WooCommerce is active
  if (class_exists('WooCommerce')) {
    // Load text domain for translations
    load_plugin_textdomain(
      'kohortpay',
      false,
      dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Send order information to KohortPay API when the order status is processing
    add_action(
      'woocommerce_order_status_processing',
      'kohortpay_send_order_to_api'
    );
  }
}
add_action('plugins_loaded', 'kohortpay_module_init');

// Function to send order information to KohortPay API
function kohortpay_send_order_to_api($order_id)
{
  $order = wc_get_order($order_id);

  // Do not send the order to the KohortPay API if the module is disabled
  if (get_option('kohortpay_enabled') !== 'yes') {
    return;
  }

  // Skip sending the order to KohortPay API if the total amount is below the configured minimum threshold
  if ($order->get_total() < get_option('kohortpay_minimum_amount')) {
    return;
  }

  // Send the order to the KohortPay API
  $response = wp_remote_post('https://api.kohortpay.dev/checkout-sessions', [
    'body' => wp_json_encode(getData($order)),
    'headers' => [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . get_option('kohortpay_api_secret_key'),
    ],
  ]);

  // Check for errors
  if (is_wp_error($response)) {
    error_log('KohortPay API error: ' . $response->get_error_message());
  } else {
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
      error_log(
        'KohortPay API error response: ' . wp_remote_retrieve_body($response)
      );
    }
  }
}

/**
 * Get order data to send to KohortPay API
 */
function getData($order)
{
  $order_data = $order->get_data();
  $line_items = $order->get_items();
  $shipping_methods = $order->get_shipping_methods();
  $discounts = $order->get_coupon_codes();
  $items = [];

  // Products
  foreach ($line_items as $item) {
    $product = $item->get_product();
    $items[] = [
      'name' => strip_tags($product->get_name()),
      'description' => strip_tags($product->get_description()),
      'quantity' => $item->get_quantity(),
      'price' => cleanPrice($product->get_price()),
      'image_url' =>
        wp_get_attachment_image_src(
          $product->get_image_id(),
          'shop_thumbnail'
        )[0] ?? wc_placeholder_img_src(),
      'type' => 'PRODUCT',
    ];
  }

  // Shipping methods
  foreach ($shipping_methods as $shipping_method) {
    $items[] = [
      'name' => $shipping_method->get_method_title(),
      'quantity' => 1,
      'price' => cleanPrice(
        $shipping_method->get_total() + $shipping_method->get_total_tax()
      ),
      'type' => 'SHIPPING',
    ];
  }

  // Discounts
  foreach ($discounts as $discount) {
    $items[] = [
      'name' => $discount,
      'quantity' => 1,
      'price' =>
        cleanPrice($order->get_discount_total() + $order->get_discount_tax()) *
        -1,
      'type' => 'DISCOUNT',
    ];
  }

  return [
    'customerFirstName' => $order_data['billing']['first_name'],
    'customerLastName' => $order_data['billing']['last_name'],
    'customerEmail' => $order_data['billing']['email'],
    'customerPhoneNumber' => $order_data['billing']['phone'],
    'locale' => get_locale(),
    'amountTotal' => cleanPrice($order_data['total']),
    'lineItems' => $items,
    'metadata' => [
      'order_id' => $order_data['id'],
      'customer_id' => $order_data['customer_id'],
    ],
  ];
}

/**
 * Clean price to avoid price with more than 2 decimals.
 */
function cleanPrice($price)
{
  $price = number_format($price, 2, '.', '');
  $price = $price * 100;

  return $price;
}

/**
 * Add a new settings tab in WooCommerce
 */
function kohortpay_add_settings_tab($settings_tabs)
{
  $settings_tabs['kohortpay_referral'] = __('Referral program', 'kohortpay');
  return $settings_tabs;
}
add_filter('woocommerce_settings_tabs_array', 'kohortpay_add_settings_tab', 50);

/**
 * Register settings fields under the new tab
 */
function kohortpay_settings_tab_content()
{
  woocommerce_admin_fields(kohortpay_get_settings());
}
add_action(
  'woocommerce_settings_tabs_kohortpay_referral',
  'kohortpay_settings_tab_content'
);

/**
 * Save settings
 */
function kohortpay_update_settings()
{
  woocommerce_update_options(kohortpay_get_settings());
}
add_action(
  'woocommerce_update_options_kohortpay_referral',
  'kohortpay_update_settings'
);

/**
 * Define the settings
 */
function kohortpay_get_settings()
{
  $settings = [
    'section_title' => [
      'name' => __('Referral Program Settings', 'kohortpay'),
      'type' => 'title',
      'desc' => '',
      'id' => 'kohortpay_settings_section_title',
    ],
    'enable_kohortpay' => [
      'name' => __('Enable Referral Program', 'kohortpay'),
      'type' => 'checkbox',
      'desc' => __(
        'Enable KohortPay referral program using cashback.',
        'kohortpay'
      ),
      'id' => 'kohortpay_enabled',
    ],
    'api_secret_key' => [
      'name' => __('API Secret Key', 'kohortpay'),
      'type' => 'password',
      'desc' => __('Enter your KohortPay API Secret Key.', 'kohortpay'),
      'id' => 'kohortpay_api_secret_key',
    ],
    'minimum_order_amount' => [
      'name' => __('Minimum Order Amount (€)', 'kohortpay'),
      'type' => 'number',
      'desc' => __(
        'Minimum order amount to trigger the referral program.',
        'kohortpay'
      ),
      'id' => 'kohortpay_minimum_amount',
      'default' => '30',
      'custom_attributes' => [
        'step' => '0.01',
        'min' => '0',
      ],
    ],
    'section_end' => [
      'type' => 'sectionend',
      'id' => 'kohortpay_settings_section_end',
    ],
  ];

  return apply_filters('kohortpay_settings', $settings);
}
