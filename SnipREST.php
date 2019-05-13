<?php namespace ProcessWire;

/**
 * SnipREST - helper class for Snipcart REST API that lets you manage your data remotely.
 * (This file is part of the SnipWire package)
 *
 * Only accepts application/json content type -> always specify "Accept: application/json" header 
 * in every request.
 *
 * The main API endpoint is https://app.snipcart.com/api
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

class SnipREST extends WireHttp {

    const apiEndpoint = 'https://app.snipcart.com/api/';
    const resourcePathOrders = 'orders';
    const resourcePathDataOrdersSales = 'data/orders/sales';
    const resourcePathDataOrdersCount = 'data/orders/count';
    const resourcePathDataPerformance = 'data/performance';
    const resourcePathSubscriptions = 'subscriptions';
    const resourcePathCustomers = 'customers';
    const resourcePathDiscounts = 'discounts';
    const resourcePathProducts = 'products';
    const resourcePathCartsAbandoned = 'carts/abandoned';
    const resourcePathShippingMethods = 'shipping_methods';
    const resourcePathSettingsGeneral = 'settings/general';
    const resourcePathSettingsDomain = 'settings/domain';
    const resourcePathRequestValidation = 'requestvalidation'; // + HTTP_X_SNIPCART_REQUESTTOKEN
    
    const cacheNameSettings = 'SnipcartSettingsGeneral';

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();

        $snipwireConfig = $this->wire('modules')->getConfig('SnipWire');
        // Need to check if module configuration is available (if configuration form was never submitted, 
        // the necessary keys aren't available!)
        if ($snipwireConfig && isset($snipwireConfig['submit_save_module'])) {
            // Snipcart environment (TEST | LIVE?)
            $snipcartAPIKey = ($snipwireConfig['snipcart_environment'] == 1)
                ? $snipwireConfig['api_key_secret']
                : $snipwireConfig['api_key_secret_test'];
            
            // Set headers required by Snipcart
            // -> Authorization: Basic <credentials>, where credentials is the base64 encoding of the secret API key and empty(!) password joined by a colon
            $this->setHeaders(array(
                'cache-control' => 'no-cache',
                'Authorization' => 'Basic ' . base64_encode($snipcartAPIKey . ':'),
                'Accept' => 'application/json',
            ));
        }
    }

    /**
     * Returns messages texts (message, warning, error) based on given key.
     *
     * @return string (will be empty if key not found)
     *
     */
    public static function getMessagesText($key) {
        $texts = array(
            'no_headers' => __('Missing request headers for Snipcart REST connection.'),
            'connection_failed' => __('Connection to Snipcart failed'),
        );
        return array_key_exists($key, $texts) ? $texts[$key] : '';
    }

    /**
     * Get the available settings from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key Which settings key to return (fallback to full settings array if $key doesnt exist)
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return boolean|array False if request failed or settings array
     *
     */
    public function getSettings($key = '', $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor('SnipWire', self::cacheNameSettings);

        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor('SnipWire', self::cacheNameSettings, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resourcePathSettingsGeneral);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

    /**
     * Snipcart REST API connection test.
     * (uses resourcePathSettingsDomain for test request)
     *
     * @return mixed $status True on success or string of status code on error
     * 
     */
    public function testConnection() {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            $this->error($status);
            return $status;
        }
        return ($this->get(self::apiEndpoint . self::resourcePathSettingsDomain)) ? true : $this->getError();
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
    
    /**
     * Getter for $headers from WireHttp.
     *
     * @return array $headers (may be empty)
     *
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Get a full http status code string from WireHttp $httpCodes.
     *
     * @param int $code Specify the HTTP code number
     * @return string (empty string if $code doesn't exist)
     *
     */
    public function getHttpStatusCodeString($code) {
        if (isset($this->httpCodes[$code])) {
            return $code . ' ' . $this->httpCodes[$code];
        }
        return '';
    }

}
