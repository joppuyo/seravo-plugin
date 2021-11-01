<?php

namespace Seravo;

class FileSystem {

  /**
   * Fetch the full disk space usage, backups and logs excluded.
   *
   * Disk usage is stored in two transients. Transient 'disk_space_usage' stores the usage
   * for DISK_SPACE_CACHE_TIME seconds and 'disk_space_usage_last' for as long as possible.
   *
   * TODO: Rewrite comment
   * When 'disk_space_usage' expires, it's set again from 'disk_space_usage_last' and
   * 'disk_space_usage_last' is updated on background (with `$no_cache = true`). If even
   * 'disk_space_usage_last' doesn't exist (new site / deleted transients), it's loaded
   * immediately when `$force = true` or on background when `$force = false` (func return false).
   *
   * @param bool $blocking Whether to run blocking or on background when needed. Blocking might time out.
   * @param bool $no_cache Whether to force cache refresh. This will always set $blocking = true.
   * @return false|array<string, mixed> Data for disk usage and plan limit.
   */
  public static function get_disk_space_usage( $blocking = false, $no_cache = false ) {
    // Directories not counted against plan's quota but can be visible in the front end
    $exclude_dirs = array(
      '--exclude=/data/backups',
      '--exclude=/data/log',
      '--exclude=/data/slog',
    );

    $data_folder = \get_transient('disk_space_usage');

    if ( $data_folder === false || $no_cache ) {
      // Transient 'disk_space_usage' has expired, cache must be refreshed
      if ( $blocking || $no_cache ) {
        // Blocking or no-cache requested, run exec immediately
        $data_folder = array();
        $exec = Compatibility::exec('du -sb /data ' . \implode(' ', $exclude_dirs), $data_folder);
        if ( $exec === false || $data_folder === array() ) {
          // Couldn't get the disk usage
        }
      }
    }

    if ( $refresh_cache ) {

    }

    return false;
  }
    /*
    $data_folder = \get_transient('disk_space_usage');
    if ( $data_folder === false || $no_cache ) {
      // Disk space usage transient has expired
      $data_folder = \get_transient('disk_space_usage_last');
      if ( $data_folder === false || $no_cache ) {
        // Last known disk space usage transient doesn't exist either. This shouldn't happen unless object-cache
        // was emptied (if using one) or the site is new. Determine next action from $force.
        $data_folder = array();
        $exec = Compatibility::exec('du -sb /data ' . \implode(' ', $exclude_dirs), $data_folder);
        if ( $exec === false || $data_folder === array() ) {
          // Couldn't get the disk usage
          return array(
            'relative_usage' => 0.0,
            'disk_usage' => 0,
            'plan_limit' => 0,
          );
        }

        // Store the latest disk usage in a never-expiring transient
        set_transient('disk_space_usage_last', $data_folder, 0);
      } else {
        // Call this function in background with $no_cache = true to get the latest disk usage
        \Seravo\Shell::background_command("wp eval '\Seravo\DashboardWidgets::get_disk_space_usage(true);'");
      }

      // Use the last know disk space usage
      \set_transient('disk_space_usage', $data_folder, self::DISK_SPACE_CACHE_TIME);
    }

    $data_size = 0;
    if ( $data_folder !== array() ) {
      $data_size = \preg_split('/\s+/', $data_folder[0]);
      $data_size = $data_size !== false ? $data_size[0] : 0;
    }

    $plan_details = API::get_site_data();
    if ( \is_wp_error($plan_details) ) {
      $plan_disk_limit = 0;
    } else {
      $plan_disk_limit = $plan_details['plan']['disklimit']; // in GB
    }

    if ( $plan_disk_limit !== 0 && $data_size !== 0 ) {
      // Calculate the data size in MB
      $data_size_human = ($data_size / 1024) / 1024;
      $relative_disk_space_usage = $data_size_human / ($plan_disk_limit * 1000);
    } else {
      $relative_disk_space_usage = 0;
    }

    return array(
      'relative_usage' => $relative_disk_space_usage,
      'disk_usage' => $data_size,
      'plan_limit' => $plan_disk_limit,
    );
    */

}
