<?php

namespace Organic;

use AMP_Base_Sanitizer;
use DOMDocument;
use DOMXPath;

class AmpAffiliateInjector extends \AMP_Base_Sanitizer {
    private Organic $organic;

    public function sanitize() {
        try {
            $this->organic = Organic::getInstance();
            $this->site_organic_domain = $this->organic->getSiteOrganicDomain();
            $this->handle();
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
    }

    public function handle() {
        if ( empty( $this->site_organic_domain ) ) {
            return; // can't insert amp-iframes without a public domain
        }
        $this->handle_product_cards();
    }

    public function handle_product_cards() {
        $xpath = new DOMXPath( $this->dom );
        $product_card_divs = $xpath->query( "//div[@data-organic-affiliate-integration='product-card']" );
        foreach ( $product_card_divs as $product_card_div ) {
            $this->injectAmpProductCard( $product_card_div );
        }
    }

    public function injectAmpProductCard( $product_card_div ) {
        $product_guid = $product_card_div->getAttribute( "data-organic-affiliate-product-guid" );
        $options_str = $product_card_div->getAttribute( "data-organic-affiliate-integration-options" );
        $url = "https://{$this->site_organic_domain}/integrations/affiliate/product-card?guid={$product_guid}";
        if ( ! empty( $options_str ) ) {
            // encode & properly for appendXML
            $url .= "&amp;" . str_replace( ",", "&amp;", $options_str );
        }
        // placeholder attribute needed for AMP, ="" needed for valid XML
        $amp_iframe_code = <<<HTML
            <amp-iframe
                height="540px"
                frameborder="0"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                src="$url"
            >
                <p placeholder="">Loading iframe content</p>
            </amp-iframe>
        HTML;
        $fragment = $this->dom->createDocumentFragment();
        $fragment->appendXML( $amp_iframe_code );
        $product_card_div->appendChild( $fragment );
    }

}
