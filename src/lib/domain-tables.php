<?php

namespace Seravo;

// Deny direct access to this file
if ( ! \defined('ABSPATH') ) {
  die('Access denied!');
}

class Seravo_Domains_List_Table extends WP_List_Table {

  public function __construct() {
    global $status, $page;

    // Set parent defaults
    parent::__construct(
      array(
        'singular' => 'domain',
        'plural'   => 'domains',
        'ajax'     => true,
        'screen'   => 'seravo-domains',
      )
    );

  }

  /**
   * @return mixed|string|void
   */
  protected function column_default( $item, $column_name ) {
    switch ( $column_name ) {
      case 'domain':
      case 'expires':
      case 'dns':
      case 'management':
        return $item[ $column_name ];
      default:
        return \print_r($item, true); // Show the whole array for troubleshooting purposes
    }
  }

  /**
   * @return string
   */
  public function column_domain( $item ) {

    $actions = array();

    $page = empty($_REQUEST['page']) ? 'domains_page' : $_REQUEST['page'];
    $paged_str = empty($_REQUEST['paged']) ? '' : '&paged=' . $_REQUEST['paged'];

    $action_request = '<a href="?page=' . $page . '&domain=' . $item['domain'] . $paged_str . '&action=%s">%s</a>';
    $action_disabled = '<a class="action-link-disabled" title="%s">%s</a>';

    $primary_str = '';
    if ( ! empty($item['primary']) ) {
      if ( $item['primary'] === \getenv('CONTAINER') ) {
        $primary_str = __('Primary', 'seravo');
      } else {
        $primary_str = __('Primary', 'seravo') . '&nbsp(Staging&nbsp' . \explode('_', $item['primary'])[1] . ')';
      }
    }

    if ( $item['subdomain'] ) {
        $actions['view'] = \sprintf($action_disabled, __("Subdomains don't have their own zone.", 'seravo'), __('View', 'seravo'));
        $actions['edit'] = \sprintf($action_disabled, __("Subdomains don't have their own zone.", 'seravo'), __('Edit', 'seravo'));
    } elseif ( $item['management'] === 'Seravo' ) {
        $actions['view'] = \sprintf($action_request, 'view', __('View', 'seravo'));
        if ( \get_option('seravo-domain-edit') === 'disabled' ) {
        $actions['edit'] = \sprintf($action_disabled, __('DNS editing is disabled for this site.', 'seravo'), __('Edit', 'seravo'));
        } else {
        $actions['edit'] = \sprintf($action_request, 'edit', __('Edit', 'seravo'));
        }
    } elseif ( $item['management'] === __('Customer', 'seravo') ) {   // Nasty check but domains is waiting rewrite
      $actions['view'] = \sprintf($action_request, 'sniff', __('View', 'seravo'));
      $actions['edit'] = \sprintf($action_disabled, __('DNS not managed by Seravo.', 'seravo'), __('Edit', 'seravo'));
    } else {
      $actions['view'] = \sprintf($action_disabled, __("This domain doesn't have a zone.", 'seravo'), __('View', 'seravo'));
      $actions['edit'] = \sprintf($action_disabled, __("This domain doesn't have a zone.", 'seravo'), __('Edit', 'seravo'));
    }

    if ( empty($item['primary']) ) {
      $actions['primary'] = \sprintf($action_request, 'primary', __('Make Primary (experimental)', 'seravo'));

    } else {
      $actions['primary'] = \sprintf($action_disabled, __('This domain is already a primary domain.', 'seravo'), __('Make Primary', 'seravo'));
    }

    return \sprintf(
      '<p class="row-title">%1$s</p><small>%2$s</small> %3$s',
      /*$1%s*/ $item['domain'],
      /*$2%s*/ $primary_str,
      /*$3%s*/ $this->row_actions($actions)
    );

  }

