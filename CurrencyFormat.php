<?php namespace ProcessWire;

/**
 * CurrencyFormat - helper class for SnipWire to handle currency formatting.
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class CurrencyFormat extends WireData {
    
    /** @var array $currenciesCache An array of available currency formats (currency formats memory cache) */
    public static $currenciesCache = null;

    /**
     * Set the static curencies definition cache.
     *
     * @return void
     * 
     */
    public static function setStaticCurrenciesCache() {
        if (!$currencies = wire('snipREST')->getSettings('currencies')) {
            $currencies = self::getDefaultCurrencyDefinition();
        }
        // Cache currency definitons in static property (DB is queried only once!)
        self::$currenciesCache = $currencies;
    }

    /**
     * Returns an array of worldwide supported currencies, as name => label
     * (comes from static file Currencies.php which holds all currencies 
     * supported by Snipcart -> copied from Snipcart dashboard)
     * 
     * @return array
     * 
     */
    public static function getSupportedCurrencies() {
        return require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Currencies.php';
    }
    
    /**
     * Get the default currency definition.
     *
     * @param boolean $json Wether to return as Json formatted string and not array
     * @return array|string (Json)
     * 
     */
    public static function getDefaultCurrencyDefinition($json = false) {
        $defaultCurrency = array(
            'currency' => 'eur',
            'precision' => 2,
            'decimalSeparator' => ',',
            'thousandSeparator' => '.',
            'negativeNumberFormat' => '- %s%v',
            'numberFormat' => '%s%v',
            'currencySymbol' => '€',
        );
        return ($json) ? wireEncodeJSON($defaultCurrency, true) : $defaultCurrency;
    }


    /**
     * Format the given price based on selected currency.
     *
     * @param int|float|string $price The price value to format
     * @param string $currency The currency tag [default: `eur`]
     * @return string The formatted price (can be empty if something goes wrong)
     * 
     */
    public static function format($price, $currency = 'eur') {
        if (empty($price) && !is_numeric($price)) return '';
        if (empty(self::$currenciesCache)) self::setStaticCurrenciesCache();
        
        // Searches the static $currencys array for $currency tag and returns the corresponding key
        $key = array_search(
            $currency,
            array_column(self::$currenciesCache, 'currency')
        );
        $currencyDefinition = self::$currenciesCache[$key];
        
        $floatPrice = (float) $price;
        if ($floatPrice < 0) {
            $numberFormatString = $currencyDefinition['negativeNumberFormat'];
            $floatPrice = $floatPrice * -1; // price needs to be unsingned ('-' sign position defined by $numberFormatString)
        } else {
            $numberFormatString = $currencyDefinition['numberFormat'];
        }
        $price = number_format(
            $floatPrice,
            (integer) $currencyDefinition['precision'],
            (string) $currencyDefinition['decimalSeparator'],
            (string) $currencyDefinition['thousandSeparator']
        );
        $numberFormatString = str_replace('%s', '%1$s', $numberFormatString); // will be currencySymbol
        $numberFormatString = str_replace('%v', '%2$s', $numberFormatString); // will be value
        return sprintf($numberFormatString, $currencyDefinition['currencySymbol'], $price);
    }

}