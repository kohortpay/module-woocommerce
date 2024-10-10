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
      'kohortpay_send_order_to_api',
      10,
      1
    );
  }
}
add_action('plugins_loaded', 'kohortpay_module_init');

// Function to send order information to KohortPay API
function kohortpay_send_order_to_api($order_id)
{
  $order = wc_get_order($order_id);

  // Do not send the order to the KohortPay API if the module is disabled
  if (get_option('kohortpay_enabled', 'no') !== 'yes') {
    return;
  }

  // Skip sending the order to KohortPay API if the total amount is below the configured minimum threshold
  if ($order->get_total() < get_option('kohortpay_minimum_amount', 30)) {
    return;
  }

  // Add a custom order note
  $order->add_order_note(__('Sending order to KohortPay API.', 'kohortpay'));
  $order->save();

  // Send the order to the KohortPay API
  $response = wp_remote_post('https://api.kohortpay.dev/checkout-sessions', [
    'body' => wp_json_encode(getData($order)),
    'headers' => [
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . get_option('kohortpay_api_secret_key'),
    ],
  ]);

  // Check for errors
  if (
    is_wp_error($response) ||
    wp_remote_retrieve_response_code($response) < 200 ||
    wp_remote_retrieve_response_code($response) >= 300
  ) {
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
    'kohortpay_enabled' => [
      'name' => __('Enable Referral Program', 'kohortpay'),
      'type' => 'checkbox',
      'desc' => __(
        'Enable KohortPay referral program using cashback.',
        'kohortpay'
      ),
      'id' => 'kohortpay_enabled',
    ],
    'kohortpay_api_secret_key' => [
      'name' => __('API Secret Key', 'kohortpay'),
      'type' => 'password',
      'desc' => __('Enter your KohortPay API Secret Key.', 'kohortpay'),
      'id' => 'kohortpay_api_secret_key',
    ],
    'kohortpay_minimum_amount' => [
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

/**
 * Validate custom KohortPay coupons
 */
function kohortpay_validate_coupon($valid, $coupon)
{
  $coupon_code = $coupon->get_code();

  // Check if the coupon code starts with "KHTPAY-"
  if (strpos($coupon_code, 'khtpay-') === 0) {
    // Simulate the existence of the coupon
    $valid = true;

    $coupon_code = str_replace('TEST', 'test', strtoupper($coupon_code));

    // Make an API call to validate the coupon
    $response = wp_remote_post(
      'https://api.kohortpay.dev/payment-groups/' . $coupon_code . '/validate',
      [
        'headers' => [
          'Authorization' => 'Bearer ' . get_option('kohortpay_api_secret_key'),
        ],
      ]
    );

    // Check for errors in the API response
    if (
      is_wp_error($response) ||
      wp_remote_retrieve_response_code($response) < 200 ||
      wp_remote_retrieve_response_code($response) >= 300
    ) {
      // Display an error message
      $errorResponse = json_decode(wp_remote_retrieve_body($response), true);
      $errorCode = $errorResponse['error']['code'] ?? 'UNKNOWN_ERROR';

      wc_add_notice(getErrorMessageByCode($errorCode), 'error');

      // Return false to indicate the coupon is not valid
      return false;
    } else {
      $data = json_decode(wp_remote_retrieve_body($response), true);

      $cashbackType = $data['discount_type'] ?? 'PERCENTAGE';
      $cashbackValue = $data['current_discount_level']['value'] ?? 0.0;

      // If cashback type is percentage, display the cashback amount in amount
      if ($cashbackType === 'PERCENTAGE') {
        $cashbackValue = WC()->cart->total * ($cashbackValue / 100);
      }

      // Display a success message and indicate the cashback amount
      if ($cashbackValue > 0) {
        wc_add_notice(
          __('Cashback unlocked:', 'kohortpay') .
            ' ' .
            wc_price($cashbackValue),
          'success'
        );
      }

      // Remove the coupon from the cart
      WC()->cart->remove_coupon($coupon_code);

      // Return true to indicate the coupon is valid
      return true;
    }
  }

  return $valid;
}
add_filter('woocommerce_coupon_is_valid', 'kohortpay_validate_coupon', 10, 2);

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
    'clientReferenceId' => (string) $order_data['id'],
    'paymentClientReferenceId' => (string) $order_data['id'],
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
 * Manage error messages
 */
function getErrorMessageByCode($errorCode)
{
  $errorMessage = '';
  switch ($errorCode) {
    case 'AMOUNT_TOO_LOW':
      $errorMessage = __(
        'The cart amount is too low to use this referral code.',
        'kohortpay'
      );
      break;
    case 'COMPLETED_EXPIRED_CANCELED':
      $errorMessage = __(
        'Unfortunately, the referral period of the kohort has ended.',
        'kohortpay'
      );
      break;
    case 'MAX_PARTICIPANTS_REACHED':
      $errorMessage = __(
        'Unfortunately, the maximum number of people in the kohort has been reached.',
        'kohortpay'
      );
      break;
    case 'EMAIL_ALREADY_USED':
      $errorMessage = __(
        'The email address has already been used to join the kohort.',
        'kohortpay'
      );
      break;
    case 'NOT_FOUND':
      $errorMessage = __(
        'The referral code is unknown or not found.',
        'kohortpay'
      );
      break;
    default:
      $errorMessage = __('The referral code is invalid.', 'kohortpay');
      break;
  }

  $minimumAmount = wc_price(get_option('kohortpay_minimum_amount'));
  $defaultSuffixErrorMessage =
    __('Complete a purchase of at least ', 'kohortpay') .
    $minimumAmount .
    __(
      ' to generate a referral code and get cashback on your order by sharing it.',
      'kohortpay'
    );

  return $errorMessage . ' ' . $defaultSuffixErrorMessage;
}

/**
 * Automatically mark the order as paid if the payment method is 'cheque'
 */

add_action('woocommerce_thankyou', 'auto_complete_check_payment_orders', 10, 1);

function auto_complete_check_payment_orders($order_id)
{
  if (!$order_id) {
    return;
  }

  $order = wc_get_order($order_id);

  // Check if the order payment method is 'cheque'
  if ($order->get_payment_method() === 'cheque') {
    // Update order status to processing
    $order->update_status(
      'processing',
      __('Order marked as processing by custom function.', 'woocommerce')
    );

    // Mark order as paid
    $order->payment_complete();
  }
}

add_action(
  'woocommerce_thankyou',
  'kohortpay_add_script_to_thankyou_page',
  20,
  1
);

function kohortpay_add_script_to_thankyou_page($order_id)
{
  if (!$order_id) {
    return;
  }

  $order = wc_get_order($order_id);
  $order_total = $order->get_total();
  $minimum_amount = get_option('kohortpay_minimum_amount', 30);

  // Check if the order total is greater than the minimum amount
  if ($order_total > $minimum_amount) {
    // Output the script with the order ID
    echo '<script src="https://discovery.kohortpay.com/modal.min.js" data-id="' .
      esc_attr($order_id) .
      '"></script>';
  }
}

// Provide fake data for the coupon
function kohortpay_fake_coupon_data($data, $coupon_code)
{
  // Check if the coupon code starts with "KHTPAY-"
  if (strpos($coupon_code, 'khtpay-') === 0) {
    // Define fake coupon data
    $data = [
      'id' => 0, // Fake ID
      'code' => $coupon_code,
      'amount' => '0', // Discount amount
      'discount_type' => 'fixed_cart', // Discount type
      'description' => 'Simulated KohortPay coupon',
      'date_expires' => null,
      'usage_limit' => null,
      'usage_count' => 0,
      'individual_use' => false,
      'product_ids' => [],
      'exclude_product_ids' => [],
      'usage_limit_per_user' => null,
      'limit_usage_to_x_items' => null,
      'free_shipping' => false,
      'product_categories' => [],
      'exclude_product_categories' => [],
      'exclude_sale_items' => false,
      'minimum_amount' => '',
      'maximum_amount' => '',
      'email_restrictions' => [],
    ];
  }

  return $data;
}
add_filter(
  'woocommerce_get_shop_coupon_data',
  'kohortpay_fake_coupon_data',
  10,
  2
);
