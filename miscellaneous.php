<?php

/**
 * Functions and (mainly) hooks which don't fit in the various
 * classes for whatever reason. Consider these various things
 * Private access, for this plugin only, please.
 *
 * Will try to keep the functions in here to an absolute minumum.
 *
 * @package Babble
 * @since   Alpha 1.1
 */

/**
 * Replicates the core comments_template function, but uses the API
 * to fetch the comments and includes more filters.
 *
 * Loads the comment template specified in $file.
 *
 * Will not display the comments template if not on single post or page, or if
 * the post does not have comments.
 *
 * Uses the WordPress database object to query for the comments. The comments
 * are passed through the 'comments_array' filter hook with the list of comments
 * and the post ID respectively.
 *
 * The $file path is passed through a filter hook called, 'comments_template'
 * which includes the TEMPLATEPATH and $file combined. Tries the $filtered path
 * first and if it fails it will require the default comment template from the
 * default theme. If either does not exist, then the WordPress process will be
 * halted. It is advised for that reason, that the default theme is not deleted.
 *
 * @since 1.5.0
 * @global array $comment           List of comment objects for the current post
 * @uses  $wpdb
 * @uses  $post
 * @uses  $withcomments Will not try to get the comments if the post has none.
 *
 * @see   comments_template()
 *
 * @param string $file              Optional, default '/comments.php'. The file to load
 * @param bool   $separate_comments Optional, whether to separate the comments by comment type. Default is false.
 *
 * @return null Returns null if no comments appear
 */
function bbl_comments_template( $file = '/comments.php', $separate_comments = false ) {
	global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_ID, $user_identity, $overridden_cpage;

	if ( !( is_single() || is_page() || $withcomments ) || empty( $post ) ) {
		return;
	}

	if ( empty( $file ) ) {
		$file = '/comments.php';
	}

	$req = get_option( 'require_name_email' );

	/**
	 * Comment author information fetched from the comment cookies.
	 *
	 * @uses wp_get_current_commenter()
	 */
	$commenter = wp_get_current_commenter();

	/**
	 * The name of the current comment author escaped for use in attributes.
	 */
	$comment_author = $commenter[ 'comment_author' ]; // Escaped by sanitize_comment_cookies()

	/**
	 * The email address of the current comment author escaped for use in attributes.
	 */
	$comment_author_email = $commenter[ 'comment_author_email' ]; // Escaped by sanitize_comment_cookies()

	/**
	 * The url of the current comment author escaped for use in attributes.
	 */
	$comment_author_url = esc_url( $commenter[ 'comment_author_url' ] );

	$query = new Bbl_Comment_Query;
	$args  = array(
		'order'   => 'ASC',
		'post_id' => $post->ID,
		'status'  => 'approve',
		'status'  => 'approve',
	);
	if ( $user_ID ) {
		$args[ 'unapproved_user_id' ] = $user_ID;
	} else if ( !empty( $comment_author ) ) {
		$args[ 'unapproved_author' ]       = wp_specialchars_decode( $comment_author, ENT_QUOTES );
		$args[ 'unapproved_author_email' ] = $comment_author_email;
	}
	$args     = apply_filters( 'comments_template_args', $args );
	$comments = $query->query( $args );

	// keep $comments for legacy's sake
	$wp_query->comments      = apply_filters( 'comments_array', $comments, $post->ID );
	$comments                = & $wp_query->comments;
	$wp_query->comment_count = count( $wp_query->comments );
	update_comment_cache( $wp_query->comments );

	if ( $separate_comments ) {
		$wp_query->comments_by_type = & separate_comments( $comments );
		$comments_by_type           = & $wp_query->comments_by_type;
	}

	$overridden_cpage = false;
	if ( '' == get_query_var( 'cpage' ) && get_option( 'page_comments' ) ) {
		set_query_var( 'cpage', 'newest' == get_option( 'default_comments_page' ) ? get_comment_pages_count() : 1 );
		$overridden_cpage = true;
	}

	if ( !defined( 'COMMENTS_TEMPLATE' ) || !COMMENTS_TEMPLATE ) {
		define( 'COMMENTS_TEMPLATE', true );
	}

	$include = apply_filters( 'comments_template', STYLESHEETPATH . $file );
	if ( file_exists( $include ) ) {
		require $include;
	} elseif ( file_exists( TEMPLATEPATH . $file ) ) {
		require TEMPLATEPATH . $file;
	} else // Backward compat code will be removed in a future release
	{
		require ABSPATH . WPINC . '/theme-compat/comments.php';
	}
}


