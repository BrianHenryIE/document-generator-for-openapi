<?php

namespace BrianHenryIE\OpenAPIGenerator;

class RestController {

    protected $namespace;
    protected $resource_name;

    public function __construct() {
        $this->namespace = 'document-generator-for-openapi/v1';
        $this->resource_name = 'document';
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->resource_name, [
            [
                'methods' => 'GET',
                'callback' => [ $this, 'generate_document' ],
                'permission_callback' => [ $this, 'generate_document_permission_check' ],
                'args' => $this->get_generate_document_args()
            ],
            'schema' => [ $this, 'get_generate_document_schema' ]
        ]);
    }

    public function generate_document_permission_check( $request ) {
        return true;
    }

    public function generate_document( $request ) {
        $namespace = '';
        $extract_common_types = false;
        $strict_component_names = false;

        if ( isset( $request['namespace'] ) ) {
            $namespace = $request['namespace'];
        }

        if ( isset( $request['extract_common_types'] ) ) {
            $extract_common_types = $request['extract_common_types'] === true;
        }

        if ( isset( $request['strict_component_names'] ) ) {
            $strict_component_names = $request['strict_component_names'] === true;
        }

        if ( empty( $namespace ) ) {
            return new \WP_Error( 'namespace_empty',
                                    esc_html__( 'The namespace needs to be defined',
                                                'document-generator-for-openapi' ),
                                    [ 'status' => 400 ] );
        }

        //the namespace needs to exist
        if ( !in_array( $namespace, rest_get_server()->get_namespaces() ) ) {
            return new \WP_Error( 'namespace_not_found',
                                    esc_html__( 'The namespace is invalid',
                                                'document-generator-for-openapi' ),
                                    [ 'status' => 400 ] );
        }

        //get wordpress rest schema
        $data = RouteData::forNamespace( $namespace );

        //generate openapi document
        //TODO create factory for switching between version
        $generator = new Generator3_1_0( $namespace, $data, $extract_common_types, $strict_component_names );

        try {
            $result = $generator->generateDocument();
        } catch ( ComponentNameCollisionException $exception ) {
            return new \WP_Error( 'component_name_collision', $exception->getMessage(), [ 'status' => 500 ] );
        }
        
        return rest_ensure_response($result);
    }

    public function get_generate_document_args() {
        return [
            'namespace' => [
                'description' => esc_html__( 'The namespace for which the OpenAPI document should be generated.',
                                                'document-generator-for-openapi' ),
                'type' => 'string',
                'required' => true
            ],
            'extract_common_types' => [
                'description' => esc_html__( 'Defines if JSON schema objects should be extracted and, if equal, merged to one single named type.',
                                                'document-generator-for-openapi' ),
                'type' => 'boolean',
                'required' => false
            ],
            'strict_component_names' => [
                'description' => esc_html__( 'With extract_common_types, fail when two different schemas have the same title, rather than naming the second after where it is.',
                                                'document-generator-for-openapi' ),
                'type' => 'boolean',
                'required' => false
            ]
        ];
    }

    public function get_generate_document_schema() {

        //TODO is this a legal JSON schema?
        return [
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            '$ref' => 'https://spec.openapis.org/oas/3.1/schema/2021-05-20'
        ];

    }
}