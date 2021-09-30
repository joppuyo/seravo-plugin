<?php

namespace Seravo\API;

/**
 * Class Site
 *
 * Class for accessing and modifying site data.
 */
class Site {

  /**
   * Get the details of the site.
   *
   * @param string $api_version   API version to use. Might not be used if invalid.
   *
   * @return array                The API response or an array with an 'error' field.
   */
  public static function get_site( $api_version = null ) {
    if ( $api_version === null || ! isset(\Seravo\API::API_HOSTS[$api_version]) ) {
      $api_version = \Seravo\API::get_api_version();
    }

    $data = \Seravo\API::get_site_data('', array( 200 ), $api_version);

    if ( \is_wp_error($data) ) {
      \error_log($data->get_error_message());
      return array( 'error' => __('An API error occured. Please try again later', 'seravo') );
    }

    // Convert v1 response to v2 format
    if ( $api_version === 'v1' ) {
      $data['notification_webhooks_json'] = $data['notification_webhooks'];
      unset($data['notification_webhooks']);
    }

    return $data;
  }

}
