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
     * @var AdsInjector
     */
    private $adsInjector;

    /**
     * @var ConnatixConfig
     */
    private $connatix;
    private $connatixEnabled = false;
    private $connatixInjected = 0;
    private $connatixPlayers = [];

    public function sanitize() {
        try {
            $this->adsInjector = new AdsInjector(
                $this->dom,
                function( $html ) {
                    $document = $this->dom::fromHtmlFragment( $html );
                    return $document->getElementsByTagName( 'body' )->item( 0 );
                }
            );
            $this->organic = Organic::getInstance();
            $this->connatix = $this->organic->getConnatixConfig();
            $this->targeting = $this->organic->getTargeting();
            $this->connatixEnabled = $this->connatix->isEnabled() && is_single();
            $this->handle();
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
    }

    public function handle() {
        $ampConfig = $this->args['ampConfig'];
        $adsConfig = $this->args['adsConfig'];

        $rule = $this->adsInjector::getBlockRule( $adsConfig->adRules, $this->targeting );
        $blockedKeys = ( $rule ? $rule['placementKeys'] : [] ) ?? [];

        // all placements are blocked by rule
        if ( $rule && ! $blockedKeys ) {
            return;
        }

        foreach ( $ampConfig->forPlacement as $key => $amp ) {
            $placement = $adsConfig->forPlacement[ $key ];
            if ( ! $placement['enabled'] ) {
                continue;
            }

            // certain placement is blocked
            if ( $rule && in_array( $key, $blockedKeys ) ) {
                continue;
            }

            $selectors = $placement['selectors'];
            $limit = $placement['limit'];
            $relative = $placement['relative'];

            // if placement's ad_type is set to 'outstream_video':
            // inject connatix player instead of generic amp template
            if ( $placement['adType'] == AD_TYPE::OUTSTREAM_VIDEO ) {
                $adHtml = $this->applyConnatixParams( $amp['html'], $this->targeting );
            } else if ( $placement['adType'] == AD_TYPE::DEFAULT ) {
                $adHtml = $this->applyAdTargeting( $amp['html'], $this->targeting );
            } else {
                $adHtml = $amp['html'];
            }

            try {
                $count = $this->adsInjector->injectAds( $adHtml, $relative, $selectors, $limit );
                if ( $placement['adType'] == AD_TYPE::OUTSTREAM_VIDEO ) {
                    $this->connatixInjected += $count;
                }
            } catch ( \Exception $e ) {
                \Organic\Organic::captureException( $e );
            }
        }

        // if no connatix players were injected by placements settings,
        // try to inject one into default position
        if ( $this->connatixEnabled && $this->connatixInjected === 0 ) {
            $this->deprecatedInjectConnatix(
                $this->connatix->getPlayspaceId(),
                ConnatixConfig::DEFAULT_AMP_RELATIVE,
                ConnatixConfig::DEFAULT_AMP_SELECTORS
            );
        }

    }

    private function deprecatedInjectConnatix( string $psid, string $relative, array $selectors, $limit = 1 ) {
        if ( ! $psid ) {
            return;
        }
        $base = AMP_BREAKPOINT::MD;
        $smallMedia = AMP_BREAKPOINT::maxWidth( $base - 1 );
        $largeMedia = AMP_BREAKPOINT::minWidth( $base );

        $smallPlayer = $this->deprecatedCreateConnatixPlayer( $psid, $smallMedia, 4, 3 );
        $largePlayer = $this->deprecatedCreateConnatixPlayer( $psid, $largeMedia, 16, 9 );
        $players = "${smallPlayer}\n${largePlayer}";

        try {
            $count = $this->adsInjector->injectAds( $players, $relative, $selectors, $limit );
            $this->connatixInjected += $count;
            return $count;
        } catch ( \Exception $e ) {
            \Organic\Organic::captureException( $e );
        }
        return 0;
    }

    private function deprecatedCreateConnatixPlayer( string $psid, string $media = '', int $width = 16, int $height = 9 ) {
        $key = "${psid}_${media}_${width}_${height}";

        if ( isset( $this->connatixPlayers[ $key ] ) ) {
            return $this->connatixPlayers[ $key ];
        }

        $mediaAttr = $media
            ? 'media="' . esc_attr( $media ) . '"'
            : '';

        $player = sprintf(
            '<amp-connatix-player
                data-player-id="ps_%s"
                layout="responsive"
                width="%s"
                height="%s"
                %s
                %s
            >
            </amp-connatix-player>',
            esc_attr( $psid ),
            esc_attr( $width ),
            esc_attr( $height ),
            $mediaAttr,
            $this->getConnatixParams( $this->targeting ),
        );

        $this->connatixPlayers[ $key ] = $player;

        return $player;
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
            esc_attr( $macros ),
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

