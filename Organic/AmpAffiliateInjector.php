<?php

namespace Organic;

use AMP_Base_Sanitizer;
use DOMXPath;

class AmpAffiliateInjector extends \AMP_Base_Sanitizer {
    private $organic;
    private $adsInjector;
    private $affiliate_domain;

    public function sanitize() {
        try {
            $this->organic = Organic::getInstance();
            # we use the "ads" injector to inject amp-iframe product card code
            $this->adsInjector = new AdsInjector(
                $this->dom,
                function( $html ) {
                    $document = $this->dom::fromHtmlFragment( $html );
                    return $document->getElementsByTagName( 'body' )->item( 0 );
                }
            );
            $this->affiliate_domain = $this->getAffiliateDomain();
            $this->handle();
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
    }

    private function getAffiliateDomain() {
        $affiliate_domain = $this->organic->getAffiliateDomain();
        if ( ! $affiliate_domain ) {
            $this->organic->syncAffiliateConfig();
            $affiliate_domain = $this->organic->getAffiliateDomain();
        }
        return $affiliate_domain;
    }

    public function handle() {

        if ( empty( $this->affiliate_domain ) ) {
            return; // can't insert amp-iframes without a public domain
        }
        $this->handleProductCards();
    }

    public function handleProductCards() {
        $xpath = new DOMXPath( $this->dom );
        $product_card_divs = $xpath->query( "//div[@data-organic-affiliate-integration='product-card']" );
        foreach ( $product_card_divs as $product_card_div ) {
            $processed = $product_card_div->getAttribute( 'data-organic-affiliate-processed' );
            if ( ! empty( $processed ) && $processed === true) {
                continue;
            }
            $this->injectAmpProductCard( $product_card_div );
        }
    }

    private function injectAmpProductCard( $product_card_div ) {
        $product_guid = $product_card_div->getAttribute( 'data-organic-affiliate-product-guid' );
        $options_str = $product_card_div->getAttribute( 'data-organic-affiliate-integration-options' );
        $url = "{$this->affiliate_domain}/integrations/affiliate/product-card?guid={$product_guid}";
        if ( ! empty( $options_str ) ) {
            // encode & properly for appendXML
            $url .= '&amp;' . str_replace( ',', '&amp;', $options_str );
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
        $product_card = $this->adsInjector->nodeFromHtml( $amp_iframe_code );
        $this->adsInjector->injectAd( $product_card, 'inside_start', $product_card_div );
        $product_card_div->setAttribute( 'data-organic-affiliate-processed', true );
    }

}
