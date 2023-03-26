<?php

namespace Organic;

/**
 * Determine whether this is an AMP response.
 *
 * Note that this must only be called after the parse_query action.
 *
 * @return bool Is AMP endpoint (and AMP plugin is active).
 */
function organic_is_amp() {
     // naive implementation due to we're in mu-plugin
    return isset( $_GET['amp'] );
}

/**
 * validate UUID
 *
 * @param string $uuid
 * @return bool
 */
function is_valid_uuid( string $uuid ) {
    return is_string( $uuid )
        ? preg_match( '/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid ) === 1
        : false;
}
