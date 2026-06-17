<?php

namespace Organic;

interface ErrorReporter {
    public function captureException( \Throwable $e ): void;
}
