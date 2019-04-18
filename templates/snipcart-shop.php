<?php namespace ProcessWire;

/**
 * SnipWire sample shop parent template for "site-regular" site-profile.
 * 
 * The purpose of this template file is to provide a product overview for your Snipcart shop.
 * 
 */

if (!defined('PROCESSWIRE')) die();
?>
<!--
This is the mini cart display which shows the "Items in cart" count and the total cart value.

Notice that the container element has a "snipcart-summary" class. Add the markup you want within 
this container, then add "snipcart-total-items" and "snipcart-total-price" to the elements that will 
contain cart information.

Wrap the container element with a link having the class "snipcart-checkout" and the cart will pop when your 
visitors click on it.

The complete markup is up to you - it just needs to have the described classes from above included!
-->
<p id="masthead-tagline" class="-uk-text-small -uk-text-muted uk-margin-remove uk-text-center snipcart-summary">
    <a href="#" class="uk-link-reset snipcart-checkout">
        <?=ukIcon('cart')?> <span class="uk-badge snipcart-total-items"></span> <span class="snipcart-total-price"></span>
    </a>
</p>

<div id="content">
    <?php
    echo ukHeading1(page()->title, 'divider'); 
    $products = page()->children('limit=10');
    echo ukProductOverview($products); 
    ?>
</div>

<?php
/* 
    We hide the <aside> element which is not used in our shop sample.
    (this is only needed to use our sample templates with the "site-regular" site-profile)
*/
?>
<aside id="sidebar" hidden></aside>

<?php
/**
 * Render a shop product overview (uk-cards)
 *
 * @param PageArray $products
 * @return string
 *
 */
function ukProductOverview(PageArray $products) {

    if (!$products->count) return '';
    
    $out = '<div class="uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>';

    foreach ($products as $product) {
        
        // We use the first image in snipcart_item_image field
        $image = $product->snipcart_item_image->first();
        
        // Create required product image variant
        $productImageMedium = $image->size(600, 0, array('quality' => 70));
        
        $out .= '<a class="uk-link-reset" href="' . $product->url . '">';
        $out .= '    <div class="uk-card uk-card-default uk-card-hover">';
        $out .= '        <div class="uk-card-media-top">';
        $out .= '            <img src="' . $productImageMedium->url . '" alt="' . $product->title . '">';
        $out .= '        </div>';
        $out .= '        <div class="uk-card-body">';
        $out .= '            <h3 class="uk-card-title">' . $product->title . '</h3>';
        $out .= '            <p>' . $product->snipcart_item_description . '</p>';
        $out .= '        </div>';
        $out .= '        <div class="uk-card-footer">';
        $out .= '            <p>';
                                // This is the part where we render the Snipcart anchor (buy button)
                                // with data-item-* attributes required by Snipcart.
                                // The anchor method is provided by MarkupSnipWire module and can be called 
                                // via custom API variable: $snipwire->anchor()
        $out .= '               ' . wire('snipwire')->anchor($product, 'Buy now', 'uk-button uk-button-primary');
        $out .= '               <span class="uk-align-right uk-text-primary">';
                                    // Get the formatted product price.
                                    // The getProductPriceFormatted method is provided by MarkupSnipWire module and can be called 
                                    // via custom API variable: $snipwire->getProductPriceFormatted()
        $out .= '                   ' . wire('snipwire')->getProductPriceFormatted($product);
        $out .= '               </span>';
        $out .= '            </p>';
        $out .= '        </div>';
        $out .= '    </div>';
        $out .= '</a>';
    }

    $out .= '</div>';

    return $out;
}
?>
