<?php


namespace Empire;

class Api {

    /**
     * @var string API key for communicating with TrackADM
     */
    private $apiKey;

    /**
     * @var string Root path for API requests for TrackADM
     */
    private $apiRoot;

    public function __construct( $environment, $apiKey ) {
        /* Set up our API root first in case any of the other classes need to make
         * API calls during initialization.
         */
        switch ( $environment ) {
            case Environment::LOCAL:
                $this->apiRoot = 'http://lcl.trackadm.com/api';
                break;
            case Environment::DEVELOPMENT:
                $this->apiRoot = 'https://dev.trackadm.com/api';
                break;
            case Environment::STAGING:
            case Environment::PRODUCTION:
            default:
                $this->apiRoot = 'https://trackadm.com/api';
                break;
        }

        $this->apiKey = $apiKey;
    }

    /**
     * Set a new API key (useful if you have just persisted a new key from the user
     * after this instance has been initialized).
     *
     * @param string $apiKey
     */
    public function updateApiKey( string $apiKey ) {
        $this->apiKey = $apiKey;
    }

    /**
     * Checks if the API token and the API Root path are healthy with a ping
     * request to the API
     */
    public function isHealthy() : bool {
        $result = $this->call( '/status' );
        if ( $result['success'] ) {
            return true;
        }

        return false;
    }

    /**
     * Checks the API for any Ad Pixels that we have access to and could
     * inject onto this site.
     *
     * @return array
     */
    public function getPixels(): array {
        $result = $this->call( '/pixels' );
        if ( isset( $result['data'] ) ) {
            return $result['data'];
        } else {
            return array();
        }
    }

    /**
     * Look up the details for a specific pixel.
     *
     * If not found, returns null. If found, returns array with fields:
     *
     * - id
     * - name
     * - published_url
     * - testing_url
     *
     * @param $id ID of the pixel to fetch
     * @return array|null
     */
    public function getPixel( $id ): ?array {
        $pixels = $this->getPixels();
        foreach ( $pixels as $pixel ) {
            if ( $pixel['id'] == $id ) {
                return $pixel;
            }
        }
        return null;
    }

    /**
     * Internal helper to execute the API call to TrackADM
     *
     * @param $path
     * @param string $method
     * @param null $data
     * @return bool[]|mixed
     */
    private function call( $path, $method = 'GET', $data = null ) {
        $ch = curl_init();

        $url = $this->apiRoot . $path;

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'authorization: Bearer ' . $this->apiKey ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );

        $responseRaw = curl_exec( $ch );
        if ( $responseRaw ) {
            $response = json_decode( $responseRaw, true );
            if ( $response ) {
                return $response;
            } else {
                return array( 'success' => false );
            }
        } else {
            return array( 'success' => false );
        }
    }
}
