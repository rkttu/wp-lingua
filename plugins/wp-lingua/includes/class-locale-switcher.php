<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches the WordPress locale dynamically based on the visitor's language.
 *
 * Hooks into the `locale` filter so that all gettext translations, date
 * formats, and other locale-dependent output follow the chosen language.
 * Also loads the corresponding language packs if available.
 */
class Lingua_Locale_Switcher {

	/**
	 * The resolved locale for this request. Cached after first determination.
	 *
	 * @var string|null
	 */
	private $resolved_locale = null;

	public function register_hooks() {
		// Filter locale as early as possible (priority 1).
		add_filter( 'locale', array( $this, 'filter_locale' ), 1 );

		// After the theme is set up, ensure language files are loaded.
		add_action( 'after_setup_theme', array( $this, 'load_language_packs' ), 1 );
	}

	/**
	 * Override the WordPress locale based on the visitor's language choice.
	 *
	 * On singular posts that have a language meta, the post's own language
	 * takes priority. Otherwise the visitor's cookie/query param is used.
	 *
	 * @param string $locale Current WP locale.
	 * @return string Filtered locale.
	 */
	public function filter_locale( $locale ) {
		// Don't touch admin locale.
		if ( is_admin() ) {
			return $locale;
		}

		if ( null !== $this->resolved_locale ) {
			return $this->resolved_locale;
		}

		$lang = $this->determine_language();

		if ( $lang ) {
			$this->resolved_locale = Lingua_Languages::code_to_locale( $lang );
		} else {
			$this->resolved_locale = $locale;
		}

		return $this->resolved_locale;
	}

	/**
	 * Determine the language for the current request.
	 *
	 * On singular views the post meta takes priority, otherwise we fall back
	 * to the ?lang= parameter, then the cookie, then the default.
	 *
	 * @return string Language code.
	 */
	private function determine_language() {
		// On singular views, use the post's language if available.
		// Note: this runs via the locale filter which may fire before
		// the main query is parsed, so we also check the cookie/QV.
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$post_lang = Lingua_Post_Meta::get_language( $queried->ID );
			if ( $post_lang ) {
				return $post_lang;
			}
		}

		// Fall back to the frontend language detection logic.
		return Lingua_Frontend::get_current_language();
	}

	/**
	 * Ensure WordPress loads the .mo files for the switched locale.
	 */
	public function load_language_packs() {
		if ( is_admin() ) {
			return;
		}

		$locale = $this->resolved_locale;
		if ( ! $locale || 'en_US' === $locale ) {
			return;
		}

		// Load core translations.
		load_default_textdomain( $locale );

		// Reload the active theme's textdomain.
		$theme = wp_get_theme();
		$textdomain = $theme->get( 'TextDomain' );
		if ( $textdomain ) {
			unload_textdomain( $textdomain );
			load_theme_textdomain( $textdomain, $theme->get_stylesheet_directory() . '/languages' );
		}
	}

	/**
	 * Reset cached locale (useful in tests).
	 */
	public function reset() {
		$this->resolved_locale = null;
	}
}
