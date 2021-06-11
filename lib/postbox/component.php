<?php
/**
 * File for Seravo postbox components.
 */

namespace Seravo\Postbox;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Component') ) {
  class Component {

    /**
     * @var string The HTML content of the component.
     */
    private $content = '';

    /**
     * @var string The opening tag of the component.
     */
    private $wrapper_open = '';
    /**
     * @var string The closing tag of the component.
     */
    private $wrapper_close = '';

    /**
     * @var \Seravo\Postbox\Component[]|mixed[] Children to be rendered after $content.
     */
    private $children = array();

    /**
     * @param Component $child Child component to be added.
     */
    public function add_child( Component $child ) {
      if ( $child === null ) {
        return;
      }

      $this->children[] = $child;
    }

    /**
     * @param Component[] $children List of children to be added.
     */
    public function add_children( $children ) {
      if ( $children === null || empty($children) ) {
        return;
      }

      $this->children = array_merge($this->children, $children);
    }

    /**
     * @return string HTML to render the component.
     */
    public function to_html() {
      $html = $this->wrapper_open . $this->content;
      foreach ( $this->children as $child ) {
        if ( $child === null ) {
          continue;
        }

        $html .= $child->to_html();
      }
      return $html . $this->wrapper_close;
    }

    /**
     * Prints HTML for the component.
     */
    public function print_html() {
      echo $this->to_html();
    }

    /**
     * @param string $open The opening tag for the component.
     * @param string $close The closing tag for the component.
     * @return $this
     */
    public function set_wrapper( $open, $close ) {
      $this->wrapper_open = $open;
      $this->wrapper_close = $close;

      return $this;
    }

    /**
     * @param string $content Content for the component.
     * @return $this 
     */
    public function set_content( $content ) {
      $this->content = $content;

      return $this;
    }


    /**#########################
     * ### LOW LEVEL HELPERS ###
     * #########################*/

    /**
     * @param string $html Component from raw HTML.
     * @return \Seravo\Postbox\Component
     */
    public static function html( $html ) {
      $component = new Component();
      $component->set_content($html);

      return $component;
    }


    /**#########################
     * ### MID LEVEL HELPERS ###
     * #########################*/

    /**
     * Component that's shown on postboxes on exception.
     * @return \Seravo\Postbox\Component
     */
    public static function seravo_plugin_error() {
      $message = __('Whoops! Something went wrong. Please see %s for instructions.', 'seravo');
      $url = get_option('siteurl') . '/wp-admin/tools.php?page=logs_page&logfile=php-error.log';
      $link = sprintf('<a href="%s">php-error.log</a>', $url);
      $html = sprintf('<p><b>' . $message . '</b></p>', $link);

      return Component::html($html);
    }

  }
}