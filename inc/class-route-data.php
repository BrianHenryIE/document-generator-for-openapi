<?php

namespace BrianHenryIE\OpenAPIGenerator;

/**
 * The routes of a namespace as the generator reads them.
 *
 * WordPress describes a route's arguments with only the schema keywords it validates, so an argument's
 * `examples` and `deprecated` are not in what `WP_REST_Server::get_data_for_routes()` returns. They are still
 * in the arguments as the route registered them, and are put back from there. The schema of a route's
 * response, and anything inside an argument, is not filtered and needs nothing.
 *
 * @see \WP_REST_Server::get_data_for_route()
 * @see rest_get_allowed_schema_keywords()
 */
class RouteData {

    /**
     * The JSON Schema keywords of an argument which WordPress leaves out of its description of a route.
     */
    const RESTORED_KEYWORDS = [ 'examples', 'deprecated' ];

    /**
     * The routes of a namespace, described as for an `OPTIONS` request, with the keywords WordPress left out.
     *
     * @param string $namespace A registered REST namespace, e.g. `wp/v2`.
     *
     * @return array<string, array>
     */
    public static function forNamespace( $namespace ) {
        $routes = rest_get_server()->get_routes( $namespace );

        return self::restoreArgumentKeywords( rest_get_server()->get_data_for_routes( $routes, 'help' ), $routes );
    }

    /**
     * Put back the keywords of the arguments which WordPress left out.
     *
     * @param array<string, array> $data   The routes as `WP_REST_Server::get_data_for_routes()` describes them.
     * @param array<string, array> $routes The routes as registered: `WP_REST_Server::get_routes()`.
     *
     * @return array<string, array>
     */
    public static function restoreArgumentKeywords( array $data, array $routes ) {
        foreach ( $data as $route => $routeData ) {
            if ( !isset( $routes[$route], $routeData['endpoints'] ) || !is_array( $routes[$route] ) ) {
                continue;
            }

            foreach ( $routeData['endpoints'] as $index => $endpoint ) {
                $handler = self::findHandler( $routes[$route], $endpoint );

                if ( $handler === null || !isset( $endpoint['args'] ) ) {
                    continue;
                }

                foreach ( $endpoint['args'] as $name => $argument ) {
                    if ( !isset( $handler['args'][$name] ) || !is_array( $handler['args'][$name] ) ) {
                        continue;
                    }

                    foreach ( self::RESTORED_KEYWORDS as $keyword ) {
                        if ( isset( $handler['args'][$name][$keyword] ) && !isset( $argument[$keyword] ) ) {
                            $data[$route]['endpoints'][$index]['args'][$name][$keyword] = $handler['args'][$name][$keyword];
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * The registered handler an endpoint was described from: the one with the same methods.
     *
     * @param array $handlers The handlers of a route as registered.
     * @param array $endpoint The description of one of them.
     *
     * @return array|null
     */
    protected static function findHandler( array $handlers, array $endpoint ) {
        if ( !isset( $endpoint['methods'] ) ) {
            return null;
        }

        foreach ( $handlers as $handler ) {
            if ( is_array( $handler ) && isset( $handler['methods'] ) && is_array( $handler['methods'] ) &&
                array_keys( $handler['methods'] ) === array_values( $endpoint['methods'] ) ) {
                return $handler;
            }
        }

        return null;
    }
}
