<?php

namespace Organic;

class NullErrorReporter implements ErrorReporter {
    public function captureException( \Throwable $e ): void {
    }
}
