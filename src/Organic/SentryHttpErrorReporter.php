<?php

namespace Organic;

class SentryHttpErrorReporter implements ErrorReporter {
    private $dsn;

    private $environment;

    private $endpoint;

    public function __construct( string $dsn, string $environment ) {
        $this->dsn = $dsn;
        $this->environment = strtolower( $environment );
        $this->endpoint = $this->buildEnvelopeEndpoint( $dsn );
    }

    public function captureException( \Throwable $e ): void {
        if ( ! $this->endpoint || ! function_exists( 'wp_remote_post' ) ) {
            return;
        }

        $event = $this->buildEvent( $e );
        $body = implode(
            "\n",
            [
                $this->jsonEncode(
                    [
                        'event_id' => $event['event_id'],
                        'dsn' => $this->dsn,
                    ]
                ),
                $this->jsonEncode( [ 'type' => 'event' ] ),
                $this->jsonEncode( $event ),
            ]
        );

        wp_remote_post(
            $this->endpoint,
            [
                'headers' => [
                    'Content-Type' => 'application/x-sentry-envelope',
                    'User-Agent' => 'organic-wordpress-plugin/' . \Organic\ORGANIC_PLUGIN_VERSION,
                ],
                'body' => $body,
                'timeout' => 2,
                'blocking' => false,
            ]
        );
    }

    private function buildEnvelopeEndpoint( string $dsn ) {
        $parts = parse_url( $dsn );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
            return null;
        }

        $path = trim( $parts['path'], '/' );
        if ( $path === '' ) {
            return null;
        }

        $segments = explode( '/', $path );
        $projectId = array_pop( $segments );
        if ( ! $projectId ) {
            return null;
        }

        $basePath = '';
        if ( ! empty( $segments ) ) {
            $basePath = '/' . implode( '/', $segments );
        }

        $port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
        return sprintf(
            '%s://%s%s%s/api/%s/envelope/',
            $parts['scheme'],
            $parts['host'],
            $port,
            $basePath,
            rawurlencode( $projectId )
        );
    }

    private function buildEvent( \Throwable $e ): array {
        $event = [
            'event_id' => $this->eventId(),
            'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'platform' => 'php',
            'logger' => 'organic-wordpress-plugin',
            'level' => 'error',
            'environment' => $this->environment,
            'release' => \Organic\ORGANIC_PLUGIN_VERSION,
            'exception' => [
                'values' => [
                    [
                        'type' => get_class( $e ),
                        'value' => $e->getMessage(),
                        'stacktrace' => [
                            'frames' => $this->buildFrames( $e ),
                        ],
                    ],
                ],
            ],
            'tags' => [
                'wordpress_plugin' => 'organic',
            ],
        ];

        $serverName = $this->serverName();
        if ( $serverName ) {
            $event['server_name'] = $serverName;
        }

        return $event;
    }

    private function buildFrames( \Throwable $e ): array {
        $frames = [
            $this->formatFrame(
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'function' => '{main}',
                ]
            ),
        ];

        foreach ( $e->getTrace() as $traceFrame ) {
            $frames[] = $this->formatFrame( $traceFrame );
        }

        return array_reverse( array_values( array_filter( $frames ) ) );
    }

    private function formatFrame( array $frame ) {
        if ( empty( $frame['file'] ) && empty( $frame['function'] ) ) {
            return null;
        }

        $formatted = [
            'in_app' => true,
        ];

        if ( ! empty( $frame['file'] ) ) {
            $formatted['filename'] = $frame['file'];
        }
        if ( ! empty( $frame['line'] ) ) {
            $formatted['lineno'] = $frame['line'];
        }
        if ( ! empty( $frame['function'] ) ) {
            $function = $frame['function'];
            if ( ! empty( $frame['class'] ) ) {
                $function = $frame['class'] . ( $frame['type'] ?? '::' ) . $function;
            }
            $formatted['function'] = $function;
        }

        return $formatted;
    }

    private function serverName() {
        if ( function_exists( 'home_url' ) ) {
            $host = parse_url( home_url(), PHP_URL_HOST );
            if ( $host ) {
                return $host;
            }
        }

        return null;
    }

    private function eventId(): string {
        try {
            return bin2hex( random_bytes( 16 ) );
        } catch ( \Exception $e ) {
            return md5( uniqid( '', true ) );
        }
    }

    private function jsonEncode( array $value ): string {
        if ( function_exists( 'wp_json_encode' ) ) {
            $json = wp_json_encode( $value );
        } else {
            $json = json_encode( $value );
        }

        return is_string( $json ) ? $json : '{}';
    }
}
