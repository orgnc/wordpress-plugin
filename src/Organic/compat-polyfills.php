<?php
if ( ! function_exists( 'wp_sanitize_script_attributes' ) ) {
    // https://developer.wordpress.org/reference/functions/wp_sanitize_script_attributes/#source
    function wp_sanitize_script_attributes( $attributes ) {
        $html5_script_support = ! is_admin() && ! current_theme_supports( 'html5', 'script' );
        $attributes_string    = '';

        // If HTML5 script tag is supported, only the attribute name is added
        // to $attributes_string for entries with a boolean value, and that are true.
        foreach ( $attributes as $attribute_name => $attribute_value ) {
            if ( is_bool( $attribute_value ) ) {
                if ( $attribute_value ) {
                    $attributes_string .= $html5_script_support ? sprintf( ' %1$s="%2$s"', esc_attr( $attribute_name ), esc_attr( $attribute_name ) ) : ' ' . esc_attr( $attribute_name );
                }
            } else {
                $attributes_string .= sprintf( ' %1$s="%2$s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
            }
        }

        return $attributes_string;
    }
}

if ( ! function_exists( 'wp_get_script_tag' ) ) {
    // https://developer.wordpress.org/reference/functions/wp_get_script_tag/#source
    function wp_get_script_tag( $attributes ) {
        if ( ! isset( $attributes['type'] ) && ! is_admin() && ! current_theme_supports( 'html5', 'script' ) ) {
            $attributes['type'] = 'text/javascript';
        }
        /**
         * Filters attributes to be added to a script tag.
         *
         * @since 5.7.0
         *
         * @param array $attributes Key-value pairs representing `<script>` tag attributes.
         *                          Only the attribute name is added to the `<script>` tag for
         *                          entries with a boolean value, and that are true.
         */
        $attributes = apply_filters( 'wp_script_attributes', $attributes );

        return sprintf( "<script%s></script>\n", wp_sanitize_script_attributes( $attributes ) );
    }
}

if ( ! function_exists( 'wp_print_script_tag' ) ) {
    // https://developer.wordpress.org/reference/functions/wp_print_script_tag/#source
    function wp_print_script_tag( $attributes ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo wp_get_script_tag( $attributes );
    }
}
