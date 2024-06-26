<?php
/*
Plugin Name: WooCommerce KohortPay Payment Gateway
Plugin URI: https://docs.kohortpay.com/plateformes-e-commerce/woocommerce
Description: Extends WooCommerce with an KohortPay payment gateway.
Version: 1.1.0
Author: KohortPay
Author URI: http://www.kohortpay.com/
Copyright: © 2024-2034 KohortPay.
License: MIT
License URI: https://github.com/kohortpay/module-woocommerce?tab=MIT-1-ov-file#readme
Text Domain: kohortpay
Domain Path: /languages
*/
add_action('plugins_loaded', 'woocommerce_gateway_kohortpay_init', 0);
function woocommerce_gateway_kohortpay_init()
{
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  /**
   * Localisation
   */
  load_plugin_textdomain(
    'kohortpay',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
  );

  /**
   * Gateway class
   */
  class WC_Gateway_Kohortpay extends WC_Payment_Gateway
  {
    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
      $this->id = 'kohortpay';
      $this->icon = apply_filters(
        'woocommerce_kohortpay_icon',
        plugins_url('assets/images/kohortpay.png', __FILE__)
      );
      $this->has_fields = false;
      $this->method_title = __('KohortPay', 'kohortpay');
      $this->method_description = __(
        'Social payment method : Pay less, together. Turn your customer into your brand advocates.',
        'kohortpay'
      );
      $this->supports = ['products'];
      $this->init_form_fields();
      $this->init_settings();

      $this->title = __('Kohortpay : Pay, refer & save money', 'kohortpay');
      $this->description = __(
        'Save money and so does your friend, from the first friend you invite.',
        'kohortpay'
      );
      $this->enabled = $this->get_option('enabled');

      add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
        $this,
        'process_admin_options',
      ]);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
      $this->form_fields = [
        'enabled' => [
          'title' => __('Activate', 'kohortpay'),
          'type' => 'checkbox',
          'label' => __(
            'Must be enabled to display KohortPay in your checkout page.',
            'kohortpay'
          ),
          'default' => 'yes',
        ],
        'secret_key' => [
          'title' => __('API Secret Key', 'kohortpay'),
          'type' => 'password',
          'description' => __(
            'Found in KohortPay Dashboard > Developer settings. Start with sk_ or sk_test (for test mode).',
            'kohortpay'
          ),
          'default' => '',
        ],
        'minimum_amount' => [
          'title' => __('Minimum amount', 'kohortpay'),
          'type' => 'text',
          'description' => __(
            'Minimum total order amount to display KohortPay in your checkout page.',
            'kohortpay'
          ),
          'default' => '30',
        ],
      ];
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id)
    {
      global $woocommerce;
      $order = new WC_Order($order_id);

      // KohortPay API request
      $response = wp_remote_post(
        'https://api.kohortpay.com/checkout-sessions',
        [
          'body' => wp_json_encode($this->getData($order)),
          'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->get_option('secret_key'),
          ],
        ]
      );

      if (is_wp_error($response)) {
        wc_add_notice(
          __(
            'Sorry, an error occurred while processing your payment. Please try again.',
            'kohortpay'
          ),
          'error'
        );
        return;
      }

      $response = json_decode($response['body']);

      // Error handling
      $error_message = $response->error->message ?? null;
      if (isset($error_message)) {
        if (!is_array($error_message)) {
          $error_message = [$error_message];
        }

        foreach ($error_message as $message) {
          wc_add_notice($message, 'error');
        }
        return;
      }

      // Redirect to KohortPay checkout page
      return [
        'result' => 'success',
        'redirect' => $response->url,
      ];
    }

    /**
     * Get order data to send to KohortPay API
     */
    private function getData($order)
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
          'price' => $this->cleanPrice($product->get_price()),
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
          'price' => $this->cleanPrice(
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
            $this->cleanPrice(
              $order->get_discount_total() + $order->get_discount_tax()
            ) * -1,
          'type' => 'DISCOUNT',
        ];
      }

      return [
        'customerFirstName' => $order_data['billing']['first_name'],
        'customerLastName' => $order_data['billing']['last_name'],
        'customerEmail' => $order_data['billing']['email'],
        #'customerPhoneNumber' => $order_data['billing']['phone'],
        'successUrl' => $this->get_return_url($order),
        'cancelUrl' => wc_get_checkout_url(),
        'locale' => get_locale(),
        'amountTotal' => $this->cleanPrice($order_data['total']),
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
    private function cleanPrice($price)
    {
      $price = number_format($price, 2, '.', '');
      $price = $price * 100;

      return $price;
    }
  }
}

/**
 * Add the Gateway to WooCommerce
 **/
add_filter(
  'woocommerce_payment_gateways',
  'woocommerce_add_gateway_kohortpay_gateway'
);
function woocommerce_add_gateway_kohortpay_gateway($methods)
{
  $methods[] = 'WC_Gateway_Kohortpay';
  return $methods;
}

/**
 * Rules to display KohortPay in the checkout page
 */
add_filter('woocommerce_available_payment_gateways', 'kohortpay_display_rules');
function kohortpay_display_rules($available_gateways)
{
  // Hide KohortPay if the total order amount is less than the minimum amount
  if (
    isset($available_gateways['kohortpay']) &&
    isset(WC()->cart) &&
    WC()->cart->total <
      $available_gateways['kohortpay']->get_option('minimum_amount')
  ) {
    unset($available_gateways['kohortpay']);
  }

  // Hide KohortPay if the currency is not supported
  if (
    isset($available_gateways['kohortpay']) &&
    !in_array(get_woocommerce_currency(), ['EUR'])
  ) {
    unset($available_gateways['kohortpay']);
  }

  // Hide KohortPay is secret key is not set
  if (
    isset($available_gateways['kohortpay']) &&
    empty($available_gateways['kohortpay']->get_option('secret_key'))
  ) {
    unset($available_gateways['kohortpay']);
  }

  return $available_gateways;
}

/**
 * Change order payment status to paid when success URL is called with parameter payment_id and save it to the order
 */
add_filter('woocommerce_thankyou', 'kohortpay_order_received', 10, 1);
function kohortpay_order_received($order_id)
{
  if (!isset($_GET['payment_id'])) {
    return;
  }

  $order = new WC_Order($order_id);
  $order->payment_complete();
  $order->add_order_note(
    sprintf(
      __('KohortPay payment %s completed.', 'kohortpay'),
      $_GET['payment_id']
    )
  );
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
 */
add_action(
  'before_woocommerce_init',
  'declare_cart_checkout_blocks_compatibility'
);
function declare_cart_checkout_blocks_compatibility()
{
  // Check if the required class exists
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    // Declare compatibility for 'cart_checkout_blocks'
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
      'cart_checkout_blocks',
      __FILE__,
      true
    );
  }
}

/**
 * Custom function to register a payment method type
 */
add_action(
  'woocommerce_blocks_loaded',
  'oawoo_register_order_approval_payment_method_type'
);
function oawoo_register_order_approval_payment_method_type()
{
  // Check if the required class exists
  if (
    !class_exists(
      'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'
    )
  ) {
    return;
  }

  // Include the custom Blocks Checkout class
  require_once plugin_dir_path(__FILE__) . 'class-block.php';

  // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
  add_action('woocommerce_blocks_payment_method_type_registration', function (
    Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
  ) {
    // Register an instance of Kohortpay_Gateway_Blocks
    $payment_method_registry->register(new Kohortpay_Gateway_Blocks());
  });
}
