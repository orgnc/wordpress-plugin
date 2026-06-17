<?php

namespace {
    if ( ! function_exists( 'wp_remote_post' ) ) {
        function wp_remote_post( $url, $args ) {
            return \Organic\SentryHttpErrorReporterTest::recordRequest( $url, $args );
        }
    }

    if ( ! function_exists( 'wp_json_encode' ) ) {
        function wp_json_encode( $value ) {
            return json_encode( $value );
        }
    }

    if ( ! function_exists( 'home_url' ) ) {
        function home_url() {
            return 'https://example.test';
        }
    }
}

namespace Organic {
    use PHPUnit\Framework\TestCase;

    if ( ! defined( 'Organic\ORGANIC_PLUGIN_VERSION' ) ) {
        define( 'Organic\ORGANIC_PLUGIN_VERSION', 'version' );
    }

    class FakeErrorReporter implements ErrorReporter {
        public $exceptions = [];

        public function captureException( \Throwable $e ): void {
            $this->exceptions[] = $e;
        }
    }

    class SentryHttpErrorReporterTest extends TestCase {
        public static $requests = [];

        public static function recordRequest( $url, $args ) {
            self::$requests[] = [
                'url' => $url,
                'args' => $args,
            ];

            return [
                'response' => [
                    'code' => 200,
                ],
            ];
        }

        public function setUp(): void {
            self::$requests = [];
        }

        public function testCaptureExceptionPostsSentryEnvelope() {
            $reporter = new SentryHttpErrorReporter( 'https://public@example.com/42', 'PRODUCTION' );
            $exception = new \RuntimeException( 'Organic failure' );

            $reporter->captureException( $exception );

            $this->assertCount( 1, self::$requests );
            $request = self::$requests[0];
            $this->assertSame( 'https://example.com/api/42/envelope/', $request['url'] );
            $this->assertSame( 'application/x-sentry-envelope', $request['args']['headers']['Content-Type'] );
            $this->assertSame( 'organic-wordpress-plugin/version', $request['args']['headers']['User-Agent'] );
            $this->assertSame( 2, $request['args']['timeout'] );
            $this->assertFalse( $request['args']['blocking'] );

            $lines = explode( "\n", $request['args']['body'] );
            $this->assertCount( 3, $lines );

            $envelopeHeader = json_decode( $lines[0], true );
            $itemHeader = json_decode( $lines[1], true );
            $event = json_decode( $lines[2], true );

            $this->assertSame( 'https://public@example.com/42', $envelopeHeader['dsn'] );
            $this->assertSame( $event['event_id'], $envelopeHeader['event_id'] );
            $this->assertSame( 'event', $itemHeader['type'] );
            $this->assertSame( 'php', $event['platform'] );
            $this->assertSame( 'organic-wordpress-plugin', $event['logger'] );
            $this->assertSame( 'production', $event['environment'] );
            $this->assertSame( 'version', $event['release'] );
            $this->assertSame( 'example.test', $event['server_name'] );
            $this->assertSame( 'organic', $event['tags']['wordpress_plugin'] );
            $this->assertSame( 'RuntimeException', $event['exception']['values'][0]['type'] );
            $this->assertSame( 'Organic failure', $event['exception']['values'][0]['value'] );
            $this->assertNotEmpty( $event['exception']['values'][0]['stacktrace']['frames'] );
        }

        public function testCaptureExceptionSupportsDsnPathPrefix() {
            $reporter = new SentryHttpErrorReporter( 'https://public@example.com/sentry/42', 'STAGING' );

            $reporter->captureException( new \RuntimeException( 'Prefixed DSN' ) );

            $this->assertCount( 1, self::$requests );
            $this->assertSame( 'https://example.com/sentry/api/42/envelope/', self::$requests[0]['url'] );
        }

        public function testCaptureExceptionDoesNotPostForInvalidDsn() {
            $reporter = new SentryHttpErrorReporter( 'not-a-dsn', 'PRODUCTION' );

            $reporter->captureException( new \RuntimeException( 'Invalid DSN' ) );

            $this->assertSame( [], self::$requests );
        }

        public function testOrganicCaptureExceptionDelegatesToConfiguredReporter() {
            $reporter = new FakeErrorReporter();
            new Organic( 'PRODUCTION', $reporter );
            $exception = new \RuntimeException( 'Delegated' );

            Organic::captureException( $exception );

            $this->assertSame( [ $exception ], $reporter->exceptions );
        }
    }
}
