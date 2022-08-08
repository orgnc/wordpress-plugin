<?php

namespace Organic;

/**
 * Handles collecting metadata that gets added to the page
 *
 * @package Organic
 */
class MetadataCollector
{
    /**
     * @var Organic
     */
    private $organic;

    private $yoastPresenters = [];

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        if (!$this->organic->isEnabled()) {
            return;
        }
        add_action( 'wpseo_frontend_presenters', [ $this, 'storePresenters'], 10, 1 );

    }

    public function getMetaTagData() {
        $data = [];
        foreach ($this->yoastPresenters as $p) {
            $val = $p->get();
            if (empty($val)) continue;
            $key = $p->get_key();
            if (!str_starts_with($key, "og:") and
                !str_starts_with($key, "twitter:")) continue;
            $items = [];
            if (is_array($val)) {
                foreach ($val as $elem) {
                    if (is_array($elem)) {
                        foreach ($elem as $subkey => $v) {
                            $newkey = $key . ":" . $subkey;
                            $items[$newkey] = $v;
                        }
                    }
                }
                if (empty($items)) {
                    $items[$key] = implode('; ', array_map(
                        function ($k, $v) {
                            return $k . "=" . $v;
                        },
                        array_keys($val),
                        array_values($val)
                    ));
                }
            } else {
                $items[$key] = $val;
            }
            $data = array_merge($data, $items);
        };
        return $data;
    }

    public function storePresenters($presenters) {
        foreach ($presenters as $p) {
            $this->yoastPresenters[] = $p;
        }
    }
}