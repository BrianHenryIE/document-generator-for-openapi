<?php

namespace BrianHenryIE\OpenAPIGenerator\Tests;

use BrianHenryIE\OpenAPIGenerator\Generator3_1_0;
use PHPUnit\Framework\TestCase;

/**
 * The whole `wp/v2` namespace, as WordPress described it when the fixture was made, against the document the
 * generator made from it: nothing about the default output changes without this failing.
 */
class WpV2FixtureTest extends TestCase {

	public function test_the_wp_v2_namespace_generates_the_same_document(): void {
		$input    = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/wp-v2-routes.json' ), true );
		$expected = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/wp-v2-openapi.json' ), true );

		$generator = new Generator3_1_0( 'wp/v2', $input['routes'], false );

		// Compared as the JSON it becomes: the empty default of an object is an object there, not an empty list.
		$this->assertEquals( $expected, json_decode( (string) json_encode( $generator->generateDocument() ), true ) );
	}
}
