<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A widget that renders a global site-wide language switcher.
 *
 * Unlike the per-post switcher, this shows all available languages and
 * switches the entire site locale via the ?lang= query parameter.
 * On singular posts with translations it links directly to the translated post.
 */
class Lingua_Widget_Switcher extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'Lingua_switcher',
			__( 'Language Switcher', 'wp-lingua' ),
			array(
				'description' => __( 'Displays a site-wide language switcher.', 'wp-lingua' ),
			)
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values.
	 */
	public function widget( $args, $instance ) {
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$title  = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$style  = ! empty( $instance['style'] ) ? $instance['style'] : 'dropdown';

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		if ( 'buttons' === $style ) {
			echo self::render_global_switcher();
		} else {
			echo Lingua_Frontend::render_dropdown_switcher();
		}

		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$style = ! empty( $instance['style'] ) ? $instance['style'] : 'dropdown';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'wp-lingua' ); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>"
			>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
				<?php esc_html_e( 'Display style:', 'wp-lingua' ); ?>
			</label>
			<select class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>"
			>
				<option value="dropdown" <?php selected( $style, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'wp-lingua' ); ?></option>
				<option value="buttons" <?php selected( $style, 'buttons' ); ?>><?php esc_html_e( 'Buttons', 'wp-lingua' ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Save widget options.
	 *
	 * @param array $new_instance Values from form.
	 * @param array $old_instance Previously saved values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['style'] = in_array( $new_instance['style'], array( 'dropdown', 'buttons' ), true )
			? $new_instance['style']
			: 'dropdown';
		return $instance;
	}

	/**
	 * Render the global language switcher HTML.
	 *
	 * If on a singular post with translations, links directly to translated posts.
	 * Otherwise links to the current URL with ?lang= parameter.
	 *
	 * @return string
	 */
	public static function render_global_switcher() {
		$languages    = Lingua_Languages::get_enabled_languages();
		$current_lang = Lingua_Frontend::get_current_language();

		// Check if we're on a singular post that has translations.
		$translations = array();
		if ( is_singular() ) {
			$post_id  = get_queried_object_id();
			$post_lang = Pressento_Post_Meta::get_language( $post_id );
			if ( $post_lang ) {
				$current_lang = $post_lang;
				$translations = Lingua_Translation_Group::get_translations( $post_id );
			}
		}

		$items = array();
		foreach ( $languages as $code => $label ) {
			if ( $code === $current_lang ) {
				$items[] = sprintf(
					'<span class="lingua-switcher__item lingua-switcher__item--active" aria-current="true">%s</span>',
					esc_html( $label )
				);
			} else {
				// Direct link to translated post if available and published.
				if ( ! empty( $translations[ $code ] ) && 'publish' === get_post_status( $translations[ $code ]->ID ) ) {
					$url = get_permalink( $translations[ $code ]->ID );
				} else {
					$url = add_query_arg( Lingua_Frontend::QUERY_VAR, $code, home_url( '/' ) );
				}

				$items[] = sprintf(
					'<a href="%s" class="lingua-switcher__item" hreflang="%s">%s</a>',
					esc_url( $url ),
					esc_attr( $code ),
					esc_html( $label )
				);
			}
		}

		$html  = '<nav class="lingua-switcher lingua-switcher--global" aria-label="' . esc_attr__( 'Language', 'wp-lingua' ) . '">';
		$html .= implode( ' ', $items );
		$html .= '</nav>';

		return $html;
	}
}
