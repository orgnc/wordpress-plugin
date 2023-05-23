<?php

namespace Organic;

class AmpAdsInjector extends \AMP_Base_Sanitizer {
    /**
     * @var Organic
     */
    private $organic;

    /**
     * @var array
     */
    private $targeting = null;
    /**
     * @var SlotsInjector
     */
    private $slotsInjector;

    public function sanitize() {
        try {
            $this->slotsInjector = new SlotsInjector(
                $this->dom,
                function( $html ) {
                    $document = $this->dom::fromHtmlFragment( $html );
                    return $document->getElementsByTagName( 'body' )->item( 0 );
                }
            );
            $this->organic = Organic::getInstance();
            $this->targeting = $this->organic->getTargeting();
            $this->handle();
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
    }

    public function handle() {
        $ampConfig = $this->args['ampConfig'];
        $adsConfig = $this->args['adsConfig'];

        $blockedKeys = $this->slotsInjector::getBlockedPlacementKeys(
            $adsConfig->adRules,
            $this->targeting
        );
        // all placements are blocked by rule
        if ( in_array( 'ALL', $blockedKeys ) ) {
            return;
        }

        foreach ( $ampConfig->forPlacement as $key => $amp ) {
            // certain placement is blocked
            if ( in_array( $key, $blockedKeys ) ) {
                continue;
            }

            $placement = $adsConfig->forPlacement[ $key ];
            if ( ! $placement['enabled'] ) {
                continue;
            }

            $relativeSelectors = $this->slotsInjector::getRelativeSelectors( $placement );
            $limit = $placement['limit'];

            if ( $placement['adType'] == AD_TYPE::OUTSTREAM_VIDEO ) {
                $adHtml = $this->applyConnatixParams( $amp['html'], $this->targeting );
            } else if ( $placement['adType'] == AD_TYPE::DEFAULT ) {
                $adHtml = $this->applyAdTargeting( $amp['html'], $this->targeting );
            } else {
                $adHtml = $amp['html'];
            }

            try {
                $this->slotsInjector->injectSlots( $adHtml, $relativeSelectors, $limit );
            } catch ( \Exception $e ) {
                \Organic\Organic::captureException( $e );
            }
        }
    }

    public function getConnatixParams( $targeting ) {
        $section = implode( ',', $targeting['sections'] ?: [] );
        $keywords = implode( ',', $targeting['keywords'] ?: [] );
        $gamPageId = $targeting['gamPageId'];
        $externalId = $targeting['gamExternalId'];
        $macros = htmlspecialchars(
            json_encode(
                [
                    'cust_params' => http_build_query(
                        [
                            'site' => $this->organic->siteDomain,
                            'targeting_article' => $externalId,
                            'targeting_section' => $section,
                            'targeting_keyword' => $keywords,
                            'article' => $gamPageId,
                        ]
                    ),
                    'article' => $gamPageId,
                    'category' => $section,
                    'keywords' => $keywords,
                ]
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        return sprintf(
            'data-param-custom-param1="%s"
            data-param-custom-param2="%s"
            data-param-custom-param3="%s"
            data-param-macros="%s"',
            esc_attr( $gamPageId ),
            esc_attr( $section ),
            esc_attr( $keywords ),
            esc_attr( $macros )
        );
    }

    public function applyConnatixParams( $html, $targeting ) {
        $dataParams = $this->getConnatixParams( $targeting );
        return str_replace( 'data-param="{}"', $dataParams, $html );
    }

    public function applyAdTargeting( $html, $values ) {
        $targeting = [
            'amp' => 1,
            'site' => $values['siteDomain'],
            'article' => $values['gamPageId'],
            'targeting_article' => $values['gamExternalId'],
        ];

        $keywords = $values['keywords'];
        if ( ! empty( $keywords ) ) {
            $targeting['content_keyword'] = $keywords;
            $targeting['targeting_keyword'] = $keywords;
        }

        $sections = $values['sections'];

        if ( ! empty( $sections ) ) {
            $targeting['site_section'] = $sections;
            $targeting['targeting_section'] = $sections;
        }

        $json = json_encode( [ 'targeting' => $targeting ] );
        return str_replace( 'json="{}"', 'json=' . $json, $html );
    }
}

