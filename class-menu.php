<?php
/**
 * Manages the translations for Menu.
 *
 * @package Babble
 * @since   1.5
 */
class Babble_Menus extends Babble_Plugin {

	/**
	 * An array of all post-type names registered for current language
	 *
	 * @var array
	 */
	protected $current_lang_post_types = array();

	/**
	 * An array of all taxonomies name registered for current language
	 *
	 * @var array
	 */
	protected $current_lang_taxonomies = array();

	function __construct() {
		$this->setup( 'babble-menus', 'plugin' );
		$this->add_filter( 'bbl_translated_post_type', null, null, 2 );
		$this->add_filter( 'nav_menu_meta_box_object', null, null, 1 );
		$this->add_action( 'parse_query', null, null, 1 );
		$this->add_action( 'wp_update_nav_menu_item', null, null, 3 );
	}

	/**
	 * Return list of post types for current language
	 *
	 * Function will check if post type list for current language is set or not
	 * If not then it will set it
	 *
	 * @return array
	 */
	public function get_current_lang_post_types() {
		if ( empty( $this->current_lang_post_types ) ) {
			$this->set_current_lang_post_types();
		}

		return $this->current_lang_post_types;
	}

	/**
	 * Function will set post type list for current language
	 *
	 * @return null
	 */
	protected function set_current_lang_post_types() {
		$this->current_lang_post_types = array();
		global $bbl_post_public;
		$base_post_types = $bbl_post_public->get_base_post_types();
		foreach ( $base_post_types as $post_type ) {
			$this->current_lang_post_types[] = $bbl_post_public->get_post_type_in_lang( $post_type->name, bbl_get_current_lang_code() );
		}
	}

	/**
	 * Return list of taxonomies for current language
	 *
	 * Function will check if taxonomies list for current language is set or not
	 * If not then it will set it
	 *
	 * @return array
	 */
	public function get_current_lang_taxonomies() {
		if ( empty( $this->current_lang_taxonomies ) ) {
			$this->set_current_lang_taxonomies();
		}

		return $this->current_lang_taxonomies;
	}

	/**
	 * Function will set taxonomies list for current language
	 *
	 * @return null
	 */
	protected function set_current_lang_taxonomies() {
		$this->current_lang_taxonomies = array( 'post_format', 'link_category' );

		global $bbl_taxonomies;
		$base_taxonomies = $bbl_taxonomies->get_base_taxonomies();

		foreach ( $base_taxonomies as $taxonomy ) {
			$this->current_lang_taxonomies[] = $bbl_taxonomies->get_taxonomy_in_lang( $taxonomy->name, bbl_get_current_lang_code() );
		}
	}

	/**
	 * This will add language code for menu item.
	 *
	 * @param int   $menu_id The ID of the menu. Required. If "0", makes the menu item a draft orphan.
	 * @param int   $menu_item_db_id The ID of the menu item. If "0", creates a new menu item.
	 * @param array $menu_item_data The menu item's data.
	 */
	public function wp_update_nav_menu_item( $menu_id, $menu_item_db_id, $args ) {
			update_post_meta( $menu_item_db_id, '_menu_lang_code', bbl_get_current_lang_code() );
	}

	/**
	 * Exclude nav_menu_item from translated list
	 *
	 * @param $translated
	 * @param $post_type
	 *
	 * @return bool
	 */
	public function bbl_translated_post_type( $translated, $post_type ) {
		if ( 'nav_menu_item' === $post_type ) {
			return false;
		}

		return $translated;
	}

	/**
	 * @param $menu_meta_item
	 *
	 * @return bool
	 */
	public function nav_menu_meta_box_object( $menus_meta_box_object ) {
		if ( isset( $menus_meta_box_object->update_count_callback ) ) {
			if ( ! in_array( $menus_meta_box_object->name, $this->get_current_lang_taxonomies() ) ) {
				return false;
			}
		} else {
			if ( ! in_array( $menus_meta_box_object->name, $this->get_current_lang_post_types() ) ) {
				return false;
			}
		}

		return $menus_meta_box_object;
	}

	function parse_query( $q ) {
		if ( ! isset( $q->query_vars['post_type'] ) ) {
			return;
		}
		if ( false === in_array( 'nav_menu_item', (array) $q->query_vars['post_type'] ) ) {
			return;
		}

		if ( isset( $q->query_vars['bbl_translate'] ) && false === $q->query_vars['bbl_translate'] ) {
			return;
		}

		$q->query_vars['meta_query'] = array( // @codingStandardsIgnoreLine
			array(
				'key'     => '_menu_lang_code',
				'value'   => bbl_get_current_lang_code(),
				'compare' => 'LIKE',
			),
		);
		// if ( bbl_get_default_lang_code() === bbl_get_current_lang_code() ) {
		//
		// $q->query_vars[ 'meta_query' ]['relation'] =  'OR';
		// $q->query_vars[ 'meta_query' ][ ] = array(
		// 'key'     => '_menu_lang_code',
		// 'compare' => 'NOT EXISTS',
		// 'value'   => bbl_get_current_lang_code()
		// );
		// }
	}

	public function wp_insert_post_data( $data, $postarr ) {
		if ( bbl_get_default_lang_code() !== bbl_get_current_lang_code() ) {
			// @fixme Check nonce
			global $bbl_post_public;
			$data['post_type'] = $bbl_post_public->get_post_type_in_lang( $data['post_type'], bbl_get_current_lang_code() );
		}

		return $data;
	}
}

global $bbl_menus;
$bbl_menus = new Babble_Menus();
