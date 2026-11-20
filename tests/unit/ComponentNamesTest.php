<?php

namespace BrianHenryIE\OpenAPIGenerator\Tests;

use BrianHenryIE\OpenAPIGenerator\ComponentNameCollisionException;
use BrianHenryIE\OpenAPIGenerator\Generator3_1_0;
use PHPUnit\Framework\TestCase;

/**
 * With common types extracted, a component is named by its schema's `title`, or else after where it is. It is
 * never named after a bare property key, and never `name_1`.
 */
class ComponentNamesTest extends TestCase {

	/**
	 * A site's activation, used by two routes.
	 */
	protected function activation( string $description = 'A site using the license.' ): array {
		return [
			'title'       => 'Activation',
			'description' => $description,
			'type'        => 'object',
			'properties'  => [
				'domain'      => [ 'type' => 'string' ],
				'environment' => [
					'title' => 'Environment',
					'type'  => 'string',
					'enum'  => [ 'live', 'staging' ],
				],
			],
		];
	}

	/**
	 * Routes as `WP_REST_Server::get_data_for_routes()` describes them.
	 *
	 * @param array $second_activation The activation schema of the second route.
	 */
	protected function routes( array $second_activation ): array {
		return [
			'/shop/v1/license'          => [
				'namespace' => 'shop/v1',
				'methods'   => [ 'GET' ],
				'endpoints' => [
					[
						'methods' => [ 'GET' ],
						'args'    => [
							'order' => [
								'type' => 'string',
								'enum' => [ 'asc', 'desc' ],
							],
						],
					],
				],
				'schema'    => [
					'title'      => 'License',
					'type'       => 'object',
					'properties' => [
						'status'  => [
							'type' => 'string',
							'enum' => [ 'active', 'expired' ],
						],
						'limits'  => [
							'type'       => 'object',
							'properties' => [
								'live' => [
									'type'       => 'object',
									'properties' => [ 'max' => [ 'type' => 'integer' ] ],
								],
							],
						],
						'domains' => [
							'type'  => 'array',
							'items' => $this->activation(),
						],
					],
				],
			],
			'/shop/v1/license/activate' => [
				'namespace' => 'shop/v1',
				'methods'   => [ 'POST' ],
				'endpoints' => [
					[
						'methods' => [ 'POST' ],
						'args'    => [
							'environment' => [
								'title' => 'Requested Environment',
								'type'  => 'string',
								'enum'  => [ '', 'live', 'staging' ],
							],
							'license'     => [
								'title' => 'LicenseKey',
								'type'  => 'string',
							],
						],
					],
				],
				'schema'    => [
					'title'      => 'ActivateResult',
					'type'       => 'object',
					'properties' => [
						'activation' => $second_activation,
						'status'     => [
							'type' => 'string',
							'enum' => [ 'activated', 'rotated' ],
						],
					],
				],
			],
		];
	}

	protected function generate( array $routes, bool $strict = false, bool $extract = true ): array {
		return ( new Generator3_1_0( 'shop/v1', $routes, $extract, $strict ) )->generateDocument();
	}

	/**
	 * Every `$ref` in a document.
	 *
	 * @return string[]
	 */
	protected function references( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$references = [];
		foreach ( $value as $key => $child ) {
			if ( '$ref' === $key && is_string( $child ) ) {
				$references[] = str_replace( '#/components/schemas/', '', $child );
				continue;
			}
			$references = array_merge( $references, $this->references( $child ) );
		}

		return $references;
	}

	public function test_a_title_names_the_component(): void {
		$document = $this->generate( $this->routes( $this->activation() ) );
		$schemas  = $document['components']['schemas'];

		$this->assertArrayHasKey( 'Activation', $schemas );
		$this->assertArrayHasKey( 'Environment', $schemas );
		$this->assertSame(
			[ '$ref' => '#/components/schemas/Activation' ],
			$schemas['ActivateResult']['properties']['activation']
		);
		$this->assertSame(
			[ '$ref' => '#/components/schemas/Environment' ],
			$schemas['Activation']['properties']['environment']
		);
	}

	public function test_the_same_schema_in_two_places_is_one_component(): void {
		$document = $this->generate( $this->routes( $this->activation() ) );
		$schemas  = $document['components']['schemas'];

		$this->assertSame(
			[ '$ref' => '#/components/schemas/Activation' ],
			$schemas['License']['properties']['domains']['items'],
			'The items of an array are the titled schema.'
		);
		$this->assertSame(
			[
				'ActivateResult',
				'ActivateResult.status',
				'Activation',
				'Environment',
				'License',
				'License.limits',
				'License.limits.live',
				'License.status',
				'LicenseKey',
				'Requested-Environment',
				'WPError',
				'license.GET.order',
			],
			$this->sorted( array_keys( $schemas ) )
		);
	}

	protected function sorted( array $values ): array {
		sort( $values );

		return $values;
	}

	public function test_without_a_title_the_name_is_the_parents_name_and_the_key(): void {
		$document = $this->generate( $this->routes( $this->activation() ) );
		$schemas  = $document['components']['schemas'];

		// Two different `status` enums: once `status` and `status_1`.
		$this->assertSame( [ 'active', 'expired' ], $schemas['License.status']['enum'] );
		$this->assertSame( [ 'activated', 'rotated' ], $schemas['ActivateResult.status']['enum'] );
		$this->assertArrayNotHasKey( 'status', $schemas );

		// An object inside an object.
		$this->assertSame(
			[ '$ref' => '#/components/schemas/License.limits.live' ],
			$schemas['License.limits']['properties']['live']
		);

		// An argument is named after its path and methods.
		$this->assertSame( [ 'asc', 'desc' ], $schemas['license.GET.order']['enum'] );
		$parameter = $document['paths']['/license']['get']['parameters'][0];
		$this->assertSame( [ '$ref' => '#/components/schemas/license.GET.order' ], $parameter['schema'] );
	}

