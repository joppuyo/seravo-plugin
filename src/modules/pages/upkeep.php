<?php

namespace Seravo\Page;

use \Seravo\Helpers;
use \Seravo\Shell;
use \Seravo\API;
use \Seravo\Compatibility;

use \Seravo\Ajax;
use \Seravo\Ajax\AjaxResponse;

use \Seravo\Postbox;
use \Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

/**
 * Class Upkeep
 *
 * Upkeep is a page for the page upkeep.
 */
class Upkeep extends Toolpage {

  /**
   * @var \Seravo\Page\Upkeep|null Instance of this page.
   */
  private static $instance;

  /**
   * Function for creating an instance of the page. This should be
   * used instead of 'new' as there can only be one instance at a time.
   * @return \Seravo\Page\Upkeep Instance of this page.
   */
  public static function load() {
    if ( self::$instance === null ) {
      self::$instance = new Upkeep();
    }

    return self::$instance;
  }

  /**
   * Constructor for Upkeep. Will be called on new instance.
   * Basic page details are given here.
   */
  public function __construct() {
    parent::__construct(
      __('Upkeep', 'seravo'),
      'tools_page_upkeep_page',
      'upkeep_page',
      'Seravo\Postbox\seravo_two_column_postboxes_page'
    );
  }

  /**
   * Will be called for page initialization. Includes scripts
   * and enables toolpage features needed for this page.
   */
  public function init_page() {
    self::init_postboxes($this);

    \add_action('admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ));
    \add_action('admin_post_toggle_seravo_updates', array( __CLASS__, 'seravo_admin_toggle_seravo_updates' ), 20);

    $this->enable_ajax();
  }

  /**
   * Will be called for setting requirements. The requirements
   * must be as strict as possible but as loose as the
   * postbox with the loosest requirements on the page.
   * @param \Seravo\Postbox\Requirements $requirements Instance to set requirements to.
   */
  public function set_requirements( Requirements $requirements ) {
    $requirements->can_be_production = \true;
    $requirements->can_be_staging = \true;
    $requirements->can_be_development = \true;
  }

  /**
   * Register scripts.
   * @param string $screen The current screen.
   * @return void
   */
  public static function enqueue_scripts( $screen ) {
    if ( $screen !== 'tools_page_upkeep_page' ) {
      return;
    }

    \wp_enqueue_script('seravo-updates-js', SERAVO_PLUGIN_URL . 'js/updates.js', array( 'jquery', 'seravo-common-js' ), Helpers::seravo_plugin_version());
    \wp_enqueue_script('seravo-screenshots-js', SERAVO_PLUGIN_URL . 'js/screenshots.js', array( 'jquery' ), Helpers::seravo_plugin_version());
    \wp_enqueue_style('seravo-upkeep-css', SERAVO_PLUGIN_URL . 'style/upkeep.css', array(), Helpers::seravo_plugin_version());

    $loc_translation = array(
      'email_fail' => __('There must be at least one contact email', 'seravo'),
    );
    \wp_localize_script('seravo-updates-js', 'seravo_upkeep_loc', $loc_translation);
  }

  /**
   * @param \Seravo\Postbox\Toolpage $page Toolpage to add postboxes to.
   * @return void
   */
  public static function init_postboxes( Toolpage $page ) {
    /**
     * Seravo Plugin Updater
     */
    $seravo_plugin_update = new Postbox\Postbox('seravo-plugin-updater');
    $seravo_plugin_update->set_title(__('Seravo Plugin Updater', 'seravo'));
    $seravo_plugin_update->set_requirements(
      array(
        Requirements::IS_SUPER_ADMIN => true,
        Requirements::CAN_BE_ANY_ENV => true,
      )
    );

    $update_button = new Ajax\SimpleForm('seravo-plugin-update');
    $update_button->set_button_text(__('Update plugin now', 'seravo'));
    $update_button->set_spinner_text(__('Updating Seravo Plugin...', 'seravo'));
    $update_button->set_ajax_func(array( __CLASS__, 'update_seravo_plugin' ));
    $seravo_plugin_update->add_ajax_handler($update_button);

    $seravo_plugin_update->set_data_func(array( __CLASS__, 'get_seravo_plugin_update' ), 0);
    $seravo_plugin_update->set_build_func(array( __CLASS__, 'build_seravo_plugin_update_postbox' ));
    $page->register_postbox($seravo_plugin_update);

    /**
     * PHP Version Tool
     */
    $php_version_tool = new Postbox\SimpleForm('change-php-version');

    // Init AJAX
    $php_compatibility = new Ajax\SimpleForm('check-php-compatibility');
    $php_compatibility->set_button_text(__('Check PHP compatibility', 'seravo'));
    $php_compatibility->set_spinner_text(__('Running PHP compatibility check. This may take up to tens of minutes.', 'seravo'));
    $php_compatibility->set_ajax_func(array( __CLASS__, 'run_php_compatibility' ));
    $php_version_tool->add_ajax_handler($php_compatibility);

    $php_version_tool->set_title(__('Change PHP Version', 'seravo'));
    $php_version_tool->set_build_form_func(array( __CLASS__, 'build_php_version_form' ));
    $php_version_tool->set_spinner_text(__('Activating... Please wait up to 30 seconds', 'seravo'));
    $php_version_tool->set_button_text(__('Change version', 'seravo'));
    $php_version_tool->set_ajax_func(array( __CLASS__, 'set_php_version' ));
    $php_version_tool->set_requirements(
      array(
        Requirements::IS_SUPER_ADMIN => true,
        Requirements::CAN_BE_ANY_ENV => true,
      )
    );
    $php_version_tool->set_build_func(array( __CLASS__, 'build_change_php_version_postbox' ));
    $page->register_postbox($php_version_tool);

    /**
     * Tests Status postbox
     */
    $tests_status = new Postbox\Postbox('tests-status');
    $tests_status->set_title(__('Tests Status', 'seravo'));
    $tests_status->set_data_func(array( __CLASS__, 'get_tests_status' ), 300);
    $tests_status->set_build_func(array( __CLASS__, 'build_tests_status' ));
    $tests_status->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $page->register_postbox($tests_status);

    /**
     * Update Status postbox
     */
    $update_status = new Postbox\Postbox('update-status');
    $update_status->set_title(__('Update Status', 'seravo'));
    $update_status->set_data_func(array( __CLASS__, 'get_update_status' ), 0);
    $update_status->set_build_func(array( __CLASS__, 'build_update_status' ));
    $update_status->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $page->register_postbox($update_status);

    /**
     * Changes Status postbox
     */
    $changes_status = new Postbox\FancyForm('backup-list-changes', 'side');
    $changes_status->set_title(__('Changes Status', 'seravo'));
    $changes_status->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $changes_status->set_ajax_func(array( __CLASS__, 'fetch_backup_list_changes' ));
    $changes_status->add_paragraph(__('This tool can be used to run command <code>wp-backup-list-changes-since</code> which finds folder and file changes in backup data since the given date. For example if you have started to have issues on your site, you can track down what folders or files have changed.', 'seravo'));
    $changes_status->add_paragraph(__('Backups are stored for 30 days which is also the maximum since offset.', 'seravo'));
    $changes_status->set_build_form_func(array( __CLASS__, 'build_backup_list_changes' ));
    $changes_status->set_button_text(__('Run', 'seravo'));
    $changes_status->set_title_text(__('Click "Run" to see changes', 'seravo'));
    $changes_status->set_spinner_text(__('Fetching changes...', 'seravo'));
    $page->register_postbox($changes_status);

    /**
     * Update Tests postbox
     */
    $update_tests = new Postbox\FancyForm('update-tests', 'side');
    $update_tests->set_title(__('Update tests', 'seravo'));
    $update_tests->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $update_tests->set_ajax_func(array( __CLASS__, 'run_update_tests' ));
    $update_tests->set_button_text(__('Run Tests', 'seravo'));
    $update_tests->set_spinner_text(__('Running rspec tests...', 'seravo'));
    $update_tests->set_title_text(__('Click "Run Tests" to run the Codeception tests', 'seravo'));
    $update_tests->add_paragraph(__('Here you can test the core functionality of your WordPress installation. Same results can be achieved via command line by running <code>wp-test</code> there. For further information, please refer to <a href="https://seravo.com/docs/tests/ng-integration-tests/" target="_BLANK"> Seravo Developer Documentation</a>.', 'seravo'));
    $page->register_postbox($update_tests);

    /**
     * Screenshots postbox
     */
    $screenshots = new Postbox\Postbox('screenshots', 'side');
    $screenshots->set_title(__('Screenshots', 'seravo'));
    $screenshots->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $screenshots->set_build_func(array( __CLASS__, 'build_screenshots_postbox' ));
    $screenshots->set_data_func(array( __CLASS__, 'get_screenshots' ), 300);
    $page->register_postbox($screenshots);

    /**
     * Seravo Updates postbox
     */
    $seravo_updates = new Postbox\Postbox('updates');
    $seravo_updates->set_title(__('Seravo Updates', 'seravo'));
    $seravo_updates->set_requirements(
      array(
        Requirements::IS_SUPER_ADMIN => true,
        Requirements::CAN_BE_PRODUCTION => true,
      )
    );
    $seravo_updates->set_data_func(array( __CLASS__, 'get_seravo_updates_data' ));
    $seravo_updates->set_build_func(array( __CLASS__, 'build_seravo_updates' ));
    $page->register_postbox($seravo_updates);
  }

  /**
   * Builder function for tests status postbox.
   * @param \Seravo\Postbox\Component $base    Base element for the postbox.
   * @param \Seravo\Postbox\Postbox   $postbox The current postbox.
   * @param mixed                     $data    Data returned by data function.
   * @return void
   */
  public static function build_update_status( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( isset($data['error']) ) {
      $base->add_child(Template::error_paragraph($data['error']));
    } else {
      if ( isset($data['over_month_warning']) ) {
        $notice = new Component();
        $notice->add_children(
          array(
            Template::paragraph($data['over_month_warning']),
            isset($data['latest_update_log']) ? Template::paragraph(__('Latest update.log details:', 'seravo'), 'bold') : null,
            isset($data['latest_update_log']) ? Template::paragraph($data['latest_update_log']) : null,
            isset($data['no_latest_log']) ? Template::paragraph($data['no_latest_log'], 'bold') : null,
          )
        );
        $base->add_child(Template::nag_notice($notice, 'notice-error', true));
      }
      $base->add_child(Template::paragraph(__('Here you can see information about the Seravo updates on your site. For full details about updates see <a href="tools.php?page=logs_page&logfile=update.log" target="_blank">update.log</a>.', 'seravo')));
      $base->add_children(
        array(
          isset($data['latest_successful_update']) ? Template::paragraph(__('Latest successful full update:', 'seravo') . ' ' . $data['latest_successful_update']) : null,
          Template::section_title(__('Last 5 partial or attempted updates', 'seravo')),
          isset($data['update_attempts']) ? Template::list_view($data['update_attempts']) : null,
        )
      );
    }
  }

  /**
   * Data function for update status postbox
   * @return array<string,array|string>
   */
  public static function get_update_status() {
    $site_info = \Seravo\API\Site::get_site();
    $data = array();

    if ( \is_wp_error($site_info) ) {
      \error_log($site_info->get_error_message());
      $data['error'] = __('An API error occured. Please try again later', 'seravo');
      return $data;
    }

    // Calculate the approx. amount of days since last succesful FULL update
    // 86400 is used to get days out of seconds (60*60*24)
    $interval = 0;
    $now = \strtotime(\date('Y-m-d'));
    $last = \strtotime($site_info['update_success']);
    if ( $now !== false && $last !== false ) {
      $interval = \round(($now - $last) / 86400);
    }

    // Check if update.log exists and if not fetch the name of the rotated log instead
    // for linking to correct log on the logs page as well as fetching the failed lines
    // from the log if needed in the update notification
    $update_logs_arr = \glob('/data/log/update.log');
    if ( $update_logs_arr === false || $update_logs_arr === array() ) {
      $update_logs = \glob('/data/log/update.log-*');
      if ( $update_logs === false ) {
        $update_logs_arr = array();
      } else {
        $update_logs_arr = \preg_grep('/(\d){8}$/', $update_logs);
        if ( $update_logs_arr === false ) {
          $update_logs_arr = array();
        }
      }
    }

    if ( $site_info['seravo_updates'] === true && $interval >= 30 ) {
      if ( $update_logs_arr === array() ) {
        $data['no_latest_log'] = __('Unable to fetch the latest update log.', 'seravo');
      } else {
        // Get last item from logs array
        $update_log_contents = array();
        $update_log_output = '';
        $update_log_fp = \fopen(\end($update_logs_arr), 'r');
        if ( $update_log_fp != false ) {
          $index = 0;
          while ( ! \feof($update_log_fp) ) {
            $line = \fgets($update_log_fp);
            if ( $line === false ) {
              continue;
            }

            // Strip timestamps from log lines
            // Show only lines with 'Updates failed!'
            $buffer = Compatibility::substr($line, 28);
            if ( $buffer === false ) {
              continue;
            }

            if ( \substr($buffer, 0, 15) === 'Updates failed!' ) {
              $update_log_contents[ $index ] = $buffer;
              ++$index;
            }
          }
          \fclose($update_log_fp);
          $update_log_output = \implode('<br>', $update_log_contents);
        }

        $data['over_month_warning'] = __('<b>Updates notice:</b> Last successful full site update was over a month ago. A developer should take a look at the <a href="tools.php?page=logs_page&logfile=update.log" target="_blank">update.log</a> and fix the issue preventing the site from updating.', 'seravo');

        if ( $update_log_contents !== array() ) {
          $data['latest_update_log'] = $update_log_output;
        }
      }
    }
    $date_format = \get_option('date_format', 'Y-m-d');
    $data['latest_successful_update'] = \date_i18n($date_format, \strtotime($site_info['update_success']));

    \exec('zgrep -h -e "Started updates for" -e "Installing urgent security" /data/log/update.log* | sort -r', $output);
    // Only match the date, hours and minutes are irrelevant
    $attempts = \preg_match_all('/\d{4}-\d{2}-\d{2}/', \implode(' ', $output), $matches);
    if ( $attempts !== false && $attempts > 0 ) {
      $updates = \array_slice($matches[0], 0, 5);
      $update_attempts = array();
      // Format the dates
      foreach ( $updates as $update ) {
        $update_attempts[] = \date_i18n($date_format, \strtotime($update));
      }
      $data['update_attempts'] = $update_attempts;
    } else {
      $data['update_attempts'] = __('There are no update attempts yet', 'seravo');
    }

    return $data;
  }

  /**
   * Builder function for tests status postbox.
   * @param \Seravo\Postbox\Component $base    Base element for the postbox.
   * @param \Seravo\Postbox\Postbox   $postbox The current postbox.
   * @param mixed                     $data    Data returned by data function.
   * @return void
   */
  public static function build_tests_status( Component $base, Postbox\Postbox $postbox, $data ) {
    $base->add_children(
      array(
        isset($data['status']) ? Template::paragraph($data['status'])->set_wrapper('<b>', '</b>') : null,
        isset($data['success']) ? Template::success_failure($data['success']) : null,
        isset($data['msg']) ? Template::paragraph($data['msg']) : null,
      )
    );
  }

  /**
   * Data function for tests status postbox.
   * @return array<string,mixed>
   */
  public static function get_tests_status() {
    \exec('zgrep -h -A 1 "Running initial tests in production" /data/log/update.log-* /data/log/update.log | tail -n 1 | cut -d " " -f 4-8', $test_status);
    $data = array();

    if ( \count($test_status) === 0 ) {
      $data['status'] = __('Unknown!', 'seravo');
      $data['msg'] = __("No tests have been ran yet. They will be ran during upcoming updates. You can try beforehand if the tests will be succesful or not with the 'Update tests' feature below.", 'seravo');
    } elseif ( $test_status[0] == 'Success! Initial tests have passed.' ) {
      $data['success'] = true;
      $data['msg'] = __('Site baseline tests have passed and updates can run normally.', 'seravo');
    } else {
      $data['success'] = false;
      $data['msg'] = __('Site baseline tests are failing and needs to be fixed before further updates are run.', 'seravo');
    }

    return $data;
  }

  /**
   * Build Change PHP version postbox.
   * @param \Seravo\Postbox\Component $base    Postbox base component.
   * @param \Seravo\Postbox\Postbox   $postbox Current postbox to build for.
   * @return void
   */
  public static function build_change_php_version_postbox( Component $base, Postbox\Postbox $postbox ) {
    $base->add_child(Template::section_title(__('Check PHP compatibility', 'seravo')));
    $base->add_child(Template::paragraph(__('With this tool you can run command <code>wp-php-compatibility-check</code>. Check <a href="tools.php?page=logs_page&logfile=php-compatibility.log" target="_blank">compatibility scan results.</a>', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('check-php-compatibility')->get_component());
    $base->add_child(Template::section_title(__('Change PHP version', 'seravo')));
    $base->add_child(Template::paragraph(__('Latest version is recommended if all plugins and theme support it. See also <a target="_blank" href="https://help.seravo.com/article/41-set-your-site-to-use-newest-php-version">more information on PHP version upgrades</a>.', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('change-php-version')->get_component());
  }

  /**
   * Build function for Change PHP Version postbox radiobuttons.
   * @param \Seravo\Postbox\Component $base Base component to add child components.
   * @return void
   */
  public static function build_php_version_form( Component $base ) {
    $data = array(
      '7.2' => array(
        'value' => '7.2',
        'name'  => 'PHP 7.2 (EOL 30 Nov 2020)',
        'checked' => false,
      ),
      '7.3' => array(
        'value' => '7.3',
        'name'  => 'PHP 7.3 (EOL 6 Dec 2021)',
        'checked' => false,
      ),
      '7.4' => array(
        'value' => '7.4',
        'name'  => 'PHP 7.4',
        'checked' => false,
      ),
      '8.0' => array(
        'value' => '8.0',
        'name'  => 'PHP 8.0',
        'checked' => false,
      ),
    );

    $current_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $data[$current_version]['checked'] = true;

      foreach ( $data as $version ) {
      $base->add_child(Template::radio_button('php-version', $version['value'], $version['name'], $version['checked']));
    }
  }

  /**
   * Run Check PHP compatibility AJAX call.
   * @return \Seravo\Ajax\AjaxResponse|mixed
   */
  public static function run_php_compatibility() {
    $polling = Ajax\AjaxHandler::check_polling();

    if ( $polling === true ) {
      $compatibility_run = '<hr>' . Template::paragraph(__('PHP compatibility check has been run. See full details on <a href="tools.php?page=logs_page&logfile=php-compatibility.log" target="_blank">compatibility scan results.</a>', 'seravo'))->to_html() . '<hr>';
      return AjaxResponse::response_with_output($compatibility_run);
    }

    if ( $polling === false ) {
      $command = 'wp-php-compatibility-check';
      $pid = Shell::background_command($command);

      if ( $pid === false ) {
        return Ajax\AjaxResponse::exception_response();
      }

      return Ajax\AjaxResponse::require_polling_response($pid);
    }

    return $polling;
  }

  /**
   * Data function for Change PHP Version AJAX.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function set_php_version() {
    $polling = Ajax\AjaxHandler::check_polling();
    $php_version = isset($_REQUEST['php-version']) ? \sanitize_text_field($_REQUEST['php-version']) : '';
    $current_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $php_version_array = array(
      '7.2' => '7.2',
      '7.3' => '7.3',
      '7.4' => '7.4',
      '8.0' => '8.0',
    );

    if ( $polling === true ) {
      $successful_change = Template::success_failure(true)->to_html() .
      Template::paragraph(__('PHP version has been changed succesfully! Please check <a href="tools.php?page=logs_page&logfile=php-error.log" target="_blank">php-error.log</a> for regressions.', 'seravo'))->to_html() . '<hr>';
      return AjaxResponse::response_with_output($successful_change);
    }

    if ( $polling === false ) {
      if ( \array_key_exists($php_version, $php_version_array) ) {

        if ( $php_version === $current_version ) {
          $already_in_use = '<hr>' . Template::error_paragraph(__('The selected PHP version is already in use.', 'seravo'))->to_html() . '<hr>';
          return AjaxResponse::response_with_output($already_in_use);
        }
        \file_put_contents('/data/wordpress/nginx/php.conf', 'set $mode php' . $php_version_array[$php_version] . ';' . PHP_EOL);
        // NOTE! The exec below must end with '&' so that subprocess is sent to the
        // background and the rest of the PHP execution continues. Otherwise the Nginx
        // restart will kill this PHP file, and when this PHP files dies, the Nginx
        // restart will not complete, leaving the server state broken so it can only
        // recover if wp-restart-nginx is run manually.
        \exec('echo "--> Setting to mode ' . $php_version_array[$php_version] . '" >> /data/log/php-version-change.log');
        //exec('wp-restart-nginx >> /data/log/php-version-change.log 2>&1 &');
        $restart_nginx = 'wp-restart-nginx >> /data/log/php-version-change.log 2>&1 &';
        $pid = Shell::background_command($restart_nginx);

        if ( $pid === false ) {
          return Ajax\AjaxResponse::exception_response();
        }

        if ( \is_executable('/usr/local/bin/s-git-commit') && \file_exists('/data/wordpress/.git') ) {
          \exec('cd /data/wordpress/ && git add nginx/*.conf && /usr/local/bin/s-git-commit -m "Set new PHP version" && cd /data/wordpress/htdocs/wordpress/wp-admin');
        }

        return Ajax\AjaxResponse::require_polling_response($pid);
      }
      return Ajax\AjaxResponse::invalid_request_response();
    }

    return $polling;
  }

  /**
   * Build Seravo Plugin Update postbox.
   * @param \Seravo\Postbox\Component $base    Postbox base component.
   * @param \Seravo\Postbox\Postbox   $postbox Current postbox to build for.
   * @param mixed                     $data    Data returned by data function.
   * @return void
   */
  public static function build_seravo_plugin_update_postbox( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( ! isset($data['current_version']) || ! isset($data['upstream_version']) || $data['upstream_version'] === false ) {
      $base->add_child(Template::error_paragraph(__('No upstream or current Seravo Plugin version available, please try again later', 'seravo')));
      return;
    }

    $base->add_child(Template::paragraph(__('Seravo automatically updates your site and the Seravo Plugin as well. If you want to immediately update to the latest Seravo Plugin version, you can do it here.', 'seravo')));
    $base->add_child(Template::paragraph(__('Current version: ', 'seravo') . '<b>' . $data['current_version'] . '</b>'));
    $base->add_child(Template::paragraph(__('Upstream version: ', 'seravo') . '<b>' . $data['upstream_version'] . '</b>'));

    if ( $data['current_version'] == $data['upstream_version'] ) {
      $base->add_child(Template::paragraph(__('Seravo Plugin installation is up to date.', 'seravo'), 'success bold'));
    } else {
      $base->add_child(Template::paragraph(__('There is a new version available', 'seravo'), 'warning bold'));
      $base->add_child($postbox->get_ajax_handler('seravo-plugin-update')->get_component());
    }
  }

  /**
   * Fetch data for Seravo Plugin Update postbox.
   * @return array<string, mixed>
   */
  public static function get_seravo_plugin_update() {
    $data = array();

    $data['current_version'] = Helpers::seravo_plugin_version();

    $upstream_version = \get_transient('seravo_plugin_upstream_version');
    if ( $upstream_version === false || $upstream_version === '' ) {
      $upstream_version = Compatibility::exec('curl -s https://api.github.com/repos/seravo/seravo-plugin/tags | grep "name" -m 1 | awk \'{gsub("\"","")}; {gsub(",","")}; {print $2}\'');
      if ( $upstream_version !== false ) {
        \set_transient('seravo_plugin_upstream_version', $upstream_version, 10800);
      }
    }

    $data['upstream_version'] = $upstream_version;

    return $data;
  }

  /**
   * AJAX function for updating Seravo Plugin installation.
   * @return Ajax\AjaxResponse
   */
  public static function update_seravo_plugin() {
    $cmd = Compatibility::exec('wp-seravo-plugin-update', $output, $result_code);

    if ( $cmd === false || $result_code !== 0 ) {
      \error_log('### Seravo Plugin installation experienced an error!');
      \error_log('### Please report this on GitHub (https://github.com/Seravo/seravo-plugin) with following:');
      foreach ( $output as $line ) {
        \error_log($line);
      }

      return Ajax\AjaxResponse::command_error_response('wp-seravo-plugin-update', $result_code);
    }

    return AjaxResponse::response_with_output(true, 'refresh');
  }

  /**
   * Fetch the site data from API
   * @param Component       $base    Base of the postbox that is built.
   * @param Postbox\Postbox $postbox Postbox that is built.
   * @param array<mixed>    $data    Data for building.
   * @return void
   */
  public static function build_seravo_updates( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( isset($data['error']) ) {
      $base->add_child(Template::error_paragraph($data['error']));
      return;
    }
    $base->add_child(Template::section_title(__('Opt-out from updates by Seravo', 'seravo')));
    $base->add_child(Template::paragraph(__("The Seravo upkeep service includes core and plugin updates to your WordPress site, keeping your site current with security patches and frequent tested updates to both the WordPress core and plugins. If you want full control of updates to yourself, you should opt out from Seravo's updates by unchecking the checkbox below.", 'seravo')));
    $updates_form = new Component('', '<form name="seravo-updates-form" action="' . \esc_url(\admin_url('admin-post.php')) . '" method="post">', '</form>');
    $updates_form->add_child(Component::from_raw(\wp_nonce_field('seravo-updates-nonce')));
    // Seravo Updates toggle
    $updates_form->add_child(Component::from_raw('<input type="hidden" name="action" value="toggle_seravo_updates">'));
    $updates_form->add_child(Template::checkbox_with_text('seravo-updates', __('Seravo updates enabled', 'seravo'), $data['seravo_updates_on']));
    $updates_form->add_child(Template::paragraph('<hr>'));
    // Slack webhook
    $updates_form->add_child(Template::section_title(__('Update Notifications with a Slack Webhook', 'seravo')));
    $updates_form->add_child(Template::paragraph(__('By defining a Slack webhook address below, Seravo can send you notifications about every update attempt, whether successful or not, to the Slack channel you have defined in your webhook. <a href="https://api.slack.com/incoming-webhooks" target="_BLANK">Read more about webhooks</a>.', 'seravo')));
    $updates_form->add_child(
      Template::side_by_side(
        Component::from_raw('<input class="slack-webhook-input" name="slack-webhook" type="url" size="30" placeholder="https://hooks.slack.com/services/..." value="' . $data['slack_webhook'] . '">'),
        Component::from_raw('<button type="button" class="slack-webhook-test button">' . __('Send a Test Notification', 'seravo') . '</button>')
      )
    );
    $updates_form->add_child(Template::paragraph('<hr>'));
    // Technical contacts
    $updates_form->add_child(Template::section_title(__('Contacts', 'seravo'), 'seravo-contacts', 'contacts'));
    $updates_form->add_child(Template::paragraph(__('Seravo may use the email addresses defined here to send automatic notifications about technical problems with you site. Remember to use a properly formatted email address.', 'seravo')));
    $updates_form->add_child(
      Template::side_by_side(
        Component::from_raw('<input class="technical-contacts-input" type="email" multiple size="30" placeholder="' . __('example@example.com', 'seravo') . '" value="" data-emails="' . \htmlspecialchars($data['contact_emails']) . '">'),
        Component::from_raw('<button type="button" class="technical-contacts-add button">' . __('Add', 'seravo') . '</button>')
      )
    );
    $updates_form->add_child(Component::from_raw('<span class="technical-contacts-error">' . __('Email must be formatted as name@domain.com', 'seravo') . '</span>'));
    $updates_form->add_child(Component::from_raw('<input name="technical-contacts" type="hidden"><div class="technical-contacts-buttons"></div><br>'));
    $updates_form->add_child(Component::from_raw('<input type="submit" id="save-settings-button" class="button button-primary" value="' . __('Save settings', 'seravo') . '">'));
    $updates_form->add_child(Template::paragraph('<small>' . __('P.S. Subscribe to our <a href="https://seravo.com/newsletter-for-wordpress-developers/" target="_blank">Newsletter for WordPress Developers</a> to get up-to-date information about our new features.', 'seravo') . '</small>'));
    $base->add_child($updates_form);
  }

  /**
   * Data func for Seravo Updates postbox.
   * @return array<mixed> Data containing the slack webhook, updates on/off status etc.
   */
  public static function get_seravo_updates_data() {
    $data = array();
    $site_info = \Seravo\API\Site::get_site();
    if ( \is_wp_error($site_info) ) {
      \error_log($site_info->get_error_message());
      $data['error'] = __('An API error occured. Please try again later', 'seravo');
      return $data;
    }

    $data['seravo_updates_on'] = $site_info['seravo_updates'] === true;
    $data['slack_webhook'] = '';

    // Check that webhooks really exist
    if ( isset($site_info['notification_webhooks']) && (isset($site_info['notification_webhooks'][0]['url']) &&
    $site_info['notification_webhooks'][0]['type'] === 'slack') ) {
      $data['slack_webhook'] = $site_info['notification_webhooks'][0]['url'];
    }

    $contact_emails = array();
    if ( isset($site_info['contact_emails']) ) {
      $contact_emails = $site_info['contact_emails'];
    }

    $contact_emails = \json_encode($contact_emails);
    if ( $contact_emails === false ) {
      $contact_emails = '[]';
    }

    $data['contact_emails'] = $contact_emails;

    return $data;
  }

  /**
   * @return void
   */
  public static function build_backup_list_changes( Component $base ) {
    $base->add_child(Template::datetime_picker(__('Choose a since date', 'seravo'), 'datepicker', \date('Y-m-d', \strtotime('-30 days')), \date('Y-m-d')));
    $base->add_child(Component::from_raw('<br>'));
  }

  /**
   * Fetch 2 days offset date
   * @return array<int,string> With formatted date and message.
   */
  public static function get_offset_date() {
    $datenow = \getdate();
    $y = $datenow['year'];
    $m = $datenow['mon'];

    if ( $datenow['mday'] >= 3 ) {
      $d = $datenow['mday'] - 2;
      $message = __('Invalid date, using 2 days offset <br><br>', 'seravo');
    } else {
      // Show since the month beginning
      $d = 1;
      $message = __('Invalid date, showing since month beginning <br><br>', 'seravo');
    }
    $date = $y . '-' . $m . '-' . $d;

    return array( $date, $message );
  }

  /**
   * AJAX function for backup list changes postbox.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function fetch_backup_list_changes() {
    $date = isset($_REQUEST['datepicker']) ? $_REQUEST['datepicker'] : '';
    $message = '';

    if ( $date === '' ) {
      $offset_date = self::get_offset_date();
      $date = $offset_date[0];
      $message = $offset_date[1];
    }

    // Check whether the date is a proper date or not
    try {
      $formal_date = new \DateTime($date);
      unset($formal_date);
    } catch ( \Exception $exception ) {
      $offset_date = self::get_offset_date();
      $date = $offset_date[0];
      $message = $offset_date[1];
    }

    $cmd = 'wp-backup-list-changes-since ' . $date;
    $lines_affected = Compatibility::exec($cmd . ' | wc -l');

    if ( $lines_affected !== false ) {
      $message .= $lines_affected . ' ' . __('rows affected', 'seravo');
    }
    $color = Ajax\FancyForm::STATUS_GREEN;
    \exec($cmd, $output);

    return Ajax\FancyForm::get_response('<pre>' . \implode("\n", $output) . '</pre>', $message, $color);
  }

  /**
   * Build function for screenshots postbox.
   * @param \Seravo\Postbox\Component $base    The base component of the postbox.
   * @param \Seravo\Postbox\Postbox   $postbox The postbox to add components / elements.
   * @param mixed[]                   $data    Data returned by data function.
   * @return void
   */
  public static function build_screenshots_postbox( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( ! isset($data['showing']) || $data['showing'] === 0 ) {
      $base->add_child(Template::error_paragraph(__('No screenshots found. They will become available during the next attempted update.', 'seravo')));
    } else {
      $base->add_child($data['screenshots_table']);
    }
  }

  /**
   * Data function for screenshots postbox.
   * @return mixed[] Data component containing screenshots and the count.
   */
  public static function get_screenshots() {
    $screenshots = \glob('/data/reports/tests/debug/*.png');
    if ( $screenshots === false ) {
      $screenshots = array();
    }

    $screenshot_rows = array();
    $data = array();

    // Shows a comparison of any and all image pair of *.png and *.shadow.png found.
    if ( \count($screenshots) > 3 ) {

      foreach ( $screenshots as $screenshot ) {
        // Skip *.shadow.png files from this loop
        if ( \strpos($screenshot, '.shadow.png') !== false || \strpos($screenshot, '.diff.png') !== false ) {
          continue;
        }

        $name = Compatibility::substr(\basename($screenshot), 0, -4);

        if ( $name === false ) {
          continue;
        }
        // Check whether the *.shadow.png exists in the set
        // Do not show the comparison if both images are not found.
        $exists_shadow = false;
        foreach ( $screenshots as $screenshotshadow ) {
          // Increment over the known images. Stop when match found
          if ( \strpos($screenshotshadow, $name . '.shadow.png') !== false ) {
            $exists_shadow = true;
            break;
          }
        }
        // Only shot the comparison if both images are available
        if ( ! $exists_shadow ) {
          continue;
        }

        $diff = 0.0;
        $screenshot_name = Compatibility::substr($screenshot, 0, -4);

        if ( $screenshot_name === false ) {
          continue;
        }
        $diff_txt = \file_get_contents($screenshot_name . '.diff.txt');
        if ( $diff_txt !== false && \preg_match('/Total: ([0-9.]+)/', $diff_txt, $matches) === 1 ) {
          $diff = (float) $matches[1];
        }

        $screenshot_element = '<hr class="seravo-updates-hr">' . Template::link($name, '?x-accel-redirect&screenshot=' . $name . '.diff.png', $name, 'diff-img-title')->to_html() . '<span';
        // Make the difference number stand out if it is non-zero
        if ( $diff > 0.011 ) {
          $screenshot_element .= ' style="background-color: yellow; color: red;"';
        }
        $screenshot_element .= '> ' . \round($diff * 100, 2) . ' %</span>';
        $screenshot_element .= self::seravo_admin_image_comparison_slider(
          array(
            'difference' => $diff,
            'img_right'  => "?x-accel-redirect&screenshot={$name}.shadow.png",
            'img_left'   => "?x-accel-redirect&screenshot={$name}.png",
          )
        );
        $screenshot_rows[] = array( $screenshot_element );
      }
      $data['screenshots_table'] = Template::table_view('seravo-screenshots', 'screenshots-title', 'screenshots-element', array( __('The Difference', 'seravo') ), $screenshot_rows);
    }
    $data['showing'] = \count($screenshot_rows);

    return $data;
  }

  /**
   * Helper function for screenshots construction.
   * @param array{'difference':float,'img_right':string,'img_left':string} $atts Attributes containing comparison images and their difference.
   * @return string Image element as a string.
   */
  public static function seravo_admin_image_comparison_slider( $atts ) {
    $knob_style = \floatval($atts['difference']) > 0.011 ? ' difference' : '';

    return '<div class="ba-slider' . $knob_style . '">
      <img src="' . $atts['img_right'] . '">
      <div class="ba-text-block" style="background-color: red; color: white;">' . __('Update Attempt', 'seravo') . '</div>
      <div class="ba-resize">
        <img src="' . $atts['img_left'] . '">
        <div class="ba-text-block" style="background-color: green; color: white;">' .
        __('Current State', 'seravo') .
        '</div>
        </div>
      <span class="ba-handle"></span>
    </div>';
  }

  /**
   * Toggle Seravo Updates postbox settings
   * @return no-return
   */
  public static function seravo_admin_toggle_seravo_updates() {
    \check_admin_referer('seravo-updates-nonce');

    $seravo_updates = isset($_POST['seravo-updates']) && $_POST['seravo-updates'] === 'on' ? 'true' : 'false';
    $data = array( 'seravo_updates' => $seravo_updates );

    // Webhooks is an anonymous array of named arrays with type/url pairs
    $data['notification_webhooks'] = array(
      array(
        'type' => 'slack',
        'url'  => $_POST['slack-webhook'],
      ),
    );

    // Handle site technical contact email addresses
    if ( isset($_POST['technical-contacts']) ) {
      $validated_addresses = array();

      // There must be at least one contact email
      if ( isset($_POST['technical-contacts']) && $_POST['technical-contacts'] !== '' ) {

        // Only unique emails are valid
        $contact_addresses = \array_unique(\explode(',', $_POST['technical-contacts']));

        // Perform email validation before making API request
        foreach ( $contact_addresses as $contact_address ) {
          $address = \trim($contact_address);

          if ( $address !== '' && \filter_var($address, FILTER_VALIDATE_EMAIL) !== false ) {
            $validated_addresses[] = $address;
          }
        }
      }

      // Only update addresses if any valid ones were found
      if ( $validated_addresses !== array() ) {
        $data['contact_emails'] = $validated_addresses;
      }
    }

    $response = API::update_site_data($data);
    if ( \is_wp_error($response) ) {
      die($response->get_error_message());
    }

    \wp_redirect(\admin_url('tools.php?page=upkeep_page&settings-updated=true'));
    die();
  }

  /**
   * AJAX function for Update tests postbox
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function run_update_tests() {
    $retval = null;
    $output = array();
    \exec('wp-test', $output, $retval);

    if ( $output === array() ) {
      return Ajax\AjaxResponse::command_error_response('wp-test', $retval);
    }

    $message = __('At least one of the tests failed.', 'seravo');
    $status_color = Ajax\FancyForm::STATUS_RED;

    $oks = \preg_grep('/OK \(/i', $output);
    if ( $oks !== false && \count($oks) >= 1 && $retval === 0 ) {
      // Success
      $message = __('Tests were run without any errors!', 'seravo');
      $status_color = Ajax\FancyForm::STATUS_GREEN;
    }

    // Format the output
    $pattern = '/\x1b\[[0-9;]*m/';
    $output = \preg_replace($pattern, '', $output);

    return Ajax\FancyForm::get_response('<pre>' . \implode("\n", $output) . '</pre>', $message, $status_color);
  }
}
