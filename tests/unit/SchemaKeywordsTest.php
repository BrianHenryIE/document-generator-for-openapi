<?php

namespace BrianHenryIE\OpenAPIGenerator\Tests;

use BrianHenryIE\OpenAPIGenerator\Generator3_1_0;
use PHPUnit\Framework\TestCase;

/**
 * Every JSON Schema keyword WordPress validates is in the document, for arguments and for responses, and what
 * only WordPress understands is not.
 *
 * @see rest_get_allowed_schema_keywords()
 */
class SchemaKeywordsTest extends TestCase {

	/**
	 * Generate the schema of one argument and of one response property from the same WordPress schema.
	 *
	 * @param array $schema A WordPress REST schema.
	 * @param bool  $extract_common_types Whether to extract components.
	 *
	 * @return array{argument: array, body: array, response: array, document: array}
	 */
	protected function generate( array $schema, bool $extract_common_types = false ): array {
		$routes = [
			'/shop/v1/things' => [
				'namespace' => 'shop/v1',
				'methods'   => [ 'GET', 'POST' ],
				'endpoints' => [
					[
						'methods' => [ 'GET' ],
						'args'    => [ 'thing' => $schema + [ 'required' => false ] ],
					],
					[
						'methods' => [ 'POST' ],
						'args'    => [ 'thing' => $schema + [ 'required' => false ] ],
					],
				],
				'schema'    => [
					'$schema'    => 'http://json-schema.org/draft-04/schema#',
					'title'      => 'Things',
					'type'       => 'object',
					'properties' => [ 'thing' => $schema ],
				],
			],
		];

		$document = ( new Generator3_1_0( 'shop/v1', $routes, $extract_common_types, true ) )->generateDocument();

		return [
			'argument' => $document['paths']['/things']['get']['parameters'][0]['schema'],
			'body'     => $document['paths']['/things']['post']['requestBody']['content']['application/json']['schema']['properties']['thing'],
			'response' => $document['components']['schemas']['Things']['properties']['thing'],
			'document' => $document,
		];
	}

