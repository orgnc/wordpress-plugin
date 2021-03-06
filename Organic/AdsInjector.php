<?php

namespace Organic;

use DOMDocument;
use DOMNode;
use DOMXPath;


class AdsInjector {
    private $fragmentBuilder;
    /**
     * @var null|\FluentDOM\Xpath\Transformer
     */
    private $xpathTransformer = null;

    public static function loadElement( $html, $type = 'html5' ) {
        $document = \FluentDOM::load(
            $html,
            $type,
            [
                \FluentDOM\HTML5\Loader::DISABLE_HTML_NAMESPACE => true,
            ]
        );
        return $document->getElementsByTagName( 'html' )->item( 0 );
    }

    public static function copyFragment( DOMDocument $dom, DOMNode $source ) {
        $target = $dom->createDocumentFragment();
        foreach ( $source->childNodes as $child ) {
            $node = $dom->importNode( $child, true );
            $target->appendChild( $node );
        }
        return $target;
    }

    public function __construct( $dom, $fragmentBuilder = null ) {
        $this->dom = $dom;
        // AMP and FluentDOM are using different methods to build fragments
        $this->fragmentBuilder = $fragmentBuilder ?? function ( $html ) {
            return self::loadElement( $html );
        };
    }

    public function getXPathTransformer() {
        if ( ! $this->xpathTransformer ) {
            $this->xpathTransformer = \FluentDOM::getXPathTransformer();
        }
        return $this->xpathTransformer;
    }

    public function querySelector( string $selector ) {
        $path = $this->getXPathTransformer()->toXpath( $selector );
        return ( new DOMXPath( $this->dom ) )->query( $path );
    }

    public function injectAds( $adHtml, $relative, $selectors, $limit ) {
        $count = 0;
        foreach ( $selectors as $selector ) {
            foreach ( $this->querySelector( $selector ) as $elem ) {
                $ad = $this->nodeFromHtml( $adHtml );
                $injected = $this->injectAd( $ad, $relative, $elem );
                if ( $injected ) {
                    $count++;
                }

                if ( $count >= $limit ) {
                    return $count;
                }
            }
        }

        return $count;
    }

    public function injectAd( $ad, $relative, $elem ) {
        switch ( $relative ) {
            case 'inside_start':
                return $elem->insertBefore( $ad, $elem->firstChild );
            case 'inside_end':
                return $elem->appendChild( $ad );
            case 'after':
                return $elem->parentNode->insertBefore( $ad, $elem->nextSibling );
            case 'before':
                return $elem->parentNode->insertBefore( $ad, $elem );
            case 'sticky_footer':
                return $elem->appendChild( $ad );
            default:
                return false;
        }
    }

    public function nodeFromHtml( $html ) {
        // ($this->clbk)() - that's how you call closure stored as attr on object
        // https://wiki.php.net/rfc/uniform_variable_syntax#incomplete_dereferencing_support
        $node = ( $this->fragmentBuilder )( $html );
        return self::copyFragment( $this->dom, $node );
    }

    public static function getBlockRule( $adRules, $targeting ) {
        foreach ( $adRules as $rule ) {
            if ( ! $rule['enabled'] ) {
                continue;
            }

            $url = $targeting['url'];
            $components = [];
            switch ( $rule['component'] ) {
                case 'PATH':
                    $components = [ parse_url( $url )['path'] ?? '/' ];
                    break;
                case 'TAG':
                    $components = $targeting['keywords'];
                    break;
                case 'CATEGORY':
                    $category = $targeting['category'];
                    if ( $category ) {
                        $components = [ $category->slug ];
                    }
                    break;
            }

            $blocked = array_reduce(
                array_map(
                    function ( $component ) use ( $rule ) {
                        switch ( $rule['comparator'] ) {
                            case 'CONTAINS':
                                return ( strpos( $component, $rule['value'] ) !== false );
                            case 'STARTS_WITH':
                                return ( strpos( $component, $rule['value'] ) === 0 );
                            case 'EXACTLY_MATCHES':
                                return ( $component === $rule['value'] );
                            default:
                                return false;
                        }
                    },
                    $components
                ),
                function ( $accumulator, $matched ) {
                    return $accumulator || $matched;
                },
                false
            );

            if ( $blocked ) {
                return $rule;
            }
        }

        return null;
    }
}
