<?php
/*
Plugin Name: SmartPablo.com
Description: Connect WooCommerce to SmartPablo.com
Version: 1.0.1
Author: Marek Hlaváč
Author URI: http://markhlavac.com
License: GPL2
 */

if (!defined('ABSPATH')) exit;

class SP_SmartPabloPlugin
{
    // static $error = false;
    // static $message = null;

    public static function admin_enqueue_files()
    {
        wp_enqueue_style('assets/css/style.css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
        wp_enqueue_script('assets/js/script.js', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], '1.0.0', true);
    }

    public static function register_settings()
    {
        register_setting('smartpablo-settings-group', 'smartpablo_secret');
    }

    public static function register_menu_link()
    {
        add_menu_page('SmartPablo Settings', 'SmartPablo', 'administrator', 'smartpablo-settings', ['SP_SmartPabloPlugin', 'show_settings_page'], 'dashicons-admin-generic');
    }

    public static function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=smartpablo-settings">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function setup_webhooks()
    {
        register_rest_route('api/v1', 'confirm', [
            'methods'   => 'POST',
            'callback'  => ['SP_SmartPabloPlugin', 'confirm'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('api/v1', 'reject', [
            'methods'   => 'POST',
            'callback'  => ['SP_SmartPabloPlugin', 'reject'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function show_settings_page()
    {
        $isWoocommerceInstalled = class_exists('WooCommerce');
        $secret = get_option('sp_smartpablo_secret');

?>
        <div class="wrap">
            <h1><?php esc_html_e('SmartPablo Plugin Settings', 'smartpablo-plugin') ?></h1>

            <?php
            if (!$isWoocommerceInstalled) :
            ?>
                <div class="sp-alert sp-alert-warning">
                    <strong>Warning!</strong> WooCommerce is not detected. Please install WooCommerce plugin to continue.
                </div>
            <?php
            elseif (empty($secret)) :
            ?>
                <div class="card">
                    <h2>Connect your account</h2>
                    <hr>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" class="form sp-form" id="form-connect">
                        <button type="submit" class="button">
                            <span class="sp-spinner" style="display:none"></span>
                            <span class="sp-text">Connect</span>
                        </button>

                        <div class="sp-alert sp-alert-danger sp-error" style="display:none">
                            <strong>Error!</strong> <span class="message"></span>
                        </div>
                    </form>

                </div>

            <?php
            else :
            ?>
                <div class="sp-alert sp-alert-success">
                    <strong>Success!</strong> Your SmartPablo account is successfully connected.
                </div>

            <?php
            endif;
            ?>
        </div>
<?php
    }

    private static function generateRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    public static function connect()
    {
        $id = SP_SmartPabloPlugin::generateRandomString(26);
        $domain = sanitize_text_field($_SERVER["SERVER_NAME"]);
        $businessName = get_bloginfo('name');
        $currency = get_woocommerce_currency();
        $country = wc_get_base_location()['country'];
        $redirect = false;
        $protocol = strtolower(substr(sanitize_text_field($_SERVER["SERVER_PROTOCOL"]), 0, 5)) == 'https' ? 'https' : 'http';
        $confirmUrl = $protocol . '://' . $domain . '?rest_route=/api/v1/confirm';
        $rejectUrl = $protocol . '://' . $domain . '?rest_route=/api/v1/reject';

        $data = [
            'ID' => $id,
            'domain' => $domain,
            'business_name' => $businessName,
            'currency' => $currency,
            'country' => $country,
            'confirm_url' => $confirmUrl,
            'reject_url' => $rejectUrl,
            'redirect' => $redirect,
        ];

        $response = wp_remote_post('https://connect.smartpablo.com/woocommerce/plugin/install', [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => json_encode($data),
            'method'      => 'POST',
            'data_format' => 'body',
        ]);

        $responseCode = wp_remote_retrieve_response_code($response);

        switch ($responseCode) {
            case 200:
                $result = json_decode(wp_remote_retrieve_body($response), true);
                wp_send_json([
                    'status' => 1,
                    'redirect_url' => $result['redirect_url'],
                ]);
                break;

            case 400:
                wp_send_json([
                    'status' => 0,
                    'message' => "Invalid parameters.",
                ]);
                break;

            case 409:
                wp_send_json([
                    'status' => 0,
                    'message' => "Id already used.",
                ]);
                break;

            case 500:
                wp_send_json([
                    'status' => 0,
                    'message' => "Unknown error.",
                ]);
                break;
        }

        wp_send_json([
            'status' => 0,
            'message' => 'No response error.',
        ]);
    }

    public static function confirm(WP_REST_Request $request)
    {
        $id = sanitize_text_field($request['id']);
        $secret = sanitize_text_field($request['secret_key']);

        delete_option('sp_smartpablo_id');
        delete_option('sp_smartpablo_secret');
        add_option('sp_smartpablo_id', $id);
        add_option('sp_smartpablo_secret', $secret);

        SP_SmartPabloPlugin::createWoocoomerceWebhook('SmartPablo Order Created', 'order.created', "https://connect.smartpablo.com/woocommerce/webhooks/$id/orders/create");
        SP_SmartPabloPlugin::createWoocoomerceWebhook('SmartPablo Order Updated', 'order.updated', "https://connect.smartpablo.com/woocommerce/webhooks/$id/orders/update");
        SP_SmartPabloPlugin::createWoocoomerceWebhook('SmartPablo Order Deleted', 'order.deleted', "https://connect.smartpablo.com/woocommerce/webhooks/$id/orders/delete");
        SP_SmartPabloPlugin::createWoocoomerceWebhook('SmartPablo Order Restored', 'order.restored', "https://connect.smartpablo.com/woocommerce/webhooks/$id/orders/restore");

        return new WP_REST_Response([
            'status' => 1
        ]);
    }

    private static function createWoocoomerceWebhook($name, $topic, $url)
    {
        $webhook = new WC_Webhook();
        $webhook->set_name($name,);
        $webhook->set_topic($topic);
        $webhook->set_secret(get_option('sp_smartpablo_secret'));
        $webhook->set_delivery_url($url);
        $webhook->set_status('active');
        $id = $webhook->save();
        add_option("sp_smartpablo_webhook_$topic", $id);
    }

    private static function deleteWoocoomerceWebhook($id)
    {
        try {
            $webhook = wc_get_webhook($id);

            if (isset($webhook)) {
                $webhook->delete();
            }
        } catch (Exception $e) {
        }
    }

    public static function reject()
    {
        return new WP_REST_Response([
            'status' => 1
        ]);
    }

    public static function uninstall()
    {
        SP_SmartPabloPlugin::deleteWoocoomerceWebhook(get_option('sp_smartpablo_webhook_order.created'));
        SP_SmartPabloPlugin::deleteWoocoomerceWebhook(get_option('sp_smartpablo_webhook_order.updated'));
        SP_SmartPabloPlugin::deleteWoocoomerceWebhook(get_option('sp_smartpablo_webhook_order.deleted'));
        SP_SmartPabloPlugin::deleteWoocoomerceWebhook(get_option('sp_smartpablo_webhook_order.restored'));

        delete_option('sp_smartpablo_id');
        delete_option('sp_smartpablo_secret');
        delete_option('sp_smartpablo_webhook_order.created');
        delete_option('sp_smartpablo_webhook_order.updated');
        delete_option('sp_smartpablo_webhook_order.deleted');
        delete_option('sp_smartpablo_webhook_order.restored');
    }

    public static function modify_order_webhook_payload($payload, $resource, $resource_id, $id)
    {
        if ($resource !== 'order') {
            return $payload;
        }

        $order = new WC_Order($resource_id);

        $payload['as_company'] = get_post_meta($order->id, 'as_company');
        $payload['business_id'] = get_post_meta($order->id, 'business_id');
        $payload['tax_id'] = get_post_meta($order->id, 'tax_id');
        $payload['vat_no'] = get_post_meta($order->id, 'vat_no');

        return $payload;
    }

    public static function enqueue_scripts()
    {
        wp_enqueue_script('assets/js/smartpablo.js', plugin_dir_url(__FILE__) . 'assets/js/smartpablo.js', [], '1.0.0', true);
    }

    public static function add_billing_fields_to_checkout($fields)
    {
        $new_fields = [];

        foreach ($fields as $key => $value) {
            if ($key == 'billing_company') {
                $new_fields['billing_as_company'] = [
                    'type' => 'checkbox',
                    'label' => 'Company?',
                    'class' => ['form-row-wide']
                ];
            }

            $new_fields[$key] = $value;

            if ($key == 'billing_company') {
                $new_fields['billing_business_id'] = [
                    'type' => 'text',
                    'label' => 'Business ID',
                    'required' => false,
                    'class' => ['form-row-wide']
                ];

                $new_fields['billing_vat_no'] = [
                    'type' => 'text',
                    'label' => 'VAT No',
                    'required' => false,
                    'class' => ['form-row-wide']
                ];

                $new_fields['billing_tax_id'] = [
                    'type' => 'text',
                    'label' => 'Tax ID',
                    'required' => false,
                    'class' => ['form-row-wide']
                ];
            }
        }

        return $new_fields;
    }

    public static function add_custom_fields_on_placed_order($order, $data)
    {
        $order->update_meta_data('as_company', $data['billing_as_company']);
        $order->update_meta_data('business_id', $data['billing_business_id']);
        $order->update_meta_data('tax_id', $data['billing_tax_id']);
        $order->update_meta_data('vat_no', $data['billing_vat_no']);
    }
}

add_action('admin_init', ['SP_SmartPabloPlugin', 'register_settings']);
add_action('admin_menu', ['SP_SmartPabloPlugin', 'register_menu_link']);
add_action('admin_enqueue_scripts', ['SP_SmartPabloPlugin', 'admin_enqueue_files']);
add_action('rest_api_init', ['SP_SmartPabloPlugin', 'setup_webhooks']);
add_action('wp_ajax_smartpablo_connect', ['SP_SmartPabloPlugin', 'connect']);
add_action('wp_ajax_smartpablo_save', ['SP_SmartPabloPlugin', 'save']);
add_action('woocommerce_checkout_create_order',  ['SP_SmartPabloPlugin', 'add_custom_fields_on_placed_order'], 10, 2);
add_action('wp_enqueue_scripts', ['SP_SmartPabloPlugin', 'enqueue_scripts']);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), ['SP_SmartPabloPlugin', 'add_settings_link']);
add_filter('woocommerce_webhook_payload', ['SP_SmartPabloPlugin', 'modify_order_webhook_payload'], 10, 4);
add_filter('woocommerce_billing_fields', ['SP_SmartPabloPlugin', 'add_billing_fields_to_checkout']);
register_uninstall_hook(plugin_basename(__FILE__), ['SP_SmartPabloPlugin', 'uninstall']);