	/**
	 * @return array<string, array{0: array}>
	 */
	public function keywords(): array {
		return [
			'string limits'     => [ [ 'type' => 'string', 'pattern' => '^[a-z]+$', 'minLength' => 1, 'maxLength' => 64, 'format' => 'uri' ] ],
			'number limits'     => [ [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'multipleOf' => 5 ] ],
			'exclusive limits'  => [ [ 'type' => 'number', 'exclusiveMinimum' => 0, 'exclusiveMaximum' => 360 ] ],
			'list limits'       => [ [ 'type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'uniqueItems' => true, 'items' => [ 'type' => 'string', 'maxLength' => 10 ] ] ],
			'object limits'     => [ [ 'type' => 'object', 'minProperties' => 1, 'maxProperties' => 2, 'additionalProperties' => false ] ],
			'a default'         => [ [ 'type' => 'string', 'default' => 'plugin' ] ],
			'a false default'   => [ [ 'type' => 'boolean', 'default' => false ] ],
			'a list default'    => [ [ 'type' => 'array', 'default' => [], 'items' => [ 'type' => 'string' ] ] ],
			'examples'          => [ [ 'type' => 'string', 'examples' => [ 'one', 'two' ] ] ],
			'a description'     => [ [ 'type' => 'string', 'description' => 'What it is.' ] ],
			'an enum'           => [ [ 'type' => 'string', 'enum' => [ 'live', 'staging' ] ] ],
			'a nullable type'   => [ [ 'type' => [ 'string', 'null' ], 'format' => 'date-time' ] ],
			'a null default'    => [ [ 'type' => [ 'string', 'null' ], 'default' => null ] ],
			'deprecated'        => [ [ 'type' => 'string', 'deprecated' => true ] ],
			'a title'           => [ [ 'type' => 'string', 'title' => 'Slug' ] ],
		];
	}

	/**
	 * @dataProvider keywords
	 */
	public function test_the_keywords_are_kept_wherever_the_schema_is( array $schema ): void {
		$generated = $this->generate( $schema );

		// The keywords are not written in the order they were read.
		foreach ( [ 'argument' => 'As an argument in the query.', 'body' => 'As an argument in the body.', 'response' => 'As a property of the response.' ] as $where => $message ) {
			ksort( $schema );
			ksort( $generated[ $where ] );
			$this->assertSame( $schema, $generated[ $where ], $message );
		}
	}

	public function test_what_only_wordpress_understands_is_left_out(): void {
		$generated = $this->generate(
			[
				'type'              => 'string',
				'context'           => [ 'view', 'edit' ],
				'arg_options'       => [ 'sanitize_callback' => 'sanitize_key' ],
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
				'$schema'           => 'http://json-schema.org/draft-04/schema#',
				'made_up'           => true,
			]
		);

		$this->assertSame( [ 'type' => 'string' ], $generated['argument'] );
		$this->assertSame( [ 'type' => 'string' ], $generated['body'] );
		$this->assertSame( [ 'type' => 'string' ], $generated['response'] );
		$this->assertArrayNotHasKey( '$schema', $generated['document']['components']['schemas']['Things'] );
	}

	public function test_readonly_is_spelled_as_json_schema_does(): void {
		$generated = $this->generate( [ 'type' => 'string', 'readonly' => true ] );

		$this->assertSame( [ 'type' => 'string', 'readOnly' => true ], $generated['response'] );

		$this->assertSame( [ 'type' => 'string' ], $this->generate( [ 'type' => 'string', 'readonly' => false ] )['response'] );
	}

	/**
	 * WordPress validates as draft 4, where `exclusiveMinimum` is a boolean beside `minimum`. OpenAPI 3.1 reads
	 * draft 2020-12, where it is the limit.
	 */
	public function test_a_draft_4_exclusive_limit_is_written_as_the_limit(): void {
		$this->assertSame(
			[ 'type' => 'integer', 'exclusiveMinimum' => 0, 'exclusiveMaximum' => 360 ],
			$this->generate( [ 'type' => 'integer', 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => 360, 'exclusiveMaximum' => true ] )['argument']
		);

		$this->assertSame(
			[ 'type' => 'integer', 'minimum' => 0, 'maximum' => 360 ],
			$this->generate( [ 'type' => 'integer', 'minimum' => 0, 'exclusiveMinimum' => false, 'maximum' => 360, 'exclusiveMaximum' => false ] )['argument'],
			'Not exclusive.'
		);

		$this->assertSame(
			[ 'type' => 'integer' ],
			$this->generate( [ 'type' => 'integer', 'exclusiveMinimum' => true ] )['argument'],
			'Exclusive, with no limit to be exclusive of.'
		);
	}

	/**
	 * WordPress writes `'default' => null` for an argument without a default.
	 */
	public function test_a_null_default_is_only_kept_where_null_is_a_value(): void {
		$this->assertSame( [ 'type' => 'string' ], $this->generate( [ 'type' => 'string', 'default' => null ] )['argument'] );
	}

	/**
	 * PHP's empty array is an empty list in JSON, which is not an object.
	 */
	public function test_the_empty_default_of_an_object_is_an_object(): void {
		$generated = $this->generate( [ 'type' => 'object', 'default' => [], 'additionalProperties' => true ] );

		$this->assertSame( '{"type":"object","additionalProperties":true,"default":{}}', json_encode( $generated['response'] ) );
	}

	public function test_required_properties_are_listed_on_their_object(): void {
		$generated = $this->generate(
			[
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => [
					'slug'    => [ 'type' => 'string' ],
					'version' => [ 'type' => 'string', 'required' => true ],
					'author'  => [ 'type' => 'string', 'required' => false ],
				],
			]
		);

		foreach ( [ 'argument', 'body', 'response' ] as $where ) {
			$this->assertSame( [ 'version', 'slug' ], $generated[ $where ]['required'], $where );
			$this->assertSame( [ 'type' => 'string' ], $generated[ $where ]['properties']['version'], 'The boolean is not left on the property.' );
		}
	}

	public function test_the_required_arguments_are_the_required_properties_of_the_body(): void {
		$routes = [
			'/shop/v1/things/(?P<id>\d+)' => [
				'namespace' => 'shop/v1',
				'methods'   => [ 'POST' ],
				'endpoints' => [
					[
						'methods' => [ 'POST' ],
						'args'    => [
							'id'      => [ 'type' => 'integer', 'required' => true ],
							'license' => [ 'type' => 'string', 'required' => true ],
							'slug'    => [ 'type' => 'string', 'required' => true ],
							'version' => [ 'type' => 'string', 'required' => false ],
						],
					],
				],
			],
		];

		$document = ( new Generator3_1_0( 'shop/v1', $routes, false ) )->generateDocument();
		$body     = $document['paths']['/things/{id}']['post']['requestBody']['content']['application/json']['schema'];

		$this->assertSame( [ 'license', 'slug' ], $body['required'], 'The path parameter is not in the body.' );
		$this->assertArrayNotHasKey( 'required', $body['properties']['license'] );
	}

	public function test_a_body_with_no_required_argument_has_no_required_list(): void {
		$body = $this->generate( [ 'type' => 'string' ] )['document']['paths']['/things']['post']['requestBody']['content']['application/json']['schema'];

		$this->assertArrayNotHasKey( 'required', $body );
	}

	public function test_schemas_inside_a_schema_are_converted_too(): void {
		$generated = $this->generate(
			[
				'type'                 => 'object',
				'additionalProperties' => [ 'type' => 'string', 'maxLength' => 5, 'context' => [ 'view' ] ],
				'patternProperties'    => [ '^x-' => [ 'type' => 'integer', 'minimum' => 1, 'readonly' => true ] ],
				'properties'           => [
					'tags' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string', 'pattern' => '^[a-z]+$', 'arg_options' => [] ],
					],
				],
			]
		);

		$this->assertSame(
			[
				'type'                 => 'object',
				'properties'           => [
					'tags' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string', 'pattern' => '^[a-z]+$' ],
					],
				],
				'patternProperties'    => [ '^x-' => [ 'type' => 'integer', 'minimum' => 1, 'readOnly' => true ] ],
				'additionalProperties' => [ 'type' => 'string', 'maxLength' => 5 ],
			],
			$generated['response']
		);
	}

