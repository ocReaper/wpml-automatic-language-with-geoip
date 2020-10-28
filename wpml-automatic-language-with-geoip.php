<?php

/**
 * Plugin Name: WPML Automatic language with GeoIP
 * Plugin URI:  https://github.com/ocReaper/wpml-automatic-language-with-geoip
 * Description: Automatically changes the load of language in WPML based on the GeoIP service
 * Version:     0.1.0
 * Author:      ocReaper
 * Author URI:  https://github.com/ocReaper
 * Donate link: https://github.com/ocReaper
 * License:     MIT
 */

require 'vendor/autoload.php';

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use WPML\Language\Detection\CookieLanguage;
use WPML\Language\Detection\Frontend;

/**
 * Class Custom_WPML_Frontend_Request
 *
 * @since 04/06/2016
 */
class Custom_WPML_Frontend_Request extends Frontend {
	public function get_requested_lang() {
		if ( is_null( $this->get_request_uri_lang() ) ) {
			return $this->get_cookie_lang();
		}

		return $this->get_request_uri_lang();
	}
}

/**
 * Class Wpml_automatic_language_with_geoip
 *
 * @since 04/06/2016
 */
class Wpml_automatic_language_with_geoip {

	protected $redirected_cookie_name = 'language-redirected';

	protected $preferred_default_language = '';

	/**
	 * Wpml_automatic_language_with_geoip constructor.
	 *
	 * @since 04/06/2016
	 */
	public function __construct() {
		if ( is_admin() ) {
			return;
		}

		$this->preferred_default_language = apply_filters( 'wpml_automatic_language_with_geoip_preferred_default_language', $this->preferred_default_language );
		add_action( 'plugins_loaded', array( &$this, 'replace_wpml_frontend_request_handler' ), - 1 );
		add_action( 'plugins_loaded', array( &$this, 'wpml_language_redirect_via_geoip' ), 11 );
	}

	/**
	 * Fix WPML's cookie handling, because of on "/" it'll return null and clears the lang cookie, which is bad for us.
	 *
	 * @since 04/06/2016
	 */
	public function replace_wpml_frontend_request_handler() {
		global $wpml_request_handler, $wpml_url_converter, $wpml_language_resolution, $sitepress;

		$cookieLanguage = new CookieLanguage( new WPML_Cookie(), $sitepress->get_default_language() );

		$wpml_request_handler = new Custom_WPML_Frontend_Request(
			$wpml_url_converter,
			$wpml_language_resolution->get_active_language_codes(),
			$sitepress->get_default_language(),
			$cookieLanguage,
			new WPML_WP_API()
		);
	}

	/**
	 * Do the language change
	 *
	 * @since 04/06/2016
	 */
	public function wpml_language_redirect_via_geoip() {
		if ( ! isset( $_COOKIE[ $this->redirected_cookie_name ] ) || empty( $_COOKIE[ $this->redirected_cookie_name ] ) ) {
			return;
		}

		global $sitepress;
		$language          = $this->get_geoip_country_code();
		$filtered_language = $this->map_country_code_to_wpml_language( $language );
		$is_not_filtered   = strtolower( $language ) === $filtered_language;
		$is_not_exists     = ! array_key_exists( $filtered_language, wpml_get_active_languages_filter( null ) );

		if ( $is_not_filtered && $is_not_exists ) {
			if ( ! empty( $this->preferred_default_language ) ) {
				$default_language = $this->preferred_default_language;
			} else {
				$default_language = $sitepress->get_default_language();
			}

			$sitepress->switch_lang( $default_language, true );
		} else {
			$sitepress->switch_lang( $filtered_language, true );
		}
		setcookie( $this->redirected_cookie_name, 1, strtotime( '+7 days' ), '/' );

	}

	/**
	 * Return the visitor's country code based on various GeoIP services
	 *
	 * @return string
	 * @throws \GeoIp2\Exception\AddressNotFoundException
	 * @throws \MaxMind\Db\Reader\InvalidDatabaseException
	 * @since 04/06/2016
	 */
	protected function get_geoip_country_code() {
		if ( $this->is_wp_engine_server() ) {
			return getenv( 'HTTP_GEOIP_COUNTRY_CODE' );
		}

		$remote_address = isset($_SERVER['REMOTE_ADDR']) ? filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) : '127.0.0.1';
		$reader = new Reader( plugin_dir_path( __FILE__ ) . 'assets/GeoLite2-Country.mmdb' );

		try {
			$record = $reader->country( $remote_address );
		} catch ( AddressNotFoundException $e ) {
			return ICL_LANGUAGE_CODE;
		}

		return $record->country->isoCode;
	}

	/**
	 * Return the language code of the visitor by mapping the GeoIP country code to the WPML languages
	 *
	 * @param string $language
	 *
	 * @return string
	 * @since 04/06/2016
	 */
	protected function map_country_code_to_wpml_language( $language ) {
		$iso_3166_2_to_gettext_map = require_once 'assets/iso-3166-2-to-gettext.php';
		$iso_3166_2_to_gettext_map = apply_filters( 'wpml_automatic_language_with_geoip_country_code_map', $iso_3166_2_to_gettext_map );

		return $iso_3166_2_to_gettext_map[ $language ];
	}

	/**
	 * Determine if the hosting provider is WP Engine
	 *
	 * @return bool
	 * @since 04/06/2016
	 */
	protected function is_wp_engine_server() {
		return class_exists( 'WPE_API', false );
	}
}

new Wpml_automatic_language_with_geoip();
