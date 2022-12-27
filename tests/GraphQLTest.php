<?php

namespace Organic;

define( 'Organic\ORGANIC_PLUGIN_VERSION', 'version' );

use PHPUnit\Framework\TestCase;

class GraphQLTest extends TestCase {
    /**
     * @var Organic Clean instance of Organic wrapper for each test
     */
    private $organic;

    /**
     * Set up a new, clean copy of the Organic wrapper for each test
     */
    public function setUp(): void {
        $this->organic = new Organic( 'TEST' );
    }

    public function testNoErrorWhenNoGraphQL() {
         $graphQL = new GraphQL( $this->organic );
        $this->assertNotEmpty( $graphQL );
    }

    public function testGraphQLSpecValidStructure() {
        $graphQL = new GraphQL( $this->organic );
        $spec = $graphQL->getGraphQLSpec();

        $this->assertNotEmpty( $spec );
        $this->assertArrayHasKey( 'description', $spec );
        $this->assertIsString( $spec['description'] );
        $this->assertArrayHasKey( 'fields', $spec );
        $this->assertIsArray( $spec['fields'] );
    }
}
