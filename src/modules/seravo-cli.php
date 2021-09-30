<?php

namespace Seravo\Module;

use \Seravo\API;
use \Seravo\Helpers;

/**
 * Class SeravoCLI
 *
 * A class for Seravo.com specific WP-CLI actions.
 */
final class SeravoCLI extends \WP_CLI_Command {
  use Module;

  /**
   * Check whether the module should be loaded or not.
   * @return bool Whether to load.
   */
  protected function should_load() {
    return Helpers::is_production();
  }

  /**
   * Initialize the module. Filters and hooks should be added here.
   * @return void
   */
  protected function init() {
    \WP_CLI::add_command('seravo updates', array( __CLASS__, 'updates' ));
    \WP_CLI::add_command('seravo api get site', array( __CLASS__, 'api_get_site'));
    \WP_CLI::add_command('seravo api get shadow', array( __CLASS__, 'api_get_shadow'));
  }

  /**
   * Seravo wp-cli functions.
   *
   * ## OPTIONS
   *
   * No options.
   *
   * ## EXAMPLES
   *
   *     wp seravo updates
   *
   * @param string[] $args       Arguments for the command.
   * @param string[] $assoc_args Associated arguments for the command.
   * @return void
   */
  public function updates( $args, $assoc_args ) {
    $site_info = API::get_site_data();
    if ( \is_wp_error($site_info) ) {
      \WP_CLI::error('Seravo API failed to return information about updates.');
      return;
    }

    if ( $site_info['seravo_updates'] === true ) {
      \WP_CLI::success('Seravo Updates: enabled');
    } elseif ( $site_info['seravo_updates'] === false ) {
      \WP_CLI::success('Seravo Updates: disabled');
    } else {
      \WP_CLI::error('Seravo API failed to return information about updates.');
    }
  }

  /**
   * Get site info from the API and print sorted.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   *     API version to use. Different one may be used if invalid one specified.
   *
   * ## EXAMPLES
   *
   *     wp seravo api get site --version=v2
   *
   * @param string[] $args       Arguments for the command.
   * @param string[] $assoc_args Associated arguments for the command.
   * @return void
   */
  public function api_get_site( $args, $assoc_args ) {
    if ( isset($assoc_args['version']) ) {
      $api_version = $assoc_args['version'];
    } else {
      $api_version = 'v1';
    }

    $site_data = \Seravo\API\Site::get_site($api_version);
    $this->print_sorted_array($site_data, 0);
  }

  /**
   * Get shadow or shadows from the API and print sorted.
   *
   * ## OPTIONS
   *
   * [<name>]
   *     Name of the shadow to get.
   *
   * [--version=<version>]
   *     API version to use. Different one may be used if invalid one specified.
   *
   * ## EXAMPLES
   *
   * List all the shadows:
   *     wp seravo api get shadow --version=v2
   *
   * Get a shadow by the name:
   *      wp seravo api get shadow my-site_abc123
   *
   * @param string[] $args       Arguments for the command.
   * @param string[] $assoc_args Associated arguments for the command.
   * @return void
   */
  public function api_get_shadow( $args, $assoc_args ) {
    if ( isset($assoc_args['version']) ) {
      $api_version = $assoc_args['version'];
    } else {
      $api_version = 'v1';
    }

    if ( count($args) == 0 ) {
      $shadow_data = \Seravo\API\Shadow::get_shadows($api_version);
    } else {
      // If user gave more than one shadow name ( wow, thanks! ), get the last one only
      $shadow_data = \Seravo\API\Shadow::get_shadow(end($args), $api_version);
    }


    $this->print_sorted_array($shadow_data, 0);
  }

  /**
   * Print an array sorted and formatted. All the nested arrays will
   * be sorted and formatted too.
   *
   * @param mixed $data    Any type of data to be printed.
   * @param int   $nesting Indenting for the data (2 spaces each).
   *
   * @return void
   */
  private function print_sorted_array( $data, $nesting = 0 ) {
    ksort($data);

    $indent = str_repeat('  ', $nesting);
    foreach ( $data as $field => $value ) {
      if ( is_array($value) ) {
        \WP_CLI::log("{$indent}[{$field}] => {");
        $this->print_sorted_array($value, $nesting + 1);
        \WP_CLI::log("{$indent}}");
      } else {
        \WP_CLI::log("{$indent}[{$field}] => {$value}");
      }
    }
  }

}
