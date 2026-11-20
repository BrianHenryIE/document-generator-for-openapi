# WordPress Document Generator for OpenAPI

## Description

This plugin generates OpenAPI (fka. Swagger) documents for a given WordPress REST Api namespace.
  
## How it works

This plugin reads the schema definition of a given WordPress REST Api namespace and transforms it to a
OpenAPI document. The generator itself is exposed via the WordPress REST Api with the namespace ``document-generator-for-openapi/v1``.

There is also a built in WP-CLI Command.
## Usage

1. Install plugin via WordPress Plugin repository or by manually copying files
2. Activate the plugin
3. Access the REST Api of the generator by calling ``/wp-json/document-generator-for-openapi/v1/document?namespace=\<NAMESPACE\>`` and your OpenAPI document will be returned.
4. Or use the integrated WP-CLI command ``wp openapi-generator export-file``

## Extracting common types

With `--extract-common-types` (`extract_common_types=true` over REST), objects and enums are moved to
`components.schemas` and referenced, so a generated client has one named type for each.

A component is named by its schema's `title`:

```php
'environment' => array(
    'title' => 'Environment',
    'type'  => 'string',
    'enum'  => array( 'live', 'staging' ),
),
```

The same schema used in several places is one component. A schema with a title is extracted whatever its type.

A schema without a title is named after where it is: `License.status` for the `status` property of the response
titled `License`, `posts.id.POST-PUT-PATCH.status` for the `status` argument of `POST /posts/{id}`. No component
is named after a bare property key, and none is a numbered copy such as `status_1`.

WordPress itself gives one title to schemas which differ (`Term ID Taxonomy Query` in `wp/v2`), so by default a
schema whose title is already taken by a different schema is also named after where it is. With
`--strict-component-names` (`strict_component_names=true`) that is an error which names both places, so in your
own namespace one title is always one type.

## Tests

`composer test` runs PHPUnit. The generator is given the routes as an array, so the tests need no WordPress.

## Build

[wp-cli/dist-archive-command](https://github.com/wp-cli/dist-archive-command/) is used to create a plugin archive to install:

```
$ composer install
Installing dependencies from lock file (including require-dev)
...

$ wp dist-archive . .
Success: Created document-generator-for-openapi.1.1.0.zip
```

## Limitations and known issues

* Beware that currently the generator is exposeed to anonymous users since the WordPress schema endpoint is also publicly 
available. Use it at your own risk or disable the plugin after use.
* No UI for configuration yet
* Currently only version 3.1.0 of the OpenAPI specification is implemented. Swagger tools for 3.0.0 might work though.
* Unittests need to be extended and improved
* Extensibility with hooks needs to be improved