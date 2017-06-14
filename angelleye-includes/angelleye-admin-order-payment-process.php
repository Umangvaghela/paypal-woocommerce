<?php

class AngellEYE_Admin_Order_Payment_Process {

    public $gateway;
    public $payment_method;
    public $credentials;
    public $paypal;
    public $utility;
    public $gateway_calculation;
    public $gateway_settings;

    public function __construct() {
        if (is_admin() && !defined('DOING_AJAX')) {
            add_action('add_meta_boxes', array($this, 'angelleye_add_meta_box'), 99);
            add_action('woocommerce_process_shop_order_meta', array($this, 'angelleye_admin_process_payment'), 51, 2);
            add_action('angelleye_admin_order_payment_process_action_hook', array($this, 'angelleye_admin_order_payment_process_action'), 10, 1);
        }
    }

    public function angelleye_add_meta_box() {
        add_meta_box('angelleye_admin_order_payment_process', __('Proceed to payment', 'paypal-for-woocommerce'), array($this, 'admin_order_payment_process'), 'shop_order', 'side', 'high');
    }

    public function angelleye_admin_process_payment($post_id, $post) {
        if (!empty($_POST['save']) && $_POST['save'] == 'Place order') {
            if (!empty($_POST['angelleye_admin_order_payment_process_action'])) {
                if (wp_verify_nonce($_POST['angelleye_admin_order_payment_process_action'], 'angelleye_admin_order_payment_process')) {
                    if (empty($post_id)) {
                        return false;
                    }
                    if ($post->post_type != 'shop_order') {
                        return false;
                    }
                    $order = wc_get_order($post_id);
                    do_action('angelleye_admin_order_payment_process_action_hook', $order);
                }
            }
        }
    }

    public function angelleye_admin_order_payment_process_action($order) {
        $this->payment_method = $order->get_payment_method();
        switch ($this->payment_method) {
            case 'paypal_express': {
                    $this->angelleye_ec_pp_pf_reference_transaction($order);
                }
                break;
            case 'braintree': {
                    $this->angelleye_braintree_reference_transaction($order);
                }
                break;
            case 'paypal_credit_card_rest': {
                    $this->angelleye_paypal_credit_card_rest_reference_transaction($order);
                }
                break;
            case 'paypal_advanced': {
                    $this->angelleye_paypal_advanced_reference_transaction($order);
                }
                break;
            case 'paypal_pro': {
                    $this->angelleye_ec_pp_pf_reference_transaction($order);
                }
                break;
            case 'paypal_pro_payflow': {
                    $this->angelleye_paypal_pro_payflow_reference_transaction($order);
                }
                break;
        }
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
    }

    public function admin_order_payment_process($post) {
        $order_id = $post->ID;
        $order = wc_get_order($order_id);
        if ($this->angelleye_is_order_created_by_admin($order) && $this->angelleye_is_order_status_pending($order) == true) {
            $reason_array = $this->angelleye_get_reason_why_place_order_button_not_available($order);
            $reason_message = $this->angelleye_reason_array_to_nice_message($reason_array);
            $this->angelleye_place_order_button($reason_message);
        } else {
            $this->angelleye_hide_metabox();
        }
    }

    public function angelleye_reason_array_to_nice_message($reason_array) {
        $reason_message = '';
        if (!empty($reason_array)) {
            $reason_message .= '<ul>';
            foreach ($reason_array as $key => $value) {
                $reason_message .= '<li>' . $value . '</li>';
            }
            $reason_message .= '</ul>';
        }
        return $reason_message;
    }

    public function angelleye_get_reason_why_place_order_button_not_available($order) {
        $reason_array = array();
        $token_list = $this->angelleye_is_usable_reference_transaction_avilable($order);
        if ($this->angelleye_is_order_status_pending($order) == false) {
            $reason_array[] = __('Order status must be pending for payment process.', 'paypal-for-woocommerce');
        }
        if ($this->angelleye_is_order_user_selected($order) == false) {
            $reason_array[] = __('Customer must be selected for order.', 'paypal-for-woocommerce');
        }
        if ($this->angelleye_is_order_payment_method_selected($order) == false) {
            $reason_array[] = __('Payment method is not available for payment process, Please select Payment method from Billing details section.', 'paypal-for-woocommerce');
        } else {
            if (empty($token_list) && $this->angelleye_is_order_user_selected($order) == true) {
                $reason_array[] = __('Payment Token Or Reference transaction ID is not available for payment process.', 'paypal-for-woocommerce');
            }
        }
        if ($this->angelleye_is_order_need_payment($order) == false) {
            $reason_array[] = __('Order total must be greater than zero an amount for payment process.', 'paypal-for-woocommerce');
        }
        if( !empty($reason_array) ) {
            $reason_array[] = __("don't forget to press update button after done with above thing.", 'paypal-for-woocommerce');
        }
        return $reason_array;
    }

