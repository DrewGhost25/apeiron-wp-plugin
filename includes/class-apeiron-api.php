<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint: /wp-json/apeiron/v1/verify
 *
 * Verifica on-chain (via RPC JSON) se un wallet ha accesso a un contenuto.
 * Chiama: gateway.hasAccess(walletAddress, contentId, accessType=0)
 */
class Apeiron_Api {

	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'apeiron/v1', '/verify', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'verify_access' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'wallet_address' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validate_address' ],
					'sanitize_callback' => 'sanitize_text_field',
				],
				'content_id' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validate_bytes32' ],
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	/**
	 * Gestisce la richiesta di verifica.
	 */
	public function verify_access( WP_REST_Request $request ): WP_REST_Response {
		$wallet     = $request->get_param( 'wallet_address' );
		$content_id = $request->get_param( 'content_id' );

		$has_access = $this->call_has_access( $wallet, $content_id );

		if ( is_wp_error( $has_access ) ) {
			return new WP_REST_Response(
				[ 'hasAccess' => false, 'error' => $has_access->get_error_message() ],
				502
			);
		}

		return new WP_REST_Response( [ 'hasAccess' => $has_access ], 200 );
	}

	/**
	 * Chiama gateway.hasAccess(wallet, contentId, 0) via eth_call JSON-RPC.
	 *
	 * Signature: hasAccess(address,bytes32,uint8) → bool
	 * Selector:  keccak256("hasAccess(address,bytes32,uint8)")[0:4]
	 */
	private function call_has_access( string $wallet, string $content_id ): bool|WP_Error {
		$rpc_url  = get_option( 'apeiron_rpc_url',         APEIRON_DEFAULT_RPC );
		$gateway  = get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY );

		// Rimuovi 0x se presente da content_id (deve essere 32 byte = 64 hex chars)
		$content_id_hex = ltrim( $content_id, '0x' );
		if ( strlen( $content_id_hex ) !== 64 ) {
			return new WP_Error( 'invalid_content_id', 'content_id deve essere bytes32' );
		}

		// ABI encode: hasAccess(address wallet, bytes32 contentId, uint8 accessType)
		// Selector: 0x... (calcolato staticamente)
		// hasAccess(address,bytes32,uint8) → 4a0f4a07
		$selector   = '4a0f4a07';
		$wallet_pad = str_pad( ltrim( strtolower( $wallet ), '0x' ), 64, '0', STR_PAD_LEFT );
		$type_pad   = str_pad( '0', 64, '0', STR_PAD_LEFT ); // accessType = 0 (human)
		$data       = '0x' . $selector . $wallet_pad . $content_id_hex . $type_pad;

		$payload = wp_json_encode( [
			'jsonrpc' => '2.0',
			'method'  => 'eth_call',
			'params'  => [
				[ 'to' => $gateway, 'data' => $data ],
				'latest',
			],
			'id'      => 1,
		] );

		$response = wp_remote_post( $rpc_url, [
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => $payload,
			'timeout'     => 10,
			'data_format' => 'body',
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['result'] ) ) {
			$msg = $body['error']['message'] ?? 'Risposta RPC non valida';
			return new WP_Error( 'rpc_error', $msg );
		}

		// Il risultato è un uint256 ABI-encoded (32 byte): 0x000...0001 = true, 0x000...0000 = false
		$result_hex = ltrim( $body['result'], '0x' );
		return ( ltrim( $result_hex, '0' ) === '1' );
	}

	// ── Validators ──────────────────────────────────────────────────────────

	public function validate_address( string $value ): bool {
		return (bool) preg_match( '/^0x[0-9a-fA-F]{40}$/', $value );
	}

	public function validate_bytes32( string $value ): bool {
		return (bool) preg_match( '/^0x[0-9a-fA-F]{64}$/', $value );
	}
}
