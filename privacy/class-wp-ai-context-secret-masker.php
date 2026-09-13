<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Secret Masker
 * Redacts sensitive information from the exported settings.
 */
class WP_AI_Context_Secret_Masker {

	/**
	 * List of keywords to identify sensitive keys.
	 */
	private $sensitive_keywords = array(
		'key',
		'secret',
		'token',
		'password',
		'pwd',
		'auth',
		'hash',
		'salt',
		'api_key',
		'client_secret',
	);

	/**
	 * Recursively masks sensitive values in an array.
	 *
	 * @param mixed $data The data to sanitize (string or array).
	 * @return mixed Sanitized data.
	 */
	public function mask( $data ) {
		// If it's a string, check if it's JSON
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				// It was JSON! Mask it and re-encode
				return wp_json_encode( $this->mask( $decoded ) );
			}
			return $this->mask_string( $data );
		}

		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( $this->is_sensitive_key( $key ) ) {
				$data[ $key ] = '********'; // Masked
			} else {
				$data[ $key ] = $this->mask( $value );
			}
		}

		return $data;
	}

	private function is_sensitive_key( $key ) {
		$key = strtolower( $key );
		foreach ( $this->sensitive_keywords as $keyword ) {
			if ( strpos( $key, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Masks sensitive patterns in raw text (like logs or theme source).
	 */
	public function mask_string( $text ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}
		
		// Regex to redact obvious API keys, tokens, and passwords
		$new_text = preg_replace( '/([a-zA-Z0-9_-]*(?:key|secret|token|password|pwd|auth|hash|salt)[a-zA-Z0-9_-]*\s*[:=]\s*[\'"]?)([a-zA-Z0-9\-_]{16,})([\'"]?)/i', '$1********$3', $text );
		if ( $new_text !== null ) $text = $new_text;

		// Regex to redact WordPress wp-config.php define() constants for passwords and salts
		$new_text = preg_replace( '/(define\s*\(\s*[\'"][^\'"]*(?:KEY|SECRET|TOKEN|PASSWORD|PWD|AUTH|HASH|SALT)[^\'"]*[\'"]\s*,\s*[\'"])(.*?)([\'"]\s*\))/i', '$1********$3', $text );
		if ( $new_text !== null ) $text = $new_text;
		
		// Specific formats
		$new_text = preg_replace( '/(sk-[a-zA-Z0-9]{20,})/i', 'sk-********', $text );
		if ( $new_text !== null ) $text = $new_text;
		
		$new_text = preg_replace( '/(Bearer\s+)[a-zA-Z0-9\-\._~+\/]+=*/i', '$1********', $text );
		if ( $new_text !== null ) $text = $new_text;
		
		$new_text = preg_replace( '/([a-z]+:\/\/[^:]+:)([^@]+)(@)/i', '$1********$3', $text ); // URIs with passwords
		if ( $new_text !== null ) $text = $new_text;
		
		// DPDP ACT PII REDACTION
		// Redact Email Addresses
		$new_text = preg_replace( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $text );
		if ( $new_text !== null ) $text = $new_text;
		
		// Redact IPv4 Addresses (naive match, sufficient for logs)
		$new_text = preg_replace( '/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/', '[REDACTED_IP]', $text );
		if ( $new_text !== null ) $text = $new_text;
		
		// Redact standard Indian/International phone numbers (simplified to prevent backtracking)
		$new_text = preg_replace( '/\+?[0-9]{1,3}?[\s\-]?(?:\(0\))?[\s\-]?[0-9]{3,4}[\s\-]?[0-9]{3,4}[\s\-]?[0-9]{3,4}/', '[REDACTED_PHONE]', $text );
		if ( $new_text !== null ) $text = $new_text;

		return $text;
	}
}