	public function test_a_title_is_made_a_valid_component_name(): void {
		$document = $this->generate( $this->routes( $this->activation() ) );

		$this->assertSame(
			[ '', 'live', 'staging' ],
			$document['components']['schemas']['Requested-Environment']['enum']
		);
		foreach ( array_keys( $document['components']['schemas'] ) as $name ) {
			$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9._-]+$/', (string) $name );
		}
	}

	public function test_a_title_extracts_a_schema_which_is_neither_an_object_nor_an_enum(): void {
		$document = $this->generate( $this->routes( $this->activation() ) );

		$this->assertSame( [ 'type' => 'string', 'title' => 'LicenseKey' ], $document['components']['schemas']['LicenseKey'] );
		$body = $document['paths']['/license/activate']['post']['requestBody']['content']['application/json']['schema'];
		$this->assertSame( [ '$ref' => '#/components/schemas/LicenseKey' ], $body['properties']['license'] );
	}

	public function test_a_title_with_two_different_schemas_is_an_error_when_strict(): void {
		$routes = $this->routes( $this->activation( 'The activation just made.' ) );

		try {
			$this->generate( $routes, true );
			$this->fail( 'Two schemas titled Activation were accepted.' );
		} catch ( ComponentNameCollisionException $exception ) {
			$this->assertSame( 'Activation', $exception->getComponentName() );
			$this->assertSame( 'the response schema "License"/domains/items', $exception->getFirstLocation() );
			$this->assertSame( 'the response schema "ActivateResult"/activation', $exception->getSecondLocation() );
			$this->assertStringContainsString( 'the response schema "License"/domains/items', $exception->getMessage() );
			$this->assertStringContainsString( 'the response schema "ActivateResult"/activation', $exception->getMessage() );
		}
	}

	public function test_a_title_which_is_taken_falls_back_to_where_the_schema_is(): void {
		$document = $this->generate( $this->routes( $this->activation( 'The activation just made.' ) ) );
		$schemas  = $document['components']['schemas'];

		$this->assertSame( 'A site using the license.', $schemas['Activation']['description'] );
		$this->assertSame( 'The activation just made.', $schemas['ActivateResult.activation']['description'] );
		$this->assertSame(
			[ '$ref' => '#/components/schemas/ActivateResult.activation' ],
			$schemas['ActivateResult']['properties']['activation']
		);
		// What is inside it is the same schema as before, so the same component.
		$this->assertSame(
			[ '$ref' => '#/components/schemas/Environment' ],
			$schemas['ActivateResult.activation']['properties']['environment']
		);
	}

	public function test_two_responses_with_one_title_are_an_error_when_strict(): void {
		$routes = $this->routes( $this->activation() );
		$routes['/shop/v1/license/activate']['schema'] = [
			'title'      => 'License',
			'type'       => 'object',
			'properties' => [ 'key' => [ 'type' => 'string' ] ],
		];

		$this->expectException( ComponentNameCollisionException::class );
		$this->expectExceptionMessage( 'The component "License" has two different schemas' );

		$this->generate( $routes, true );
	}

	public function test_an_argument_and_a_generated_component_cannot_share_a_name(): void {
		$routes = $this->routes( $this->activation() );
		$routes['/shop/v1/license/activate']['endpoints'][0]['args']['license']['title'] = 'WPError';

		$this->expectException( ComponentNameCollisionException::class );
		$this->expectExceptionMessage( "the generator's own components" );

		$this->generate( $routes, true );
	}

	public function test_nothing_is_extracted_without_the_flag(): void {
		$document = $this->generate( $this->routes( $this->activation() ), false, false );

		$this->assertSame(
			[ 'ActivateResult', 'License', 'WPError' ],
			$this->sorted( array_keys( $document['components']['schemas'] ) )
		);
		$this->assertSame( [], array_diff( $this->references( $document['paths'] ), [ 'ActivateResult', 'License', 'WPError' ] ) );
	}

	/**
	 * WordPress gives the title "Term ID Taxonomy Query" to schemas which differ, so `wp/v2` only generates when
	 * that is not an error.
	 *
	 * @dataProvider wp_v2_provider
	 */
	public function test_the_wp_v2_namespace( bool $strict ): void {
		$input = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/wp-v2-routes.json' ), true );

		if ( $strict ) {
			$this->expectException( ComponentNameCollisionException::class );
			$this->expectExceptionMessage( 'Term-ID-Taxonomy-Query' );
		}

		$document = ( new Generator3_1_0( 'wp/v2', $input['routes'], true, $strict ) )->generateDocument();
		$names    = array_map( 'strval', array_keys( $document['components']['schemas'] ) );

		$this->assertSame( [], preg_grep( '/_\d+$/', $names ), 'No component is a numbered copy of another.' );
		$this->assertSame( [], preg_grep( '/^[a-zA-Z0-9._-]+$/', $names, PREG_GREP_INVERT ) );
		$this->assertContains( 'Term-ID-Taxonomy-Query', $names );
		$this->assertContains( 'posts.GET.orderby', $names );
		$this->assertContains( 'posts.id.POST-PUT-PATCH.status', $names );
		$this->assertContains( 'post.status', $names );

		$references = array_unique( $this->references( $document ) );
		$this->assertSame( [], array_values( array_diff( $references, $names ) ), 'Every reference resolves.' );
		$this->assertSame( [], array_values( array_diff( $names, $references ) ), 'Every component is used.' );
	}

	public function wp_v2_provider(): array {
		return [
			'titles may clash' => [ false ],
			'strict'           => [ true ],
		];
	}
}