/**
 * WordPress Comment Query class.
 *
 * See Trac: http://core.trac.wordpress.org/ticket/19623
 *
 * @since 3.1.0
 */
class Bbl_Comment_Query {

	/**
	 * Execute the query
	 *
	 * @since 3.1.0
	 *
	 * @param string|array $query_vars
	 *
	 * @return int|array
	 */
	function query( $query_vars ) {
		global $wpdb;

		$defaults = array(
			'author_email'            => '',
			'ID'                      => '',
			'karma'                   => '',
			'number'                  => '',
			'offset'                  => '',
			'orderby'                 => '',
			'order'                   => 'DESC',
			'parent'                  => '',
			'post_ID'                 => '',
			'post_id'                 => '',
			'post__in'                => '',
			'post_author'             => '',
			'post_name'               => '',
			'post_parent'             => '',
			'post_status'             => '',
			'post_type'               => '',
			'status'                  => '',
			'type'                    => '',
			'unapproved_author'       => '',
			'unapproved_author_email' => '',
			'unapproved_user_id'      => '',
			'user_id'                 => '',
			'search'                  => '',
			'count'                   => false,
		);

		$this->query_vars = wp_parse_args( $query_vars, $defaults );
		do_action_ref_array( 'pre_get_comments', array( &$this ) );
		extract( $this->query_vars, EXTR_SKIP );

		// $args can be whatever, only use the args defined in defaults to compute the key
		$key          = md5( serialize( compact( array_keys( $defaults ) ) ) );
		$last_changed = wp_cache_get( 'last_changed', 'comment' );
		if ( !$last_changed ) {
			$last_changed = time();
			wp_cache_set( 'last_changed', $last_changed, 'comment' );
		}
		$cache_key = "get_comments:$key:$last_changed";

		if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
			return $cache;
		}

		if ( empty( $post_id ) && empty( $post__in ) ) {
			$post_id = 0;
		}

		$post_id = absint( $post_id );

		$where = '';

		$show_unapproved = ( '' != $unapproved_user_id || '' !== $unapproved_author || '' != $unapproved_author_email );

		if ( $show_unapproved ) {
			$where .= ' ( ';
		}

		if ( 'hold' == $status ) {
			$where .= "comment_approved = '0'";
		} elseif ( 'approve' == $status ) {
			$where .= "comment_approved = '1'";
		} elseif ( 'spam' == $status ) {
			$where .= "comment_approved = 'spam'";
		} elseif ( 'trash' == $status ) {
			$where .= "comment_approved = 'trash'";
		} else {
			$where .= "( comment_approved = '0' OR comment_approved = '1' )";
		}

		if ( $show_unapproved ) {
			$where .= ' OR ( comment_approved = 0 ';
			if ( '' !== $unapproved_author ) {
				$where .= $wpdb->prepare( ' AND comment_author = %s', $unapproved_author );
			}
			if ( '' !== $unapproved_author_email ) {
				$where .= $wpdb->prepare( ' AND comment_author_email = %s', $unapproved_author_email );
			}
			if ( '' !== $unapproved_user_id ) {
				$where .= $wpdb->prepare( ' AND user_id = %d', $unapproved_user_id );
			}
			$where .= ' ) ) ';
		}

		$order = ( 'ASC' == strtoupper( $order ) ) ? 'ASC' : 'DESC';

		if ( !empty( $orderby ) ) {
			$ordersby = is_array( $orderby ) ? $orderby : preg_split( '/[,\s]/', $orderby );
			$ordersby = array_intersect( $ordersby, array(
					'comment_agent',
					'comment_approved',
					'comment_author',
					'comment_author_email',
					'comment_author_IP',
					'comment_author_url',
					'comment_content',
					'comment_date',
					'comment_date_gmt',
					'comment_ID',
					'comment_karma',
					'comment_parent',
					'comment_post_ID',
					'comment_type',
					'user_id',
				) );
			$orderby  = empty( $ordersby ) ? 'comment_date_gmt' : implode( ', ', $ordersby );
		} else {
			$orderby = 'comment_date_gmt';
		}

