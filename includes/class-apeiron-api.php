<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints Apeiron:
 *
 * GET /wp-json/apeiron/v1/verify
 *   Verifica on-chain se un wallet ha accesso (usato dal frontend JS).
 *
 * GET /wp-json/apeiron/v1/content/<post_id>
 *   Endpoint x402 machine-readable per agenti AI:
 *   - Senza header x-wallet-address → HTTP 402 con istruzioni pagamento
 *   - Con header x-wallet-address   → verifica on-chain → HTTP 200 con contenuto
 */
class Apeiron_Api {

	// Bot User-Agent regex — identico al SDK Apeiron Node.js
	const KNOWN_BOTS = '/GPTBot|ChatGPT-User|ClaudeBot|Claude-Web|Google-Extended|Googlebot|PerplexityBot|YouBot|Diffbot|CCBot|FacebookBot|Applebot|BingBot|anthropic|openai|bot|crawler|spider|X402-Agent/i';

	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {

		// ── Endpoint verifica (usato dal JS frontend) ─────────────────────────
		// Intenzionalmente pubblico: dati on-chain pubblici, nessun dato sensibile.
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

		// ── Endpoint x402 per agenti AI ───────────────────────────────────────
		// Intenzionalmente pubblico: protocollo x402, dati on-chain pubblici.
		register_rest_route( 'apeiron/v1', '/content/(?P<post_id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'x402_content' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'post_id' => [
					'required'          => true,
					'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v > 0,
				],
			],
		] );

		// ── Endpoint statistiche bot (autenticato con Stats API Key) ──────────
		register_rest_route( 'apeiron/v1', '/bot-stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_bot_stats' ],
			'permission_callback' => [ $this, 'check_stats_key' ],
		] );
	}

	/**
	 * Verifica la Stats API Key dalla header o dal parametro GET.
	 */
	public function check_stats_key( WP_REST_Request $request ): bool {
		$stored_key = get_option( 'apeiron_stats_api_key', '' );
		if ( ! $stored_key ) {
			return false;
		}

		$provided = $request->get_header( 'x_apeiron_stats_key' );
		if ( ! $provided ) {
			$provided = $request->get_param( 'key' );
		}

		return hash_equals( $stored_key, (string) $provided );
	}

	/**
	 * GET /wp-json/apeiron/v1/bot-stats
	 * Ritorna le statistiche dei bot per gli ultimi N giorni.
	 */
	public function get_bot_stats( WP_REST_Request $request ): WP_REST_Response {
		$days  = (int) ( $request->get_param( 'days' ) ?: 7 );
		$days  = max( 1, min( 365, $days ) );
		$stats = ( new Apeiron_Logger() )->get_stats( $days );
		return new WP_REST_Response( $stats, 200 );
	}

	// ── /verify ─────────────────────────────────────────────────────────────

	public function verify_access( WP_REST_Request $request ): WP_REST_Response {
		$wallet     = $request->get_param( 'wallet_address' );
		$content_id = $request->get_param( 'content_id' );
		$has_access = $this->call_has_access( $wallet, $content_id, 0 );

		if ( is_wp_error( $has_access ) ) {
			return new WP_REST_Response(
				[ 'hasAccess' => false, 'error' => $has_access->get_error_message() ],
				502
			);
		}

		return new WP_REST_Response( [ 'hasAccess' => $has_access ], 200 );
	}

	// ── /content/<post_id> — x402 ────────────────────────────────────────────

	public function x402_content( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		// Post non trovato o non pubblicato
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
		}

		// Legge modalità con retrocompatibilità
		$mode = get_post_meta( $post_id, '_apeiron_mode', true );
		if ( ! $mode ) {
			$mode = get_post_meta( $post_id, '_apeiron_protected', true ) === '1' ? 'full' : 'disabled';
		}

		// Nessuna protezione → accesso libero
		if ( 'disabled' === $mode ) {
			return $this->response_content( $post );
		}

		// Dati on-chain
		$content_id  = get_post_meta( $post_id, '_apeiron_content_id', true );
		$human_price = get_post_meta( $post_id, '_apeiron_human_price', true ) ?: '0.10';
		$ai_price    = get_post_meta( $post_id, '_apeiron_ai_price', true )    ?: '1.00';

		if ( ! $content_id ) {
			return new WP_REST_Response(
				[ 'error' => 'Content not registered on-chain yet' ],
				503
			);
		}

		// Rilevamento bot via User-Agent
		$user_agent  = $request->get_header( 'user_agent' ) ?? '';
		$is_bot      = (bool) preg_match( self::KNOWN_BOTS, $user_agent );
		$access_type = $is_bot ? 1 : 0;
		$price_usdc  = $is_bot ? $ai_price : $human_price;

		// Modalità ai_only → umani ricevono contenuto libero via REST
		if ( 'ai_only' === $mode && ! $is_bot ) {
			return $this->response_content( $post );
		}
		$price_wei   = $this->usdc_to_wei( $price_usdc );

		$gateway_address = get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ) ?: APEIRON_DEFAULT_GATEWAY;
		$usdc_address    = get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC )    ?: APEIRON_DEFAULT_USDC;

		// ── Nessun wallet → HTTP 402 ─────────────────────────────────────────
		$wallet = $request->get_header( 'x_wallet_address' );
		if ( ! $wallet || ! preg_match( '/^0x[0-9a-fA-F]{40}$/', $wallet ) ) {
			return new WP_REST_Response( [
				'error'          => 'Payment Required',
				'protocol'       => 'x402',
				'version'        => '1.0',
				'gatewayAddress' => $gateway_address,
				'usdcAddress'    => $usdc_address,
				'contentId'      => $content_id,
				'accessType'     => $is_bot ? 'AI_LICENSE' : 'HUMAN_READ',
				'price'          => $price_wei,
				'priceFormatted' => number_format( (float) $price_usdc, 6 ) . ' USDC',
				'instructions'   => [
					'step1' => sprintf( 'USDC.approve("%s", %s)', $gateway_address, $price_wei ),
					'step2' => $is_bot
						? sprintf( 'gateway.unlockAsAgent("%s", 2592000)', $content_id )
						: sprintf( 'gateway.unlockAsHuman("%s", 0)', $content_id ),
				],
				'network'        => [ 'name' => 'Base', 'chainId' => APEIRON_CHAIN_ID ],
				'postId'         => $post_id,
				'postUrl'        => get_permalink( $post_id ),
			], 402 );
		}

		// ── Wallet presente → verifica on-chain ──────────────────────────────
		$has_access = $this->call_has_access( $wallet, $content_id, $access_type );

		if ( is_wp_error( $has_access ) ) {
			return new WP_REST_Response(
				[ 'error' => 'RPC error', 'detail' => $has_access->get_error_message() ],
				502
			);
		}

		if ( ! $has_access ) {
			return new WP_REST_Response( [
				'error'     => 'Payment Required',
				'reason'    => 'No valid access found on-chain for this wallet',
				'wallet'    => $wallet,
				'contentId' => $content_id,
			], 402 );
		}

		// ── Accesso verificato → HTTP 200 ────────────────────────────────────
		return $this->response_content( $post, $wallet, $access_type );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function response_content( WP_Post $post, string $wallet = '', int $access_type = 0 ): WP_REST_Response {
		return new WP_REST_Response( [
			'postId'     => $post->ID,
			'title'      => $post->post_title,
			'body'       => wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ),
			'url'        => get_permalink( $post->ID ),
			'author'     => get_the_author_meta( 'display_name', $post->post_author ),
			'date'       => $post->post_date,
			'accessedBy' => $wallet ?: 'public',
			'accessType' => $access_type === 1 ? 'AI_LICENSE' : 'HUMAN_READ',
		], 200 );
	}

	/**
	 * Chiama gateway.hasAccess(wallet, contentId, accessType) via eth_call.
	 * Selector hasAccess(address,bytes32,uint8) = 4a0f4a07
	 */
	private function call_has_access( string $wallet, string $content_id, int $access_type = 0 ): bool|WP_Error {
		$rpc_url  = get_option( 'apeiron_rpc_url',         APEIRON_DEFAULT_RPC )     ?: APEIRON_DEFAULT_RPC;
		$gateway  = get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ) ?: APEIRON_DEFAULT_GATEWAY;

		$content_id_hex = ltrim( $content_id, '0x' );
		if ( strlen( $content_id_hex ) !== 64 ) {
			return new WP_Error( 'invalid_content_id', 'content_id must be bytes32' );
		}

		$selector   = '24ea5704';
		$wallet_pad = str_pad( ltrim( strtolower( $wallet ), '0x' ), 64, '0', STR_PAD_LEFT );
		$type_pad   = str_pad( dechex( $access_type ), 64, '0', STR_PAD_LEFT );
		$data       = '0x' . $selector . $wallet_pad . $content_id_hex . $type_pad;

		$payload = wp_json_encode( [
			'jsonrpc' => '2.0',
			'method'  => 'eth_call',
			'params'  => [ [ 'to' => $gateway, 'data' => $data ], 'latest' ],
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
			return new WP_Error( 'rpc_error', $body['error']['message'] ?? 'Invalid RPC response' );
		}

		$result_hex = ltrim( $body['result'], '0x' );
		return ( ltrim( $result_hex, '0' ) === '1' );
	}

	private function usdc_to_wei( string $amount ): string {
		return (string) (int) ( (float) $amount * 1_000_000 );
	}

	public function validate_address( string $value ): bool {
		return (bool) preg_match( '/^0x[0-9a-fA-F]{40}$/', $value );
	}

	public function validate_bytes32( string $value ): bool {
		return (bool) preg_match( '/^0x[0-9a-fA-F]{64}$/', $value );
	}
}
