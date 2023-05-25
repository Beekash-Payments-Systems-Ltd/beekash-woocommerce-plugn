<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once ('BeekashStatus.php');
require_once ('Helpers.php');

class WC_Gateway_Beekash extends WC_Payment_Gateway_CC
{
    /**
     * Beekash test publish key.
     *
     * @var string
     */
    public $publish_key;

    /**
     * Beekash test voucherkard id.
     *
     * @var string
     */
    public $voucherkard_id;
    /**
     * Should custom metadata be enabled?
     *
     * @var bool
     */
    public $custom_metadata;
    /**
     * Should the order id be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_order_id;

    /**
     * Should the customer name be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_name;

    /**
     * Should the billing email be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_email;

    /**
     * Should the billing phone be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_phone;

    /**
     * Should the billing address be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_billing_address;

    /**
     * Should the shipping address be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_shipping_address;

    /**
     * Should the order items be sent as a custom metadata to Beekash?
     *
     * @var bool
     */
    public $meta_products;

    /**
     * Should the order items status be moved to completed after successful transaction to Beekash?
     *
     * @var bool
     */
    public $auto_complete;

    public $checkout_url;

    public $id;

    public $helpers;

    public $webhook_endpoint;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->base_url = "https://api.beekash.net/api/v1/"; //API URL
        $this->website_url = "https://beekash.net";
        $this->docs_url = "https://docs.beekash.net";
        $this->beekash_auth_url = "https://api.beekash.net/sbt/api/v1/auth";
        $this->checkout_url = "https://checkout.api.beekash.net/api/v1/beekash.js";
        $this->order_url = "https://api.beekash.net/api/v1/payments/order";
        $this->beekash_token_encrypt_url = $this->base_url . "encrypt/keys";
        $this->beekash_transaction_verify = "https://api.beekash.net/api/v1/payments/query/";

        $this->id                 = 'beekash';
        $this->method_title       = __('Beekash', 'beekash-payment');
        $this->method_description = sprintf(__('Beekash - Experience seamless payment with Card, Banking, Transfer, Mobile Money, USSD', 'beekash-payment'), $this->website_url, $this->docs_url);
        $this->has_fields         = true;
        $this->helpers         = new Helpers();


        // Load the form fields
        $this->init_form_fields();
        // Load the settings
        $this->init_settings();

        // Get setting values
        $this->title       = $this->get_option('title');
        $this->webhook_endpoint = 'beekash_notification';
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');
        $this->publish_key = $this->get_option('publish_key');
        $this->voucherkard_id = $this->get_option('voucherkard_id');
        $this->meta_products = $this->get_option('meta_products') === 'yes' ? true : true;
        $this->auto_complete = $this->get_option( 'auto_complete' ) === 'yes' ? true : false;

