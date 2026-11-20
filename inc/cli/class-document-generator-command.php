<?php

namespace BrianHenryIE\OpenAPIGenerator\cli;

use WP_CLI\Utils;

/**
 * Generates OpenAPI json documents based on the WordPress REST Api schema.
 */
class DocumentGenerator_Command
{

	/**
	 * Export the OpenAPI document of a given namespace as a JSON file.
	 *
	 * ## OPTIONS 
	 *
	 * [<namespace>]
	 * : The namespace for which the OpenAPI document should be generated.
	 * 
	 * [--destination]
	 * : File name of the resulting OpenAPI JSON file.
	 *
	 * [--extract-common-types]
	 * : Defines if JSON schema objects should be extracted and, if equal, merged to one single named type (default=false). A type is named by its schema's `title`, or else after where it is.
	 *
	 * [--strict-component-names]
	 * : With --extract-common-types, fail when two different schemas have the same `title`, rather than naming the second after where it is (default=false).
	 * 
	 * @subcommand export-file
	 */
	public function export_file($args, $assoc_args)
	{
		$namespace = isset($args[0]) ? $args[0] : 'wp/v2';
		$output = isset($assoc_args['destination']) ? $assoc_args['destination'] : 'openapi.json';
		$extract_common_types      = (bool) Utils\get_flag_value( $assoc_args, 'extract-common-types', false );
		$strict_component_names    = (bool) Utils\get_flag_value( $assoc_args, 'strict-component-names', false );

		if (empty($namespace)) {
			\WP_CLI::error('The namespace needs to be defined', true);
		}

		if (!in_array($namespace, rest_get_server()->get_namespaces())) {
			\WP_CLI::error('The namespace is invalid', true);
		}

		$this->do_export_file($namespace, $extract_common_types, $output, $strict_component_names);
	}

	private function do_export_file($namespace, $extract_common_types, $output, $strict_component_names = false)
	{
		//get wordpress rest schema
		$data = \BrianHenryIE\OpenAPIGenerator\RouteData::forNamespace($namespace);

		//generate openapi document
		//TODO create factory for switching between version
		$generator = new \BrianHenryIE\OpenAPIGenerator\Generator3_1_0($namespace, $data, $extract_common_types, $strict_component_names);

		try {
			$result = $generator->generateDocument();
		} catch (\BrianHenryIE\OpenAPIGenerator\ComponentNameCollisionException $exception) {
			\WP_CLI::error($exception->getMessage(), true);
		}

		//write json to file
		if (file_put_contents($output, json_encode($result))) {
			\WP_CLI::success("The JSON file ($output) created successfully.");
		} else {
			\WP_CLI::error("Error creating json file: $output", true);
		}
	}
}

?>