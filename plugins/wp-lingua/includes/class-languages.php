<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the list of supported languages.
 *
 * Provides a default set and allows filtering via `Lingua_available_languages`.
 */
class Lingua_Languages {

	/**
	 * Get available languages as code => label pairs.
	 *
	 * @return array<string, string>
	 */
	public static function get_available_languages() {
		$defaults = array(
			'ko' => '한국어',
			'en' => 'English',
			'ja' => '日本語',
			'zh' => '中文',
			'es' => 'Español',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'pt' => 'Português',
			'vi' => 'Tiếng Việt',
			'th' => 'ไทย',
		);

		return apply_filters( 'Lingua_available_languages', $defaults );
	}

	/**
	 * Map of short language codes to WordPress locale codes.
	 *
	 * @return array<string, string>
	 */
	public static function get_locale_map() {
		$defaults = array(
			'ko' => 'ko_KR',
			'en' => 'en_US',
			'ja' => 'ja',
			'zh' => 'zh_CN',
			'es' => 'es_ES',
			'fr' => 'fr_FR',
			'de' => 'de_DE',
			'pt' => 'pt_BR',
			'vi' => 'vi',
			'th' => 'th',
		);

		return apply_filters( 'Lingua_locale_map', $defaults );
	}

	/**
	 * Convert a short language code to a WordPress locale string.
	 *
	 * @param string $code Short language code (e.g. "ko").
	 * @return string WP locale (e.g. "ko_KR"). Falls back to the code itself.
	 */
	public static function code_to_locale( $code ) {
		$map = self::get_locale_map();
		return isset( $map[ $code ] ) ? $map[ $code ] : $code;
	}

	/**
	 * Convert a WordPress locale string to a short language code.
	 *
	 * @param string $locale WP locale (e.g. "ko_KR").
	 * @return string Short code (e.g. "ko"). Falls back to the locale itself.
	 */
	public static function locale_to_code( $locale ) {
		$map = array_flip( self::get_locale_map() );
		return isset( $map[ $locale ] ) ? $map[ $locale ] : $locale;
	}

	/**
	 * Get the default language code for the site.
	 *
	 * @return string
	 */
	public static function get_default_language() {
		$saved = get_option( Lingua_Settings::OPTION_DEFAULT_LANG, 'ko' );
		return apply_filters( 'Lingua_default_language', $saved ? $saved : 'ko' );
	}

	/**
	 * Get only the enabled languages (filtered by admin settings).
	 *
	 * @return array<string, string>
	 */
	public static function get_enabled_languages() {
		return Lingua_Settings::get_enabled_languages_with_labels();
	}

	/**
	 * Get the label for a language code.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function get_language_label( $code ) {
		$languages = self::get_available_languages();
		return isset( $languages[ $code ] ) ? $languages[ $code ] : $code;
	}
}
