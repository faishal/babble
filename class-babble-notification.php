<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Babble_Notification {
	/**
	 * Babble setting option name
	 *
	 * @var string
	 */
	public static $option_name = 'babble_notification_settings';

	/**
	 * Hook function
	 */
	static function init() {
		add_action( 'babble_post_ready_for_translation', array(
			get_called_class(),
			'babble_post_ready_for_translation',
		), 10, 3 );

		// Register setting page
		add_action( 'admin_menu', array( get_called_class(), 'admin_menu' ) );
		add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
	}

	/**
	 * Notify site admin when post ready for transaltion
	 * Hooked in babble_post_ready_for_translation
	 */
	public static function babble_post_ready_for_translation( $post_id, WP_Post $post, array $jobs ) {

		// get setting and apply filter
		$is_notification_enable = apply_filters( 'notify_babble_post_ready_for_translation', self::is_notificaion_enable() );

		// Return if notification is disable in setting or by filter
		if ( false === $is_notification_enable ) {
			return;
		}

		// Get current user
		$current_user = wp_get_current_user();

		$post_author = get_userdata( $post->post_author );

		$blogname = get_option( 'blogname' );

		$post_title = _draft_or_post_title( $post_id );
		$post_type  = get_post_type_object( $post->post_type )->labels->singular_name;

		$subject = sprintf( __( '[%1$s] New %2$s is ready for translation : "%3$s"', 'babble' ), $blogname, $post_type, $post_title );

		$body  = sprintf( __( 'A new %1$s (#%2$s "%3$s") was marked ready for translation by %4$s %5$s', 'babble' ), $post_type, $post_id, $post_title, $current_user->display_name, $current_user->user_email ) . "\r\n";

		$body .= sprintf( __( 'This action was taken on %1$s at %2$s %3$s', 'babble' ), date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ), get_option( 'timezone_string' ) ) . "\r\n";

		$body .= "\r\n\r\n";

		$body .= "--------------------\r\n\r\n";

		if ( false === empty( $jobs ) ) {
			$body .= __( '== Translation Jobs ==', 'babble' ) . "\r\n";
			foreach ( $jobs as $job ) {
				global $bbl_jobs;
				$job_lang = $bbl_jobs->get_job_language( $job );
				$body .= sprintf( '%1$s: %2$s', $job_lang->display_name, htmlspecialchars_decode( get_edit_post_link( $job ) ) ) . "\r\n";
			}
			$body .= "\r\n";
		}

		$body .= sprintf( __( '== %s Details ==', 'babble' ), $post_type ) . "\r\n";
		$body .= sprintf( __( 'Title: %s', 'babble' ), $post_title ) . "\r\n";
		/* translators: 1: author name, 2: author email */
		$body .= sprintf( __( 'Author: %1$s (%2$s)', 'babble' ), $post_author->display_name, $post_author->user_email ) . "\r\n";

		$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
		if ( 'publish' !== $post->post_status ) {
			$view_link = add_query_arg( array( 'preview' => 'true' ), wp_get_shortlink( $post_id ) );
		} else {
			$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
		}
		$body .= "\r\n";
		$body .= __( '== Actions ==', 'babble' ) . "\r\n";
		$body .= sprintf( __( 'Edit: %s', 'babble' ), $edit_link ) . "\r\n";
		$body .= sprintf( __( 'View: %s', 'babble' ), $view_link ) . "\r\n";

		$body .= "\r\n--------------------\r\n";
		$body .= sprintf( __( 'This email was sent %s.', 'edit-flow' ), date( 'r' ) );
		$body .= "\r\n \r\n";
		$body .= get_option( 'blogname' ) . ' | ' . get_bloginfo( 'url' ) . ' | ' . admin_url( '/' ) . "\r\n";

		$blogadmins = get_users( array( 'fields' => array( 'user_email' ), 'role' => 'administrator' ) );
		foreach ( $blogadmins as $admin ) {
			wp_mail( $admin->user_email, $subject, $body );
		}
	}

	/**
	 * Return true if notification is enable in setting
	 *
	 * @return bool
	 */
	private static function is_notificaion_enable() {
		$setting = get_option( self::$option_name, array() );

		return ( isset( $setting['is_enable'] ) && '1' === $setting['is_enable'] );
	}

	/**
	 * Add babble subpage into WordPress setting Menu
	 */
	public static function admin_menu() {
		add_submenu_page( 'options-general.php', 'Babble', 'Babble', 'manage_options', 'babble-settings', array(
			get_called_class(),
			'options_page',
		) );
	}

	/**
	 * Register setting , setction and fields using WordPress setting api
	 */
	public static function admin_init() {

		register_setting( 'babble_notification_page', self::$option_name );

		add_settings_section( 'babble_notification_section', __( 'Notification', 'babble' ), array(
			get_called_class(),
			'settings_section_callback',
		), 'babble_notification_page' );

		add_settings_field( 'is_enable', __( 'Translation Notification', 'babble' ), array(
			get_called_class(),
			'is_enable_render',
		), 'babble_notification_page', 'babble_notification_section' );

	}

	/**
	 * Render field UI for Transaltion notification switch
	 */
	public static function is_enable_render() {
	?>
		<label for="<?php echo esc_attr( self::$option_name ); ?>_is_enable"><input type='checkbox'
		                                                                            id="<?php echo esc_attr( self::$option_name ); ?>_is_enable"
		                                                                            name='<?php echo esc_attr( self::$option_name ); ?>[is_enable]' <?php checked( self::is_notificaion_enable() ); ?>
		                                                                            value='1'>Enable</label>
		<p class="description"><?php esc_html_e( 'Send out an email to the site admin(s) whenever a post is marked as ready for translation', 'babble' ); ?></p>
	<?php
	}

	/**
	 * Notification section description callback function
	 */
	public static function settings_section_callback() { }

	/**
	 * Render option page for babble
	 */
	public static function options_page() {
	?>
		<form action='options.php' method='post'>
			<h2>Babble Settings</h2>
			<?php
			settings_fields( 'babble_notification_page' );
			do_settings_sections( 'babble_notification_page' );
			submit_button();
			?>
		</form>
	<?php

	}
}

Babble_Notification::init();
