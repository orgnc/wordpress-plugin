<?php

namespace Empire;

use PHPUnit\Framework\TestCase;

class GraphQLTest extends TestCase
{
    /**
     * @var Empire Clean instance of Empire wrapper for each test
     */
    private $empire;

    /**
     * Set up a new, clean copy of the Empire wrapper for each test
     */
    public function setUp() : void {
        $this->empire = new Empire('TEST');
    }

    public function testNoErrorWhenNoGraphQL()
    {
        $graphQL = new GraphQL($this->empire);
        $this->assertNotEmpty($graphQL);
    }

    public function testGraphQLSpecValidStructure() {
        $graphQL = new GraphQL($this->empire);
        $spec = $graphQL->getGraphQLSpec();

        $this->assertNotEmpty($spec);
        $this->assertArrayHasKey('description', $spec);
        $this->assertIsString($spec['description']);
        $this->assertArrayHasKey('fields', $spec);
        $this->assertIsArray($spec['fields']);
    }
}
