<?php
/**
 * File for Seravo AJAX handling.
 */

namespace Seravo\Postbox;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Ajax_Handler') ) {
  class Ajax_Handler {

    /**
     * @var string String for transient key to be prefixed with.
     */
    public const CACHE_KEY_PREFIX = 'seravo_ajax_';
    /**
     * @var string String for transient key to be suffixed with.
     */
    public const CACHE_KEY_SUFFIX = '_data';


    /**
     * @var string|null Unique id/slug of the postbox.
     */
    private $id;
    /**
     * @var string|null Unique section inside the postbox.
     */
    private $section;
    /**
     * @var string|null WordPress nonce for the page.
     */
    private $ajax_nonce;


    /**
     * @var array|null Function to be called on AJAX call.
     */
    private $ajax_func;
    /**
     * @var array|null Function to be called on AJAX component render.
     */
    private $build_func;
    /**
     * @var int|null Seconds to cache data returned by $ajax_func.
     */
    private $data_cache_time;

    /**
     * @param string $section Unique section inside the postbox.
     */
    public function __construct( $section ) {
      $this->section = $section;
    }

    /**
     * @param string $id    Unique id/slug of the postbox.
     * @param string $nonce Name of WordPress nonce for the page.
     */
    public function init( $id, $nonce ) {
      $this->id = $id;
      $this->ajax_nonce = $nonce;

      add_action(
        'wp_ajax_seravo_ajax_' . $this->id,
        function() {
          return $this->_ajax_handler();
        }
      );
    }

    /**
     * @param array $ajax_func Function to be called on AJAX call.
     * @param int   $cache_time Seconds to cache data for (default is 0).
     */
    public function set_ajax_func( $ajax_func, $cache_time = 0 ) {
      $this->ajax_func = $ajax_func;
      $this->data_cache_time = $cache_time;
    }

    /**
     * @param array $build_func Function to be called on AJAX component render.
     */
    public function set_build_func( $build_func ) {
      $this->build_func = $build_func;
    }

    /**
     * @param int $cache_time Seconds to cache data for (default is 0).
     */
    public function set_cache_time( $cache_time ) {
      $this->data_cache_time = $cache_time;
    }

    /**
     * Get component this AJAX handler needs to function. Calls
     * build_func to build the component.
     * @return Component Component for this Ajax handler.
     */
    public function get_component() {
      $component = new Component();

      if ( $this->build_func !== null ) {
        \call_user_func($this->build_func, $component, $this->section);
      }

      return $component;
    }

    /**
     * This function will be called by WordPress
     * if AJAX call is made here.
     */
    public function _ajax_handler() {
      check_ajax_referer($this->ajax_nonce, 'nonce');

      if ( ! isset($_REQUEST['section']) ) {
        echo Ajax_Response::invalid_request_response()->to_json();
        wp_die();
      }

      if ( $_REQUEST['section'] !== $this->section ) {
        // This request doesn't concern us
        return;
      } 

      $cache_key = self::CACHE_KEY_PREFIX . $this->section . self::CACHE_KEY_SUFFIX;

      $response = null;

      try {

        // Check if we should be using transients
        if ( $this->data_cache_time > 0 ) {
          $response = \get_transient($cache_key);
          if ( false === $response ) {
            // The data was not cached, call data_func
            $response = \call_user_func($this->ajax_func, $this->section);
            if ( null !== $response ) {
              // Cache new result unless it's null
              \set_transient($cache_key, $response, $this->data_cache_time);
            }
          }
        } else {
          // Not using cache
          $response = \call_user_func($this->ajax_func, $this->section);
        }

      } catch ( \Exception $exception ) {
        error_log('### Seravo Plugin experienced an error!');
        error_log('### Please report this on GitHub (https://github.com/Seravo/seravo-plugin) with following:');
        error_log($exception);

        $response = Ajax_Response::exception_response();
      }

      if ( $response !== null ) {
        echo $response->to_json();
        wp_die();
      }

      echo Ajax_Response::unknown_error_response()->to_json();
      wp_die();
    }

  }
}

if ( ! class_exists('Ajax_Response') ) {
  class Ajax_Response {

    /**
     * @var array Data to respond with.
     */
    private $data = array();

    public function __constructor() {
      $this->data['success'] = false;
    }

    /**
     * @param bool $is_success Whether the action was succesful or not.
     */
    public function is_success( $is_success ) {
      $this->data['success'] = $is_success;
    }

    /**
     * @param string $error Error to be shown for user.
     */
    public function set_error( $error ) {
      $this->data['error'] = $error;
    }

    /**
     * @param array $data The response data.
     */
    public function set_data( $data ) {
      $this->data = array_merge($this->data, (array) $data);
    }

    /**
     * @param mixed $response Raw response that won't be tampered with.
     */
    public function set_raw_response( $response ) {
      $this->data = $response;
    }

    /**
     * @return string The response data as JSON.
     */
    public function to_json() {
      return json_encode($this->data);
    }

    /**
     * @return Ajax_Response Response that's supposed to be sent on invalid requests.
     */
    public static function invalid_request_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__('Error: Your browser made an invalid request!', 'seravo'));
      return $response;
    }

    /**
     * @return Ajax_Response Response that's supposed to be sent on unknown error.
     */
    public static function unknown_error_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__('Error: Something went wrong! Please see the php-error.log', 'seravo'));
      return $response;
    }

    /**
     * @return Ajax_Response Response that's supposed to be sent on exception.
     */
    public static function exception_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__("Error: Oups, this wasn't supposed to happen! Please see the php-error.log", 'seravo'));
      return $response;
    }

  }
}
