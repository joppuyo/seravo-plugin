<?php

namespace Seravo\API;

/**
 * Class Shadow
 *
 * Class for accessing and modifying shadow data.
 */
class Shadow {

  /**
   * Get the details of all the shadow instances.
   *
   * @param string $api_version   API version to use. Might not be used if invalid.
   *
   * @return array                The API response or an array with an 'error' field.
   */
  public static function get_shadows( $api_version = null ) {
    if ( $api_version === null || ! isset(\Seravo\API::API_HOSTS[$api_version]) ) {
      $api_version = \Seravo\API::get_api_version();
    }

    $data = \Seravo\API::get_site_data('/shadows', array(200), $api_version);

    if ( \is_wp_error($data) ) {
      \error_log($data->get_error_message());
      return array( 'error' => __('An API error occured. Please try again later', 'seravo') );
    }

    // Convert v1 response to v2 format
    if ( $api_version === 'v1' ) {
      foreach ($data as $i => $shadow) {
        $data[$i]['env'] = array('WP_ENV' => $shadow['env']);
      }
    }

    return $data;
  }

  /**
   * Get the details of a shadow by a name.
   *
   * Note: API v1 response will not have an 'env' -field.
   *
   * @param string $shadow        The name of the shadow to get details of.
   * @param string $api_version   API version to use. Might not be used if invalid.
   *
   * @return array                The API response or an array with an 'error' field.
   */
  public static function get_shadow( $shadow, $api_version = null ) {
    if ( $api_version === null || ! isset(\Seravo\API::API_HOSTS[$api_version]) ) {
      $api_version = \Seravo\API::get_api_version();
    }

    // Convert v1 response to v2 format
    if ( $api_version === 'v1' ) {
      $data = \Seravo\API::get_site_data('/shadow/' . $shadow, array(200), $api_version);
    } else {
      $data = \Seravo\API::get_site_data('/shadows/' . $shadow, array(200), $api_version);
    }

    if ( \is_wp_error($data) ) {
      \error_log($data->get_error_message());
      return array( 'error' => __('An API error occured. Please try again later', 'seravo') );
    }

    // Convert v1 response to v2 format
    if ( $api_version === 'v1' ) {
      $data['env'] = array( 'WP_ENV' => $data['env'] );
    }

    return $data;
  }

}
