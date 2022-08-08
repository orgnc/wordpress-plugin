<?php

namespace Organic;

/**
 * Handles collecting metadata that gets added to the page
 *
 * @package Organic
 */
class MetadataCollector {
    /**
     * @var Organic
     */
    private $organic;

    /**
     * @var array List of existing YOAST presenters
     */
    private $yoastPresenters = [];

    /**
     * Create a meta data collector
     *
     * @param Organic
     */
    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        if ( ! $this->organic->isEnabled() ) {
            return;
        }
        add_action( 'wpseo_frontend_presenters', [ $this, 'storePresenters' ], 10, 1 );

    }

    /**
     * Gets third party integration information
     *
     * @return array
     */
    public function getThirdPartyIntegrations() {
        $opt_ga = get_option( 'options_google_analytics_opt_ga_enabled' );
        $opt_gtm = get_option( 'options_google_tag_manager_opt_gtm_enabled' );
        $opt_fb = get_option( 'options_facebook_targeting_pixel_enabled' );
        $opt_cb = get_option( 'options_chartbeat_opt_chartbeat_enabled' );
        $opt_qc = get_option( 'options_quantcast_tag_quantcast_tag_enabled' );
        return [
            'has_google_analytics' => ( $opt_ga == '1' ),
            'has_google_tag_manager' => ( $opt_gtm == '1' ),
            'has_facebook_targeting' => ( $opt_fb == '1' ),
            'has_chart_beat' => ( $opt_cb == '1' ),
            'has_quant_cast' => ( $opt_qc == '1' ),
        ];
    }

    /**
     * Gets Meta tags information
     *
     * @return array
     */
    public function getMetaTagData() {
        $data = [];
        foreach ( $this->yoastPresenters as $p ) {
            $val = $p->get();
            if ( empty( $val ) ) {
                continue;
            }
            $key = $p->get_key();
            if ( ! str_starts_with( $key, 'og:' ) &&
                ! str_starts_with( $key, 'twitter:' ) ) {
                continue;
            };
            $items = array();
            if ( is_array( $val ) ) {
                foreach ( $val as $elem ) {
                    if ( is_array( $elem ) ) {
                        foreach ( $elem as $subkey => $v ) {
                            $newkey = $key . ':' . $subkey;
                            $items[ $newkey ] = $v;
                        }
                    }
                }
                if ( empty( $items ) ) {
                    $items[ $key ] = implode(
                        '; ',
                        array_map(
                            function ( $k, $v ) {
                                return $k . '=' . $v;
                            },
                            array_keys( $val ),
                            array_values( $val )
                        )
                    );
                }
            } else {
                $items[ $key ] = $val;
            }
            $data = array_merge( $data, $items );
        };
        return $data;
    }

    /**
     * Gets SEO schema data
     *
     * @param $post_id
     * @return array
     */
    public function getSeoSchemaData( $post_id ) {
        if ( function_exists( 'YoastSEO' ) ) {
            $seo_schema = YoastSEO()->meta->for_post( $post_id )->schema;
            if ( $seo_schema && $seo_schema['@graph'] ) {
                return $seo_schema['@graph'];
            }
        }
        return array();
    }

    /**
     * Gets SEO data
     *
     * @param $post_id
     * @return array
     */
    public function getSeoData( $post_id ) {
        if ( function_exists( 'YoastSEO' ) ) {
            $seo_data = YoastSEO()->meta->for_post( $post_id );
            return array(
                'seo_title' => $seo_data->title,
                'seo_description' => $seo_data->description,
                'seo_image_url' => '',
            );
        }
        return array();
    }

    /**
     * Stores existing YOAST presenters, provided by a filter
     *
     * @param $presenters
     * @return void|null
     */
    public function storePresenters( $presenters ) {
        foreach ( $presenters as $p ) {
            $this->yoastPresenters[] = $p;
        }
    }
}
