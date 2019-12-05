<?php namespace ProcessWire;

/**
 * SnipREST - service class for Snipcart REST API that lets you manage your data remotely.
 * (This file is part of the SnipWire package)
 *
 * Only accepts application/json content type -> always specify "Accept: application/json" header 
 * in every request.
 *
 * Snipcart is using HTTP Basic Auth.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WireHttpExtended.php';

class SnipREST extends WireHttpExtended {

    const apiEndpoint = 'https://app.snipcart.com/api/';
    const resPathDataPerformance = 'data/performance'; // undocumented
    const resPathDataOrdersSales = 'data/orders/sales'; // undocumented
    const resPathDataOrdersCount = 'data/orders/count'; // undocumented
    const resPathOrders = 'orders';
    const resPathOrdersNotifications = 'orders/{token}/notifications';
    const resPathOrdersRefunds = 'orders/{token}/refunds';
    const resPathSubscriptions = 'subscriptions';
    const resPathCartsAbandoned = 'carts/abandoned';
    const resPathCustomers = 'customers';
    const resPathCustomersOrders = 'customers/{id}/orders';
    const resPathProducts = 'products';
    const resPathDiscounts = 'discounts';
    const resPathSettingsGeneral = 'settings/general'; // undocumented
    const resPathSettingsDomain = 'settings/domain';
    const resPathSettingsAllowedDomains = 'settings/alloweddomains';
    const resPathShippingMethods = 'shipping_methods';
    const resPathRequestValidation = 'requestvalidation'; // + HTTP_X_SNIPCART_REQUESTTOKEN
    const snipcartInvoiceUrl = 'https://app.snipcart.com/invoice/{token}'; // currently not possible via API
    
    const cacheNamespace = 'SnipWire';
    const cacheExpireDefault = 900; // max. cache expiration time in seconds
    
    const cacheNamePrefixDashboard = 'Dashboard';
    const cacheNamePrefixPerformance = 'Performance';
    const cacheNamePrefixOrdersSales = 'OrdersSales';
    const cacheNamePrefixOrdersCount = 'OrdersCount';
    const cacheNamePrefixOrders = 'Orders';
    const cacheNamePrefixOrdersNotifications = 'OrdersNotifications';
    const cacheNamePrefixOrderDetail = 'OrderDetail';
    const cacheNamePrefixSubscriptions = 'Subscriptions';
    const cacheNamePrefixSubscriptionDetail = 'SubscriptionDetail';
    const cacheNamePrefixCartsAbandoned = 'CartsAbandoned';
    const cacheNamePrefixCartAbandonedDetail = 'CartAbandonedDetail';
    const cacheNamePrefixCustomers = 'Customers';
    const cacheNamePrefixCustomersOrders = 'CustomersOrders';
    const cacheNamePrefixCustomerDetail = 'CustomerDetail';
    const cacheNamePrefixProducts = 'Products';
    const cacheNamePrefixProductDetail = 'ProductDetail';
    const cacheNamePrefixDiscounts = 'Discounts';
    const cacheNamePrefixDiscountDetail = 'DiscountDetail';
    const cacheNamePrefixSettings = 'Settings';

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();

        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $snipwireConfig = $this->wire('modules')->get('SnipWire');
        
        // Snipcart environment (TEST | LIVE?)
        $snipcartAPIKey = ($snipwireConfig->snipcart_environment == 1)
            ? $snipwireConfig->api_key_secret
            : $snipwireConfig->api_key_secret_test;
        
        // Set headers required by Snipcart
        // -> Authorization: Basic <credentials>, where credentials is the base64 encoding of the secret API key and empty(!) password joined by a colon
        $this->setHeaders(array(
            'cache-control' => 'no-cache',
            'Authorization' => 'Basic ' . base64_encode($snipcartAPIKey . ':'),
            'Accept' => 'application/json',
        ));
    }

    /**
     * Returns messages texts (message, warning, error) based on given key.
     *
     * @return string (will be empty if key not found)
     *
     */
    public static function getMessagesText($key) {
        $texts = array(
            'no_headers' => __('Missing request headers for Snipcart REST connection'),
            'connection_failed' => __('Connection to Snipcart failed'),
            'cache_refreshed' => __('Snipcart cache for this section refreshed'),
            'full_cache_refreshed' => __('Full Snipcart cache refreshed'),
            'dashboard_no_curl' => __('cURL extension not available - the SnipWire Dashboard will respond very slow without'),
            'no_order_token' => __('No order token provided'),
            'no_subscription_id' => __('No subscription ID provided'),
            'no_cart_id' => __('No cart ID provided'),
            'no_customer_id' => __('No customer ID provided'),
            'no_product_id' => __('No product ID provided'),
            'no_product_url' => __('No product URL provided'),
            'no_userdefined_id' => __('No userdefined ID provided'),
            'no_discount_id' => __('No discount ID provided'),
        );
        return array_key_exists($key, $texts) ? $texts[$key] : '';
    }

    /**
     * Get the available settings from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key Which settings key to return (fallback to full settings array if $key doesnt exist)
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return boolean|array False if request failed or settings array
     *
     */
    public function getSettings($key = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixSettings);

        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, self::cacheNamePrefixSettings, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resPathSettingsGeneral);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

    /**
     * Get all dashboard results using cURL multi (fallback to single requests if cURL not available)
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @param string $currency Currency string
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return mixed Dashboard data as array (each package indexed by full URL)
     *
     */
    public function getDashboardData($start, $end, $currency, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->hasCURL) {
            $this->warning(self::getMessagesText('dashboard_no_curl'));
            // Get data without cURL multi
            return $this->_getDashboardDataSingle($start, $end, $currency);
        }

        // Segmented orders cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDashboard . '.' . md5($start . $end . $currency);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get orders array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($start, $end, $currency) {
            return $this->_getDashboardDataMulti($start, $end, $currency);
        });

        return $response;
    }

    /**
     * Get all dashboard results using multi cURL requests
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @param string $currency Currency string
     * @return array $data Dashboard data as array (indexed by `resPath...`)
     *
     */
    private function _getDashboardDataMulti($start, $end, $currency) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        // ---- Part of performance boxes data ----

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataPerformance . $query);

        // ---- Part of performance boxes + performance chart data ----

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
            'currency' => $currency,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataOrdersSales . $query);
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataOrdersCount . $query);

        // ---- Top 10 customers ----
        
        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathCustomers . $query);

        // ---- Top 10 products ----

        $selector = array(
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathProducts . $query);

        // ---- Latest 10 orders ----

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
            'format' => 'Excerpt',
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathOrders . $query);

        return $this->getMultiJSON();
    }

    /**
     * Get all dashboard results using single requests
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @param string $currency Currency string
     * @return array $data Dashboard data as array (indexed by `resPath...`)
     *
     */
    private function _getDashboardDataSingle($start, $end, $currency) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $data = array();
        
        // ---- Part of performance boxes data ----
        
        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $data[] = $this->getPerformance($selector);

        // ---- Part of performance boxes + performance chart data ----

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
            'currency' => $currency,
        );

        $data[] = $this->getSalesCount($selector);
        $data[] = $this->getOrdersCount($selector);
        
        // ---- Top 10 customers ----
        
        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $data[] = $this->getCustomers($selector);

        // ---- Top 10 products ----

        $selector = array(
            'offset' => 0,
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        );
        $data[] = $sniprest->getProducts($selector);

        // ---- Latest 10 orders ----

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
            'format' => 'Excerpt',
        );
        $data[] = $sniprest->getOrders($selector);
        
        return $data;
    }

    /**
     * Get all orders from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your order collection. (Possible values: InProgress, Processed, Disputed, Shipped, Delivered, Pending, Cancelled)
     *  - `paymentStatus` (string) A payment status criteria for your order collection. (Possible values: Paid, PaidDeferred, Deferred)
     *  - `invoiceNumber` (string) The invoice number of the order to retrieve
     *  - `placedBy` (string) The name of the person who made the purchase
     *  - `from` (datetime) Will return only the orders placed after this date
     *  - `to` (datetime) Will return only the orders placed before this date
     *  - 'format' (string) Get a simplified version of orders payload + faster query (Possible values: Excerpt) #undocumented
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrders($key = '', $options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('offset', 'limit', 'status', 'paymentStatus', 'invoiceNumber', 'placedBy', 'from', 'to', 'format');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrders . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathOrders . $query);
        });

        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        if ($response === false) $response = array();
        $data[self::resPathOrders] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get orders items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your order collection. (Possible values: InProgress, Processed, Disputed, Shipped, Delivered, Pending, Cancelled)
     *  - `invoiceNumber` (string) The invoice number of the order to retrieve
     *  - `placedBy` (string) The name of the person who made the purchase
     *  - `from` (datetime) Will return only the orders placed after this date
     *  - `to` (datetime) Will return only the orders placed before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getOrdersItems($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getOrders('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get a single order from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $token The Snipcart $token of the order to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrder($token = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrderDetail . '.' . md5($token);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($token) {
            return $this->getJSON(self::apiEndpoint . self::resPathOrders . '/' . $token);
        });

        if ($response === false) $response = array();
        $data[self::resPathOrders . '/' . $token] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Delete a single order cache (WireCache).
     *
     * @param string $token The Snipcart $token of the order
     * @return void
     *
     */
    public function deleteOrderCache($token) {
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        $cacheName = self::cacheNamePrefixOrderDetail . '.' . md5($token);
        $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
    }

    /**
     * Creates a new notification on a specified order.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart token of the order
     * @param array $options An array of options that will be sent as POST params:
     *  - `type` (string) Type of notification. (Possible values: Comment, OrderStatusChanged, OrderShipped, TrackingNumber, Invoice) #required
     *  - `deliveryMethod` (string) 'Email' send by email, 'None' keep it private. #required
     *  - `message` (string) Message of the notification. Optional when used with type 'TrackingNumber'.
     * @return array $data
     * 
     */
    public function postOrderNotification($token = '', $options = array()) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');

        $allowedOptions = array('type', 'deliveryMethod', 'message');
        $defaultOptions = array(
            'type' => 'TrackingNumber',
            'deliveryMethod' => 'Email',
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = wirePopulateStringTags(
            self::apiEndpoint . self::resPathOrdersNotifications,
            array('token' => $token)
        );
        $requestbody = wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);

        if ($response === false) $response = array();
        $data[$token] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Creates a new refund on a specified order.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart token of the order
     * @param array $options An array of options that will be sent as POST params:
     *  - `amount` (float) The amount to be refunded #required
     *  - `comment` (string) The reason for the refund (internal note - not for customer)
     * @return array $data
     * 
     */
    public function postOrderRefund($token = '', $options = array()) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');

        $allowedOptions = array('amount', 'comment');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = wirePopulateStringTags(
            self::apiEndpoint . self::resPathOrdersRefunds,
            array('token' => $token)
        );
        $requestbody = wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);

        if ($response === false) $response = array();
        $data[$token] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get all subscriptions from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A criteria to return items having the specified status. (Possible values: Active, Paused, Canceled)
     *  - `userDefinedPlanName` (string) A criteria to return items matching the specified plan name.
     *  - `userDefinedCustomerNameOrEmail` (string) A criteria to return items belonging to the specified customer name or email.
     *  - `from` (datetime) Filter subscriptions to return items that start on specified date.
     *  - `to` (datetime) Filter subscriptions to return items that end on specified date.
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSubscriptions($key = '', $options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('offset', 'limit', 'status', 'userDefinedPlanName', 'userDefinedCustomerNameOrEmail', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixSubscriptions . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathSubscriptions . $query);
        });

        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        if ($response === false) $response = array();
        $data[self::resPathSubscriptions] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get subscriptions items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A criteria to return items having the specified status. (Possible values: Active, Paused, Canceled)
     *  - `userDefinedPlanName` (string) A criteria to return items matching the specified plan name.
     *  - `userDefinedCustomerNameOrEmail` (string) A criteria to return items belonging to the specified customer name or email.
     *  - `from` (datetime) Filter subscriptions to return items that start on specified date.
     *  - `to` (datetime) Filter subscriptions to return items that end on specified date.
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getSubscriptionsItems($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getSubscriptions('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get a single subscription from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart item id of the subscription to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSubscription($id = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixSubscriptions . '.' . md5($id);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathSubscriptions . '/' . $id);
        });

        if ($response === false) $response = array();
        $data[self::resPathSubscriptions . '/' . $id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the abandoned carts from Snipcart dashboard as array.
     *
     * The Snipcart API has no pagination in this case!
     * (only "Load more" button possible)
     *
     *   From the response use
     *     - `continuationToken`
     *     - `hasMoreResults`
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `limit` (int) Number of results to fetch. [default = 50]
     *  - `continuationToken` (string) The contionuation token for abandoned cart pager [default = null]
     *  - `timeRange` (string) A time range criteria for abandoned carts. (Possible values: Anytime, LessThan4Hours, LessThanADay, LessThanAWeek, LessThanAMonth)
     *  - `minimalValue` (float) The minimum total cart value of results to fetch
     *  - `email` (string) The email of the customer who placed the order
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCarts($key = '', $options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('limit', 'continuationToken', 'timeRange', 'minimalValue', 'email');
        $defaultOptions = array(
            'limit' => 50,
            'continuationToken' => null,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCartsAbandoned . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathCartsAbandoned . $query);
        });

        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        if ($response === false) $response = array();
        $data[self::resPathCartsAbandoned] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the abandoned carts items from Snipcart dashboard as array.
     *
     * The Snipcart API handles pagination different in this case!
     * (need to use prev / next button instead of pagination)
     *
     *   From the response use
     *     - `continuationToken`
     *     - `hasMoreResults`
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `timeRange` (string) A time range criteria for abandoned carts. (Possible values: Anytime, LessThan4Hours, LessThanADay, LessThanAWeek, LessThanAMonth)
     *  - `minimalValue` (float) The minimum total cart value of results to fetch
     *  - `email` (string) The email of the customer who placed the order
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCartsItems($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getAbandonedCarts('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get a single abandoned cart from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the cart to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCart($id = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_cart_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCartAbandonedDetail . '.' . md5($id);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathCartsAbandoned . '/' . $id);
        });

        if ($response === false) $response = array();
        $data[self::resPathCartsAbandoned . '/' . $id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the all customers from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomers($key = '', $options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('offset', 'limit', 'status', 'email', 'name', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomers . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathCustomers . $query);
        });

        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        if ($response === false) $response = array();
        $data[self::resPathCustomers] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get customers items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getCustomersItems($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getCustomers('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get all orders of a customer from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the customer
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomersOrders($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_customer_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomersOrders . '.' . md5($id);

        $url = wirePopulateStringTags(
            self::apiEndpoint . self::resPathCustomersOrders,
            array('id' => $id)
        );

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($url) {
            return $this->getJSON($url);
        });

        if ($response === false) $response = array();
        $data[self::resPathCustomersOrders] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get a single customer from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the customer to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomer($id = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_customer_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomerDetail . '.' . md5($id);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathCustomers . '/' . $id);
        });

        if ($response === false) $response = array();
        $data[self::resPathCustomers . '/' . $id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get all products from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `userDefinedId` string The custom product ID
     *  - `keywords` string A keyword to search for
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getProducts($key = '', $options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('offset', 'limit', 'userDefinedId', 'keywords', 'archived', 'excludeZeroSales', 'orderBy', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
            'orderBy' => 'SalesValue',
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixProducts . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathProducts . $query);
        });

        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        if ($response === false) $response = array();
        $data[self::resPathProducts] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get products items from Snipcart dashboard.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `userDefinedId` string The custom product ID
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getProductsItems($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getProducts('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get a single product from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the product to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getProduct($id = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixProductDetail . '.' . md5($id);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathProducts . '/' . $id);
        });

        if ($response === false) $response = array();
        $data[self::resPathProducts . '/' . $id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the id of a Snipcart product by it's userDefinedId.
     *
     * @param string $userDefinedId The user defined id of a product (SKU)
     * @return boolean|string $id The Snipcart product id or false if not found or something went wrong
     *
     */
    public function getProductId($userDefinedId) {
        if (!$userDefinedId) {
            $this->error(self::getMessagesText('no_userdefined_id'));
            return false;
        }
        $options = array(
            'offset' => 0,
            'limit' => 1,
            'orderBy' => '',
            'userDefinedId' => $userDefinedId,
        );
        // Get a specific item
        $data = $this->getProductsItems($options, WireCache::expireNow); // Get uncached result

        if ($data[self::resPathProducts][WireHttpExtended::resultKeyHttpCode] == 200) {
            $id = $data[self::resPathProducts][WireHttpExtended::resultKeyContent][0]['id'];
        } else {
            $id = false;
        }
        return $id;
    }

    /**
     * Fetch the URL passed in parameter and generate product(s) found on this page.
     *
     * @param string $fetchUrl The URL of the page to be fetched
     * @return array $data
     * 
     */
    public function postProduct($fetchUrl) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$fetchUrl) {
            $this->error(self::getMessagesText('no_product_url'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');

        $options = array(
            'fetchUrl' => $fetchUrl,
        );
        
        $url = self::apiEndpoint . self::resPathProducts;
        $requestbody = wireEncodeJSON($options);
        
        $response = json_decode($this->send($url, $requestbody, 'POST'), true);

        if ($response === false) $response = array();
        $data[$fetchUrl] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Update a specific product.
     *
     * @param string $id The Snipcart id of the product to be updated
     * @param array $options An array of options that will be sent as POST params:
     *  - `inventoryManagementMethod` (string) Specifies how inventory should be tracked for this product. (Possible values: Single, Variant)
     *  - `variants` (array) Allows to set stock per product variant
     *  - `stock` (integer) The number of items in stock. (Will be used when `inventoryManagementMethod` = Single)
     *  - `allowOutOfStockPurchases` (boolean) Allow out-of-stock purchase.
     * @return array $data
     * 
     */
    public function putProduct($id, $options = array()) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }
        // Add necessary header for PUT request
		$this->setHeader('content-type', 'application/json; charset=utf-8');

        $allowedOptions = array('inventoryManagementMethod', 'variants', 'stock', 'allowOutOfStockPurchases');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = self::apiEndpoint . self::resPathProducts . '/' . $id;
        $requestbody = wireEncodeJSON($options);

        $response = json_decode($this->send($url, $requestbody, 'PUT'), true);

        if ($response === false) $response = array();
        $data[$id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Delete a specific product.
     * (the product isn't actually deleted, but it's "archived" flag is set to true)
     *
     * @param string $id The Snipcart id of the product to be deleted (archived)
     * @return array $data
     * 
     */
    public function deleteProduct($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }
        // Add necessary header for DELETE request
		$this->setHeader('content-type', 'application/json; charset=utf-8');

        $url = self::apiEndpoint . self::resPathProducts . '/' . $id;
        
        $response = json_decode($this->send($url, array(), 'DELETE'), true);

        if ($response === false) $response = array();
        $data[$id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get discounts from Snipcart dashboard as array.
     *
     * The Snipcart API has no pagination in this case!
     * The Snipcart API has no options for query!
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getDiscounts($expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDiscounts;

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resPathDiscounts);
        });

        if ($response === false) $response = array();
        $data[self::resPathDiscounts] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get a single discount from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the discount to be returned
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getDiscount($id = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_discount_id'));
            return false;
        }

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDiscountDetail . '.' . md5($id);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathDiscounts . '/' . $id);
        });

        if ($response === false) $response = array();
        $data[self::resPathDiscounts . '/' . $id] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the store performance from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the performance after this date
     *  - `to` (datetime) Will return only the performance before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getPerformance($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('from', 'to');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixPerformance . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataPerformance . $query);
        });

        if ($response === false) $response = array();
        $data[self::resPathDataPerformance] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the sales (amount of sales by day) from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the sales after this date
     *  - `to` (datetime) Will return only the sales before this date
     *  - `currency` (string) Will return only sales with this currency
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSalesCount($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('from', 'to', 'currency');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersSales . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataOrdersSales . $query);
        });

        if ($response === false) $response = array();
        $data[self::resPathDataOrdersSales] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Get the order counts (number of orders by days) from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the order counts after this date
     *  - `to` (datetime) Will return only the order counts before this date
     *  - `currency` (string) Will return only order counts with this currency
     * @param mixed $expires Lifetime of this cache
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrdersCount($options = array(), $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $allowedOptions = array('from', 'to', 'currency');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersCount . '.' . md5($query);

        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);

        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataOrdersCount . $query);
        });

        if ($response === false) $response = array();
        $data[self::resPathDataOrdersCount] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

    /**
     * Snipcart REST API connection test.
     * (uses resPathSettingsDomain for test request)
     *
     * @return mixed $status True on success or string of status code on error
     * 
     */
    public function testConnection() {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        return ($this->get(self::apiEndpoint . self::resPathSettingsDomain)) ? true : $this->getError();
    }
    
    /**
     * Reset the full Snipcart cache for all sections.
     *
     * @return boolean
     * 
     */
    public function resetFullCache() {
        return $this->wire('cache')->deleteFor(self::cacheNamespace);
    }
    
    /**
     * Completely refresh Snipcart settings cache.
     *
     * @return boolean|array False if request failed or settings array
     *
     */
    public function refreshSettings() {
        return $this->getSettings('', WireCache::expireNever, true);
    }

}
