<?php

namespace Seravo;

if ( ! defined('ABSPATH') ) {
  dies('Access denied!');
}

if ( ! class_exists('Toolbox') && current_user_can('administrator') ) {
  class Toolbox {
    public static function load() {
      add_action('admin_menu', array( __CLASS__, 'register_toolbox_pages' ));
    }

    public static function register_toolbox_pages() {
      $name = __('Toolbox', 'seravo');
      $icon = '/wp-content/mu-plugins/seravo-plugin/seravo_logo.png';

      add_menu_page(
        $name,
        $name,
        'manage_options',
        'toolbox_page',
        'Seravo/seravo_postboxes_page',
        $icon
      );

      add_submenu_page(
        'toolbox_page',
        __('Site Status', 'seravo'),
        __('Site Status', 'seravo'),
        'manage_options',
        'site_status_page',
        'Seravo\seravo_postboxes_page'
      );

      add_submenu_page(
        'toolbox_page',
        __('Upkeep', 'seravo'),
        __('Upkeep', 'seravo'),
        'manage_options',
        'upkeep_page',
        'Seravo\seravo_postboxes_page'
      );

      add_submenu_page(
        'toolbox_page',
        __('Logs', 'seravo'),
        __('Logs', 'seravo'),
        'manage_options',
        'logs_page',
        array( Logs::init(), 'render_tools_page' )
      );

      add_submenu_page(
        'toolbox_page',
        __('Security', 'seravo'),
        __('Security', 'seravo'),
        'manage_options',
        'security_page',
        'Seravo\seravo_postboxes_page'
      );

      add_submenu_page(
        'toolbox_page',
        __('Domains', 'seravo'),
        __('Domains', 'seravo'),
        'manage_options',
        'domains_page',
        array( Domains::init(), 'load_domains_page' )
      );

      add_submenu_page(
        'toolbox_page',
        __('Mails', 'seravo'),
        __('Mails', 'seravo'),
        'manage_options',
        'mails_page',
        'Seravo\seravo_postboxes_page'
      );

      add_submenu_page(
        'toolbox_page',
        __('Development', 'seravo'),
        __('Development', 'seravo'),
        'manage_options',
        'development_page',
        'Seravo\seravo_postboxes_page'
      );
    }
  }

  Toolbox::load();
}