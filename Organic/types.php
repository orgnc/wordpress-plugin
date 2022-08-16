<?php

namespace Organic;

class AD_TYPE {
    const DEFAULT = 'display_default';
    const OUTSTREAM_VIDEO = 'outstream_video';
}

class AMP_BREAKPOINT {
    const SM = 576;
    const MD = 768;
    const LG = 992;
    const XL = 1200;

    public static function minWidth( int $size ) {
        return "(min-width: ${size}px)";
    }

    public static function maxWidth( int $size ) {
        return "(max-width: ${size}px)";
    }
}
