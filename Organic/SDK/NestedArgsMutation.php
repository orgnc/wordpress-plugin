<?php

namespace Organic\SDK;

use GraphQL\Mutation;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Patch of GraphQL\Mutation that allows to handle nested arguments.
 * TODO: make a PR with this patch to upstream gmostafa/php-graphql-client
 * For example, if we have the following object as a mutation argument
 *
 *   array(
 *     'externalId' => 'external_id_1',
 *     'name' => 'Category level 0',
 *     'children' => [
 *       array(
 *         'externalId' => 'external_id_2',
 *         'name' => 'Category level 1',
 *       )
 *     ]
 *   )
 *
 * it will serialize like this:
 *
 *     externalId: 'external_id_1'
 *     name: 'Category level 0'
 *     children: [
 *       {
 *         externalId: 'external_id_2'
 *         name: 'Category level 1'
 *       }
 *     ]
 *
 * while the original Mutation class produces invalid GraphQL:
 *
 *     externalId: 'external_id_1'
 *     name: 'Category level 0'
 *     children: [ Array ]
 *
 *
 * @package Organic\SDK
 */
class NestedArgsMutation extends Mutation {
    /**
     * @return string
     */
    protected function formatArray( $arr, $braces = false ): string {
        $res = '';
        $is_object = false;
        $first             = true;
        foreach ( $arr as $name => $value ) {
            // Append space at the beginning if it's not the first item on the list
            if ( $first ) {
                $is_object = ! is_int( $name );
                $first = false;
            } else {
                $res .= ' ';
            }

            // Convert argument values to graphql string literal equivalent
            if ( is_scalar( $value ) || $value === null ) {
                // Convert scalar value to its literal in graphql
                $value_str = StringLiteralFormatter::formatValueForRHS( $value );
            } elseif ( is_array( $value ) ) {
                // Convert PHP array to its array representation in graphql arguments
                $value_str = $this->formatArray( $value, true );
            }
            // TODO: Handle cases where a non-string-convertible object is added to the arguments
            if ( is_int( $name ) ) {
                $res .= $value_str;
            } else {
                $res .= $name . ': ' . $value_str;
            }
        }
        if ( $braces ) {
            if ( $is_object ) {
                $res = '{' . $res . '}';
            } else {
                $res = '[' . $res . ']';
            }
        }
        return $res;
    }

    /**
     * @return string
     */
    protected function constructArguments(): string {
        // Return empty string if list is empty
        if ( empty( $this->arguments ) ) {
            return '';
        }

        // Construct arguments string if list not empty
        $constraintsString = '(';
        $constraintsString .= $this->formatArray( $this->arguments );
        $constraintsString .= ')';

        return $constraintsString;
    }

}
