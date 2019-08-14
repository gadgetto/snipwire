<?php namespace ProcessWire;

/**
 * Returns extended installation resources for ProcessSnipWire.
 * (This file is part of the SnipWire package)
 *
 */

$resources = array(

    'templates' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop',
            'label' => 'Snipcart Shop',
            'icon' => 'shopping-cart', 
            'noChildren' => 0,
            'tags' => 'Snipcart',
            '_allowedChildTemplates' => 'snipcart-product', // comma separated list of allowed child template names
        ),
        'snipcart-product' => array(
            'name' => 'snipcart-product',
            'label' => 'Snipcart Product',
            'icon' => 'cube', 
            'noChildren' => 1,
            'tags' => 'Snipcart',
            '_allowedParentTemplates' => 'snipcart-shop', // comma separated list of allowed parent template names
        ),
    ),
    
    'files' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop.php',
            'type' => 'templates' // destination folder
        ),
        'snipcart-product' => array(
            'name' => 'snipcart-product.php',
            'type' => 'templates' // destination folder
        ),
    ),

    /*
    Snipcart product fields: https://docs.snipcart.com/configuration/product-definition
    
    Required fields:
    ================
    
    - data-item-id: string Unique Stock Keeping Unit - SKU (will be prefilled with page ID) 
    - data-item-name: string (ProcessWire Page title by default - can be changed to any text field type)
    - data-item-price: string (Will be created by selecting the desired currency(s) in module config form)
    - data-item-url: string (URL where Snipcart crawler will find the Buy button)
    
    Optional fields:
    ================
    
    - data-item-description: string (Short product description, visible in cart and during checkout)
    - data-item-image: string (Thumbnail of product in the cart. This must be an absolute URL.)
    - data-item-categories: string (The categories this product belongs to. Example: data-item-categories="cat1, cat2, cat3")
    - data-item-weight: integer (Required only if using shipping rates. Using grams as weight units.)
    - data-item-width: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    - data-item-length: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    - data-item-height: integer? (Using centimeters as dimension unit and this attribute is required to use Australia Post)
    - data-item-shippable: boolean (Setting this to false, the product will be flagged as an item that can not be shipped)
    - data-item-quantity: integer (Set a default quantity for the item that you are about to add.)
    - data-item-quantity-step: integer (The quantity of a product will increment by this value.)
    - data-item-max-quantity: integer (Maximum allowed quantity of product)
    - data-item-min-quantity: integer (Minimum allowed quantity for product)
    - data-item-taxable: boolean (Set to false to exclude item from the taxes calculation. Default value is true.)
    - data-item-taxes: string (Using this option, you can define which tax will be applied on this product)
    data-item-stackable: boolean (Setting this to false, adding the same product to the cart will result in two distinct items in the cart, instead of simply increasing the quantity)
    data-item-file-guid: 
    data-item-payment-interval: 
    data-item-payment-interval-count: 
    data-item-payment-trial: 
    data-item-recurring-shipping: boolean

    Will be set by SnipCart automatically (not defined as PW fields)
    ================================================================
    
    - data-item-has-taxes-included: boolean (Set to true if the taxes you defined are included in your product prices.)
    - data-item-metadata: json-object (Example usage: data-item-metadata='{"key": "value"}')
    */

    'fields' => array(
        // `title` is an alredy available field which only needs to be configured in template context
        'title' => array(
            'name' => 'title',
            '_templateFieldOptions' => array(
                'snipcart-product' => array(
                    'label' => __('Product Name (Title)'),
                    'notes' => __('Name of the product to be used in catalogue and cart.'),
                    'columnWidth' => 70,
                ),
            ),
        ),
        'snipcart_item_id' => array(
            'name' => 'snipcart_item_id',
            'type' => 'FieldtypeText',
            'label' => __('SKU'),
            'notes' => __('Individual ID for your product e.g. 1377 or NIKE_PEG-SW-43'),
            'maxlength' => 100,
            'required' => true,
            'pattern' => '^[\w\-_*+.,]+$',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
            '_templateFieldOptions' => array(
                'snipcart-product' => array(
                    'columnWidth' => 30,
                ),
            ),
        ),
        'snipcart_item_price_eur' => array(
            'name' => 'snipcart_item_price_eur',
            'type' => 'FieldtypeText',
            'label' => __('Product Price (EUR)'),
            'notes' => __('Decimal with a dot (.) as separator e.g. 19.99'),
            'maxlength' => 20,
            'required' => true,
            'pattern' => '[-+]?[0-9]*[.]?[0-9]+',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_description' => array(
            'name' => 'snipcart_item_description',
            'type' => 'FieldtypeTextarea',
            'label' => __('Product Description'),
            'description' => __('The product description that your customers will see on product pages in cart and during checkout.'),
            'notes' => __('Provide a short description of your product without HTML tags.'),
            'maxlength' => 300,
            'rows' => 3,
            'showCount' => 1,
            'stripTags' => 1,
            'textformatters' => array('TextformatterEntities'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_image' => array(
            'name' => 'snipcart_item_image',
            'type' => 'FieldtypeImage',
            'label' => __('Product Image(s)'),
            'description' => __('The product image(s) your customers will see on product pages in cart and during checkout.'),
            'notes' => __('The image on first position will be used as the Snipcart thumbnail image. Only this image will be used in cart and during checkout'),
            'required' => false,
            'extensions' => 'gif jpg jpeg png',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_categories' => array(
            'name' => 'snipcart_item_categories',
            'type' => 'FieldtypePage',
            'inputfield' => 'InputfieldAsmSelect',
            'labelFieldName' => 'title', // (used for AsmSelect)
            'usePageEdit' => true, // (used for AsmSelect)
            'addable' => true, // (used for AsmSelect)
            'label' => __('Categories'),
            'description' => __('The categories for this product.'),
            'derefAsPage' => 0, // (used for AsmSelect)
            'parent_id' => '/categories/', // will be converted to page ID by installer (used for AsmSelect)
            'template_id' => 'category', // will be converted to template ID by installer (used for AsmSelect)
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_quantity' => array(
            'name' => 'snipcart_item_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Default Quantity'),
            'description' => __('The default quantity for the product that will be added to cart.'),
            'notes' => __('Integer number (min value = 1).'),
            'defaultValue' => 1,
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_quantity_step' => array(
            'name' => 'snipcart_item_quantity_step',
            'type' => 'FieldtypeInteger',
            'label' => __('Quantity Step'),
            'description' => __('The quantity of a product will increment by this value.'),
            'notes' => __('Integer number (min value = 1).'),
            'defaultValue' => 1,
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_max_quantity' => array(
            'name' => 'snipcart_item_max_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Maximum Quantity'),
            'description' => __('Set the maximum allowed quantity for this product.'),
            'notes' => __('Leave empty for no limit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_min_quantity' => array(
            'name' => 'snipcart_item_min_quantity',
            'type' => 'FieldtypeInteger',
            'label' => __('Minimum Quantity'),
            'description' => __('Set the minimum allowed quantity for this product.'),
            'notes' => __('Leave empty for no limit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_weight' => array(
            'name' => 'snipcart_item_weight',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Weight'),
            'description' => __('Set the weight for this product.'),
            'notes' => __('Uses grams as weight unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_width' => array(
            'name' => 'snipcart_item_width',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Width'),
            'description' => __('Set the width for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_length' => array(
            'name' => 'snipcart_item_length',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Length'),
            'description' => __('Set the length for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_height' => array(
            'name' => 'snipcart_item_height',
            'type' => 'FieldtypeInteger',
            'label' => __('Product Height'),
            'description' => __('Set the height for this product.'),
            'notes' => __('Uses centimeters as unit.'),
            'min' => 1,
            'inputType' => 'number',
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_shippable' => array(
            'name' => 'snipcart_item_shippable',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Shippable'),
            'label2' => __('Product is shippable'),
            'description' => __('Uncheck, if this product should be flagged as not shippable.'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_taxable' => array(
            'name' => 'snipcart_item_taxable',
            'type' => 'FieldtypeCheckbox',
            'label' => __('Taxable'),
            'label2' => __('Product is taxable'),
            'description' => __('Uncheck, if this product should be excluded from taxes calculation.'),
            'required' => false,
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),
        'snipcart_item_taxes' => array(
            'name' => 'snipcart_item_taxes',
            'type' => 'FieldtypeSnipWireTaxSelector',
            'label' => __('VAT'),
            'description' => __('Select the tax which should be applied.'),
            'required' => false,
            'tags' => 'Snipcart',
            'taxesType' => 1, // = taxesTypeProducts
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        ),

    ),

    'pages' => array(
        'snipcart-shop' => array(
            'name' => 'snipcart-shop',
            'title' => 'Snipcart Shop',
            'template' => 'snipcart-shop',
            'parent' => '/', // needs to be page path
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'fuzzy-regalia' => array(
            'name' => 'big-schlemel-stout',
            'title' => 'Big Schlemel Stout',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price_eur' => '69.98',
                'snipcart_item_description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
                'snipcart_item_image' => 'sample_images/beer1.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'square-cream-hoax' => array(
            'name' => 'festish-wet-warmer',
            'title' => 'Festish Wet Warmer',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price_eur' => '19.90',
                'snipcart_item_description' => 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
                'snipcart_item_image' => 'sample_images/beer2.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
        'axolotl-juicer' => array(
            'name' => 'axolotl-juicer',
            'title' => 'Axolotl Juicer',
            'template' => 'snipcart-product',
            'parent' => '/snipcart-shop/', // needs to be page path
            'fields' => array(
                'snipcart_item_price_eur' => '1199',
                'snipcart_item_description' => 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
                'snipcart_item_image' => 'sample_images/beer3.jpg', // source file from module directory
            ),
            '_uninstall' => 'delete', // "trash" or "delete" or "no"
        ),
    ),
);




