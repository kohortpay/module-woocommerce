<?php
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