    public function angelleye_is_order_created_by_admin($order) {
        return ($order->get_created_via() == '') ? true : false;
    }

    public function angelleye_is_usable_reference_transaction_avilable($order) {
        $token_list = $this->get_usable_reference_transaction($order);
        return (!empty($token_list)) ? $token_list : false;
    }

    public function angelleye_is_order_status_pending($order) {
        return ($order->get_status() == 'pending') ? true : false;
    }

    public function angelleye_is_order_status_auto_draft($order) {
        return ($order->get_status() == 'auto-draft') ? true : false;
    }

    public function angelleye_is_order_need_payment($order) {
        return ($order->get_total() > 0) ? true : false;
    }

    public function angelleye_is_order_payment_method_selected($order) {
        return ($order->get_payment_method() != '') ? true : false;
    }

    public function angelleye_is_order_user_selected($order) {
        return ($order->get_user_id() != '0') ? true : false;
    }

    public function is_display_admin_order_payment_process_box($order) {
        if ($order->get_status() == 'pending' && $order->get_created_via() == '' && $order->get_total() > 0 && $order->get_payment_method() != '' && $order->get_user_id() != '0') {
            return true;
        } else {
            false;
        }
    }

    public function get_usable_reference_transaction($order) {
        $this->payment_method = $order->get_payment_method();
        $user_id = $order->get_user_id();
        if (in_array($this->payment_method, array('paypal_express', 'braintree', 'paypal_credit_card_rest', 'paypal_advanced', 'paypal_pro', 'paypal_pro_payflow'))) {
            return $this->angelleye_get_all_tokens_by_payment_method($user_id, $order);
        }
    }