  /**
   * @return string|void
   */
  public function column_expires( $item ) {
    $expires = $item['expires'];
    if ( ! empty($expires) ) {
      $timestamp = \date_create_from_format('Y-m-d\TH:i:sO', $expires);

      if ( $timestamp !== false ) {
        return \date_format($timestamp, \get_option('date_format') . ' ' . \get_option('time_format'));
      }
    }
  }

  /**
   * @return string
   */
  public function column_dns( $item ) {
    $dns = $item['dns'];
    if ( ! empty($dns) ) {
      return \implode('<br>', $dns);
    }
    return '';
  }

  /**
   * @return array<string, string>
   */
  public function get_columns() {
    return array(
      'domain'     => __('Domain', 'seravo'),
      'expires'    => __('Expires', 'seravo'),
      'dns'        => __('DNS', 'seravo'),
      'management' => __('Managed by', 'seravo'),
    );
  }

  /**
   * @return array<string, array<string|bool>>
   */
  protected function get_sortable_columns() {
    return array(
      'domain'     => array( 'domain', false ),     // true means it's already sorted
      'expires'    => array( 'expires', false ),
      'dns'        => array( 'dns', false ),
      'management' => array( 'management', false ),
    );
  }

  /**
   * @return mixed[]
   */
  protected function get_bulk_actions() {
    return array(
    //  'delete' => 'Delete',
    );
  }

  public function prepare_items() {
    global $wpdb; // This is used only if making any database queries

    // Domains per page
    $per_page = 500;

    // Define column headers
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array( $columns, $hidden, $sortable );

    // Define custom bulk actions
    $this->process_bulk_action();

    // Fetch list of domains
    $api_query = '/domains';
    $rawdata = API::get_site_data($api_query);
    if ( \is_wp_error($rawdata) ) {
      die($rawdata->get_error_message());
    }

    $data = array();
    foreach ( $rawdata as $index => $entry ) {
      // Check if subdomain
      $rawdata[$index]['subdomain'] = empty($entry['management']);

      // Try to figure out 'dns' if API couldn't
      if ( empty($entry['dns']) ) {
        $transient = 'domain_' . $entry['domain'] . '_ns';
        $dns = \get_transient($transient);
        if ( empty($dns) ) {
          // Nameserver weren't cached
          if ( ! empty($entry['management']) ) {
            // Get nameservers
            $nameservers = \dns_get_record($entry['domain'], DNS_NS);
          } else {
            // Get subdomain nameservers
            $domain = $entry['domain'];
            while ( \substr_count($domain, '.') >= 1 ) {
              $nameservers = \dns_get_record($domain, DNS_NS);
              if ( empty($nameservers) ) {
                $domain_split = \explode('.', $domain, 2);
                $domain = \end($domain_split);
              } else {
                break;
              }
            }
          }
          $dns = array();
          if ( ! empty($nameservers) ) {
            foreach ( $nameservers as $ns ) {
              $dns[] = $ns['target'];
            }
          }
        }
        \set_transient($transient, $dns, 600);
        $rawdata[$index]['dns'] = $dns;
      }

      // Try to figure out 'expires' and 'management' if API couldn't
      if ( $rawdata[$index]['subdomain'] === true && (empty($entry['management']) || empty($entry['expires'])) ) {
          $domain = $entry['domain'];
          while ( \substr_count($domain, '.') >= 1 ) {
          foreach ( $rawdata as $entry_compare ) {
            if ( isset($entry_compare['subdomain']) && ! $entry_compare['subdomain'] && $domain === $entry_compare['domain'] ) {
              $rawdata[$index]['expires'] = $entry_compare['expires'];
              $rawdata[$index]['management'] = $entry_compare['management'];
              break 2;
              }
            }
          $tmp = \explode('.', $domain, 2);
          $domain = \end($tmp);
          }
      }

      // Translate management
      $rawdata[$index]['management'] = \str_replace('Customer', __('Customer', 'seravo'), $rawdata[$index]['management']);

      $data[] = $rawdata[$index];
    }

    /**
     * This checks for sorting input and sorts the data in our array accordingly.
     *
     * In a real-world situation involving a database, you would probably want
     * to handle sorting by passing the 'orderby' and 'order' values directly
     * to a custom query. The returned data will be pre-sorted, and this array
     * sorting technique would be unnecessary.
     */
    function usort_reorder( $a, $b ) {
      // If no sort, default to domain name
      $orderby = (empty($_REQUEST['orderby'])) ? 'domain' : $_REQUEST['orderby'];
        // If no order, default to asc
      $order = (empty($_REQUEST['order'])) ? 'asc' : $_REQUEST['order'];
        // Determine sort order
      $result = \strcmp($a[ $orderby ], $b[ $orderby ]);
        // Send final sort direction to usort
      return ($order === 'asc') ? $result : -$result;
    }
    \usort($data, 'Seravo\usort_reorder');

    // Required for pagnation
    $current_page = $this->get_pagenum();
    $total_items = \count($data);

    /**
     * The WP_List_Table class does not handle pagination for us, so we need
     * to ensure that the data is trimmed to only the current page. We can use
     * array_slice() to
     */
    $data = \array_slice($data, (($current_page - 1) * $per_page), $per_page);

    /**
     * REQUIRED. Now we can add our *sorted* data to the items property, where
     * it can be used by the rest of the class.
     */
    $this->items = $data;

    /**
     * REQUIRED. We also have to register our pagination options & calculations.
     */
    $this->set_pagination_args(
      array(
        // WE have to calculate the total number of items
        'total_items' => $total_items,
        // WE have to determine how many items to show on a page
        'per_page'    => $per_page,
        // WE have to calculate the total number of pages
        'total_pages' => \ceil($total_items / $per_page),
      )
    );
  }

