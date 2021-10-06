<?php

namespace Empire;


class PrefillAdsInjector {
    private $adsConfig;
    private $prefillConfig;
    private $targeting;

    public function __construct( $adsConfig, $prefillConfig, $targeting ) {
        $this->adsConfig = $adsConfig;
        $this->prefillConfig = $prefillConfig;
        $this->targeting = $targeting;
    }

    public function prefill( $content ) {
        $contentDom = \FluentDOM::load(
            $content, 'html5', [\FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true],
        );

        $adsInjector = new AdsInjector($contentDom, function($html) {
            $document = \FluentDOM::load(
                $html, 'html5', [\FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true],
            );
            return $document->getElementsByTagName('html')->item(0);
        });

        if ($adsInjector->checkAdsBlocked($this->adsConfig['adRules'], $this->targeting)) {
            return $content;
        }

        $styles = '';
        foreach ($this->prefillConfig['forPlacement'] as $key => $prefill) {
            $placement = $this->adsConfig['forPlacement'][$key];

            [
                'selectors' => $selectors,
                'limit' => $limit,
                'relative' => $relative,
            ] = $placement;
 
            $adContainer = $prefill['html'];
            $count = $adsInjector->injectAds($adContainer, $relative, $selectors, $limit);
            if ( $count > 0) {
                $styles = $styles . $prefill['css'] . "\n";
            }
        }

        if ($styles) {
            $this->injectStyles($contentDom, $styles);
        }

        return $contentDom->saveHTML();
    }

    public function injectStyles($dom, $styles) {
        $dom->getElementsByTagName('head')->item(0)->appendElement(
          'style', $styles, ['type' => 'text/css', 'id' => 'empire-prefill-css']
        );
    }
}

