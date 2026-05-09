<?php
/**
 * Apeiron helper functions for safe superglobal access.
 *
 * Centralizes sanitization logic to comply with WordPress Coding Standards.
 * All superglobal access in the plugin should go through this class.
 *
 * @package Apeiron
 */

defined( 'ABSPATH' ) || exit;

class Apeiron_Helpers {

	/**
	 * Get the remote IP address, sanitized and validated.
	 *
	 * @return string Sanitized IP address, or empty string if invalid/unavailable.
	 */
	public static function get_remote_ip(): string {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Get the User-Agent header, sanitized.
	 *
	 * @return string Sanitized User-Agent string, or empty string if unavailable.
	 */
	public static function get_user_agent(): string {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * Get a sanitized HTTP header value from $_SERVER.
	 *
	 * @param string $header The $_SERVER key (e.g., 'HTTP_X_APEIRON_AGENT_ID').
	 * @return string Sanitized header value, or empty string if not present.
	 */
	public static function get_header( string $header ): string {
		if ( ! isset( $_SERVER[ $header ] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
	}

	/**
	 * Get the request URI, sanitized.
	 *
	 * @return string Sanitized request URI, or empty string if unavailable.
	 */
	public static function get_request_uri(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Get a sanitized string value from $_POST.
	 *
	 * @param string $key     The $_POST key.
	 * @param string $default Default value if key is not present.
	 * @return string Sanitized string value.
	 */
	public static function get_post_text( string $key, string $default = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $default;
		}
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Get a sanitized integer value from $_POST.
	 *
	 * @param string $key     The $_POST key.
	 * @param int    $default Default value if key is not present.
	 * @return int Sanitized integer value.
	 */
	public static function get_post_int( string $key, int $default = 0 ): int {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $default;
		}
		return absint( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Get a sanitized string value from $_GET.
	 *
	 * @param string $key     The $_GET key.
	 * @param string $default Default value if key is not present.
	 * @return string Sanitized string value.
	 */
	public static function get_get_text( string $key, string $default = '' ): string {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $default;
		}
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get a sanitized integer value from $_GET.
	 *
	 * @param string $key     The $_GET key.
	 * @param int    $default Default value if key is not present.
	 * @return int Sanitized integer value.
	 */
	public static function get_get_int( string $key, int $default = 0 ): int {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $default;
		}
		return absint( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