	public function test_any_of_and_one_of_are_kept_with_the_type(): void {
		$generated = $this->generate(
			[
				'type'  => 'string',
				'anyOf' => [
					[ 'type' => 'string', 'format' => 'uri' ],
					[ 'type' => 'string', 'maxLength' => 0 ],
				],
			]
		);

		$this->assertSame(
			[
				'type'  => 'string',
				'anyOf' => [
					[ 'type' => 'string', 'format' => 'uri' ],
					[ 'type' => 'string', 'maxLength' => 0 ],
				],
			],
			$generated['response']
		);
	}

	/**
	 * As WordPress describes an image edit: the alternatives have no `type`, their parent has it.
	 */
	public function test_an_alternative_without_a_type_keeps_its_properties(): void {
		$generated = $this->generate(
			[
				'type'  => 'object',
				'oneOf' => [
					[
						'title'      => 'Rotation',
						'properties' => [ 'angle' => [ 'type' => 'number' ] ],
					],
					[
						'title' => 'Crop',
						'items' => [ 'type' => 'number' ],
					],
				],
			]
		);

		$this->assertSame(
			[
				'type'  => 'object',
				'oneOf' => [
					[
						'properties' => [ 'angle' => [ 'type' => 'number' ] ],
						'title'      => 'Rotation',
					],
					[
						'items' => [ 'type' => 'number' ],
						'title' => 'Crop',
					],
				],
			],
			$generated['response']
		);
	}

	public function test_a_schema_which_says_nothing_is_a_string(): void {
		$this->assertSame( [ 'type' => 'string', 'description' => 'Anything.' ], $this->generate( [ 'description' => 'Anything.' ] )['response'] );
	}

	public function test_a_list_of_types_with_one_of_is_the_one_of_alone(): void {
		$generated = $this->generate(
			[
				'type'  => [ 'string', 'integer' ],
				'oneOf' => [
					[ 'type' => 'string' ],
					[ 'type' => 'integer' ],
				],
			]
		);

		$this->assertSame( [ 'oneOf' => [ [ 'type' => 'string' ], [ 'type' => 'integer' ] ] ], $generated['response'] );
	}

	public function test_an_object_which_may_be_null_keeps_its_properties(): void {
		$generated = $this->generate(
			[
				'type'       => [ 'object', 'null' ],
				'properties' => [ 'max' => [ 'type' => [ 'integer', 'null' ] ] ],
			]
		);

		$this->assertSame(
			[
				'type'       => [ 'object', 'null' ],
				'properties' => [ 'max' => [ 'type' => [ 'integer', 'null' ] ] ],
			],
			$generated['response']
		);
	}

	/**
	 * With the keywords compared, the same titled schema used twice is still one component.
	 */
	public function test_one_schema_with_every_keyword_used_twice_is_one_component(): void {
		$schema = [
			'title'      => 'Thing',
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'slug' => [ 'type' => 'string', 'pattern' => '^[a-z]+$', 'examples' => [ 'one' ], 'required' => true ],
			],
		];

		$document = $this->generate( $schema, true )['document'];

		$this->assertSame( [ '$ref' => '#/components/schemas/Thing' ], $document['components']['schemas']['Things']['properties']['thing'] );
		$this->assertSame( [ '$ref' => '#/components/schemas/Thing' ], $document['paths']['/things']['get']['parameters'][0]['schema'] );
		$this->assertSame(
			'{"type":"object","properties":{"slug":{"type":"string","pattern":"^[a-z]+$","examples":["one"]}},"required":["slug"],"title":"Thing","default":{}}',
			json_encode( $document['components']['schemas']['Thing'] )
		);
	}
}
