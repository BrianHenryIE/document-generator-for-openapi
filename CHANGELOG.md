# Changelog
## [Unreleased]
- Changed: with `--extract-common-types`, a component is named by its schema's `title`, or else after where it is (`License.status`, `posts.id.POST-PUT-PATCH.status`). Components were named after the property key, with `_1`, `_2` appended when two different schemas had the same key. The document generated without `--extract-common-types` is unchanged.
- Added: a schema with a `title` is extracted whatever its type.
- Added: `--strict-component-names` (`strict_component_names` over REST): two different schemas with one `title` are an error which names both places.
- Changed: the PHP namespace is `BrianHenryIE\OpenAPIGenerator`.
- Added: PHPUnit tests which need no WordPress (`composer test`).
## [1.1.0] - 2023-07-04
- Added WP-CLI command to export OpenAPI document via CLI (Thanks to @vnmedeiros - Vinícius Nunes Medeiros)
## [1.0.2] - 2021-09-17
- Added missing files for WordPress plugin repository
## [1.0.1] - 2021-09-17
- Changed name of plugin from openapi-generator to document-generator-for-openapi
## [1.0.0] - 2021-09-17
- Initial release