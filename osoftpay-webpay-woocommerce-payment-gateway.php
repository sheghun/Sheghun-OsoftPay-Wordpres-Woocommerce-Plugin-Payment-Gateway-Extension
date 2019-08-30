<?php
/**
 * Description: Osoftpay WooCommerce Payment Gateway allows you to accept payment on your Woocommerce store via Verve Card, Visa Card and MasterCard.

 * OsoftPay WordPress Plugin.

 * PHP Version 7

 * @author Oladiran Segun <sheghunoladiran9@gmail.com>

 * @license GNU General Public Lincense v 3.0
 * @version SVN: <1.0>

 * @link https://github.com/sheghun/Sheghun-OsoftPay-Wordpres-Woocommerce-Plugin-Payment-Gateway-Extension

 * @package WordPress Plugin

 * @description

 * Author: Oladiran Sheghun
 * Version: 1.0
 * Plugin URI: https://developer.osoftpay.net
 * Plugin Name: Osoftpay WooCommerce Payment Gateway
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Author URI: https://github.com/sheghun
 * GitHub Plugin URI: https://github.com/sheghun/Sheghun-OsoftPay-Wordpres-Woocommerce-Plugin-Payment-Gateway-Extension
 */

    /**
     * If Statement For Blocking Direct Acces To The Plugin Through Url
     * Order 1
     */
    if (! defined('ABSPATH')) {
        echo 'Sorry No Direct Access';
        exit();
    }

/**
 * Hooks When The Plugins Are Loaded To Call This Function Initializer.
 * Order 2
 */
add_action('plugins_loaded', 'sheghun_osoftpay_wc_webpay_init', 0);

/**
 * FUNCTION FOR INTAILIZING THE DOCUMENT WHICH CONTAINS THE CLASS.
 * @example add_action( 'plugins_loaded', 'sheghun_osoftpay_wc_webpay_init', 10 );
 * This Is The Function Being Called
 * Order 3
 */