  public function single_row( $item ) {
    // Item row
    echo '<tr data-domain="' . $item['domain'] . '">';
    $this->single_row_columns($item);
    echo '</tr>';
    // Action row
    echo '<tr class="pre-action-row"><td></td></tr>';
    echo '<tr class="action-row" style="display:none;"><td class="action-row-data" colspan="4"></td></tr>';
  }

  public function display() {
    $singular = $this->_args['singular'];

    $this->screen->render_screen_reader_content('heading_list');

    ?>
    <table class="wp-list-table <?php echo \implode(' ', $this->get_table_classes()); ?>">
      <thead>
        <tr>
          <?php $this->print_column_headers(); ?>
        </tr>
      </thead>
      <tbody id="the-list" <?php echo $singular ? "data-wp-lists='list:{$singular}'" : ''; ?>>
        <?php $this->display_rows_or_placeholder(); ?>
      </tbody>
    </table>
    <?php
  }
}

class Seravo_Mails_Forward_Table extends WP_List_Table {

  public function __construct() {
    // Set parent defaults
    parent::__construct(
      array(
        'singular' => 'mail-forward',
        'plural'   => 'mail-forwards',
        'ajax'     => true,
        'screen'   => 'seravo-domains',
      )
    );
  }

  protected function display_tablenav( $which ) {
    if ( $which === 'top' ) {
      echo '<hr style="margin: 15px 0;">';
    }
  }

  /**
   * @return array<string, string>
   */
  public function get_columns() {
    return array(
      'domain'       => __('Domain', 'seravo'),
      'source'       => __('Forwards', 'seravo'),
    );
  }

  /**
   * @return array<string, array<string|bool>>
   */
  protected function get_sortable_columns() {
    return array( 'domain' => array( 'domain', false ) );     // true means it's already sorted
  }

  /**
   * @return string
   */
  public function column_domain( $item ) {
    $page = empty($_REQUEST['page']) ? 'domains_page' : $_REQUEST['page'];
    $view = '<a href="?page=' . $page . '&domain=' . $item['domain'] . '&action=fetch_forwards">' . __('Fetch', 'seravo') . '</a>';
    $edit = '<a href="?page=' . $page . '&domain=' . $item['domain'] . '&action=create_forward">' . __('Create', 'seravo') . '</a>';

    return \sprintf(
      '<p>%s</p>%s',
      $item['domain'],
      $this->row_actions(
        array(
          'view' => $view,
          'edit' => $edit,
        )
      )
    );

  }