		$number = absint( $number );
		$offset = absint( $offset );

		if ( !empty( $number ) ) {
			if ( $offset ) {
				$limits = 'LIMIT ' . $offset . ',' . $number;
			} else {
				$limits = 'LIMIT ' . $number;
			}
		} else {
			$limits = '';
		}

		if ( $count ) {
			$fields = 'COUNT(*)';
		} else {
			$fields = '*';
		}

		$join = '';

		if ( !empty( $post_id ) ) {
			$where .= $wpdb->prepare( ' AND comment_post_ID = %d', $post_id );
		} else if ( '' != $post__in ) {
			$_post__in = implode( ',', array_map( 'absint', $post__in ) );
			$where .= " AND comment_post_ID IN ($_post__in)";
		}
		if ( '' !== $author_email ) {
			$where .= $wpdb->prepare( ' AND comment_author_email = %s', $author_email );
		}
		if ( '' !== $karma ) {
			$where .= $wpdb->prepare( ' AND comment_karma = %d', $karma );
		}
		if ( 'comment' == $type ) {
			$where .= " AND comment_type = ''";
		} elseif ( 'pings' == $type ) {
			$where .= ' AND comment_type IN ("pingback", "trackback")';
		} elseif ( !empty( $type ) ) {
			$where .= $wpdb->prepare( ' AND comment_type = %s', $type );
		}
		if ( '' !== $parent ) {
			$where .= $wpdb->prepare( ' AND comment_parent = %d', $parent );
		}
		if ( '' !== $user_id ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		}
		if ( '' !== $search ) {
			$where .= $this->get_search_sql( $search, array(
				'comment_author',
				'comment_author_email',
				'comment_author_url',
				'comment_author_IP',
				'comment_content'
			) );
		}

		$post_fields = array_filter( compact( array(
					'post_author',
					'post_name',
					'post_parent',
					'post_status',
					'post_type',
				) ) );
		if ( !empty( $post_fields ) ) {
			$join = "JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID";
			foreach ( $post_fields as $field_name => $field_value ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field_name} = %s", $field_value );
			}
		}

		$pieces  = array( 'fields', 'join', 'where', 'orderby', 'order', 'limits' );
		$clauses = apply_filters_ref_array( 'comments_clauses', array( compact( $pieces ), &$this ) );
		foreach ( $pieces as $piece ) {
			$$piece = isset( $clauses[ $piece ] ) ? $clauses[ $piece ] : '';
		}

		$query = "SELECT $fields FROM $wpdb->comments $join WHERE $where ORDER BY $orderby $order $limits";

		if ( $count ) {
			return $wpdb->get_var( $query );
		}

		$comments = $wpdb->get_results( $query );
		$comments = apply_filters_ref_array( 'the_comments', array( $comments, &$this ) );

		wp_cache_add( $cache_key, $comments, 'comment' );

		return $comments;
	}
}

/**
 * Add Metabox for job queue,
 */

add_action( 'bbl_translation_post_meta_boxes', 'acl_bbl_translation_post_meta_boxes', 10, 3 );

