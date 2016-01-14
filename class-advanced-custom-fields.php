<?php

/**
 * Manages the translations for Advanced custom field plugins.
 *
 * @package Babble
 * @since   1.5
 */
class Babble_ACF extends Babble_Plugin {

	function __construct() {
		$this->setup( 'babble-acf', 'plugin' );
		// add ACF metabox in to Job translation view
		$this->add_action( 'bbl_translation_post_meta_boxes', null, 10, 3 );
		$this->add_action( 'admin_head', null, 5, 10 );
		$this->add_filter( 'bbl_translated_post_type', null, 10, 2 );
		$this->add_filter( 'bbl_sync_meta_key', null, 10, 2 );

	}

	/**
	 * Add Metabox for job queue editor view
	 */

	function bbl_translation_post_meta_boxes( $type, $original, $translation ) {
		// return if acf is not activate
		if ( ! class_exists( 'acf' ) ) {
			return;
		}

		/**
		 * Remove own filter for Advance Custom Field Hooks to avoid duplicate metaboxes
		 */
		remove_filter( 'acf/location/match_field_groups', array(
			$this,
			'bbl_acf_location_match_field_groups',
		), 999, 2 );
		remove_filter( 'acf/get_field_groups', array( $this, 'bbl_acf_get_field_groups' ), 999, 1 );

		// Add default value filter only for job, it will fetch meta value from original post of it is not saved for queue
		add_filter( 'acf/load_value', array( $this, 'bbl_acf_load_value' ), 6, 3 );
		global $post;
		// Helper original post id to fetch meta value
		$GLOBALS['bbl_job_edit_original_post'] = $original->ID;
		$GLOBALS['bbl_job_edit_original_job']  = $post->ID;

		// get field groups for original post type
		$filter      = array(
			'post_id'   => $original->ID,
			'post_type' => bbl_get_base_post_type( $original->post_type ),
		);
		$metabox_ids = array();
		// Fetch metabox ids for original posts
		$metabox_ids = apply_filters( 'acf/location/match_field_groups', $metabox_ids, $filter );

		// get field groups
		$acfs = apply_filters( 'acf/get_field_groups', array() );

		global $post;

		$languages = get_the_terms( $post, 'bbl_job_language' );
		if ( empty( $languages ) ) {
			if ( isset( $_GET['lang'] ) ) {
				$lang = sanitize_term_field( $_GET['lang'] );
				if ( bbl_get_lang( $lang ) ) {
					bbl_switch_to_lang( $lang );
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			bbl_switch_to_lang( reset( $languages )->name );
		}

		if ( $acfs ) {
			$acf_input = new acf_controller_post();
			foreach ( $acfs as $acf ) {
				// load options
				$acf['options'] = apply_filters( 'acf/field_group/get_options', array(), $post->ID );

				// vars
				$show     = in_array( $acf['id'], $metabox_ids ) ? 1 : 0;
				$priority = 'high';
				if ( $acf['options']['position'] == 'side' ) {
					$priority = 'core';
				}

				// add meta box
				$acf['options']['layout'] = 'box';
				if ( 1 === $show ) {
					add_meta_box( 'acf_' . $acf['id'], // id
						$acf['title'], // title
						array( $acf_input, 'meta_box_input' ), // callback
						'bbl_translation_editor_post', // $original->post_type, //screen
						'post', // $acf['options']['position'], //context
						$priority, // priority
						array(
						              'layout'      => 'box',
						              'field_group' => $acf,
						              'show'        => true,
						              'post_id'     => $post->ID,
					              ) // args
					);
				}
			}
		}
		// Restore filter
		add_filter( 'acf/location/match_field_groups', array( $this, 'bbl_acf_location_match_field_groups' ), 999, 2 );
		add_filter( 'acf/get_field_groups', array( $this, 'bbl_acf_get_field_groups' ), 999, 1 );
	}

	/**
	 * Advance custom field Support for babble, apply filter
	 */

	function admin_head() {
		global $current_screen;

		if ( ! class_exists( 'acf' ) ) {
			return;
		}

		if ( ! isset( $current_screen ) || ! isset( $current_screen->post_type ) ) {
			return;
		}

		if ( 'post' === $current_screen->base && 'bbl_job' === $current_screen->post_type ) {
			/**
			 * Filter to ignore custom filed metabox registration on admin_head, we manually adding it for babble job
			 */
			add_filter( 'acf/location/match_field_groups', array(
				$this,
				'bbl_acf_location_match_field_groups',
			), 999, 2 );
			add_filter( 'acf/get_field_groups', array( $this, 'bbl_acf_get_field_groups' ), 999, 1 );
		}

	}

	/**
	 *
	 * @param $metabox_ids
	 * @param $filter
	 *
	 * @return array
	 */
	function bbl_acf_location_match_field_groups( $metabox_ids, $filter ) {
		return array();
	}

	/**
	 *
	 * @param $field_group
	 *
	 * @return array
	 */
	function bbl_acf_get_field_groups( $field_group ) {
		return array();
	}

	/**
	 * Function will filter meta filed value with original post if it is not set
	 *
	 * @param $value
	 * @param $post_id
	 * @param $field
	 *
	 * @return mixed
	 */
	function bbl_acf_load_value( $value, $post_id, $field ) {
		if ( false === $value || empty( $value ) ) {
			$found              = false;
			$bbl_acf_load_value = wp_cache_get( 'bbl_acf_load_value/post_id=' . $GLOBALS['bbl_job_edit_original_job'], 'babble', false, $found );

			if ( ! $found ) {
				$bbl_acf_load_value = get_post_meta( $post_id, 'bbl_acf_load_value', true );
				wp_cache_set( 'bbl_acf_load_value/post_id=' . $GLOBALS['bbl_job_edit_original_job'], $bbl_acf_load_value, 'babble' );
			}
			if ( 'self' !== $bbl_acf_load_value ) {
				remove_filter( 'acf/load_value', array( $this, 'bbl_acf_load_value' ), 6, 3 );
				// get value from original post type
				$value = apply_filters( 'acf/load_value', $value, $GLOBALS['bbl_job_edit_original_post'], $field );
				add_filter( 'acf/load_value', array( $this, 'bbl_acf_load_value' ), 6, 3 );
			}
		}

		return $value;
	}


	/**
	 * remove acf post type from translated post types
	 *
	 * @param $translated
	 * @param $post_type
	 *
	 * @return bool
	 */

	function bbl_translated_post_type( $translated, $post_type ) {
		if ( 'acf' === $post_type ) {
			$translated = false;
		}

		return $translated;
	}

	/**
	 * @param $sync     boolean flag to allow sync meta key
	 * @param $meta_key string name of the meta_key
	 *
	 * @internal param bool $return true if you want to sync meta key
	 * @fixme    Identify acf meta key and only ignore that keys
	 * @return bool
	 */
	function bbl_sync_meta_key( $sync, $meta_key ) {
		if ( class_exists( 'acf' ) ) {
			if ( 0 <= strpos( $meta_key, 'bbl' ) ) {
				return true;
			}
			if ( 0 < strpos( $meta_key, '_' ) ) {
				return false;
			}
		}

		return $sync;
	}
}

global $bbl_acf;
$bbl_acf = new Babble_ACF();
