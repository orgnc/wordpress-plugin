<?php

namespace Organic;

class AmpAffiliateInjector extends \AMP_Base_Sanitizer {
    private Organic $organic;

    public function sanitize() {
        try {
            $this->organic = Organic::getInstance();
            $this->handle();
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
    }

    public function handle() {
        $site_public_domain = $this->organic->getSitePublicDomain();
        if ( empty( $site_public_domain ) ) {
            return; // can't insert amp-iframes without a public domain
        }
        $this->handle_product_cards();
    }

    public function handle_product_cards() {
        $xpath = new DOMXPath($this->dom);
        $product_card_divs = $xpath->query("//a[@data-organic-affiliate-integration='product-card']");
        foreach ( $product_card_divs as $product_card_div ) {
            $this->injectAmpProductCard($product_card_div);
        }
    }

    public function injectAmpProductCard( $product_card_div, $site_public_domain ) {
        $product_guid = $product_card_div->getAttribute('data-organic-affiliate-product-guid');
        $options_str = $product_card_div->getAttribute('data-organic-affiliate-integration-options');
        $public_domain = $this->organic->getSitePublicDomain();
        $url = $site_public_domain . "?guid={$product_guid}";
        if ( ! empty( $options_str ) ) {
            $url += "&" . str_replace( ",", '&' . $options_str );
        }
        $amp_iframe_code = <<<HTML
            <amp-iframe
                height="540px"
                frameborder="0"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                src="$url"
            >
                <p placeholder>Loading iframe content</p>
            </amp-iframe>
        HTML;
        $amp_iframe = $this->dom->createElement( $amp_iframe_code );
        $product_card_div.appendChild($amp_iframe);
    }

}