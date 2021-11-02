<?php

namespace Empire;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Empire
 */
class AdsTxt
{

    /**
     * @var Empire
     */
    private $empire;

    public function __construct(Empire $empire)
    {
        $this->empire = $empire;

        add_action('init', array( $this, 'show' ));
    }

    public function get()
    {
        return get_option('empire::ads_txt');
    }

    public function show()
    {
        if (isset($_SERVER) && $_SERVER['REQUEST_URI'] === '/ads.txt') {
            $enabled = get_option('empire::enabled');

            if ($enabled) {
                $adsTxt = get_option('empire::ads_txt');
                header('content-type: text/plain; charset=UTF-8');
                header('cache-control: public, max-age=86400');
                echo $adsTxt;
                exit;
            }
        }
    }

    public function update(string $content)
    {
        update_option('empire::ads_txt', $content);
    }
}
