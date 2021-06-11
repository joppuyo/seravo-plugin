<?php
/**
 * File for custom Seravo postbox.
 */

namespace Seravo\Postbox;

use \Seravo\Helpers;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Toolpage') ) {
  class Toolpage {

    /**
     * @var string Admin screen id where the page should be displayed in.
     */
    private $screen;

    /**
     * @var \Seravo\Postbox\Postbox[] Postboxes registered on the page.
     */
    private $postboxes = array();

    /**
     * @param string $screen Admin screen id where the page should be displayed in.
     */
    public function __construct( $screen ) {
      $this->screen = $screen;
    }

    /**
     * Enables AJAX features for this page.
     */
    public function enable_ajax() {
      // Generates WordPress nonce for this page
      // and prints it as JavaScipt variable inside <SCRIPT>.
      add_action(
        'before_seravo_postboxes_' . $this->screen,
        function() {
          $nonce = wp_create_nonce($this->screen);
          echo "<script>SERAVO_AJAX_NONCE = \"{$nonce}\";</script>";
        }
      );
    }

    /**
     * Register postbox to be shown on the page.
     * @param \Seravo\Postbox\Postbox $postbox
     */
    public function register_postbox( Postbox $postbox ) {
      $postbox->on_page_assign($this->screen);
      $this->postboxes[] = $postbox;
    }

    /**
     * Register the page to be rendered.
     * @param \Seravo\Postbox\Postbox $postbox
     */
    public function register_page() {
      foreach ( $this->postboxes as $postbox ) {
        if ( $postbox->_is_allowed() ) {
          seravo_add_postbox($this->screen, $postbox);
        }
      }
    }

  }
}

