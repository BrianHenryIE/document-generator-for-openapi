<?php

namespace BrianHenryIE\OpenAPIGenerator\Tests;

use BrianHenryIE\OpenAPIGenerator\Generator3_1_0;
use BrianHenryIE\OpenAPIGenerator\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * WordPress describes a route's arguments without the keywords it does not validate. They are put back from the
 * arguments as registered.
 *
 * @see \WP_REST_Server::get_data_for_route()
 */
class RouteDataTest extends TestCase {

	/**
	 * The routes as registered: `WP_REST_Server::get_routes()`.
	 */
	protected function registered(): array {
		return [
			'/shop/v1/things' => [
				[
					'methods'  => [ 'GET' => true ],
					'callback' => 'strrev',
					'args'     => [
						'slug' => [
							'type'              => 'string',
							'examples'          => [ 'my-plugin' ],
							'deprecated'        => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'page' => [ 'type' => 'integer' ],
					],
				],
				[
					'methods'  => [ 'POST' => true, 'PUT' => true, 'PATCH' => true ],
					'callback' => 'strrev',
					'args'     => [
						'slug' => [
							'type'     => 'string',
							'examples' => [ 'another-plugin' ],
						],
					],
				],
			],
		];
	}

	/**
	 * The same routes as `WP_REST_Server::get_data_for_routes()` describes them.
	 */
	protected function described(): array {
		return [
			'/shop/v1/things' => [
				'namespace' => 'shop/v1',
				'methods'   => [ 'GET', 'POST', 'PUT', 'PATCH' ],
				'endpoints' => [
					[
						'methods' => [ 'GET' ],
						'args'    => [
							'slug' => [ 'type' => 'string', 'required' => false ],
							'page' => [ 'type' => 'integer', 'required' => false ],
						],
					],
					[
						'methods' => [ 'POST', 'PUT', 'PATCH' ],
						'args'    => [
							'slug' => [ 'type' => 'string', 'required' => false ],
						],
					],
				],
			],
		];
	}

	public function test_the_examples_of_an_argument_are_put_back_for_the_endpoint_they_belong_to(): void {
		$data = RouteData::restoreArgumentKeywords( $this->described(), $this->registered() );

		$this->assertSame(
			[ 'type' => 'string', 'required' => false, 'examples' => [ 'my-plugin' ], 'deprecated' => true ],
			$data['/shop/v1/things']['endpoints'][0]['args']['slug']
		);
		$this->assertSame(
			[ 'type' => 'string', 'required' => false, 'examples' => [ 'another-plugin' ] ],
			$data['/shop/v1/things']['endpoints'][1]['args']['slug']
		);
		$this->assertSame( [ 'type' => 'integer', 'required' => false ], $data['/shop/v1/things']['endpoints'][0]['args']['page'] );
	}

	public function test_a_route_which_is_not_registered_is_left_as_it_is(): void {
		$this->assertSame( $this->described(), RouteData::restoreArgumentKeywords( $this->described(), [] ) );
	}

	public function test_an_endpoint_with_no_matching_handler_is_left_as_it_is(): void {
		$registered = $this->registered();
		unset( $registered['/shop/v1/things'][1] );

		$data = RouteData::restoreArgumentKeywords( $this->described(), $registered );

		$this->assertSame( $this->described()['/shop/v1/things']['endpoints'][1], $data['/shop/v1/things']['endpoints'][1] );
	}

	public function test_the_examples_reach_the_document(): void {
		$data     = RouteData::restoreArgumentKeywords( $this->described(), $this->registered() );
		$document = ( new Generator3_1_0( 'shop/v1', $data, false ) )->generateDocument();

		$this->assertSame(
			[ 'type' => 'string', 'examples' => [ 'my-plugin' ], 'deprecated' => true ],
			$document['paths']['/things']['get']['parameters'][0]['schema']
		);
		$this->assertSame(
			[ 'type' => 'string', 'examples' => [ 'another-plugin' ] ],
			$document['paths']['/things']['post']['requestBody']['content']['application/json']['schema']['properties']['slug']
		);
	}
}
