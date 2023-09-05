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
 * Replace spaces in a URL with plus symbol (`+`)
 * @param string $url
 * @return string
 */
function fix_url_spaces( string $url ) : string {
    return str_replace( ' ', '+', $url );
}

/**
 * Copy of the built-in WordPress function `get_edit_post_link`.
 * Retrieves the edit post link for post without a permissions check.
 *
 * @param int|WP_Post $id      Optional. Post ID or post object. Default is the global `$post`.
 * @param string      $context Optional. How to output the '&' character. Default '&amp;'.
 * @return string|null|void    The edit post link for the given post. Null if the post type does not
 *                             exist or does not allow an editing UI.
 */
function get_edit_post_link( $id = 0, string $context = 'display' ) {
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