function acl_bbl_translation_post_meta_boxes( $type, $original, $translation ) {
	// return if acf is not activate

	if ( ! class_exists('acf') ) {
		return;
	}

	/**
	 * Remove own filter for Advance Custom Field Hooks to avoid duplicate metaboxes
	 **/
	remove_filter( 'acf/location/match_field_groups', 'bbl_acf_location_match_field_groups', 999, 2 );
	remove_filter( 'acf/get_field_groups', 'bbl_acf_get_field_groups', 999, 1 );

	// Add default value filter only for job, it will fetch meta value from original post of it is not saved for queue
	add_filter( 'acf/load_value', 'bbl_acf_load_value', 6, 3 );
	global $post;
	// Helper original post id to fetch meta value
	$GLOBALS[ 'bbl_job_edit_original_post' ] = $original->ID;
	$GLOBALS[ 'bbl_job_edit_original_job' ] = $post->ID;

	// get field groups for original post type
	$filter      = array(
		'post_id'   => $original->ID,
		'post_type' => bbl_get_base_post_type($original->post_type)
	);
	$metabox_ids = array();
	//Fetch metabox ids for original posts
	$metabox_ids = apply_filters( 'acf/location/match_field_groups', $metabox_ids, $filter );

	// get field groups
	$acfs = apply_filters( 'acf/get_field_groups', array() );

	global $post;

	$languages = get_the_terms( $post, 'bbl_job_language' );
	if ( empty( $languages ) )
		return false;
	bbl_switch_to_lang(reset($languages)->name);

	if ( $acfs ) {
		$acf_input = new acf_controller_post();
		foreach ( $acfs as $acf ) {
			// load options
			$acf[ 'options' ] = apply_filters( 'acf/field_group/get_options', array(), $post->ID );

			// vars
			$show     = in_array( $acf[ 'id' ], $metabox_ids ) ? 1 : 0;
			$priority = 'high';
			if ( $acf[ 'options' ][ 'position' ] == 'side' ) {
				$priority = 'core';
			}

			// add meta box
			$acf[ 'options' ][ 'layout' ] = 'box';
			if ( 1 === $show ) {
				add_meta_box( 'acf_' . $acf[ 'id' ], //id
					$acf[ 'title' ], //title
					array( &$acf_input, 'meta_box_input' ), //callback
					'bbl_translation_editor_post', //$original->post_type, //screen
					'post', //$acf['options']['position'], //context
					$priority, // priority
					array( 'layout' => 'box', 'field_group' => $acf, 'show' => true, 'post_id' => $post->ID ) //args
				);
			}


		}
	}
	// Restore filter
	add_filter( 'acf/location/match_field_groups', 'bbl_acf_location_match_field_groups', 999, 2 );
	add_filter( 'acf/get_field_groups', 'bbl_acf_get_field_groups', 999, 1 );
}

/**
 * Advance custom field Support for babble, apply filter
 */

add_action( 'admin_head', 'bbl_acf_admin_head', 9, 10 );


function bbl_acf_admin_head() {
	global $current_screen;
	if ( !( isset( $current_screen ) && isset( $current_screen->post_type ) && 'post' === $current_screen->base && 'bbl_job' === $current_screen->post_type ) ) {
		return;
	}

	if ( ! class_exists('acf') ) {
		return;
	}

	/**
	 * Filter to ignore custom filed metabox registration on admin_head, we manually adding it for babble job
	 */
	add_filter( 'acf/location/match_field_groups', 'bbl_acf_location_match_field_groups', 999, 2 );
	add_filter( 'acf/get_field_groups', 'bbl_acf_get_field_groups', 999, 1 );
}

function bbl_acf_location_match_field_groups( $metabox_ids, $filter ) {
	return array();
}

function bbl_acf_get_field_groups( $field_group ) {
	return array();
}

/**
 * Function will filter meta filed value with original post if it is not set
 * @param $value
 * @param $post_id
 * @param $field
 *
 * @return mixed
 */
function bbl_acf_load_value( $value, $post_id, $field ) {
	$found              = false;
	$bbl_acf_load_value = wp_cache_get( 'bbl_acf_load_value/post_id=' . $GLOBALS[ 'bbl_job_edit_original_job' ], 'babble', false, $found );
	if ( !$found ) {
		$bbl_acf_load_value = get_post_meta( $post_id, 'bbl_acf_load_value', true );
		wp_cache_set( 'bbl_acf_load_value/post_id=' . $GLOBALS[ 'bbl_job_edit_original_job' ], $bbl_acf_load_value, 'babble' );
	}

	if ( 'self' !== $bbl_acf_load_value  || false === $value  || empty($value) ) {
		remove_filter( 'acf/load_value', 'bbl_acf_load_value', 6, 3 );
		//get value from original post type
		$value = apply_filters( 'acf/load_value', $value, $GLOBALS[ 'bbl_job_edit_original_post' ], $field );
		add_filter( 'acf/load_value', 'bbl_acf_load_value', 6, 3 );
	}

	return $value;
}

/**
 * remove acf post type from translated post types
 */

add_filter( 'bbl_translated_post_type', 'bbl_acf_filter_translated_post_type', 10, 2 );
function bbl_acf_filter_translated_post_type( $translated, $post_type ) {
	if ( 'acf' === $post_type ) {
		$translated = false;
	}

	return $translated;
}