    public function angelleye_get_all_tokens_by_payment_method($user_id, $order) {
        $this->payment_method = $order->get_payment_method();
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, $this->payment_method);
        if (!empty($tokens)) {
            return $this->angelleye_get_payment_token_list($tokens);
        } else {
            if (in_array($this->payment_method, array('paypal_pro', 'paypal_pro_payflow', 'paypal_advanced'))) {
                return $this->angelleye_get_transaction_id_by_payment_method($order, $this->payment_method);
            } else {
                return array();
            }
        }
    }

    public function angelleye_get_transaction_id_by_payment_method($order, $payment_method) {
        global $wpdb;
        $tokens_array = array();
        $user_id = $order->get_user_id();
        $ids = $wpdb->get_results("SELECT id
		FROM $wpdb->posts AS posts
		LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id 
                LEFT JOIN {$wpdb->postmeta} AS meta1 on posts.ID = meta1.post_id 
                WHERE
		meta.meta_key = '_customer_user' AND   meta.meta_value = {$user_id} AND
                meta1.meta_key = '_payment_method' AND   meta1.meta_value = '{$payment_method}'
		AND   posts.post_type = 'shop_order'
		AND   posts.post_status IN ( 'wc-processing','wc-completed' )
		ORDER BY posts.ID DESC
	", ARRAY_A);
        if (!empty($ids)) {
            foreach ($ids as $key => $value) {
                $transaction_id = get_post_meta($value['id'], '_transaction_id', true);
                if (!empty($transaction_id)) {
                    $tokens_array[] = $transaction_id;
                }
            }
        }
        return $tokens_array;
    }

    public function angelleye_load_payment_method_setting($order) {
        $this->payment_method = $order->get_payment_method();
        if (WC()->payment_gateways()) {
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            if (isset($payment_gateways[$this->payment_method])) {
                $this->gateway_settings = $payment_gateways[$this->payment_method]->settings;
                $this->gateway = $payment_gateways[$this->payment_method];
            }
        }
        switch ($this->payment_method) {
            case (in_array($this->payment_method, array('paypal_express', 'paypal_pro', 'paypal_pro_payflow'))): {
                    if (empty($this->utility)) {
                        $this->utility = new AngellEYE_Utility(null, null);
                    }
                    $this->utility->add_ec_angelleye_paypal_php_library();
                    $this->paypal = $this->utility->paypal;
                }
                break;
            case 'braintree' : {
                    
                }
                break;
            case 'paypal_advanced': {
                    echo 'paypal_advanced';
                }
                break;
            case 'paypal_credit_card_rest': {
                    
                }
                break;
        }
    }

    public function angelleye_reference_transaction_request_ec_pp_pf($order, $referenceid) {
        $this->angelleye_load_calculation();
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $PayPalRequestData = array();
        $DRTFields = array(
            'referenceid' => $referenceid,
            'paymentaction' => ($this->gateway_settings['payment_action'] == 'Authorization' || $order->get_total() == 0 ) ? 'Authorization' : $this->gateway_settings['payment_action'],
            'returnfmfdetails' => '1',
            'softdescriptor' => $this->gateway_settings['softdescriptor']
        );
        $PayPalRequestData['DRTFields'] = $DRTFields;
        $customer_note_value = version_compare(WC_VERSION, '3.0', '<') ? wptexturize($order->customer_note) : wptexturize($order->get_customer_note());
        $customer_notes = $customer_note_value ? substr(preg_replace("/[^A-Za-z0-9 ]/", "", $customer_note_value), 0, 256) : '';
        $PaymentDetails = array(
            'amt' => AngellEYE_Gateway_Paypal::number_format($order->get_total()),
            'currencycode' => version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency(),
            'custom' => apply_filters('ae_ppec_custom_parameter', json_encode(array('order_id' => $order_id, 'order_key' => version_compare(WC_VERSION, '3.0', '<') ? $order->order_key : $order->get_order_key()))),
            'invnum' => $this->gateway_settings['invoice_id_prefix'] . preg_replace("/[^a-zA-Z0-9]/", "", str_replace("#", "", $order->get_order_number())),
            'notetext' => $customer_notes
        );
        if (isset($this->gateway_settings['notifyurl']) && !empty($this->gateway_settings['notifyurl'])) {
            $PaymentDetails['notifyurl'] = $this->gateway_settings['notifyurl'];
        }
        if ($order->needs_shipping_address()) {
            $shipping_first_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_first_name : $order->get_shipping_first_name();
            $shipping_last_name = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_last_name : $order->get_shipping_last_name();
            $shipping_address_1 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_1 : $order->get_shipping_address_1();
            $shipping_address_2 = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_address_2 : $order->get_shipping_address_2();
            $shipping_city = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_city : $order->get_shipping_city();
            $shipping_state = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_state : $order->get_shipping_state();
            $shipping_postcode = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_postcode : $order->get_shipping_postcode();
            $shipping_country = version_compare(WC_VERSION, '3.0', '<') ? $order->shipping_country : $order->get_shipping_country();
            $ShippingAddress = array('shiptoname' => $shipping_first_name . ' ' . $shipping_last_name,
                'shiptostreet' => $shipping_address_1,
                'shiptostreet2' => $shipping_address_2,
                'shiptocity' => wc_clean(stripslashes($shipping_city)),
                'shiptostate' => $shipping_state,
                'shiptozip' => $shipping_postcode,
                'shiptocountrycode' => $shipping_country,
                'shiptophonenum' => '',
            );
            $PayPalRequestData['ShippingAddress'] = $ShippingAddress;
        }
        $this->order_param = $this->gateway_calculation->order_calculation($order_id);
        if ($this->gateway_settings['send_items']) {
            $Payment['order_items'] = $this->order_param['order_items'];
        } else {
            $Payment['order_items'] = array();
        }
        $PaymentDetails['taxamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['taxamt']);
        $PaymentDetails['shippingamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['shippingamt']);
        $PaymentDetails['itemamt'] = AngellEYE_Gateway_Paypal::number_format($this->order_param['itemamt']);
        $PayPalRequestData['PaymentDetails'] = $PaymentDetails;
        return $PayPalRequestData;
    }

    public function angelleye_get_payment_token_list($tokens) {
        $tokens_array = array();
        $i = 0;
        foreach ($tokens as $key => $token) {
            $tokens_array[$i] = $token->get_token();
            $i = $i + 1;
        }
        return $tokens_array;
    }

    public function angelleye_hide_metabox() {
        ?>
        <style type="text/css">
            #angelleye_admin_order_payment_process {
                display: none;
            }
        </style>
        <?php

    }

    public function angelleye_load_calculation() {
        if (!class_exists('WC_Gateway_Calculation_AngellEYE')) {
            require_once( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-calculations-angelleye.php' );
        }
        $this->gateway_calculation = new WC_Gateway_Calculation_AngellEYE();
    }

    public function angelleye_ec_pp_pf_reference_transaction($order) {
        $tokens = $this->get_usable_reference_transaction($order);
        if (!empty($tokens)) {
            $this->angelleye_load_payment_method_setting($order);
            $PayPalRequestData = $this->angelleye_reference_transaction_request_ec_pp_pf($order, $tokens[0]);
            $result = $this->paypal->DoReferenceTransaction($PayPalRequestData);
            if (!empty($result['ACK']) && ($result['ACK'] == 'Success' || $result['ACK'] == 'SuccessWithWarning')) {
                $order->payment_complete($result['TRANSACTIONID']);
                $order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'paypal-for-woocommerce'), $this->payment_method, $result['TRANSACTIONID']));
            } else {
                if (!empty($result['L_ERRORCODE0'])) {
                    $ErrorCode = urldecode($result['L_ERRORCODE0']);
                } else {
                    $ErrorCode = '';
                }
                if (!empty($result['L_SHORTMESSAGE0'])) {
                    $ErrorShortMsg = urldecode($result['L_SHORTMESSAGE0']);
                } else {
                    $ErrorShortMsg = '';
                }
                if (!empty($result['L_LONGMESSAGE0'])) {
                    $ErrorLongMsg = urldecode($result['L_LONGMESSAGE0']);
                } else {
                    $ErrorLongMsg = '';
                }
                if (!empty($result['L_SEVERITYCODE0'])) {
                    $ErrorSeverityCode = urldecode($result['L_SEVERITYCODE0']);
                } else {
                    $ErrorSeverityCode = '';
                }
                $message = sprintf(__('PayPal %s API call failed', 'paypal-for-woocommerce') . PHP_EOL . __('Detailed Error Message: %s', 'paypal-for-woocommerce') . PHP_EOL . __('Short Error Message: %s', 'paypal-for-woocommerce') . PHP_EOL . __('Error Code: %s', 'paypal-for-woocommerce') . PHP_EOL . __('Error Severity Code: %s', 'paypal-for-woocommerce'), 'DoReferenceTransaction', $ErrorLongMsg, $ErrorShortMsg, $ErrorCode, $ErrorSeverityCode);
                $order->add_order_note($message);
            }
        }
    }

    public function angelleye_braintree_reference_transaction($order) {
        $tokens = $this->get_usable_reference_transaction($order);
        if (!empty($tokens)) {
            $this->angelleye_load_payment_method_setting($order);
            if (class_exists('WC_Gateway_Braintree_AngellEYE')) {
                $braintree = new WC_Gateway_Braintree_AngellEYE();
                $braintree->process_subscription_payment($order, $amount = '', $tokens[0]);
            }
        }
    }

    public function angelleye_place_order_button($reason_message) {
        $is_disable = '';
        if (!empty($reason_message)) {
            $is_disable = 'disabled';
        }
        echo '<div class="wrap angelleye_admin_order_process">' . $reason_message . '<input type="hidden" name="angelleye_admin_order_payment_process_action" id="angelleye_admin_order_payment_process" value="' . wp_create_nonce('angelleye_admin_order_payment_process') . '" /><input type="submit" ' . $is_disable . ' id="angelleye_payment_submit_button" value="Place order" name="save" class="button button-primary"></div>';
    }

    public function angelleye_paypal_credit_card_rest_reference_transaction($order) {
        $tokens = $this->get_usable_reference_transaction($order);
        if (!empty($tokens)) {
            $this->angelleye_load_payment_method_setting($order);
            if (empty($this->paypal_rest_api)) {
                if (class_exists('PayPal_Rest_API_Utility')) {
                    $this->paypal_rest_api = new PayPal_Rest_API_Utility($this->gateway);
                } else {
                    include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/angelleye-includes/paypal-rest-api-utility.php' );
                    $this->paypal_rest_api = new PayPal_Rest_API_Utility($this->gateway);
                }
            }
            $this->paypal_rest_api->admin_process_payment($order, $tokens[0]);
        }
    }

    public function angelleye_paypal_advanced_reference_transaction($order) {
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();
        $tokens = $this->get_usable_reference_transaction($order);
        if (!empty($tokens)) {
            $this->angelleye_load_payment_method_setting($order);
            if (class_exists('WC_Gateway_PayPal_Advanced_AngellEYE')) {
                $paypal_advanced = new WC_Gateway_PayPal_Advanced_AngellEYE();
                $paypal_advanced->create_reference_transaction($tokens[0], $order);
                $inq_result = $paypal_advanced->inquiry_transaction($order, $order_id);
                if ($inq_result == 'Approved') {
                    $order->payment_complete($tokens[0]);
                    $order->add_order_note(sprintf(__('Payment completed for the  (Order: %s)', 'paypal-for-woocommerce'), $order->get_order_number()));
                    if ($paypal_advanced->debug == 'yes') {
                        $paypal_advanced->log->add('paypal_advanced', sprintf(__('Payment completed for the  (Order: %s)', 'paypal-for-woocommerce'), $order->get_order_number()));
                    }
                }
            }
        }
    }

    public function angelleye_paypal_pro_payflow_reference_transaction($order) {
        $tokens = $this->get_usable_reference_transaction($order);
        if (!empty($tokens)) {
            $this->angelleye_load_payment_method_setting($order);
            if (class_exists('WC_Gateway_PayPal_Pro_PayFlow_AngellEYE')) {
                $paypal_pro_payflow = new WC_Gateway_PayPal_Pro_PayFlow_AngellEYE();
                $paypal_pro_payflow->process_subscription_payment($order, $amount = '', $tokens[0]);
            }
        }
    }

}