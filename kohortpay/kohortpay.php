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
    'wc-gateway-kohortpay',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
  );

  /**
   * Gateway class
   */
  class WC_Gateway_Kohortpay extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $this->id = 'kohortpay';
      $this->icon = apply_filters(
        'woocommerce_kohortpay_icon',
        plugins_url('assets/images/kohortpay.png', __FILE__)
      );
      $this->has_fields = false;
      $this->method_title = __('KohortPay', 'wc-gateway-kohortpay');
      $this->method_description = __(
        'Pay, refer & save up to 30%.',
        'wc-gateway-kohortpay'
      );
      $this->supports = ['products'];
      $this->init_form_fields();
      $this->init_settings();

      $this->title = __('KohortPay', 'wc-gateway-kohortpay');
      $this->description = __(
        'Pay, refer & save up to 30%.',
        'wc-gateway-kohortpay'
      );
      $this->enabled = $this->get_option('enabled');

      add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
        $this,
        'process_admin_options',
      ]);
    }

    public function init_form_fields()
    {
      $this->form_fields = [
        'enabled' => [
          'title' => __('Enable/Disable', 'wc-gateway-kohortpay'),
          'type' => 'checkbox',
          'label' => __(
            'Enable KohortPay Payment Gateway',
            'wc-gateway-kohortpay'
          ),
          'default' => 'yes',
        ],
        'minimum_amount' => [
          'title' => __('Minimum Amount', 'wc-gateway-kohortpay'),
          'type' => 'text',
          'description' => __(
            'The minimum amount (in EUR) for which this gateway should be available.',
            'wc-gateway-kohortpay'
          ),
          'default' => '30',
        ],
        'secret_key' => [
          'title' => __('Merchant Secret Key', 'wc-gateway-kohortpay'),
          'type' => 'password',
          'description' => __(
            'Get your API secret key from your KohortPay account.',
            'wc-gateway-kohortpay'
          ),
          'default' => '',
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
}
