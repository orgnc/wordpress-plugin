<?php

namespace Organic;

class PrefillAdsInjector {
    /**
     * @var AdsConfig
     */
    private $adsConfig;

    /**
     * @var PrefillConfig
     */
    private $prefillConfig;
    private $targeting;

    public function __construct( AdsConfig $adsConfig, PrefillConfig $prefillConfig, $targeting ) {
        $this->adsConfig = $adsConfig;
        $this->prefillConfig = $prefillConfig;
        $this->targeting = $targeting;
    }

    public function prefill( $content ) {
        $contentDom = \FluentDOM::load(
            $content,
            'html5',
            [ \FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true ]
        );

        $implementation = new \DOMImplementation();
        $doctype = $implementation->createDocumentType( 'html' );
        $contentDom->insertBefore( $doctype, $contentDom->firstChild );

        $slotsInjector = new SlotsInjector(
            $contentDom,
            function( $html ) {
                $document = \FluentDOM::load(
                    $html,
                    'html5',
                    [ \FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true ]
                );
                return $document->getElementsByTagName( 'html' )->item( 0 );
            }
        );

        $blockedKeys = slotsInjector::getBlockedPlacementKeys(
            $this->adsConfig->adRules,
            $this->targeting
        );
        // all placements are blocked by rule
        if ( in_array( 'ALL', $blockedKeys ) ) {
            return $content;
        }

        $styles = '';
        foreach ( $this->prefillConfig->forPlacement as $key => $prefill ) {
            // certain placement is blocked
            if ( in_array( $key, $blockedKeys ) ) {
                continue;
            }

            $placement = $this->adsConfig->forPlacement[ $key ];
            if ( ! $placement['enabled'] ) {
                continue;
            }

            if ( $placement['prefillDisabled'] ?? false ) {
                continue;
            }

            $relativeSelectors = $slotsInjector::getRelativeSelectors( $placement );
            $limit = $placement['limit'];

            $adContainer = $prefill['html'];

            $count = 0;
            try {
                $count = $slotsInjector->injectSlots( $adContainer, $relativeSelectors, $limit );
            } catch ( \Exception $e ) {
                \Organic\Organic::captureException( $e );
            }

            if ( $count > 0 ) {
                $styles = $styles . $prefill['css'] . "\n";
            }
        }

        if ( $styles ) {
            $this->injectStyles( $contentDom, $styles );
        }

        return $contentDom->saveHTML();
    }

    public function injectStyles( \FluentDOM\DOM\Document $dom, string $styles ) {
        $dom->getElementsByTagName( 'head' )->item( 0 )->appendElement(
            'style',
            $styles,
            [
                'type' => 'text/css',
                'id' => 'organic-prefill-css',
            ]
        );
    }
}