function sheghun_osoftpay_wc_webpay_init()
{
    /**
     * Checks If WooCommerce Class Exists Else Kill The Page
    * Order 4
    */
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     *
    * Description: The Gateway Class Used For WooCommerce Payment
    * Order 6
    */
    class Sheghun_Osoftpay_Webpay_Gateway extends WC_Payment_Gateway
    {
        /**
         * The Class __Construct Function
         * Order 2
         */
        public function __construct()
        {
            // Set The Id Of The GateWay
            $this->id = 'sheghun_osoftpay_gateway';

            // Set The Icon Of The Gateway
            $this->icon = apply_filters('woocommerce_gateway_icon', plugins_url('assets/images/logo.png', __FILE__));

            // Sets If The Plugin Is Going To Have Fields
            $this->has_fields = false;

            /**
            * Load The Form Fields
            * @internal Runs The public function init_form_fields()
            */
            $this->init_form_fields();

            //Load the settings
            $this->init_settings();

            /**
            * Define The User Set Varaibles
            */
            $this->title              =  $this->get_option('title');
            $this->description        =  $this->get_option('description');

            // Get the mode
            $this->productionMode          = $this->get_option('productionMode');

            // Get The Merchant Number
            $this->merchant_id_test        =    $this->get_option('Merchant_ID_Testmode');
            $this->merchant_id_live        =    $this->get_option('Merchant_ID_Live');

            $this->merchant_id             =   $this->productionMode == 'no' ? $this->merchant_id_test : $this->merchant_id_live;

            // Get The PaymentItemName
            $this->paymentItemName_test      =   $this->get_option('Payment_ItemName_Testmode');
            $this->paymentItemName_live      =   $this->get_option('Payment_ItemName_Live');

            $this->paymentItemName          =   $this->productionMode == 'no' ? $this->paymentItemName_test : $this->paymentItemName_live ;

            $this->method_title       = 'Osoft Pay';
            $this->method_description = "Payments Made Easy MasterCard, Verve Card and Visa Card accepted";

            // Redirect Url || Response URL
            $this->redirect_url  = WC()->api_request_url('Sheghun_Osoftpay_Webpay_Gateway');

            // Test Url
            $this->testUrl = 'https://developer.osoftpay.net/api/TestPublicPayments';

            // Live Url
            $this->liveUrl = 'https://osoftpay.net/api/PublicPayments';

            $this->paymentUrl = $this->productionMode == 'no' ? $this->testUrl : $this->liveUrl;

            // This Action is used to call the reciept_page
            add_action('woocommerce_receipt_sheghun_osoftpay_gateway', array( $this, 'receipt_page' ));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
            add_action('woocommerce_thankyou_sheghun_osoftpay_gateway', array($this, 'thankyou_page'), 0);

            // Payment listener/API hook
            add_action('woocommerce_api_sheghun_osoftpay_webpay_gateway', array( $this, 'check_osoftpay_response' ));

            // Check If The Gateway Can Be Used
            if ($this->is_valid_for_use()) {
                $this->enabled = true;
            }
        }

        /**
        * Check if the store curreny is set to NGN
        **/
        public function is_valid_for_use()
        {
            if (! in_array(get_woocommerce_currency(), array('NGN'))) {
                $this->msg = 'OsoftPay Webpay doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
                return false;
            }

            return true;
        }

        /**
         * Check if this gateway is enabled
         */
        public function is_available()
        {
            if ($this->enabled == "yes") {
                if (! ($this->merchant_id) || ! $this->paymentItemName) {
                    return false;
                }
                return true;
            }

            return false;
        }


        /**
         * The Init Form Fields Function
         * Order 1
         * @param array $this->form_fields
         * @return void
        */
        public function init_form_fields()
        {
            /**
            * For Setting The Form Fields The Admin Will Need
            */
            $this->form_fields = array(
                /**
                 * A Check box For Setting If The GateWay Is Enable
                 */
                'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('OsoftPay Payments', 'woocommerce'),
                'default' => 'yes'
                ),

                /**
                * Title  Of The Gateway
                */
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Osoft Pay', 'woocommerce')
                ),

                /**
                * A Text Input For Storing The Merchant Number
                * Will Contain The Merchant Number
                */
                'Merchant_ID_Testmode' => [
                    'title'         => __('Merchant ID (testmode)', 'woocommerce'),
                    'type'          => 'text',
                    'description'   => __('This Is Your Unique Merchant Number Keep It Safe', 'woocommerce'),
                    'default'       => '',
                    'label'         => __('This Is Your Merchant Number Given To You On OsoftPay', 'woocommerce')
                ],

                /**
                 * For Setting The PaymentItemName
                 */
                'Payment_ItemName_Testmode' => [
                    'title'         => __('PaymentItemName (testmode)', 'woocommerce'),
                    'type'          => 'text',
                    'label'         =>   __('PaymentItemName', 'woocommerce'),
                    'default'       => '',
                    'description'   =>  __('The PaymentItemName for testmode set it to TestServiceName1 for test purpose only', 'woocommerce')
                ],

                /**
                 * Merchant Id For Live Payment
                 */
                'Merchant_ID_Live'  =>  [
                    'title'         =>  __('Merchant ID (LiveMode)', 'woocommerce'),
                    'type'          =>  'text',
                    'label'         =>  __('Merchant_ID_Live', 'woocommerce'),
                    'default'       =>  __('', 'woocommerce'),
                    'description'   =>  __('This is your live merchant id for real live transactions', 'woocommerce')
                ],

                /**
                 * Payment ItemName For Live
                 */
                'Payment_ItemName_Live' =>  [
                    'title'         =>  __('PaymentItemName (Live)', 'woocommerce'),
                    'type'          =>  'text',
                    'label'         =>  __('Payment_ItemName_LIve', 'woocommerce'),
                    'default'       =>  __('', 'woocommerce'),
                    'description'   => __('This is your live merchant PaymentItemName for real live transactions', 'woocommerce')
                ],

                /**
                 * For The Description Of The Gateway
                 */
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description'   => __('This is what your users will see when they click this gateway', 'woocommerce'),
                    'default' => __('Accepts Master Card, Visa Card and Verve Card', 'woocommerce')
                ),

                /**
                 * For Switch To Live Mode
                 */
                'productionMode' => [
                    'title'       	=> __('Go Live Production', 'woocommerce'),
                    'type'        	=> 'checkbox',
                    'label'       	=> __('Enable Live Production', 'woocommerce'),
                    'default'     	=> 'no',
                    'description' 	=> __('Production Mode Let\'s You Make And Recieve Real Life Transactions', 'woocommerce')
                ]

            );
        }

        /**
         * Process The Admin Options
         */
        public function admin_options()
        {
            echo '<h3>OsoftPay</h3>';
            echo '<p>OsoftPay allows you to accept MasterCard, Verve and Visa payment.</p>';
            // print_r( $this->paymentUrl );
            if (! $this->is_available() && $this->productionMode == 'no') : ?>
				<p><strong style="color: Red;">Please Input Your Merchant ID And Your Payment Item Name
				<br>
				Set TestServiceName1 For TestMode</strong></p>
			<?php else :
                if ($this->productionMode == 'yes' && ! $this->is_available()) : ?>
					<div style="padding: 15px;background-color:white;border-left: 3px solid red;margin:1rem 0;color: red;">
						<p>Please input your live Merchant ID in the Merchant ID (livemode) box and your Live PaymentItemName in the PaymentItemName (livemode) box</p>
					</div>
				<?php elseif ($this->productionMode == 'yes') : ?>
						<p><strong style="color: green" >You Are In Production Mode</strong><p>
				<?php endif;
            endif;
            if ($this->is_valid_for_use()) :?>
				<table class="form-table">
				<?php $this->generate_settings_html(); ?>
				</table>;
				<div style="padding: 15px;background-color: white;">
					Visit <a href="https://osoftpay.net" target="_blank">Osoftpay.net</a>  For More Details
				</div>
			<?php else: ?>
			<div class="inline error">
				<p><strong>OsoftPay Payment Gateway Disabled</strong>:
				<?php echo $this->msg ?></p>
			</div>
			<?php endif;
        }

        /**
         * Process The Payments
         * @param int $order_id
         * @return array(string)
         */
        public function process_payment($order_id)
        {

            // Get The Order
            $order 	= new WC_Order($order_id);

            // Return Results
            return array(
                'result' 	=> 'success',
                // Redirect To the CheckOut Page
                'redirect'	=> $order->get_checkout_payment_url(true)
            );
        }


        /**
         * Dont know yet
         * Been Coding now for Up to Ten Hours
         * I Want to Be Rich
         * I Think PHP Syntax Is Too Traditional
         * I Love My Self
         * I Think I'm The Best
         */
        public function get_osoftpay_args($order)
        {
            $order_total = $order->get_total();

            //Get The Order Id
            $order_id = $order->get_id();

            // Get The Merchant ID Set By The Admin
            $merchant_id = $this->merchant_id;

            // Response Url
            $redirect_url = $this->redirect_url;

            // Get The Order Id
            $trac_ref = uniqid();
            $trac_ref = $trac_ref . '_' . $order_id;

            // Get The Customer Name
            $customer_name =  $order->get_formatted_billing_full_name();

            // Get The Customer Id Which Is The Email
            $customer_id = $order->get_billing_email();

            // Set The Transaction_Type
            $transaction_type = 2;

            // MerchantNumber+TransactionReference+TransactionType+Amount+SiteRedirectURL
            $hash = $merchant_id.$trac_ref.$transaction_type.$order_total.$redirect_url;
            // Hashing With Sha512
            $hash = hash('sha512', $hash);
            //print_r($hash);

            /**
             * Array With All The Arguments
             * @param array(string)
             */
            $osoftPay_args = [
                'TransactionType'       =>      $transaction_type,
                'MerchantNumber'        =>      $merchant_id,
                'SiteRedirectURL'       =>      $redirect_url,
                'TransactionReference'  =>      $trac_ref,
                'CustomerName'          =>      $customer_name,
                'CustomerId'            =>      $customer_id,
                'PaymentItemName'       =>      $this->paymentItemName,
                'Amount'                =>      $order_total,
                'hash'                  =>      $hash
            ];

            WC()->session->set('sheghun_osoftpay_gateway', $order_id);
            return $osoftPay_args;
        }

        /**
         * Function Generate The Payment Form And Redirection The Url
         */
        public function generate_webpay_form($order_id)
        {
            $order = wc_get_order($order_id);

            $osoft_pay_args = $this->get_osoftpay_args($order);

            $osoft_pay_args_array = [];

            // Do This Action Before Payment
            do_action('sheghun_wc_osoftpay_before_payment', $osoft_pay_args);

            foreach ($osoft_pay_args as $key => $value) :
                $osoft_pay_args_array[] = '<input type="hidden" name="'. esc_attr__($key, 'woocommerce') .'" value="'. esc_attr__($value, 'woocommerce') .'" >';
            endforeach;

            wc_enqueue_js('
				$.blockUI({
						message: "Thank you for your order. We are now redirecting you to OsoftPay to make payment.",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
				jQuery("#submit_webpay_payment_form").click();
		');
            return '<form action="' . $this->paymentUrl. '" method="post" id="webpay_payment_form">
					' . implode('', $osoft_pay_args_array) . '
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_webpay_payment_form" value="Pay via Interswitch Webpay" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">Cancel order &amp; restore cart</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
        }


        /**
         * Output for the order received page.
        **/
        public function receipt_page($order_id)
        {
            echo '<p>Thank you - your order is now pending payment. You will be automatically redirected to OsoftPay to make payment.</p>';
            print_r($this->generate_webpay_form($order_id));
        }

        public function check_osoftpay_response()
        {
            // If The Transaction Reference Is Not Empty

            $trac_ref           = $_GET[ 'TransactionReference' ];

            $order_details      = explode('_', $trac_ref);
            $trac_ref           = $order_details[0];
            $order_id           = $order_details[1];

            $order_id           =   (int) $order_id;

            $order              = new WC_Order($order_id);
            $order_total        = $order->get_total();
            $response           = $this->sheghun_osoftpay_transaction_details($trac_ref, $order_id, $order_total);
            if ($response["ResponseCode"] == '00' && $response["PayRef"] !== null | '') :

                    $message        =   'Payment Successful <br>' . $_GET[ 'ResDesc' ];
            $message_type   =   'success';

            $order->update_status('completed', 'Order Paid Successfully');

            // Add Admin Note
            $order->add_order_note('Payment Via OsoftPay <br >Transaction Reference: '. $_GET[ 'TransactionReference' ] .'<br />Payment Reference: '.$_GET[ 'PayRef' ]);

            // Reduce stock levels
            $order->reduce_order_stock();

            // Empty cart
            WC()->cart->empty_cart();

            //Set session variables
            WC()->session->set('TransactionReference', $response["TransactionReference"]);
            WC()->session->set('PayRef', $response["PayRef"]);
            WC()->session->set('ResponseDescription', $response["ResponseDescription"]);

            $message = 'Transaction Reference: ' . $response["TransactionReference"] . '<br>'
                    . 'Payment Reference: ' . $response["PayRef"] . '<br>'
                    . $response["ResponseDescription"] . '';

            $message_type = 'success';

            wc_add_notice($message, $message_type); else:

                    if (! empty($_GET[ 'ResDesc' ])) :

                        $message = 'Payment Failed. <br>' . $response["ResponseDescription"] ; else :

                        $message = 'Payment Failed';

            endif;


            $message_type = 'error';

            wc_add_notice($message, $message_type);

            wp_safe_redirect(wc_get_checkout_url());
            exit;

            endif;

            $notification_message = array(
                    'message'		=> $message,
                    'message_type' 	=> $message_type
                );

            $redirect_url = $this->get_return_url($order);

            wp_redirect($redirect_url);
        }

        public function thankyou_page($order_id)
        {
            $trans_ref  = WC()->session->get('TransactionReference');
            $pay_ref    = WC()->session->get('PayRef');
            $res_des    = WC()->session->get('ResponseDescription');
            $message = '';

            if ($trans_ref !== null) {
                $message = 'Transaction Reference: ' . $trans_ref . '<br>'
                . 'Payment Reference: ' . $pay_ref . '<br>'
                . $res_des . '';
            }

            echo $message;
            WC()->session->set('TransactionReference', null);
            WC()->session->set('PayRef', null);
            WC()->session->set('ResponseDescription', null);
        }


        /**
         * Queries the osoftpay server for the transaction details
         */
        public function sheghun_osoftpay_transaction_details($trac_ref, $order_id, $total)
        {

            // Get The Merchant Number
            $merchant_id         =   $this->merchant_id;

            // Get The Transaction Reference
            $trac_ref           =   $trac_ref . '_' . $order_id;

            // Get The Transaction Type
            $transaction_type   =   2;

            // Get The Total
            $total              =   $total;

            //Get The Redirect Url
            $redirect_url       =   $this->redirect_url;

            /**
             * Now We Have To Generate Our Hash
             * First We Concate The Strings
             * MerchantNumber+TransactionReference+TransactionType+Amount+SiteRedirectURL
             */
            $hash = $merchant_id.$trac_ref.$transaction_type.$total.$redirect_url;

            $hash = hash('sha512', $hash);

            // Url To Query For Our Status
            $testurl = "https://developer.osoftpay.net/api/TestPublicPayments?TransactionNumber=" . $trac_ref . "&Amount=". $total;

            $liveurl = "https://osoftpay.net/api/PublicPayments?TransactionNumber=" . $trac_ref . "&Amount=". $total;

            $url = $this->productionMode == 'no' ? $testurl : $liveurl;

            // For The CURL
            $args = [
                    'timeout' => 120,
                    'headers' => [
                        "Accept: application/json",
                        "Hash: $hash"
                    ]
                ];

            $response = wp_remote_get($url, $args);

            /**
             * For The Major Part Which Is Using CURL To Get Response And Status
             */
            /*                 $curl = curl_init();

                            // Disable SSL Security
                            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );

                            // Will Recieve Response
                            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

                            // Set The Headers
                            curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );

                            // Set The Url First
                            curl_setopt( $curl, CURLOPT_URL, $url );

                            $response = curl_exec( $curl );
                                print_r( $response );

                            $respone = json_encode($response, true); */



            if (! is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) :

                $response = json_decode($response['body'], true); else:

                $response['ResponseCode'] = '400';
            $response['ResponseDescription'] = 'Can\'t verify payment. Contact us for more details about the order and payment status.';


            endif;

            return $response;
        }
    }
    // End Of The Class


    /**
    * Add Settings link to the plugin entry in the plugins menu
    **/
    function sheghun_osoftpay_plugin_action_links($links, $file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file == $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=sheghun_osoftpay_gateway">Settings</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }
    add_filter('plugin_action_links', 'sheghun_osoftpay_plugin_action_links', 10, 2);

    /**
 * only add the naira currency and symbol if WC versions is less than 2.1
 */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

    /**
    * Add NGN as a currency in WC
    **/
        add_filter('woocommerce_currencies', 'sheghun_add_naira');

        if (! function_exists('sheghun_add_naira')) {
            function sheghun_add_naira($currencies)
            {
                $currencies['NGN'] = __('Naira', 'woocommerce');
                return $currencies;
            }
        }

        /**
        * Enable the naira currency symbol in WC
        **/
        add_filter('woocommerce_currency_symbol', 'sheghun_add_naira_symbol', 10, 2);

        if (! function_exists('sheghun_add_naira_symbol')) {
            function sheghun_add_naira_symbol($currency_symbol, $currency)
            {
                switch ($currency) {
                    case 'NGN': $currency_symbol = '&#8358; '; break;
                }
                return $currency_symbol;
            }
        }
    }


    /**
     *
     * Order 8
     * @param array($methods
     * @return classes
     */
    add_filter('woocommerce_payment_gateways', 'sheghun_add_your_gateway_class');
    function sheghun_add_your_gateway_class($methods)
    {
        $methods[] = 'Sheghun_Osoftpay_Webpay_Gateway';
        return $methods;
    }

    /**
     * The Filter Used To Tell WooComerce About The Class
    * Order 7
    */

    function is_valid_for_use()
    {
        if (! in_array(get_woocommerce_currency(), array('NGN'))) {
            $this->msg = 'OsoftPay Webpay doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
            return false;
        }

        return true;
    }

    /**
     * Display Testmode Notice
     */
    function sheghun_osoftpay_testmode_notice()
    {
        $sheghun_osoftpay_settings = get_option('woocommerce_sheghun_osoftpay_gateway_settings');

        $sheghun_osoftpay_test_mode = $sheghun_osoftpay_settings['productionMode'];

        if ('no' == $sheghun_osoftpay_test_mode && is_valid_for_use()) {
            ?>
			<div class="update-nag">
				<p> Osoftpay Testmode Enabled Use TestServiceName1 As Your PaymentItemName </p>
				<!--  <a href="<?php echo get_bloginfo('wpurl') ?>/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_Tbz_Webpay_Gateway">here</a> to disable it when you want to start accepting live payment on your site. -->
			</div>
		<?php
        }
    }
    add_action('admin_notices', 'sheghun_osoftpay_testmode_notice');
}

    /**
     * I Saw A Fine Girl This Morning
     * She fine Die ooh
     */
