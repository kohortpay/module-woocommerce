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
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Kohortpay_Gateway_Blocks extends AbstractPaymentMethodType
{
  private $gateway;
  protected $name = 'kohortpay'; // your payment gateway name

  public function initialize()
  {
    $this->settings = get_option('woocommerce_kohortpay_settings', []);
    $this->gateway = new WC_Gateway_Kohortpay();
  }

  public function is_active()
  {
    return $this->gateway->is_available();
  }

  public function get_payment_method_script_handles()
  {
    wp_register_script(
      'kohortpay-blocks-integration',
      plugin_dir_url(__FILE__) . 'checkout.js',
      [
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities',
        'wp-i18n',
      ],
      null,
      true
    );
    if (function_exists('wp_set_script_translations')) {
      wp_set_script_translations(
        'kohortpay-blocks-integration',
        'kohortpay',
        plugin_dir_path(__FILE__) . 'languages'
      );
      load_plugin_textdomain(
        'kohortpay',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
      );
    }
    return ['kohortpay-blocks-integration'];
  }

  public function get_payment_method_data()
  {
    return [
      'title' => $this->gateway->title,
      'description' => $this->gateway->description,
    ];
  }
}
?>
