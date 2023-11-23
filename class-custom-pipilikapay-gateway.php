<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Custom_pipilikapay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $url        = WC_HTTPS::force_https_url(plugins_url('/assets/images/banners.png', __FILE__));
        
        $this->id                 = 'pipilikapay';
        $this->icon               = esc_attr($url); // Add a URL to your payment gateway icon.
        $this->has_fields         = false;
        $this->method_title       = 'pipilikapay';
        $this->method_description = 'A payment gateway that sends your customers to pipilikapay to pay with bKash, Rocket, Nagad, Upay.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        // Actions.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'redirect_to_pipilikapay' ) );
        add_action( 'woocommerce_api_custom_pipilikapay_gateway', array( $this, 'handle_pipilikapay_callback' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable pipilikapay Payment Gateway',
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the payment method title shown to the user during checkout.',
                'default'     => 'pipilikapay',
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the payment method description shown to the user during checkout.',
                'default'     => 'A payment gateway that sends your customers to pipilikapay to pay with bKash, Rocket, Nagad, Upay.',
            ),
			'api_key' => array(
                'title'       => 'Api Key',
                'type'        => 'text',
                'description' => 'Enter Your pipilikapay Api Key',
                'default'     => '',
            ),
            'secret_key' => array(
                'title'       => 'Secret Key',
                'type'        => 'text',
                'description' => 'Enter Your pipilikapay Secret Key',
                'default'     => '',
            ),            
            'apiURL' => array(
                'title'       => 'Panel URL',
                'type'        => 'text',
                'description' => 'Enter Your Payment Panel URL',
                'default'     => '',
            ),
			'exchange_rate'    => array(
				'title'       => __('Exchange Rate'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __('This rate will be apply to the total amount of the cart'),
				'default'     => 0,
			),
			'digital_product'     => array(
				'title'       => __('Digital Product'),
				'type'        => 'checkbox',
				'label'       => __('If you are providing digital product then you can use this option. It will mark order as complete as soon as user paid.'),
				'default'     => 'no',
			),
            // Add more settings here if required.
        );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        // Redirect to pipilikapay.com here with the order details.
        // You need to generate pipilikapay payment URL using the order details and redirect the user.
        // After successful payment, pipilikapay will redirect back to your website's callback URL.

		
		$full_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		$email = $order->get_billing_email();

        $baseURL = $this->get_option('apiURL');
    
        $callbackURL= $order->get_checkout_payment_url( true );
        $webhookURL= $order->get_checkout_payment_url( true );
        $cancelURL= $order->get_cancel_order_url();
    
        $apiKey= $this->get_option('api_key');
        $secretKey= $this->get_option('secret_key');
        
        $order_total = $order->get_total();
        
        $exchange_rate= $this->get_option('exchange_rate');
        
        if($exchange_rate == "0"){
            
        }else{
            $order_total = $order_total*$exchange_rate;
        }

        $metadata = array(
            'customerID' => $email,
            'orderID' => $order->get_id()
        );

        $data = array(
            'apiKey' => $apiKey,
            'secretkey' => $secretKey,
            'fullname' => $full_name,
            'email' => $email,
            'amount' => $order_total,
            'successurl' => $callbackURL,
            'cancelurl' => $cancelURL,
            'webhookUrl' => $webhookURL,
            'metadata' => json_encode($metadata)
        );

        $ch = curl_init("$baseURL/payment/api/create_payment");

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        $resultdata = json_decode($response, true);

        return array(
            'result'   => 'success',
            'redirect' => $resultdata['paymentURL'],
        );
    }

    public function redirect_to_pipilikapay( $order_id ) {
        $order = wc_get_order( $order_id );
        
        $postData = file_get_contents('php://input');
        
        $postDataArray = json_decode($postData, true);
        
        $amount = $postDataArray['amount'];
        $fee = $postDataArray['fee'];
        $paymentMethod = $postDataArray['payment_method'];
        $transactionID = $postDataArray['transaction_id'];
        $payment_id = $postDataArray['payment_id'];

        //Verify Payment
        $baseURL = $this->get_option('apiURL');

        $apiKey= $this->get_option('api_key');
        $secretKey= $this->get_option('secret_key');

        $requestbody = array(
            'apiKey' => $apiKey,
            'secretkey' => $secretKey,
            'paymentID' => $payment_id
        );
        $url = curl_init("$baseURL/payment/api/verify_payment");                     

        curl_setopt($url, CURLOPT_POST, 1);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbody);
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        $resultdata = curl_exec($url);
        curl_close($url);
        $resultdata = json_decode($resultdata, true);

        $paymentStatus = $resultdata['PaymentStatus'];
        //Verify Payment

		if ($order->get_status() != 'completed') {
			if ($paymentStatus === 'Completed') {
				if ($this->get_option('digital_product') === 'yes') {
					$order->update_status('completed', __("pipilikapay payment was successfully completed. Payment Method: {$paymentMethod}, Amount: {$amount}, Fee: {$fee}, Transaction ID: {$transactionID}"));
					// Reduce stock levels
					$order->reduce_order_stock();
					$order->payment_complete();
				} else {
					$order->update_status('processing', __("pipilikapay payment was successfully processed. Payment Method: {$paymentMethod}, Amount: {$amount}, Fee: {$fee}, Transaction ID: {$transactionID}"));
					// Reduce stock levels
					$order->reduce_order_stock();
					$order->payment_complete();
				}
				return true;
			} else {
				$order->update_status('on-hold', __('pipilikapay payment was successfully on-hold. Transaction id not found. Please check it manually.'));
				return true;
			}
		}
    }

    //public function handle_pipilikapay_callback() {
        // Handle pipilikapay callback here after successful payment.
        // Verify payment details and update the order status accordingly.
        // Make sure to handle security checks and validation.
    //}
}

// Add the custom payment gateway to WooCommerce.
add_filter( 'woocommerce_payment_gateways', 'add_custom_pipilikapay_gateway' );
function add_custom_pipilikapay_gateway( $gateways ) {
    $gateways[] = 'Custom_pipilikapay_Gateway';
    return $gateways;
}
