<?php

namespace Organic;

class FbiaProxy {
    private $article;
    private FbiaAdsInjector $injector;

    public function __construct( $article, $organic ) {
        $this->article = $article;
        $this->injector = new FbiaAdsInjector( $organic );
    }

    private function render( $arguments ) {
        $html = call_user_func_array( [ $this->article, 'render' ], $arguments );
        $injected = $this->injector->inject( $html );
        return $injected ?? $html;
    }

    public function __call( $name, $arguments ) {
        if ( $name === 'render' ) {
            return $this->render( $arguments );
        }
        return call_user_func_array(
            [
                $this->article,
                $name,
            ],
            $arguments
        );
    }

}
