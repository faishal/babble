<?php

add_action( 'widgets_init', 'babble_widget' );
function babble_widget() {
	register_widget( 'Babble_Widget' );
}

class Babble_Widget extends WP_Widget {

	function __construct() {
		parent::__construct(
			'bbl_widget', // Base ID
			__( 'Language Switcher','babble' ), // Name
			array( 'description' => __( 'Displays a list of links to translations / equivalent pages.', 'babble' ) ) // Args
		);
	}

	function widget( $args, $instance ) {

		$args = array_merge( array(
			'show_as' => 'dropdown',
		), $args );

		echo $args['before_widget']; // @codingStandardsIgnoreLine

		echo $args['before_title'] . esc_html__( 'Languages', 'babble' ) . $args['after_title']; // @codingStandardsIgnoreLine

		$list = bbl_get_switcher_links();

		switch ( $instance['show_as'] ) {

			case 'dropdown':
				echo '<select onchange="document.location.href=this.options[this.selectedIndex].value;">';
				foreach ( $list as $item ) :

					if ( $item['active'] ) {
						$selected = 'selected="selected" ';
					} else {
						$selected = '';
					}

					if ( in_array( 'bbl-add',$item['classes'] ) ) {
						/*
						We're logged in, there's no translation
						of this page as yet, but the user has the
						ability to create one; so here's a link
						to allow him/her to do so
						*/
						echo '<option ' . $selected . 'class="' . esc_attr( $item['class'] ) . '" value="' . esc_url( $item['href'] ) . '">' . esc_html( $item['lang']->display_name ) . ' [' . esc_html__( 'Add' ) . ']</option>'; // @codingStandardsIgnoreLine
					} elseif ( $item['href'] ) {
						/*
						Means there is a translation of this page
						into the language in question
						*/
						echo '<option ' . $selected . 'class="' . esc_attr( $item['class'] ) . '" value="' . esc_url( $item['href'] ) . '">' . esc_html( $item['lang']->display_name ) . '</option>'; // @codingStandardsIgnoreLine
					} elseif ( 'on' === $instance['show_if_unavailable'] ) {
						/*
						We're on the front end, but there is
						no translation into this language as yet;
						the user has said 'Show if unavailable'
						in the widget management screen.
						Applies a no-translation class to the <li>,
						and a bbl-no-translation class to the <div>
						in case you want to 'grey it out' somehow
						*/
						echo '<option disabled class="' . esc_attr( $item['class'] ) . '" value="">' . esc_html( $item['lang']->display_name ) . '</option>';
					}
				endforeach;
				echo '</select>';
		break;

			case 'list':
				echo '<ul class="languages_list">';
				foreach ( $list as $item ) :

					if ( in_array( 'bbl-add',$item['classes'] ) ) {
						/*
						We're logged in, there's no translation
						of this page as yet, but the user has the
						ability to create one; so here's a link
						to allow him/her to do so
						*/
						echo '<li><a href="' . esc_url( $item['href'] ) . '" class="' . esc_attr( $item['class'] ) . '">' . esc_html( $item['lang']->display_name ) . ' [' . esc_html__( 'Add', 'babble' ) . ']</a></li>'; // @codingStandardsIgnoreLine
					} elseif ( $item['href'] ) {
						/*
						Means there is a translation of this page
						into the language in question
						*/
						echo '<li><a href="' . esc_url( $item['href'] ) . '" class="' . esc_attr( $item['class'] ) . '">' . esc_html( $item['lang']->display_name ) . '</a></li>';
					} elseif ( 'on' === $instance['show_if_unavailable'] ) {
						/*
						We're on the front end, but there is
						no translation into this language as yet;
						the user has said 'Show if unavailable'
						in the widget management screen.
						Applies a no-translation class to the <li>,
						and a bbl-no-translation class to the <div>
						in case you want to 'grey it out' somehow
						*/
						echo '<li class="no-translation"><div class="bbl-no-translation">' . esc_html( $item['lang']->display_name ) . '</div></li>';
					}

				endforeach;
				echo '</ul>';
		break;

		}

		echo $args['after_widget']; // @codingStandardsIgnoreLine
	}

	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;
		$instance['show_as'] = strip_tags( $new_instance['show_as'] );
		$instance['show_if_unavailable'] = strip_tags( $new_instance['show_if_unavailable'] );
		return $instance;

	}

	function form( $instance ) {

		global $wpdb;
		$defaults = array( 'show_if_unavailable' => 'off' );
		$instance = wp_parse_args( $instance, $defaults );

		?>
		<p>
			<?php esc_html_e( 'Show as:','babble' ); ?>
			<select id="<?php echo esc_attr( $this->get_field_id( 'show_as' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_as' ) ); ?>">
				<option value="dropdown" <?php selected( $instance['show_as'],'dropdown' ); ?>><?php esc_html_e( 'Dropdown','babble' ); ?></option>
				<option value="list" <?php selected( $instance['show_as'],'list' ); ?>><?php esc_html_e( 'List','babble' ); ?></option>
			</select>
		</p>
		<p>
			<input id="<?php echo esc_attr( $this->get_field_id( 'show_if_unavailable' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_if_unavailable' ) ); ?>" type="checkbox" <?php checked( 'on', $instance['show_if_unavailable'] ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_if_unavailable' ) ); ?>"><?php esc_html_e( 'Show all languages in widget, even if there is no translation / equivalent page?', 'babble' ); ?></label>
		</p>
		<p class="description">
			<?php esc_html_e( "Don't worry: if there's no equivalent page, the link won't be clickable.",'babble' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Links allowing logged-in administrators to add translations will always be shown.','babble' ); ?>
		</p>

		<?php
	}
}
