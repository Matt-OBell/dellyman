<?php
/*
Plugin Name: Dellyman Logistics
Plugin URI: https://dellyman.com/
Description: Your shipping method plugin
Version: 1.0.0
Author: Babatope Ajepe
Author URI: https://dellyman.com/
*/

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
{

    function delly_logistics_method_init()
    {
        if (!class_exists('WC_Dellyman_Shiping_Method'))
        {

            class WC_Dellyman_Shiping_Method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct()
                {
                    $this->id = 'delly_logistics_method';
                    $this->method_title = isset($this->settings['title']) ? $this->settings['title'] : "Dellyman Logistics";;
                    $this->method_description = __('Description of your shipping method');

                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : "Dellyman Logistics";
                    $this->number = 2020;
                    $this->fee = 700;
                    $this->has_settings = true;
                    $this->api_key = '';
                    $this->dellymanBaseURL = isset($this->settings['dellyman_base_url']) ? $this->settings['dellyman_base_url'] : "";

                    $this->pickUpContactName = isset($this->settings['PickUpContactName']) ? $this->settings['PickUpContactName'] : "";
                    $this->pickUpContactNumber = isset($this->settings['PickUpContactNumber']) ? $this->settings['PickUpContactNumber'] : "";
                    $this->pickUpGooglePlaceAddress = isset($this->settings['PickUpGooglePlaceAddress']) ? $this->settings['PickUpGooglePlaceAddress'] : "";

                    $this->init();
                    $this->dcompany_id = null;
                    $this->dtoken = $this->getAuthenticationToken();
                    $auth = $this->getCustomerAuthentication($this->dtoken);
                    $this->dcustomer_id = $auth['cid'];
                    $this->dcustomer_auth = $auth['auth'];
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array(
                        $this,
                        'process_admin_options'
                    ));
                }

                function init_form_fields()
                {

                    $this->form_fields = array(

                        'enabled' => array(
                            'title' => 'Enable',
                            'Dellyman',
                            'type' => 'select',
                            'options' => array(
                                'yes' => 'Yes',
                                'no' => 'No'
                            ) ,
                            'description' => 'Enable Dellyman shipping.',
                            'default' => 'yes'
                        ) ,

                        'title' => array(
                            'title' => 'Title',
                            'type' => 'text',
                            'description' => 'Title to be display on site',
                            'default' => 'Delly Logistics',
                        ) ,

                        'api_id' => array(
                            'title' => 'API ID',
                            'type' => 'text',
                            'description' => 'Dellyman API Identity number',
                            'default' => '',
                        ) ,
                        'api_secret' => array(
                            'title' => 'API Secret',
                            'type' => 'text',
                            'description' => 'Dellyman API secret key',
                            'default' => '',
                        ) ,
                        'dlogin' => array(
                            'title' => 'Login',
                            'type' => 'text',
                            'description' => 'Dellyman Login',
                            'default' => '',
                        ) ,
                        'dpassword' => array(
                            'title' => 'Password',
                            'type' => 'text',
                            'description' => 'Dellyman Password',
                            'default' => '',
                        ) ,
                        'dellyman_base_url' => array(
                            'title' => 'Base URL',
                            'type' => 'text',
                            'description' => 'Dellyman Base URL',
                            'default' => '',
                        ) ,
                        'dellyman_company_id' => array(
                            'title' => 'Company ID',
                            'type' => 'number',
                            'description' => 'Company ID',
                            'default' => '',
                        ) ,
                        //
                        'PickUpContactName' => array(
                            'title' => 'PickUp Contact Name',
                            'type' => 'text',
                            'description' => 'PickUp Contact Name',
                            'default' => '',
                        ) ,
                        'PickUpContactNumber' => array(
                            'title' => 'PickUp Contact Number',
                            'type' => 'text',
                            'description' => 'PickUp Contact Number',
                            'default' => '',
                        ) ,
                        'PickUpGooglePlaceAddress' => array(
                            'title' => 'PickUp Google Place Address',
                            'type' => 'text',
                            'description' => 'PickUp Google Place Address',
                            'default' => '',
                        ) ,

                    );
                }

                public function getAuthenticationToken()
                {

                    $url = $this->settings['dellyman_base_url'] . '/api/v1.0/Authentication';
                    $options = ['body' => json_encode(["APIID" => $this->settings['api_id'], "APISecret" => $this->settings['api_secret']]) , 'headers' => ['Content-Type' => 'application/json', ], 'timeout' => 60, 'redirection' => 5, 'blocking' => true, 'httpversion' => '1.0', 'sslverify' => false, 'data_format' => 'body', ];
                    if ($this->settings['api_secret'])
                    {

                        return json_decode(wp_remote_post($url, $options) ['body'])->authentication_token;
                    }
                }

                public function getCustomerAuthentication($token)
                {
                    if ($token)
                    {
                        $url = $this->settings['dellyman_base_url'] . '/api/v1.0/CustomerValidation';
                        $options = ['body' => json_encode(["Email" => $this->settings['dlogin'], "Password" => $this->settings['dpassword']]) , 'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, ], 'timeout' => 60, 'redirection' => 5, 'blocking' => true, 'httpversion' => '1.0', 'sslverify' => false, 'data_format' => 'body', ];
                        $body = json_decode(wp_remote_post($url, $options) ['body']);

                        return ['auth' => $body->CustomerAuth, 'cid' => $body->CustomerID];

                    }
                    return ['auth' => '', 'cid' => ''];

                }

                protected function _getStorePhysicalAddress()
                {
                    $address = get_option('woocommerce_store_address');
                    $city = get_option('woocommerce_store_city');
                    $country = get_option('woocommerce_default_state');
                    return $address . ' ' . $city;
                }

                protected function _customerCalculculateAmountPayload($settings, $authentication, $package)
                {

                    $state = $package["destination"]["state"];
                    $city = $package["destination"]["city"];
                    $address = $package["destination"]["address"] ? $package["destination"]["address"] : "Lagos"; //placehoder when customer is not login
                    $country = $package["destination"]["country"];
                    $postcode = $package["destination"]["postcode"];
                    $address_1 = $package["destination"]["address_1"];
                    $address_2 = $package["destination"]["address_2"];

                    $payload = ["CustomerID" => $authentication['cid'],

                    "CreatedThrough" => "web", "IsProductOrder" => 0, "PaymentMode" => "delivery", "ProductAmount" => [0], "CustomerID" => $authentication['cid'], "CustomerAuth" => $authentication['auth'], "VehicleID" => 1, "IsCoD" => 1, "PickupRequestedTime" => "06 AM to 09 PM", "PickupRequestedDate" => date('d/m/Y') , "PickupAddress" => $this->_getStorePhysicalAddress() , "DeliveryAddress" => [$address . ' ' . $city . ' ' . $state . ',' . $country]

                    ];

                    return $payload;
                }

                public function customerCalculateAmount($settings, $package)
                {
                    $url = $this->settings['dellyman_base_url'] . '/api/v1.0/CustomerCalculateAmount';
                    $token = $this->getAuthenticationToken();
                    $authentication = $this->getCustomerAuthentication($token);

                    $payload = $this->_customerCalculculateAmountPayload($settings, $authentication, $package);
                    $options = ['body' => json_encode($payload) , 'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token, ], 'timeout' => 60, 'redirection' => 5, 'blocking' => true, 'httpversion' => '1.0', 'sslverify' => false, 'data_format' => 'body', ];
                    $body = json_decode(wp_remote_post($url, $options) ['body']);

                    $this->settings['dellyman_company_id'] = $body->Companies[0]->CompanyID;

                    return $body->Companies[0]->PackagePrice ? $body->Companies[0]->PackagePrice : 1000;
                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = [])
                {

                    $calculateAmount = $this->customerCalculateAmount($this->settings, $package);

                    // foreach ($package['contents'] as $key => $values) {
                    //     $data = (object) $values;
                    // }
                    $rate = array(
                        'label' => $this->title,
                        'cost' => $calculateAmount,
                        'calc_tax' => 'per_order'
                    );

                    // Register the rate
                    $this->add_rate($rate);
                }
            }
        }
    }

    add_action('woocommerce_shipping_init', 'delly_logistics_method_init');

    function add_your_shipping_method($methods)
    {
        $methods['delly_logistics_method'] = 'WC_Dellyman_Shiping_Method';
        return $methods;
    }

    function push_order_to_dellyman($order_id)
    {
        $shpping = WC()
            ->shipping
            ->get_shipping_methods();
        $order = wc_get_order($order_id);
        $address = $order->get_address();

        $dellyman = $shpping['delly_logistics_method'];
        if (!$order_id)
        {

            return;
        }
        if (!get_post_meta($order_id, '_order_created_on_dellyman', true))
        {

            $payload = ["CustomerID" => $dellyman->dcustomer_id, "CompanyID" => intval($dellyman->settings['dellyman_company_id']) , "CustomerAuth" => $dellyman->dcustomer_auth, "PaymentMode" => "pickup", "IsProductOrder" => 0, "AccountNumber" => "", "BankCode" => "", "VehicleID" => 1, "IsCoD" => 1, "PickUpContactName" => $dellyman->settings['PickUpContactName'], "PickUpContactNumber" => $dellyman->settings['PickUpContactNumber'], "PickUpGooglePlaceAddress" => $dellyman->settings['PickUpGooglePlaceAddress'], "PickUpLandmark" => " ", "PickUpRequestedDate" => date('d/m/Y') , "PickUpRequestedTime" => "06 AM to 09 PM", "DeliveryRequestedTime" => "06 AM to 09 PM", "Packages" => [["ProductAmount" => "0", "PackageDescription" => $order->get_order_number() , "DeliveryContactName" => $address['first_name'] . ' ' . $address['last_name'], "DeliveryContactNumber" => $address['phone'], "DeliveryGooglePlaceAddress" => $address['address_1'] . ' ' . $address['city'] . ' ' . $address['state'] . ' ' . $address['country'], "DeliveryLandmark" => ""]]];

            $url = $dellyman->settings['dellyman_base_url'] . '/api/v1.0/CustomerPickupRequest';
            $options = ['body' => json_encode($payload) , 'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $dellyman->dtoken, ], 'timeout' => 60, 'redirection' => 5, 'blocking' => true, 'httpversion' => '1.0', 'sslverify' => false, 'data_format' => 'body', ];

            $body = wp_remote_post($url, $options);

            $order->update_meta_data('_order_created_on_dellyman', true);
            $order->save();

        }

    }

    add_filter('woocommerce_shipping_methods', 'add_your_shipping_method');

    add_action('woocommerce_before_thankyou', 'push_order_to_dellyman');
}