        // Hooks
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        add_action('admin_notices', array($this, 'admin_notices'));

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        add_action( 'woocommerce_api_callback', array($this, 'verify_beekash_transaction'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action( 'woocommerce_api_' . $this->webhook_endpoint , array( $this, 'webhook' ) );

//        add_action('woocommerce_api_wc_gateway_beekash', array($this, 'verify_beekash_transaction') );

        // Check if the gateway can be used.
        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    public function webhook() {
        $this->helpers->Log('HIT WEBHOOK');
        if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) ) {
            http_response_code( 400 );
            exit;
        }

        $json = file_get_contents( 'php://input' );

        $request = json_decode( $json );

        $event = $request->notificationItems[0]->notificationRequestItem;

        $eventBody = $event->data;
        $eventType = $event->eventType;
        $reference = $eventBody->reference;
        $code = $eventBody->gatewayCode;
        $message = $eventBody->gatewayMessage;

        $reference_split = explode('_', $reference);
        $order_id = (int)$reference_split[0];
        $order = wc_get_order($order_id);

        $order_txn_ref = get_post_meta( $order_id, '_beekash_tran_ref', true );
        if ( $reference != $order_txn_ref ) {
            exit;
        }
        #validate transaction reference with the order

        if ($eventType === 'transaction') {
            if (BeekashStatus::STATUS_SUCCESSFUL == $code) {
                //check if order has been paid for already
                if($order->needs_payment()){
                    $order->payment_complete($order_id);
                    wc_reduce_stock_levels($order_id);
                    if ($this->auto_complete){
                        $order->update_status('completed',sprintf(__('Payment made with Beekash was successful and the order was auto-completed via Webhook notification (Transaction Reference: %s)', 'beekash-payment'), $reference));
                    }
                }
                exit;
            }else if (
                BeekashStatus::STATUS_PENDING == $code
                || BeekashStatus::STATUS_PENDING_2 == $code
                || BeekashStatus::STATUS_PENDING_3 == $code
            ) {
                //transaction pending
                $order->update_status('pending', sprintf(__('Payment confirmation is pending from Beekash. Reason: &1', 'beekash-payment'), $message));
                exit;
            } else
            {
                //transaction failed
                $order->update_status('failed', sprintf(__('Payment was declined by Beekash. Reason: &1', 'beekash-payment'), $message));
                exit;
            }
        }
        http_response_code( 200 );
    }


    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use()
    {

        if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_beekash_supported_currencies', array('NGN', 'USD', 'GBP', 'GHS','KES','TZS','XOF')))) {

            $this->msg = sprintf(__('Beekash does not support your store currency. Kindly set it to either NGN (&#8358), GHS (&#x20b5;), USD (&#36;), KES, TZS, XOF or GBP (&#163;) <a href="%s">here</a>', 'beekash-payment'), admin_url('admin.php?page=wc-settings&tab=general'));

            return false;
        }

