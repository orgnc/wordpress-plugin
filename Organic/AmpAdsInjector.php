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

                // get playspaceId from placement's 'connatixId'
                $connatixId = trim( $placement['connatixId'] ) ?: '';
                $psid = is_valid_uuid( $connatixId ) ? $connatixId : '';

                $this->injectConnatix(
                    $psid,
                    $relative,
                    $selectors,
                    $limit
                );
                continue;
            }

            $adHtml = $this->applyTargeting( $amp['html'], $this->targeting );
            try {
                $this->adsInjector->injectAds( $adHtml, $relative, $selectors, $limit );
            } catch ( \Exception $e ) {
                \Organic\Organic::captureException( $e );
            }
        }

        // if no connatix players were injected by placements settings,
        // try to inject one into default position
        if ( $this->connatixEnabled && $this->connatixInjected === 0 ) {
            $this->injectConnatix(
                $this->connatix->getPlayspaceId(),
                ConnatixConfig::DEFAULT_AMP_RELATIVE,
                ConnatixConfig::DEFAULT_AMP_SELECTORS
            );
        }

    }

    private function injectConnatix( string $psid, string $relative, array $selectors, $limit = 1 ) {
        if ( ! $psid ) {
            return;
        }
        $base = AMP_BREAKPOINT::MD;
        $smallMedia = AMP_BREAKPOINT::maxWidth( $base - 1 );
        $largeMedia = AMP_BREAKPOINT::minWidth( $base );

        $smallPlayer = $this->createConnatixPlayer( $psid, $smallMedia, 4, 3 );
        $largePlayer = $this->createConnatixPlayer( $psid, $largeMedia, 16, 9 );
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

    private function createConnatixPlayer( string $psid, string $media = '', int $width = 16, int $height = 9 ) {
        $key = "${psid}_${media}_${width}_${height}";

        if ( isset( $this->connatixPlayers[ $key ] ) ) {
            return $this->connatixPlayers[ $key ];
        }

        $targeting = $this->targeting;
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

        $mediaAttr = $media
            ? "media=\"$media\""
            : '';

        $player = "
            <amp-connatix-player
                $mediaAttr,
                data-player-id=\"ps_$psid\"
                layout=\"responsive\"
                width=\"$width\"
                height=\"$height\"
                data-param-custom-param1=\"$gamPageId\"
                data-param-custom-param2=\"$section\"
                data-param-custom-param3=\"$keywords\"
                data-param-macros=\"$macros\"
            >
            </amp-connatix-player>";

        $this->connatixPlayers[ $key ] = $player;

        return $player;
    }


    public function applyTargeting( $html, $values ) {
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