if ( ! class_exists('Postbox') ) {
  class Postbox {

    /**
     * @var string String for transient key to be prefixed with.
     */
    public const CACHE_KEY_PREFIX = 'seravo_';
    /**
     * @var string String for transient key to be suffixed with.
     */
    public const CACHE_KEY_SUFFIX = '_data';


    /**
     * @var string Unique id/slug of the postbox.
     */
    public $id;
    /**
     * @var string Display title of the postbox.
     */
    public $title = 'WIP';
    /**
     * @var string Default admin dashboard context where the postbox should be displayed in.
     */
    public $context = 'normal';
    /**
     * @var string|null Admin screen id where the postbox should be displayed in.
     */
    public $screen;


    /**
     * @var array|null Function to be called for building the components.
     */
    private $build_func;
    /**
     * @var array|null Function to be called for data processing.
     */
    private $data_func;
    /**
     * @var int|null Seconds to cache data returned by $data_func.
     */
    private $data_cache_time;
    /**
     * @var mixed|null Data returned by data_func.
     */
    private $data;
    /**
     * @var \Exception|null Exception thrown during data_func or null.
     */
    private $error;


    /**
     * @var Ajax_Handler[] Ajax handlers assigned for this postbox.
     */
    private $ajax_handlers = array();


    /**
     * @var \Seravo\Postbox\Requirements Requirements for this postbox.
     */
    private Requirements $requirements;
    /**
     * @var \Seravo\Postbox\Component The base component to add other components on.
     */
    private Component $component;

    /**
     * @param string $id      Unique id/slug of the postbox.
     * @param string $context Default admin dashboard context where the postbox should be displayed in.
     */
    public function __construct( $id, $context ) {
      $this->requirements = new Requirements();
      $this->component = new Component();

      $this->id = $id;
      $this->context = $context;
    }

    /**
     * @param string $title Title to be shown on postbox top bar.
     */
    public function set_title( $title ) {
      $this->title = $title;
    }

    /**
     * @param array $build_func Function to be called for building the components.
     */
    public function set_build_func( $build_func ) {
      $this->build_func = $build_func;
    }

    /**
     * @param array $data_func  Function to be called for data processing.
     * @param int   $cache_time Seconds to cache data for (default is 0).
     */
    public function set_data_func( $data_func, $cache_time = 0 ) {
      $this->data_func = $data_func;
      $this->data_cache_time = $cache_time;
    }

    /**
     * @param array<string, mixed>|Requirements $requirements Requirements for this postbox.
     *                                                        Array in "[Requirements::*] => mixed" format.
     */
    public function set_requirements( $requirements ) {
      if ( is_array($requirements) ) {
        $this->requirements->init_from_array($requirements);
      } else {
        $this->requirements = $requirements;
      }
    }

    /**
     * Check if the postbox should be shown or not. Applies
     * the 'seravo_show_postbox-$id' filter which defaults to true.
     * @return bool Whether the postbox should be shown or not.
     */
    public function _is_allowed() {
      if ( ! $this->requirements->is_allowed() ) {
        return false;
      }

      return (bool) apply_filters('seravo_show_postbox-' . $this->id, true);
    }


    /**
     * @param Ajax_Handler $ajax_handler Ajax handler to be added for the postbox.
     */
    public function add_ajax_handler( $ajax_handler ) {
      $this->ajax_handlers[] = $ajax_handler;
    }

    /**
     * Called when postbox is assigned a page.
     * @param string Admin screen id where the postbox should be displayed in
     */
    public function on_page_assign( $screen ) {
      $this->screen = $screen;
      foreach ( $this->ajax_handlers as $ajax_handler ) {
        $ajax_handler->init( $this->id, $this->screen );
      }
    }

    /**
     * Calls the data function of postbox. Caching
     * and exception handling is done here.
     */
    public function _get_data() {
      if ( ! $this->data_func ) {
        return;
      }

      $cache_key = self::CACHE_KEY_PREFIX . $this->id . self::CACHE_KEY_SUFFIX;

      try {

        // Check if we should be using transients
        if ( $this->data_cache_time > 0 ) {
          $this->data = \get_transient($cache_key);
          if ( false === $this->data ) {
            // The data was not cached, call data_func
            $this->data = \call_user_func($this->data_func);
            if ( null !== $this->data ) {
              // Cache new result unless it's null
              \set_transient($cache_key, $this->data, $this->data_cache_time);
            }
          }
          return;
        }

        $this->data = \call_user_func($this->data_func);

      } catch ( \Exception $exception ) {
        error_log('### Seravo Plugin experienced an error!');
        error_log('### Please report this on GitHub (https://github.com/Seravo/seravo-plugin) with following:');
        error_log($exception);

        $this->error = $exception;
        $this->data = null;
      }
    }

    /**
     * WordPress will call this when it's
     * time to render the postbox.
     */
    public function _build() {
      $this->_get_data();

      if ( $this->error !== null ) {
        $this->component = Component::seravo_plugin_error();
        return;
      }

      \call_user_func($this->build_func, $this->component, $this->data);

      $this->component->print_html();
    }

  }
}