        return true;
    }

    /**
     * Display beekash payment icon.
     */
    public function get_icon()
    {

        $icon = '<img src="' . plugins_url('assets/img/beekash.png', WC_SEERBIT_FILE) . '" alt="beekash" style="width:100px;margin-top: 5px"/>';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Check if Beekash merchant details is filled.
     */
    public function admin_notices()
    {
        if ($this->enabled == 'no') {
            return;
        }
        // Check required fields.
        if (!($this->publish_key && $this->voucherkard_id)) {
            echo '<div class="error"><p>' . sprintf(__('Please enter your Beekash merchant details <a href="%s">here</a> to be able to use the Beekash WooCommerce plugin.', 'beekash-payment'), admin_url('admin.php?page=wc-settings&tab=checkout&section=beekash')) . '</p></div>';
            return;
        }
        if ($this->auto_complete) {
            echo '<div class="notice notice-warn is-dismissible"><p>' . __('Auto Complete order status is active. Successful transactions will automatically update linked order status to completed.', 'beekash-payment') . '</p></div>';
        }else{
            echo '';
        }


    }
    

    /**
     * Check if Beekash gateway is enabled.
     *
     * @return bool
     */
    public function is_available()
    {

        if ('yes' == $this->enabled) {

            if (!($this->publish_key && $this->voucherkard_id)) {

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Admin Panel Options.
     */
    public function admin_options()
    {

        ?>
        <h2><?php _e('Beekash', 'beekash-payment'); ?>
            <?php
            if (function_exists('wc_back_link')) {
                wc_back_link(__('Return to payments', 'beekash-payment'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            }
            ?>
        </h2>
        <div class="beekash_styling">
            <h5>
            <strong><?php printf( __( 'Optional: In cases where transactions are not verified immediately after payment a webhook URL needs to be setup on your <a href="%1$s" target="_blank" rel="noopener noreferrer">Merchant Dashboard</a><span style="color: red"><pre><code>%2$s</code></pre></span>', 'beekash-payment' ), 'https://dashboard.beekash.net/#/account/webhooks', WC()->api_request_url( $this->webhook_endpoint ) ); ?></strong>
        </h5>
        </div>
        <?php

        if ($this->is_valid_for_use()) {
            echo '<div class="beekash_styling">';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
            echo '</div>';
        } else {
            ?>
            <div class="inline error">
                <p><strong><?php _e('Beekash Payment Gateway Disabled', 'beekash-payment'); ?></strong>: <?php echo $this->msg; ?></p>
            </div>

            <?php
        }

    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = require( dirname( __FILE__ ).'/admin/beekash-settings.php' );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        if (!is_ssl()) {
            return;
        }
    }

    /**
     * Outputs scripts used for beekash payment.
     */
    public function payment_scripts()
    {
        //check if the page is checkout or cart page
        if ( ! is_checkout_pay_page()) {
            return;
        }

        //check if payment option is enabled
        if ($this->enabled === 'no') {
            return;
        }

        //check if the merchant has set up publish key
        if ( empty( $this->publish_key ) ) {
            return;
        }

        //if checkout redirects with linkingReference, then verify the transaction
        if (isset($_GET['reference'])){
            $this->verify_beekash_transaction();
            return;
        }

        $order_key = urldecode($_GET['key']);
        $order_id  = absint(get_query_var('order-pay'));

        if ( ! $order = wc_get_order( $order_id ) ) {
            return;
        }

        $payment_method = method_exists($order, 'get_payment_method') ? $order->get_payment_method() : $order->payment_method;

        if ($this->id !== $payment_method) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('beekash', $this->checkout_url, array('jquery'), date("h:i:s"), false);
        wp_enqueue_script('wc_beekash', plugins_url('assets/js/beekash.js', WC_SEERBIT_FILE), array('jquery', 'beekash'), WC_SEERBIT_VERSION, false);

        $params = array(
            'publish_key' => $this->publish_key
        );
        if (is_checkout_pay_page() && get_query_var('order-pay')) {

            $amount        = $order->get_total();
            $tranref        = $order_id . '_' . time() . '_woocommerce_' . strtolower(str_replace(' ', '', get_bloginfo( 'name' )));
            $the_order_id  = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
            $the_order_key = method_exists($order, 'get_order_key') ? $order->get_order_key() : $order->order_key;
            $first_name = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
            $last_name  = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
            $currency = get_woocommerce_currency();
            if ($the_order_id == $order_id && $the_order_key == $order_key) {
                $params['tranref']      = $tranref;
                $params['currency']     = $currency;
                $params['country']     = $order->get_billing_country();
                $params['amount']       = $amount;
                $params['customer_name'] = $first_name . ' ' . $last_name;
                $params['phone_number'] =  method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : $order->billing_phone;
                $params['encrypted_token'] = $this->GetEncryptedToken();
                $params['endpoint'] = $this->order_url;
                $params['customer_email'] = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
            }

            if ($this->meta_products) {
                $line_items = $order->get_items();
                $products =  array();
                foreach ($line_items as $item) {
                    $name      = $item['name'];
                    $quantity  = $item['qty'];
                    $description = $name . ' (Qty: ' . $quantity . ')';
                    $price = $item["subtotal"];
                    $array = array(
                        "productId" => $item['product_id'],
                        "productDescription" => $description,
                        "orderId" => $the_order_id,
                        "currency" => $currency,
                        "amount" => $price
                    );
                    array_push($products, $array);
                }

                $params['meta_products'] = $products;
            };
            update_post_meta($order_id, '_beekash_tran_ref', $tranref);
        }
        wp_localize_script('wc_beekash', 'wc_params', $params);
    }

    public function GetEncryptedToken()
    {
        $client_secret =  $this->voucherkard_id . "." . $this->publish_key;
        $beekash_auth_args =
            array(
                'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
                'body'        =>  json_encode(['key' => $client_secret], true),
                'method'      => 'POST'
            );
        $token_request = wp_remote_post($this->beekash_token_encrypt_url, $beekash_auth_args);
        $token_response = json_decode(wp_remote_retrieve_body($token_request));
        return $token_response;
    }
    /**
     * Process the payment.
     *
     * @param int $order_id
     *
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order( $order_id );

        // Return thank you page redirect.
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        );
    }

    /**
     * Displays the payment page.
     *
     * @param $order_id
     */
    public function receipt_page($order_id)
    {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with Beekash.', 'beekash-payment') . '</p>';
        echo '<div id="beekash_form"><form id="order_review" method="post" action="' . WC()->api_request_url('WC_Gateway_Beekash') . '"></form><button class="button alt" id="beekash-payment-button">' . __('Pay Now', 'beekash-payment') . '</button> <a class="button cancel" href="' . wc_get_cart_url() . '">' . __('Cancel', 'beekash-payment') . '</a></div>';
    }
    /**
     * Verify Beekash payment.
     */
    public function verify_beekash_transaction()
    {
        @ob_clean();
        if ( isset( $_REQUEST['reference'] ) ) {
            $beekash_encrypt_keys_header =
                array(
                    'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                    'body' => json_encode(['key' => $this->voucherkard_id . "." . $this->publish_key], true),
                    'method' => 'POST'
                );
            $token_request = wp_remote_post("https://api.beekash.net/api/v1/encrypt/keys", $beekash_encrypt_keys_header);
            if (!is_wp_error($token_request) && 200 === wp_remote_retrieve_response_code($token_request)) {
                $token_response = json_decode(wp_remote_retrieve_body($token_request));
                $this->helpers->Log('GENERATING TOKEN SUCCESSFUL: RESPONSE' . json_encode($token_response));
                $token = (string)$token_response->data->EncryptedSecKey->encryptedKey;
                $trans_ref = $_REQUEST['reference'];
                $beekash_transaction_verify = $this->beekash_transaction_verify . sanitize_text_field($trans_ref);
                $headers = array(
                    'Authorization' => 'Bearer ' . $token,
                    'method' => 'GET'
                );
                $args = array(
                    'headers' => $headers,
                    'timeout' => 60,
                );
                //Verify transaction validation response from Beekash
                $request = wp_remote_get($beekash_transaction_verify, $args);
                if (!is_wp_error($request) && 200 === wp_remote_retrieve_response_code($request)) {
                    $beekash_response = json_decode(wp_remote_retrieve_body($request));
                    $this->helpers->Log('TRANSACTION VALIDATION SUCCESSFUL: RESPONSE: ' . json_encode($beekash_response));
                    if (BeekashStatus::STATUS_SUCCESSFUL == $beekash_response->data->code) {
                        //transaction successful
                        $order_details = explode('_', $trans_ref);
                        $order_id = (int)$order_details[0];
                        $order = wc_get_order($order_id);
                        //check if order has been paid for already
                            if($order->needs_payment()){
                                $order->payment_complete($order_id);
                                wc_reduce_stock_levels($order_id);
                                if ($this->auto_complete){
                                    $order->update_status('completed',sprintf(__('Payment via Beekash was successful and order was auto completed (Transaction Reference: %s)', 'beekash-payment'), $trans_ref));
                                }
                            }
                        wp_redirect($this->get_return_url($order));
                        exit;
                    }else if (
                        BeekashStatus::STATUS_PENDING == $beekash_response->data->code
                        || BeekashStatus::STATUS_PENDING_2 == $beekash_response->data->code
                        || BeekashStatus::STATUS_PENDING_3 == $beekash_response->data->code
                    ) {
                        //transaction pending
                        $order_details = explode('_', $trans_ref);
                        $order_id = (int)$order_details[0];
                        $order = wc_get_order($order_id);
                        $order->update_status('pending', __('Payment confirmation is pending from Beekash.', 'beekash-payment'));
                        exit;
                    } else
                    {
                        //transaction failed
                        $order_details = explode('_', $trans_ref);
                        $order_id = (int)$order_details[0];
                        $order = wc_get_order($order_id);
                        $order->update_status('failed', __('Payment was declined by Beekash.', 'beekash-payment'));
                        exit;
                    }
                }else{
                    $beekash_response = json_decode(wp_remote_retrieve_body($request));
                    $this->helpers->Log('TRANSACTION VALIDATION FAILED: RESPONSE: ' . json_encode($beekash_response));
                    wc_add_notice(  'Please try again.', 'error' );
                    exit;
                }
            }
            $this->helpers->Log('ERROR GENERATING TOKEN: RESPONSE' . json_encode(wp_remote_retrieve_body($token_request)));
            wc_add_notice(  'Unable to complete. Kindly contact support.', 'error' );
            exit;
        }
        $this->helpers->Log('VERIFY TRANSACTION: ELSE CONDITION WHEN REFERENCE IS NOT SET');
        wp_redirect( wc_get_page_permalink( 'cart' ) );
        exit;
    }

    /**
     * Get custom fields to pass to Beekash.
     *
     * @param int $order_id WC Order ID
     *
     * @return array
     */
    public function get_custom_fields($order_id)
    {

        $order = wc_get_order($order_id);

        $custom_fields = array();

        $custom_fields[] = array(
            'display_name'  => 'Plugin',
            'variable_name' => 'plugin',
            'value'         => 'beekash-payment',
        );

        if ($this->meta_order_id) {

            $custom_fields[] = array(
                'display_name'  => 'Order ID',
                'variable_name' => 'order_id',
                'value'         => $order_id,
            );
        }

        if ($this->meta_name) {

            $first_name = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
            $last_name  = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;

            $custom_fields[] = array(
                'display_name'  => 'Customer Name',
                'variable_name' => 'customer_name',
                'value'         => $first_name . ' ' . $last_name,
            );
        }

        if ($this->meta_email) {

            $email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;

            $custom_fields[] = array(
                'display_name'  => 'Customer Email',
                'variable_name' => 'customer_email',
                'value'         => $email,
            );
        }

        if ($this->meta_phone) {

            $billing_phone = method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : $order->billing_phone;

            $custom_fields[] = array(
                'display_name'  => 'Customer Phone',
                'variable_name' => 'customer_phone',
                'value'         => $billing_phone,
            );
        }

        if ($this->meta_products) {

            $line_items = $order->get_items();

            $products = '';

            foreach ($line_items as $item_id => $item) {
                $name     = $item['name'];
                $quantity = $item['qty'];
                $products .= $name . ' (Qty: ' . $quantity . ')';
                $products .= ' | ';
            }

            $products = rtrim($products, ' | ');

            $custom_fields[] = array(
                'display_name'  => 'Products',
                'variable_name' => 'products',
                'value'         => $products,
            );
        }

        if ($this->meta_billing_address) {

            $billing_address = $order->get_formatted_billing_address();
            $billing_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $billing_address));

            $params['meta_billing_address'] = $billing_address;

            $custom_fields[] = array(
                'display_name'  => 'Billing Address',
                'variable_name' => 'billing_address',
                'value'         => $billing_address,
            );
        }

        if ($this->meta_shipping_address) {

            $shipping_address = $order->get_formatted_shipping_address();
            $shipping_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $shipping_address));

            if (empty($shipping_address)) {

                $billing_address = $order->get_formatted_billing_address();
                $billing_address = esc_html(preg_replace('#<br\s*/?>#i', ', ', $billing_address));

                $shipping_address = $billing_address;
            }
            $custom_fields[] = array(
                'display_name'  => 'Shipping Address',
                'variable_name' => 'shipping_address',
                'value'         => $shipping_address,
            );
        }

        return $custom_fields;
    }
    /**
     * Checks if WC version is less than passed in version.
     *
     * @param string $version Version to check against.
     *
     * @return bool
     */
    public static function is_wc_lt($version)
    {
        return version_compare("WC_VERSION", $version, '<');
    }
}
