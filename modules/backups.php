<?php
/*
 * Plugin name: Backups
 * Description: Enable users to list and create backups
 * Version: 1.0
 */

namespace Seravo;

use \Seravo\Postbox\Postboxes;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

require_once dirname(__FILE__) . '/../lib/backups-ajax.php';

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Backups') ) {
  class Backups {

    public static function load() {
      // Add AJAX endpoint for backups
      add_action('wp_ajax_seravo_backups', 'Seravo\seravo_ajax_backups');

      add_action('admin_enqueue_scripts', array( __CLASS__, 'register_backups_scripts' ));

      // TODO: check if this hook actually ever fires for mu-plugins
      register_activation_hook(__FILE__, array( __CLASS__, 'register_view_backups_capability' ));

      $page = new Toolpage('tools_page_backups_page');

      /**
       * Backup excludes postbox
       */
      $backup_excludes = new Postboxes\Postbox_Auto_Command('backup-excludes');
      $backup_excludes->set_title(__('Files Excluded from the Backups', 'seravo'));
      $backup_excludes->set_auto_command('cat /data/backups/exclude.filelist', 60);
      $backup_excludes->set_info_text(__('Below are the contents of <code>/data/backups/exclude.filelist</code>.', 'seravo'));
      $backup_excludes->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
      $page->register_postbox($backup_excludes);

      /**
       * Current Backups postbox
       */
      $backups_list = new Postboxes\Postbox_Auto_Command('backups-list');
      $backups_list->set_title(__('Current Backups', 'seravo'));
      $backups_list->set_auto_command('wp-backup-status 2>&1', 60);
      $backups_list->set_info_text(__('This list is produced by the command <code>wp-backup-status</code>.', 'seravo'));
      $backups_list->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
      $page->register_postbox($backups_list);

      $page->enable_ajax();
      $page->register_page();

      \Seravo\Postbox\seravo_add_raw_postbox(
        'backups-info',
        __('Backups', 'seravo'),
        array( __CLASS__, 'backups_info_postbox' ),
        'tools_page_backups_page',
        'normal'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'backups-create',
        __('Create a New Backup', 'seravo'),
        array( __CLASS__, 'backups_create_postbox' ),
        'tools_page_backups_page',
        'normal'
      );
    }

    /**
     * Register scripts
     *
     * @param string $page hook name
     */
    public static function register_backups_scripts( $page ) {
      wp_register_script('seravo_backups', plugin_dir_url(__DIR__) . '/js/backups.js', '', Helpers::seravo_plugin_version());
      wp_register_style('seravo_backups', plugin_dir_url(__DIR__) . '/style/backups.css', '', Helpers::seravo_plugin_version());

      if ( $page === 'tools_page_backups_page' ) {
        wp_enqueue_script('seravo_backups');
        wp_enqueue_style('seravo_backups');

        $loc_translation_backups = array(
          'ajaxurl'    => admin_url('admin-ajax.php'),
          'ajax_nonce' => wp_create_nonce('seravo_backups'),
          'no_entries' => __('No entries were found', 'seravo'),
        );
        wp_localize_script('seravo_backups', 'seravo_backups_loc', $loc_translation_backups);
      }

    }

    public static function backups_info_postbox() {
      ?>
      <p><?php _e('Backups are automatically created every night and preserved for 30 days. The data can be accessed on the server in under <code>/data/backups</code>.', 'seravo'); ?></p>
      <?php
    }

    public static function backups_create_postbox() {
      ?>
      <p><?php _e('You can also create backups manually by running <code>wp-backup</code> on the command line. We recommend that you get familiar with the command line option that is accessible to you via SSH. That way recovering a backup will be possible whether the WP Admin is accessible or not.', 'seravo'); ?></p>
      <p class="create_backup">
        <button id="create_backup_button" class="button-primary"><?php _e('Create a backup', 'seravo'); ?> </button>
        <div id="create_backup_loading"><img class="hidden" src="/wp-admin/images/spinner.gif"></div>
        <pre><div id="create_backup"></div></pre>
      </p>
      <?php
    }
  }

  Backups::load();
}
