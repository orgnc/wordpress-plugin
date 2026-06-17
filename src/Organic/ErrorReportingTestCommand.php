<?php

namespace Organic;

class ErrorReportingTestCommand {

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            \WP_CLI::add_command( 'organic-test-error-reporting', $this );
        }
    }

    /**
     * Send a synthetic exception through Organic error reporting.
     *
     * ## OPTIONS
     *
     * [--message=<message>]
     * : Exception message to send.
     * ---
     * default: Organic WP-CLI error reporting test
     * ---
     *
     * @param array $args Positional arguments.
     * @param array $opts Associative arguments.
     * @return void
     */
    public function __invoke( $args, $opts ) {
        $message = $opts['message'] ?? 'Organic WP-CLI error reporting test';
        Organic::captureException( new \RuntimeException( $message ) );

        $this->organic->info(
            'Organic error reporting test exception captured',
            [
                'message' => $message,
            ]
        );
    }
}