  public function prepare_items() {

    // Fetch list of domains
    $api_query = '/domains';
    $rawdata = API::get_site_data($api_query);
    if ( \is_wp_error($rawdata) ) {
      die($rawdata->get_error_message());
    }

    $data = array();
    foreach ( $rawdata as $domain ) {
      if ( $domain['management'] === 'Seravo' ) {
        $data[] = array(
          'domain' => $domain['domain'],
        );
      }
    }

    // Define column headers
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array( $columns, $hidden, $sortable );

    function usort_reorder_domains( $a, $b ) {
      // If no sort, default to domain name
      $orderby = (empty($_REQUEST['orderby'])) ? 'domain' : $_REQUEST['orderby'];
      // If no order, default to asc
      $order = (empty($_REQUEST['order'])) ? 'asc' : $_REQUEST['order'];
      // Determine sort order
      $result = \strcmp($a[ $orderby ], $b[ $orderby ]);
      // Send final sort direction to usort
      return ($order === 'asc') ? $result : -$result;
    }
    \usort($data, 'Seravo\usort_reorder_domains');

    $this->items = $data;

  }

  public function single_row( $item ) {
    // Item row
    echo '<tr data-forwards="' . $item['domain'] . '">';
    $this->single_row_columns($item);
    echo '</tr>';
  }

  public function display() {
    $singular = $this->_args['singular'];

    $this->screen->render_screen_reader_content('heading_list');

    ?>
    <table class="wp-list-table <?php echo \implode(' ', $this->get_table_classes()); ?>">
      <thead>
      <tr>
        <?php $this->print_column_headers(); ?>
      </tr>
      </thead>
      <tbody id="the-list" <?php echo $singular ? "data-wp-lists='list:{$singular}'" : ''; ?>>
      <?php $this->display_rows_or_placeholder(); ?>
      </tbody>
    </table>
    <?php
  }

}

class Seravo_DNS_Table {

