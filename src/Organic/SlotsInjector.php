<?php

namespace Organic;

use DOMDocument;
use DOMNode;
use DOMXPath;


class SlotsInjector {
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

    public function injectSlots( $slotHtml, $relativeSelectors, $limit ) {
        $count = 0;
        foreach ( $relativeSelectors as $relativeSelector ) {
            $selector = $relativeSelector['selector'];
            $relative = $relativeSelector['relative'];
            foreach ( $this->querySelector( $selector ) as $elem ) {
                $slot = $this->nodeFromHtml( $slotHtml );
                $injected = $this->injectSlot( $slot, $relative, $elem );
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

    public function injectSlot( $slot, $relative, $elem ) {
        switch ( strtoupper( $relative ) ) {
            case 'INSIDE_START':
                return $elem->insertBefore( $slot, $elem->firstChild );
            case 'INSIDE_END':
                return $elem->appendChild( $slot );
            case 'AFTER':
                return $elem->parentNode->insertBefore( $slot, $elem->nextSibling );
            case 'BEFORE':
                return $elem->parentNode->insertBefore( $slot, $elem );
            case 'STICKY_FOOTER':
                return $elem->appendChild( $slot );
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

    public static function getBlockRules( $adRules, $targeting ) {
        return array_filter(
            $adRules,
            function( $rule ) use ( $targeting ) {
                if ( ! $rule['enabled'] ) {
                    return false;
                }

                $components = [];
                $url = $targeting['url'];
                switch ( $rule['component'] ) {
                    case 'PATH':
                        $components = [ parse_url( $url )['path'] ?? '/' ];
                        break;
                    case 'URL':
                        $components = [ $url ];
                        break;
                    case 'TAG':
                        $components = $targeting['keywords'];
                        break;
                    case 'CATEGORY':
                        $components = $targeting['sections'];
                        break;
                    default:
                        return false;
                }

                $checkRule = function ( $component ) use ( $rule ) {
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
                };

                foreach ( $components as $component ) {
                    if ( $checkRule( $component ) ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    public static function getBlockedPlacementKeys( $adRules, $targeting ) {
        $blockRules = self::getBlockRules( $adRules, $targeting );
        return array_merge(
            ...array_map(
                function( $rule ) {
                    $placementKeys = $rule['placementKeys'];
                    return empty( $placementKeys ) ? [ 'ALL' ] : $placementKeys;
                },
                $blockRules
            )
        );
    }

    // For compatibility during the first deploy before new config pulled
    public static function getRelativeSelectors( $placement ) {
        $relativeSelectors = $placement['relativeSelectors'] ?? [];
        if ( ! empty( $relativeSelectors ) ) {
            return $relativeSelectors;
        }

        foreach ( $placement['selectors'] as $selector ) {
            $relativeSelectors[] = [
                'relative' => $placement['relative'],
                'selector' => $selector,
            ];
        }
        return $relativeSelectors;
    }
}
