<?php

namespace Organic;

use WP_Post;

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

/**
 * Copy of the WordPress `get_edit_post_link` function without a permission check
 *
 * @param WP_Post|int $id
 * @param string $context
 * @return mixed|void|null
 */
function get_edit_post_link($id = 0, string $context = 'display' ) {
    $post = get_post( $id );
    if ( ! $post ) {
        return;
    }

    if ( 'revision' === $post->post_type ) {
        $action = '';
    } elseif ( 'display' === $context ) {
        $action = '&amp;action=edit';
    } else {
        $action = '&action=edit';
    }

    $post_type_object = get_post_type_object( $post->post_type );
    if ( ! $post_type_object ) {
        return;
    }

    if ( $post_type_object->_edit_link ) {
        $link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
    } else {
        $link = '';
    }

    return apply_filters( 'get_edit_post_link', $link, $post->ID, $context );
}