  public static function display_zone_table( $action, $domain ) {
    $records = self::fetch_dns_records($action, $domain);

    if ( empty($records) ) {
      return;
    }
    if ( isset($records['error']) ) {
      echo '<div><p style="margin-left: 3px;"><b>' . $records['error'] . '</b></p></div>';
      return;
    }

    $timestamp = \date_create_from_format('Y-m-d H:i:s T', $records['timestamp']);

    echo '<hr>';
    echo '<p class="update-time"><b>' . __('Update time:', 'seravo') . '</b> ' . \date_format($timestamp, \get_option('date_format') . ' ' . \get_option('time_format')) . '</p>';
    echo '<div class="dns-wrapper">';
    echo '<table class="wp-list-table widefat fixed striped" id="zone-table">';
    echo '<thead>
      <tr class="zone-titles">
        <th width="20%">' . __('Name', 'seravo') . '</th>
        <th width="15%">' . __('TTL', 'seravo') . '</th>
        <th width="15%"> </th>
        <th width="20%">' . __('Type', 'seravo') . '</th>
        <th width="30%">' . __('Value', 'seravo') . '</th>
      </tr>
    </thead>';
    foreach ( $records['records'] as $record ) {
      echo '<tr>';
      echo '<td>' . $record['name'] . '</td>
        <td>' . $record['ttl'] . '</td>
        <td></td>
        <td>' . $record['type'] . '</td>
        <td>' . $record['value'] . '</td>';
      echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    echo '<hr>';
  }

  public static function display_zone_edit( $domain ) {
    $records = self::fetch_dns_records('zone', $domain);
    $error = isset($records['error']);

    if ( empty($records) ) {
      return;
    }
    if ( $error ) {
      echo '<div><p style="margin-left: 3px;"><b>' . $records['error'] . '</b></p></div>';
      return;
    }

    echo '<hr>';
    if ( ! $error && $records['pending_activation'] ) {
      echo '<input type="hidden" name="action" value="change_zone_file">';
      echo '<input type="hidden" name="domain" value="' . $records['name'] . '">';
      echo '<table id="zone-edit-table">';
      echo '<tr><td style="padding-bottom: 0;">';
      // translators: %s domain of the site
      echo '<p style="max-width:50%;">' . \wp_sprintf(__('Our systems have detected that <strong>%s</strong> does not point to the Seravo servers. For your protection, manual editing is disabled. Please contact the Seravo customer service if you want changes to be done to the zone in question. You can publish the site yourself when you so desire with the following button:', 'seravo'), $records['name']) . '</p>';
      echo '</td></tr>';
      echo '<tr><td>';
      echo '<textarea type="hidden" name="zonefile" style="display: none; font-family: monospace;">' . (\implode("\n", $records['compulsory']['records']) . "\n" . \implode("\n", $records['editable']['records'])) . '</textarea>';
      echo '<div id="zone-edit-response"></div/>';
      echo '<button id="publish-zone-btn" class="button"' . ($error ? ' disabled' : '') . '>' . __('Publish', 'seravo') . '</button>';
      echo '<div id="zone-update-spinner" style="margin: 4px 10px 0 0"></div>';
      echo '</td></tr>';
      echo '</table>';
    } else {
      echo '<input type="hidden" name="action" value="change_zone_file">';
      // If $error is true, show empty / disabled fields
      echo '<input type="hidden" name="domain" value="' . ($error ? '' : $records['name']) . '">';
      echo '<table id="zone-edit-table">';
      echo '<tr><td style="padding-bottom: 0;">';
      echo '<h3 style="margin: 0 0 5px 0;">' . __('Compulsory Records', 'seravo') . '</h3>';
      echo '<p>' . __('It is not recommended to edit these records. Please contact the Seravo customer service if you want changes to be done to them.', 'seravo') . '</p>';
      echo '</td><td style="padding-bottom: 0;">';
      echo '<h3 style="margin: 0 0 5px 0;">' . __('Editable records', 'seravo') . '</h3>';
      echo '<p>' . __('Here you can add, edit and delete records. Please do not try to add records conflicting with the compulsory records. They will not be activated.', 'seravo') . '</p>';
      echo '</td></tr>';

      echo '<tr><td style="padding:0 0 0 10px;"><div id="zone-fetch-response"><p style="margin:0;"><b>' . ($error ? $records['error'] : '') . '</p></b></div></td></tr>';
      echo '<tr><td style="width:50%;padding-bottom:0;"><textarea name="compulsory" readonly style="width: 100%; font-family: monospace;" rows="15">' . ($error ? '' : \implode("\n", $records['compulsory']['records'])) . '</textarea></td>';
      echo '<td style="width:50%;padding-bottom:0;"><textarea name="zonefile" style="width: 100%; font-family: monospace;" rows="15"' . ($error ? ' readonly>' : '>' . \implode("\n", $records['editable']['records'])) . '</textarea></td></tr>';
      echo '<tr><td></td><td><div id="zone-edit-response"></div></td></tr><tr><td></td><td>';
      echo '<button id="update-zone-btn" class="button alignright"' . ($error ? ' disabled' : '') . '>' . __('Update Zone', 'seravo') . '</button>';
      echo '<div id="zone-update-spinner" class="alignright" style="margin: 4px 10px 0 0"></div></td></tr>';
      echo '</table>';
    }
    echo '<hr>';

 }

  /**
   * @return mixed[]|\WP_Error
   */
  public static function fetch_dns_records( $action, $domain ) {
    $api_query = '/domain/' . $domain . '/' . $action;

    $records = API::get_site_data($api_query);

    if ( \is_wp_error($records) ) {
      $records = array( 'error' => $records->get_error_message() );
    }

    return $records;
  }

}
