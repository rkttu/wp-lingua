<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page for WP Lingua.
 *
 * Allows the admin to choose which languages are enabled (visible in switchers).
 */
class Lingua_Settings {

	const OPTION_ENABLED_LANGS  = 'Lingua_enabled_languages';
	const OPTION_DEFAULT_LANG   = 'Lingua_default_language';

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu_page() {
		add_options_page(
			__( 'Lingua Languages', 'wp-lingua' ),
			__( 'Lingua Languages', 'wp-lingua' ),
			'manage_options',
			'lingua-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( 'Lingua_settings', self::OPTION_ENABLED_LANGS, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_enabled_languages' ),
			'default'           => array(),
		) );

		register_setting( 'Lingua_settings', self::OPTION_DEFAULT_LANG, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'ko',
		) );

		add_settings_section(
			'Lingua_main',
			__( 'Language Settings', 'wp-lingua' ),
			'__return_false',
			'lingua-settings'
		);

		add_settings_field(
			'Lingua_enabled_languages',
			__( 'Enabled Languages', 'wp-lingua' ),
			array( $this, 'render_enabled_languages_field' ),
			'lingua-settings',
			'Lingua_main'
		);

		add_settings_field(
			'Lingua_default_language',
			__( 'Default Language', 'wp-lingua' ),
			array( $this, 'render_default_language_field' ),
			'lingua-settings',
			'Lingua_main'
		);
	}

	/**
	 * Sanitize the enabled languages option.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public function sanitize_enabled_languages( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$all = Lingua_Languages::get_available_languages();
		return array_values( array_intersect( $value, array_keys( $all ) ) );
	}

	/**
	 * Get the list of enabled language codes.
	 *
	 * If nothing is saved yet, returns all available languages (backwards compat).
	 *
	 * @return string[]
	 */
	public static function get_enabled_languages() {
		$saved = get_option( self::OPTION_ENABLED_LANGS, array() );

		if ( empty( $saved ) ) {
			return array_keys( Lingua_Languages::get_available_languages() );
		}

		return (array) $saved;
	}

	/**
	 * Get enabled languages as code => label pairs.
	 *
	 * @return array<string, string>
	 */
	public static function get_enabled_languages_with_labels() {
		$enabled = self::get_enabled_languages();
		$all     = Lingua_Languages::get_available_languages();
		$result  = array();

		foreach ( $enabled as $code ) {
			if ( isset( $all[ $code ] ) ) {
				$result[ $code ] = $all[ $code ];
			}
		}

		return $result;
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lingua Language Settings', 'wp-lingua' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'Lingua_settings' );
				do_settings_sections( 'lingua-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_enabled_languages_field() {
		$all     = Lingua_Languages::get_available_languages();
		$enabled = self::get_enabled_languages();

		echo '<fieldset>';
		foreach ( $all as $code => $label ) {
			$checked = in_array( $code, $enabled, true ) ? 'checked' : '';
			printf(
				'<label style="display:inline-block;margin-right:2em;margin-bottom:0.8em;padding:0.3em 0.6em;border:1px solid #ddd;border-radius:4px;cursor:pointer"><input type="checkbox" name="%s[]" value="%s" %s style="margin-right:0.4em"> %s (%s)</label>',
				esc_attr( self::OPTION_ENABLED_LANGS ),
				esc_attr( $code ),
				$checked,
				esc_html( $label ),
				esc_html( $code )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Only checked languages will appear in the language switcher.', 'wp-lingua' ) . '</p>';
	}

	public function render_default_language_field() {
		$all     = Lingua_Languages::get_available_languages();
		$default = get_option( self::OPTION_DEFAULT_LANG, 'ko' );

		echo '<select name="' . esc_attr( self::OPTION_DEFAULT_LANG ) . '">';
		foreach ( $all as $code => $label ) {
			printf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr( $code ),
				selected( $default, $code, false ),
				esc_html( $label ),
				esc_html( $code )
			);
		}
		echo '</select>';
	}
}
