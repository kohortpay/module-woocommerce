<?php
/*
Plugin Name: WooCommerce KohortPay Payment Gateway
Plugin URI: https://docs.kohortpay.com/plateformes-e-commerce/woocommerce
Description: Extends WooCommerce with an KohortPay payment gateway.
Version: 1.0.0
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
          'price' => $product->get_price() * 100,
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
          'price' => $shipping_method->get_total() * 100,
          'type' => 'SHIPPING',
        ];
      }

      // Discounts
      foreach ($discounts as $discount) {
        $items[] = [
          'name' => $discount,
          'quantity' => 1,
          'price' => $order_data['discount_total'] * -100,
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
        'amountTotal' => $order_data['total'] * 100,
        'lineItems' => $items,
        'metadata' => [
          'order_id' => $order_data['id'],
          'customer_id' => $order_data['customer_id'],
        ],
      ];
    }
  }

  /**
   * Add the Gateway to WooCommerce
   **/
  function woocommerce_add_gateway_kohortpay_gateway($methods)
  {
    $methods[] = 'WC_Gateway_Kohortpay';
    return $methods;
  }

  add_filter(
    'woocommerce_payment_gateways',
    'woocommerce_add_gateway_kohortpay_gateway'
  );

  /**
   * Display KohortPay payment method if the total order amount is greater than the minimum amount
   */
  function kohortpay_minimum_amount($available_gateways)
  {
    if (
      isset($available_gateways['kohortpay']) &&
      WC()->cart->total <
        $available_gateways['kohortpay']->get_option('minimum_amount')
    ) {
      unset($available_gateways['kohortpay']);
    }
    return $available_gateways;
  }
  add_filter(
    'woocommerce_available_payment_gateways',
    'kohortpay_minimum_amount'
  );

  /**
   * Change order payment status to paid when success URL is called with parameter payment_id and save it to the order
   */
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
  add_filter('woocommerce_thankyou', 'kohortpay_order_received', 10, 1);
}