if ( ! class_exists('Requirements') ) {
  final class Requirements {

    /**
     * @var string Key for 'from_array' initilization for is_admin.
     */
    public const IS_ADMIN = 'is_admin';
    /**
     * @var string Key for 'from_array' initilization for is_wp_cli.
     */
    public const IS_WP_CLI = 'is_wp_cli';
    /**
     * @var string Key for 'from_array' initilization for is_multisite.
     */
    public const IS_MULTISITE = 'is_multisite';
    /**
     * @var string Key for 'from_array' initilization for is_not_multisite.
     */
    public const IS_NOT_MULTISITE = 'is_not_multisite';
    /**
     * @var string Key for 'from_array' initilization for can_be_production.
     */
    public const CAN_BE_PRODUCTION = 'can_be_production';
    /**
     * @var string Key for 'from_array' initilization for can_be_staging.
     */
    public const CAN_BE_STAGING = 'can_be_staging';
    /**
     * @var string Key for 'from_array' initilization for can_be_development.
     */
    public const CAN_BE_DEVELOPMENT = 'can_be_development';
    /**
     * @var string Key for 'from_array' initilization for capabilities.
     */
    public const CAPABILITIES = 'capabilities';


    /**
     * @var bool Whether user must be admin and able to manager network.
     */
    public $is_admin = \true;
    /**
     * @var bool Whether plugin must be loaded by WP CLI.
     */
    public $is_wp_cli = \false;
    /**
     * @var bool Whether site must be multisite install.
     */
    public $is_multisite = \false;
    /**
     * @var bool Whether site must be multisite install or not.
     */
    public $is_not_multisite = \false;
    /**
     * @var bool Whether the site can be in production environment.
     */
    public $can_be_production = \false;
    /**
     * @var bool Whether the site can be in staging environment.
     */
    public $can_be_staging = \false;
    /**
     * @var bool Whether the site can be in local development environment.
     */
    public $can_be_development = \false;

    /**
     * Capabilities array contains capabilities as strings and
     * arrays with capability string at index[0] and extra args at index[1].
     * @var mixed[] Additional WordPress capabilities required.
     * @see https://wordpress.org/support/article/roles-and-capabilities
     */
    public $capabilities = array();

    /**
     * @param array<string, mixed> $requirements Requirements to be initialized.
     */
    public function init_from_array( $requirements ) {
      if ( isset($requirements[self::IS_ADMIN]) ) {
        $this->is_admin = $requirements[self::IS_ADMIN];
      }
      if ( isset($requirements[self::IS_WP_CLI]) ) {
        $this->is_wp_cli = $requirements[self::IS_WP_CLI];
      }
      if ( isset($requirements[self::IS_MULTISITE]) ) {
        $this->is_multisite = $requirements[self::IS_MULTISITE];
      }
      if ( isset($requirements[self::IS_NOT_MULTISITE]) ) {
        $this->is_not_multisite = $requirements[self::IS_NOT_MULTISITE];
      }
      if ( isset($requirements[self::CAN_BE_PRODUCTION]) ) {
        $this->can_be_production = $requirements[self::CAN_BE_PRODUCTION];
      }
      if ( isset($requirements[self::CAN_BE_STAGING]) ) {
        $this->can_be_staging = $requirements[self::CAN_BE_STAGING];
      }
      if ( isset($requirements[self::CAN_BE_DEVELOPMENT]) ) {
        $this->can_be_development = $requirements[self::CAN_BE_DEVELOPMENT];
      }
      if ( isset($requirements[self::CAN_BE_DEVELOPMENT]) ) {
        $this->capabilities = $requirements[self::CAN_BE_DEVELOPMENT];
      }
    }

    /**
     * @return bool Whether the requirements match rights and environment or not.
     */
    public function is_allowed() {
      if ( $this->is_admin && ! current_user_can('administrator') ) {
        return false;
      }
      if ( $this->is_admin && is_multisite() && ! current_user_can('manage_network') ) {
        return false;
      }
      if ( $this->is_wp_cli && ! (defined('WP_CLI') && WP_CLI) ) {
        return false;
      }
      if ( $this->is_multisite && ! is_multisite() ) {
        return false;
      }
      if ( $this->is_not_multisite && is_multisite() ) {
        return false;
      }
      if ( ! $this->can_be_production && Helpers::is_production() ) {
        return false;
      }
      if ( ! $this->can_be_staging && Helpers::is_staging() ) {
        return false;
      }
      if ( ! $this->can_be_development && Helpers::is_development() ) {
        return false;
      }

      foreach ( $this->capabilities as $capability ) {
        $args = null;

        if ( is_array($capability) ) {
          $args = isset($capability[1]) ? $capability[1] : null;
          $capability = $capability[0];
        }

        if ( ! \current_user_can($capability, $args) ) {
          return false;
        }
      }

      return \true;
    }

  }
}
