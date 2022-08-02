<?php

namespace Organic;

use FluentDOM\DOM\Document;

class FbiaAdsInjector {
    const ALL_KEYS = 'ALL_KEYS';

    /**
     * @var FbiaConfig
     */
    private $config;

    /**
     * @var Organic
     */
    private $organic;
    private $_targeting;
    private $_blockedKeys;

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

    public function inject( string $html ) {
        $dom = \FluentDOM::load(
            $html,
            'html5',
            [ \FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true ]
        );
        $this->injectMeta( $dom );

        if ( $this->config->isAutomatic() ) {
            $dom = $this->injectAutomaticPlacements( $dom );
        } else {
            $dom = $this->injectManualPlacements( $dom );
        }

        if ( ! $dom ) {
            return null;
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
            $attrs
        );

        $dom->getElementsByTagName( 'head' )->item( 0 )->appendChild(
            $meta
        );
    }


    private function injectAutomaticPlacements( Document $dom ) {
        $placement = $this->config->raw['placements'][0];
        if ( $this->isKeyBlocked( $placement['key'] ) ) {
            return null;
        }

        $html = $this->injectTargeting( $placement['html'] );
        if ( ! $html ) {
            return null;
        }

        $node = AdsInjector::copyFragment(
            $dom,
            AdsInjector::loadElement( $html )
        );

        $dom->getElementsByTagName( 'header' )->item( 0 )->appendChild( $node );
        return $dom;
    }

    private function injectManualPlacements( Document $dom ) {
        $injector = new AdsInjector( $dom );
        $adsConfig = $this->organic->getAdsConfig();
        $count = 0;

        foreach ( $this->config->forPlacement as $key => $fbia ) {
            if ( $this->isKeyBlocked( $key ) ) {
                continue;
            }

            $html = $this->injectTargeting( $fbia['html'] );
            if ( ! $html ) {
                continue;
            }

            $placement = $adsConfig->forPlacement[ $key ];
            if ( ! $placement['enabled'] ) {
                continue;
            }
            try {
                $count += $injector->injectAds(
                    $html,
                    $placement['relative'],
                    $placement['selectors'],
                    $placement['limit']
                );
            } catch ( \Exception $e ) {
                \Organic\Organic::captureException( $e );
            }
        }

        return $count ? $dom : null;
    }

    private function injectTargeting( string $html ) {
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

    private function getBlockedKeys() {
        if ( ! $this->_blockedKeys ) {
            $adsConfig = $this->organic->getAdsConfig();
            $targeting = $this->getTargeting();
            $rule = AdsInjector::getBlockRule( $adsConfig->adRules, $targeting );

            if ( ! $rule ) {
                return [];
            }

            $keys = $rule['placementKeys'] ?? [];
            $this->_blockedKeys = $keys ?: [ self::ALL_KEYS ];

        }
        return $this->_blockedKeys;
    }

    private function isKeyBlocked( $key ) {
        $keys = $this->getBlockedKeys();
        if ( in_array( $key, $keys ) || in_array( self::ALL_KEYS, $keys ) ) {
            return true;
        }
        return false;
    }

}
