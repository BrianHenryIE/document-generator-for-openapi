<?php

namespace BrianHenryIE\OpenAPIGenerator;

class Generator3_1_0 extends GeneratorBase {

    protected $components;

    /**
     * Where each extracted component was first found, to say so when another schema takes the same name.
     *
     * @var array<string, string> Component name to location.
     */
    protected $componentLocations = [];

    public $extractCommonTypes = false;    

    /**
     * Whether two different schemas with the same `title` are an error. Otherwise the second is named after
     * where it is, as a schema without a title is.
     *
     * @var bool
     */
    public $strictComponentNames = false;

    public function __construct( $namespace, $routes, $extractCommonTypes, $strictComponentNames = false ) {
        parent::__construct($namespace, $routes);

        $this->extractCommonTypes = $extractCommonTypes;
        $this->strictComponentNames = $strictComponentNames;

        $this->components = [
            'schemas' => [
                'WPError' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'description' => esc_html__( 'The error code', 'document-generator-for-openapi' ),
                            'type' => 'string'
                        ],
                        'message' => [
                            'description' => esc_html__( 'The description of the error', 'document-generator-for-openapi' ),
                            'type' => 'string'
                        ],
                        'data' => [
                            'description' => esc_html__( 'Data of the error', 'document-generator-for-openapi' ),
                            'type' => 'any'
                        ],
                        'additional_data' => [
                            'description' => esc_html__( 'Additional data of the error', 'document-generator-for-openapi' ),
                            'type' => 'any'
                        ],
                        'additional_errors' => [
                            'description' => esc_html__( 'More errors', 'document-generator-for-openapi' ),
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/WPError'
                            ]
                        ]
                    ]
                ]
            ]
        ];    
    }

    public function generateDocument() {
        return apply_filters( 'openapi_generator_v3_1', $this->generateRoot(), $this );
    }

    public function generateRoot () {
        $result = [
            'openapi' => '3.1.0',
            'info' => $this->generateInfo(),
            'jsonSchemaDialect' => 'http://json-schema.org/draft-04/schema#',
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
            'security' => $this->generateSecurity()
        ];

        if ( !empty( $this->components ) ) {
            $result['components'] = $this->components;
        }

        return $result;
    }

    public function generateInfo() {
        return [
            'title' => $this->namespace,
            'summary' => esc_html(
                            sprintf(
                                esc_html__( 'Generated OpenAPI document of the namespace %s on %s.',
                                            'document-generator-for-openapi') ,
                                            $this->namespace,
                                            get_option( 'blogname' )
                                )
                            ),
            'version' => '1'
        ];
    }

    public function generateServers() {
        return [
            [ 'url' => rest_url( $this->namespace ) ]
        ];
    }

    public function generatePaths() {

        $result = [];

        foreach ( $this->routes as $url => $spec ) {
            //remove namespace portion from url
            $url = preg_replace( '#' . $this->namespace . '/?#' , '', $url );

            $substitutions = $this->getSubstitutions( $url );

            //replace all regex substituions with OpenAPI substitutions
            $url = preg_replace( '/\(\?P\<(.*?)\>.*?\)(\/|$)/', '{$1}$2', $url ); 

            $name = '';
            
            //try to get a fallback entity name for this path by getting the last part of the url
            //TODO we need to ignore all substitution names
            $matches = [];
            if ( preg_match_all( '/\/(.*?)(\/|$)/', $url, $matches, PREG_SET_ORDER ) ) {
                $name = $matches[count($matches) - 1][1];
            }

            //it is better to use the name of the defined WP schema result if it exists
            if ( isset( $spec['schema'] ) && isset( $spec['schema']['title'] ) ) {
                $name = $spec['schema']['title'];
            }

            $result[ $url ] = $this->generatePathItem( $name, $spec, $substitutions, $url );
        }

        return $result;
    }

    public function getSubstitutions( $url ) {
        //create OpenAPI style substitutions by replacing regex named capture grouping used in WordPress
        //url/<?P<paramname>[regex]+)/further/url
        //to
        //url/{paramname}/further/url

        $substitutions = [];
        $found = preg_match_all( '/\(\?P\<(.*?)\>(.*?)\)(\/|$)/', $url, $matches, PREG_SET_ORDER );
        if ( $found && $found > 0 ) {
            //for each found substituion, store the given regex
            foreach ( $matches as $foundSubstitution ) {
                $substitutions[$foundSubstitution[1]] = $foundSubstitution[2]; 
            }
        }

        return $substitutions;
    }

    public function generatePathItem( $name, $spec, $substitutions, $url = '' ) {
        
        $result = [];

        foreach ( $spec['endpoints'] as $endpoint ) {
            $parameters = [];
            $contentProperties = [];
            $contentRequired = [];
            $substitutionParameters = [];

            //the arguments of an endpoint belong to its path and methods, which no other endpoint has
            $methods = implode( '-', $endpoint['methods'] );
            $argumentsName = $this->getPathName( $url ) . '.' . $methods;
            $argumentsLocation = trim( $methods . ' ' . $url );

            //create parameters for all the following methods of this endpoint
            //this means, yes, currently those parameters are duplicated in the OpenAPI document
            //because we don't use refs yet. 
            foreach ( $endpoint['args'] as $argumentName => $argument ) {
                $argumentContext = [
                    'currentKey' => $argumentName,
                    'name' => $argumentsName . '.' . $argumentName,
                    'location' => $argumentsLocation . ' argument ' . $argumentName
                ];

                $paramSchema = $this->generateParameterObject( $argumentName, $argument, $substitutions, $argumentContext );
                $parameters[] = $paramSchema;

                //if this is not a path parameter, add it to the content schema
                if ( !array_key_exists( $argumentName, $substitutions ) ) {
                    $contentProperties[ $argumentName ] = $this->generateSchemaObject( $argument, $argumentContext );

                    if ( isset( $argument['required'] ) && $argument['required'] === true ) {
                        $contentRequired[] = (string) $argumentName;
                    }
                } else {
                    $substitutionParameters[] = $paramSchema;
                }
            }
            foreach ( $endpoint['methods'] as $methodName ) {

                $method = [
                    'responses' => [
                        '200' => ['description' => 'OK'],
                        '400' => [
                            'description' => 'Bad Request',
                            'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/WPError'    
                                        ]
                                    ]
                                ]
                            ],
                        '404' => ['description' => 'Not Found']
                    ]
                ];



                if(isset($spec['schema']['tags'])) {
                    $method['tags'] = $spec['schema']['tags'];
                }
                else 
                    $method['tags'] = ['root'];

                //for all methods with possible request body content,
                //we move the parameters to its requestbody
                //all substitutions parameters should be kept in the query parameter list
                if ( in_array(strtolower( $methodName ), ['post', 'put', 'patch'] ) ) {

                    if (count($contentProperties)) {
                        $method['requestBody'] = [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'title' => $name,
                                        'properties' => $contentProperties
                                    ]
                                ]
                            ]
                        ];

                        //the arguments WordPress requires are the required properties of the body
                        if ( count( $contentRequired ) ) {
                            $method['requestBody']['content']['application/json']['schema']['required'] = $contentRequired;
                        }
                    }
  
                    if ( count( $substitutionParameters ) ) {               
                        $method['parameters'] = $substitutionParameters;
                    }

                } else {
                    $method['parameters'] = $parameters;
                }

                //if a schema is defined for the reponse of the current route add it.
                if ( isset( $spec['schema'] ) && !empty( $spec['schema'] )) {
                    $method['responses']['200']['content'] = $this->generateResponseSchema( $spec['schema'] );
                }

                //create operation object for path item with the specific method
                $result[strtolower( $methodName )] = $method;
            }
        }

        return $result;
    }

    public function generateParameterObject( $argumentName, $argument, $substitutions, $context = null ) {
        $in = array_key_exists( $argumentName, $substitutions ) ? 'path' : 'query';
        
        $result = [
            'name' => $argumentName,
            'in' => $in,
            'description' => isset( $argument['description'] ) ? $argument['description'] : '',
            'required' => $in === 'path' ? true : (isset ( $argument['required'] ) ? $argument['required'] : false ),
            'schema' => $this->generateSchemaObject( $argument, $context ? $context : [ 'currentKey' => $argumentName ] )
        ];

        return $result;
    }

    public function generateResponseSchema( $schema ) {
                  
        $schemaName = $schema['title'];

        //add schema to the current schema pool to add it to the components part of the document later on.
        $generated = $this->generateSchemaObject( $schema, [
            'currentKey' => null,
            'name' => $this->getComponentName( $schemaName ),
            'location' => 'the response schema "' . $schemaName . '"',
            'isResponse' => true
        ]);

        if ( $this->extractCommonTypes && $this->strictComponentNames ) {
            $this->addComponent( $schemaName, $generated, 'the response schema "' . $schemaName . '"' );
        } else {
            $this->components['schemas'][$schemaName] = $generated;
        }

        return [
            'application/json' => [
                'schema' => [
                    '$ref' => '#/components/schemas/' . $schemaName
                ]
            ]
        ];
    }

    /**
     * The name of a path for the components of its arguments: `/posts/{id}/revisions` is `posts.id.revisions`.
     */
    public function getPathName( $url ) {
        $name = $this->getComponentName( str_replace( [ '{', '}', '/' ], [ '', '', '.' ], trim( $url, '/' ) ) );

        return $name === '' ? 'root' : $name;
    }

    /**
     * Make a string a valid key of `components.schemas`, which allows `A-Z a-z 0-9 . _ -`.
     */
    public function getComponentName( $name ) {
        return trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', (string) $name ), '-.' );
    }

    /**
     * Add an extracted schema to `components.schemas`.
     *
     * The same schema found again is the same component. A different schema under a name already taken is an
     * error: renaming it `name_1` would hide that two things share a name.
     *
     * @throws ComponentNameCollisionException
     */
    protected function addComponent( $name, $schema, $location ) {
        if ( isset( $this->components['schemas'][$name] ) && !$this->isSameSchema( $this->components['schemas'][$name], $schema ) ) {
            throw new ComponentNameCollisionException(
                $name,
                isset( $this->componentLocations[$name] ) ? $this->componentLocations[$name] : 'the generator\'s own components',
                $location
            );
        }

        $this->components['schemas'][$name] = $schema;

        if ( !isset( $this->componentLocations[$name] ) ) {
            $this->componentLocations[$name] = $location;
        }
    }

    /**
     * Whether two generated schemas are the same. Compared as the JSON they become, because an empty object,
     * e.g. the `default` of an object, is a `stdClass`, and two of those are never identical.
     */
    protected function isSameSchema( $first, $second ) {
        return json_encode( $first ) === json_encode( $second );
    }

    /**
     * The JSON Schema keywords copied as they are. With `type`, `enum`, `properties`, `items`, `required`,
     * `additionalProperties`, `patternProperties`, `oneOf` and `anyOf`, which are converted, these are the
     * keywords WordPress validates, and the annotations a route can add.
     *
     * @see rest_get_allowed_schema_keywords()
     */
    const COPIED_KEYWORDS = [
        'title',
        'description',
        'format',
        'pattern',
        'minLength',
        'maxLength',
        'minimum',
        'maximum',
        'exclusiveMinimum',
        'exclusiveMaximum',
        'multipleOf',
        'minItems',
        'maxItems',
        'uniqueItems',
        'minProperties',
        'maxProperties',
        'examples',
        'deprecated',
        'readOnly',
        'writeOnly',
    ];

    /**
     * @param array $schemaObject The WordPress REST schema.
     * @param array $context {
     *     @type string|null $currentKey The property or argument the schema is the value of.
     *     @type string      $name       Optional. The name the schema has as a component when it has no `title`:
     *                                   its parent's name and its key.
     *     @type string      $location   Optional. Where the schema is, for error messages.
     *     @type bool        $isResponse Optional. The schema is a route's response, which is always a component.
     * }
     */
    public function generateSchemaObject( $schemaObject, $context ) {

        $hasTitle = $this->extractCommonTypes &&
                    isset( $schemaObject['title'] ) && is_string( $schemaObject['title'] ) &&
                    $this->getComponentName( $schemaObject['title'] ) !== '';

        if ( !$hasTitle || $this->strictComponentNames ) {
            return $this->generateNamedSchemaObject( $schemaObject, $context, $hasTitle );
        }

        //WordPress itself gives one title to different schemas, e.g. "Term ID Taxonomy Query" in wp/v2.
        //Unless that is to be an error, a schema whose title is taken is named after where it is instead.
        $components = $this->components;
        $componentLocations = $this->componentLocations;

        try {
            return $this->generateNamedSchemaObject( $schemaObject, $context, true );
        } catch ( ComponentNameCollisionException $exception ) {
            $this->components = $components;
            $this->componentLocations = $componentLocations;

            return $this->generateNamedSchemaObject( $schemaObject, $context, false );
        }
    }

    /**
     * @param array $schemaObject The WordPress REST schema.
     * @param array $context      See generateSchemaObject().
     * @param bool  $useTitle     Whether the schema's `title` names its component.
     *
     * @throws ComponentNameCollisionException
     */
    protected function generateNamedSchemaObject( $schemaObject, $context, $useTitle ) {

        $currentKey = isset( $context['currentKey'] ) ? $context['currentKey'] : null;
        $location = isset( $context['location'] ) ? $context['location'] : (string) $currentKey;

        //a title names the component, otherwise it is the parent's name and the key
        $title = $useTitle ? $this->getComponentName( $schemaObject['title'] ) : null;

        $name = isset( $context['name'] ) ? $context['name'] : $currentKey;
        if ( $title !== null ) {
            $name = $title;
        }

        //the schemas inside this one
        $childContext = array_merge( $context, [ 'isResponse' => false ] );

        $result = [];

        $types = isset( $schemaObject['type'] ) ? (array) $schemaObject['type'] : [];
        $hasOneOf = isset( $schemaObject['oneOf'] ) && is_array( $schemaObject['oneOf'] );
        $hasAnyOf = isset( $schemaObject['anyOf'] ) && is_array( $schemaObject['anyOf'] );

        //without a `type`, e.g. an alternative of `oneOf` whose parent has it, `properties` and `items` still apply
        $hasProperties = isset( $schemaObject['properties'] ) && is_array( $schemaObject['properties'] ) &&
                            ( in_array( 'object', $types, true ) || empty( $types ) );
        $hasItems = isset( $schemaObject['items'] ) && is_array( $schemaObject['items'] ) &&
                            ( in_array( 'array', $types, true ) || empty( $types ) );

        if ( isset( $schemaObject['type'] ) ) {
            //a list of types with `oneOf` is written as the `oneOf` alone, which says the same
            if ( !( is_array( $schemaObject['type'] ) && $hasOneOf ) ) {
                $result['type'] = $schemaObject['type'];
            }
        } elseif ( !$hasOneOf && !$hasAnyOf && !$hasProperties && !$hasItems ) {
            //a schema which says nothing of what it holds is taken as a string
            $result['type'] = 'string';
        }

        foreach ( [ 'oneOf', 'anyOf' ] as $combination ) {
            if ( !isset( $schemaObject[$combination] ) || !is_array( $schemaObject[$combination] ) ) {
                continue;
            }

            $result[$combination] = [];

            foreach ( array_values( $schemaObject[$combination] ) as $index => $type ) {
                $result[$combination][] = $this->generateSchemaObject( $type,
                                            array_merge( $childContext, [
                                                'currentKey' => null,
                                                'name' => $name === null ? null : $name . '.option' . ( $index + 1 ),
                                                'location' => $location . '/' . $combination . '/' . $index
                                            ] ) );
            }
        }

        //WordPress marks a property as required on the property, JSON Schema since draft 4 lists them on the object
        $requiredProperties = [];

        if ( $hasProperties ) {

            foreach( $schemaObject['properties'] as $key => $parameter) {
                $result['properties'][$key] = $this->generateSchemaObject( $parameter,
                                                array_merge( $childContext, [
                                                    'currentKey' => $key,
                                                    'name' => $name === null ? $key : $name . '.' . $key,
                                                    'location' => $location . '/' . $key
                                                ] ) );

                if ( isset( $parameter['required'] ) && $parameter['required'] === true ) {
                    $requiredProperties[] = (string) $key;
                }
            }
        }

        if ( isset( $schemaObject['required'] ) && is_array( $schemaObject['required'] ) ) {
            $requiredProperties = array_merge( $requiredProperties, array_map( 'strval', $schemaObject['required'] ) );
        }

        if ( !empty( $requiredProperties ) ) {
            $result['required'] = array_values( array_unique( $requiredProperties ) );
        }

        if ( isset( $schemaObject['patternProperties'] ) && is_array( $schemaObject['patternProperties'] ) ) {
            $index = 0;

            foreach ( $schemaObject['patternProperties'] as $pattern => $parameter ) {
                $index++;
                $result['patternProperties'][$pattern] = $this->generateSchemaObject( $parameter,
                                                            array_merge( $childContext, [
                                                                'currentKey' => null,
                                                                'name' => $name === null ? null : $name . '.pattern' . $index,
                                                                'location' => $location . '/patternProperties/' . $pattern
                                                            ] ) );
            }
        }

        if ( isset( $schemaObject['additionalProperties'] ) ) {
            $result['additionalProperties'] = is_array( $schemaObject['additionalProperties'] )
                ? $this->generateSchemaObject( $schemaObject['additionalProperties'],
                    array_merge( $childContext, [
                        'currentKey' => null,
                        'name' => $name === null ? null : $name . '.additionalProperties',
                        'location' => $location . '/additionalProperties'
                    ] ) )
                : (bool) $schemaObject['additionalProperties'];
        }

        if ( $hasItems ) {
            //TODO Is it safe to always pass context with same currentKey of parent?
            $result['items'] = $this->generateSchemaObject( $schemaObject['items'],
                                    array_merge( $childContext, [
                                        'name' => $name,
                                        'location' => $location . '/items'
                                    ] ) );
        }

        foreach ( self::COPIED_KEYWORDS as $keyword ) {
            if ( isset( $schemaObject[$keyword] ) ) {
                $result[$keyword] = $schemaObject[$keyword];
            }
        }

        //WordPress validates as JSON Schema draft 4, where `exclusiveMinimum` is a boolean which makes
        //`minimum` exclusive. OpenAPI 3.1's schemas are draft 2020-12, where it is the limit itself.
        foreach ( [ 'exclusiveMinimum' => 'minimum', 'exclusiveMaximum' => 'maximum' ] as $exclusive => $inclusive ) {
            if ( !isset( $result[$exclusive] ) || !is_bool( $result[$exclusive] ) ) {
                continue;
            }

            if ( $result[$exclusive] && isset( $result[$inclusive] ) ) {
                $result[$exclusive] = $result[$inclusive];
                unset( $result[$inclusive] );
            } else {
                unset( $result[$exclusive] );
            }
        }

        //WordPress spells it in lowercase
        if ( isset( $schemaObject['readonly'] ) && $schemaObject['readonly'] === true ) {
            $result['readOnly'] = true;
        }

        if ( isset( $schemaObject['enum'] ) && is_array( $schemaObject['enum'] ) ) {
            $result['enum'] = array_values( $schemaObject['enum'] );
        }

        //WordPress writes `'default' => null` for "no default", which is only a default where null is a value
        if ( isset( $schemaObject['default'] ) ||
            ( array_key_exists( 'default', $schemaObject ) && in_array( 'null', $types, true ) ) ) {
            //PHP's empty array is JSON's empty list, which is not the default of an object
            $result['default'] = $schemaObject['default'] === [] && in_array( 'object', $types, true )
                ? new \stdClass()
                : $schemaObject['default'];
        }

        //a route's response is made a component by generateResponseSchema()
        $isResponse = isset( $context['isResponse'] ) && $context['isResponse'];

        //objects and enums are extracted, and anything given a title
        if ( $this->extractCommonTypes && !$isResponse && $name !== null && $name !== '' &&
            ( $title !== null ||
                ( isset( $result['type'] ) && $result['type'] === 'object' && $currentKey ) ||
                ( isset( $result['enum'] ) && is_array( $result['enum'] ) ) ) ) {

            $name = $this->getComponentName( $name );

            $this->addComponent( $name, $result, $location );

            return [
                '$ref' => '#/components/schemas/' . $name
            ];
        }


        return $result;
    }

    public function generateSecurity() {
        return [];
    }

}