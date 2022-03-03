<?php

namespace Organic;

use FluentDOM\DOM\Document;
use FluentDOM\DOM\Element;

class FbiaAdsInjector {
    private FbiaConfig $config;
    private Organic $organic;
    private $_targeting;

    private function getTargeting() {
        if ( ! $this->_targeting ) {
            $this->_targeting = $this->organic->getTargeting();
        }
        return $this->_targeting;
    }


    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        $this->config = $organic->getFbiaConfig();
    }

    private function getArticleHtml() {
        $adapter = new \Instant_Articles_Post( \get_post() );
        $article = $adapter->to_instant_article();
        return $article->render( null, true );
    }

    public function handle() {
        if ( ! $this->config->enabled ) {
            return null;
        }

        $html = $this->getArticleHtml();
        $dom = \FluentDOM::load(
            $html,
            'html5',
            [ \FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true ],
        );
        $this->injectMeta( $dom );

        if ( $this->config->isAutomatic() ) {
            $dom = $this->injectAutomaticPlacements( $dom );
        } else {
            $dom = $this->injectManualPlacements( $dom );
        }

        if ( ! $dom ) {
            return;
        }

        return $dom->saveHTML();
    }

    /**
     * Adds <meta> tag to head.
     */
    private function injectMeta( Document $dom ) {
        if ( $this->config->isAutomatic() ) {
            $content = "enabled=true ad_density={$this->config->adDensity}";
        } else {
            $content = 'false';
        }

        $attrs = [
            'property' => 'fb:use_automatic_ad_placement',
            'content' => $content,
        ];

        $meta = $dom->createElement(
            'meta',
            '',
            $attrs,
        );

        $dom->getElementsByTagName( 'head' )->item( 0 )->appendChild(
            $meta
        );
    }


    private function injectAutomaticPlacements( Document $dom ) {
        $html = $this->setTargeting(
            $this->config->raw['placements'][0]['html']
        );

        if ( ! $html ) {
            return null;
        }
        $node = AdsInjector::copyFragment(
            $dom,
            AdsInjector::loadElement( $html ),
        );

        $dom->getElementsByTagName( 'header' )->item( 0 )->appendChild( $node );
        return $dom;
    }

    private function injectManualPlacements( Document $dom ) {
    }

    private function setTargeting( string $html ) {
        if ( ! $html ) {
            return $html;
        }

        $targeting = array_merge(
            $this->getTargeting(),
            [
                'fbia' => 1,
            ]
        );

        $json = json_encode( $targeting );

        return str_replace(
            'targeting = {};',
            "targeting = {$json};",
            $html
        );
    }

}
