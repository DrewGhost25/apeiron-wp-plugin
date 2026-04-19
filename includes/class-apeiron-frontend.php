<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestisce il paywall sul frontend.
 *
 * Modalità:
 *  - 'full'           → paywall JS per umani + bot bloccati via x402 endpoint
 *  - 'ai_only'        → umani leggono gratis, bot intercettati con 402
 *  - 'registry_log'   → logga agenti verificati, serve contenuto a tutti
 *  - 'registry_block'  → richiede Apeiron Registry ID, altrimenti 401
 *  - 'disabled'       → nessuna protezione
 *
 * Registry features:
 *  - Transient cache (1h) per evitare chiamate API ad ogni richiesta bot
 *  - Fail-open: se l'API Apeiron è irraggiungibile, serve comunque il contenuto
 *  - Email debounce: notifica solo primo accesso per agente per 24h
 *  - X-Apeiron-Verified header in risposta
 *  - IP + User-Agent inviati per compliance/provenance
 */
class Apeiron_Frontend {

	// Bot User-Agent regex — identico a class-apeiron-api.php e SDK Node.js
	const KNOWN_BOTS = '/GPTBot|ChatGPT-User|ClaudeBot|Claude-Web|Google-Extended|Googlebot|PerplexityBot|YouBot|Diffbot|CCBot|FacebookBot|Applebot|BingBot|anthropic|openai|bot|crawler|spider|X402-Agent/i';

	// Cache TTL for verified agents (in seconds)
	const VERIFY_CACHE_TTL = 3600; // 1 hour

	// Debounce TTL for email notifications per agent (in seconds)
	const EMAIL_DEBOUNCE_TTL = 86400; // 24 hours

	// Max time to wait for Apeiron API (in seconds)
	const API_TIMEOUT = 2;

	public function init(): void {
		add_action( 'template_redirect',   [ $this, 'maybe_intercept_bot' ], 1 );
		add_filter( 'the_content',         [ $this, 'maybe_apply_paywall' ], 99 );
		add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	// ── Intercettazione bot ──────────────────────────────────────────────────

	public function maybe_intercept_bot(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id    = get_the_ID();
		$mode       = $this->get_mode( $post_id );
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		// ── DETECT mode: always log every AI bot hit ──────────────────────────
		$detector = new Apeiron_Detector();
		$bot_info = $detector->detect( $user_agent );
		if ( $bot_info['detected'] ) {
			$agent_id_header = $_SERVER['HTTP_X_APEIRON_AGENT_ID'] ?? '';
			$logger          = new Apeiron_Logger();
			$logger->log(
				$bot_info,
				$post_id,
				$user_agent,
				$_SERVER['REMOTE_ADDR'] ?? '',
				$agent_id_header,
				false,
				'logged'
			);
		}

		// ── Registry modes ───────────────────────────────────────────────────
		if ( in_array( $mode, [ 'registry_log', 'registry_block' ], true ) ) {
			if ( ! preg_match( self::KNOWN_BOTS, $user_agent ) ) {
				return; // umano → lascia passare
			}

			$agent_id = $_SERVER['HTTP_X_APEIRON_AGENT_ID'] ?? '';
			$api_key  = $_SERVER['HTTP_X_APEIRON_API_KEY']  ?? '';

			if ( $agent_id && $api_key ) {
				// ── FIX 1: Transient cache — evita chiamate API duplicate ─────
				$cache_key = 'apeiron_v_' . md5( $agent_id . $api_key );
				$cached    = get_transient( $cache_key );

				if ( false !== $cached ) {
					// Cache hit — skip API call
					if ( $cached['verified'] ) {
						// ── FIX 5: Confirmation header ───────────────────────
						header( 'X-Apeiron-Verified: true' );
						header( 'X-Apeiron-Agent: ' . sanitize_text_field( $agent_id ) );

						// Log this access (non-blocking) but skip email (debounce)
						$this->log_verified_access_async( $agent_id, $api_key, $post_id, $user_agent, false );
						return;
					}
					// Cached as invalid → reject
					http_response_code( 401 );
					header( 'Content-Type: application/json; charset=utf-8' );
					echo wp_json_encode( [
						'error'        => 'Invalid agent credentials',
						'code'         => $cached['code'] ?? 'INVALID',
						'register_url' => 'https://www.apeiron-registry.com/register',
					] );
					exit;
				}

				// ── Cache miss — call Apeiron API ────────────────────────────
				$result = $this->verify_with_registry( $agent_id, $api_key, $post_id, $user_agent );

				// Cache the result (1 hour for valid, 5 min for invalid)
				$ttl = $result['verified'] ? self::VERIFY_CACHE_TTL : 300;
				set_transient( $cache_key, $result, $ttl );

				if ( $result['verified'] ) {
					// ── FIX 5: Confirmation header ───────────────────────────
					header( 'X-Apeiron-Verified: true' );
					header( 'X-Apeiron-Agent: ' . sanitize_text_field( $agent_id ) );
					return;
				}

				// ── FIX 4: Fail-open check ───────────────────────────────────
				// If the result code is REGISTRY_UNREACHABLE, we already passed
				// through in verify_with_registry(). This path means invalid creds.
				http_response_code( 401 );
				header( 'Content-Type: application/json; charset=utf-8' );
				echo wp_json_encode( [
					'error'        => 'Invalid agent credentials',
					'code'         => $result['code'] ?? 'INVALID',
					'register_url' => 'https://www.apeiron-registry.com/register',
				] );
				exit;
			}

			// Nessun header Registry
			if ( 'registry_block' === $mode ) {
				http_response_code( 401 );
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'X-Apeiron-Protocol: registry-v1' );
				echo wp_json_encode( [
					'error'            => 'Agent identification required',
					'protocol'         => 'Apeiron Registry v1.0',
					'register_url'     => 'https://www.apeiron-registry.com/register',
					'standard_headers' => [
						'X-Apeiron-Agent-ID' => 'your_agent_id',
						'X-Apeiron-API-Key'  => 'your_api_key',
						'X-Apeiron-Purpose'  => 'training|inference|search',
					],
					'message' => 'Register your AI agent at www.apeiron-registry.com to access this content legally',
				] );
				exit;
			}

			// registry_log + nessun header → serve il contenuto, logga come anonimo
			$this->log_anonymous_bot( $post_id, $user_agent );
			return;
		}

		// ── ai_only (comportamento originale x402) ───────────────────────────
		if ( 'ai_only' !== $mode ) {
			return;
		}

		if ( ! preg_match( self::KNOWN_BOTS, $user_agent ) ) {
			return;
		}

		$wallet = $_SERVER['HTTP_X_WALLET_ADDRESS'] ?? '';
		if ( $wallet && preg_match( '/^0x[0-9a-fA-F]{40}$/', $wallet ) ) {
			$redirect = rest_url( 'apeiron/v1/content/' . $post_id );
			wp_redirect( $redirect, 302 );
			exit;
		}

		$content_id      = get_post_meta( $post_id, '_apeiron_content_id', true ) ?: '';
		$ai_price        = get_post_meta( $post_id, '_apeiron_ai_price', true )   ?: '1.00';
		$gateway_address = get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY );
		$usdc_address    = get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC );
		$price_wei       = (string) (int) ( (float) $ai_price * 1_000_000 );

		http_response_code( 402 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Apeiron-Protocol: x402' );
		echo wp_json_encode( [
			'error'          => 'Payment Required',
			'protocol'       => 'x402',
			'version'        => '1.0',
			'gatewayAddress' => $gateway_address,
			'usdcAddress'    => $usdc_address,
			'contentId'      => $content_id,
			'accessType'     => 'AI_LICENSE',
			'price'          => $price_wei,
			'priceFormatted' => number_format( (float) $ai_price, 6 ) . ' USDC',
			'instructions'   => [
				'step1' => sprintf( 'USDC.approve("%s", %s)', $gateway_address, $price_wei ),
				'step2' => sprintf( 'gateway.unlockAsAgent("%s", 2592000)', $content_id ),
				'step3' => 'Retry request with header: x-wallet-address: <your_address>',
			],
			'verifyEndpoint' => rest_url( 'apeiron/v1/content/' . $post_id ),
			'network'        => [ 'name' => 'Base', 'chainId' => APEIRON_CHAIN_ID ],
			'postUrl'        => get_permalink( $post_id ),
		] );
		exit;
	}

	// ── Registry API calls ──────────────────────────────────────────────────

	/**
	 * Chiama www.apeiron-registry.com/api/registry/verify
	 *
	 * FIX 2: HTTPS enforced (registry URL must be https://)
	 * FIX 4: Fail-open — if API unreachable within 2s, allow access
	 * FIX 5: Sends IP + User-Agent for compliance/provenance
	 * FIX 3: Email debounce — sends X-Debounce-Key header
	 */
	private function verify_with_registry( string $agent_id, string $api_key, int $post_id, string $user_agent ): array {
		$registry_url    = get_option( 'apeiron_registry_url', 'https://www.apeiron-registry.com/api/registry/verify' );
		$publisher_email = get_option( 'apeiron_registry_publisher_email', '' );

		// FIX 2: Enforce HTTPS — refuse to send API key over plaintext
		if ( strpos( $registry_url, 'https://' ) !== 0 ) {
			error_log( 'Apeiron Registry: HTTPS required for verify endpoint. Refusing to send credentials over HTTP.' );
			// Fail-open: allow access but don't verify
			return [ 'verified' => true, 'code' => 'HTTPS_REQUIRED' ];
		}

		// FIX 3: Email debounce — check if we already notified for this agent today
		$debounce_key  = 'apeiron_email_' . md5( $agent_id );
		$should_notify = ( false === get_transient( $debounce_key ) );

		$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';

		$response = wp_remote_post( $registry_url, [
			'timeout' => self::API_TIMEOUT, // FIX 4: 2s max
			'headers' => [
				'X-Apeiron-Agent-ID'   => $agent_id,
				'X-Apeiron-API-Key'    => $api_key,
				'X-Content-URL'        => get_permalink( $post_id ),
				'X-Content-Title'      => get_the_title( $post_id ),
				'X-Publisher-Email'    => $publisher_email,
				'X-Publisher-Wallet'   => get_option( 'apeiron_publisher_wallet', '' ),
				// FIX 5: IP + User-Agent for provenance
				'X-Agent-IP'           => $remote_ip,
				'X-Agent-User-Agent'   => substr( $user_agent, 0, 500 ),
				// FIX 3: Tell API whether to send email notification
				'X-Notify-Publisher'   => $should_notify ? 'true' : 'false',
			],
		] );

		// FIX 4: Fail-open — if API is down, allow access and log error
		if ( is_wp_error( $response ) ) {
			error_log( 'Apeiron Registry: API unreachable (' . $response->get_error_message() . '). Failing open.' );
			return [ 'verified' => true, 'code' => 'REGISTRY_UNREACHABLE' ];
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		// FIX 4: Fail-open on server errors (5xx)
		if ( $http_code >= 500 ) {
			error_log( 'Apeiron Registry: API returned ' . $http_code . '. Failing open.' );
			return [ 'verified' => true, 'code' => 'REGISTRY_ERROR' ];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return [ 'verified' => true, 'code' => 'INVALID_RESPONSE' ]; // fail-open
		}

		// FIX 3: If verified, set debounce transient (no more emails for 24h)
		if ( ! empty( $body['verified'] ) && $should_notify ) {
			set_transient( $debounce_key, time(), self::EMAIL_DEBOUNCE_TTL );
		}

		return $body;
	}

	/**
	 * Log verified access asynchronously (non-blocking).
	 * Used on cache-hit to still record the access without calling /verify.
	 */
	private function log_verified_access_async( string $agent_id, string $api_key, int $post_id, string $user_agent, bool $notify ): void {
		$registry_url    = get_option( 'apeiron_registry_url', 'https://www.apeiron-registry.com/api/registry/verify' );
		$publisher_email = get_option( 'apeiron_registry_publisher_email', '' );

		wp_remote_post( $registry_url, [
			'timeout'  => 1,
			'blocking' => false, // fire and forget — don't slow down the response
			'headers'  => [
				'X-Apeiron-Agent-ID'   => $agent_id,
				'X-Apeiron-API-Key'    => $api_key,
				'X-Content-URL'        => get_permalink( $post_id ),
				'X-Content-Title'      => get_the_title( $post_id ),
				'X-Publisher-Email'    => $publisher_email,
				'X-Agent-IP'           => $_SERVER['REMOTE_ADDR'] ?? '',
				'X-Agent-User-Agent'   => substr( $user_agent, 0, 500 ),
				'X-Notify-Publisher'   => $notify ? 'true' : 'false',
				'X-Cache-Hit'          => 'true', // tell API this was cached
			],
		] );
	}

	/**
	 * Logga accesso di bot anonimo su Apeiron Registry (agent_id = 'anonymous').
	 * Non-bloccante. Alimenta il counter pubblico sulla landing page.
	 */
	private function log_anonymous_bot( int $post_id, string $user_agent ): void {
		$registry_url    = get_option( 'apeiron_registry_url', 'https://www.apeiron-registry.com/api/registry/verify' );
		$publisher_email = get_option( 'apeiron_registry_publisher_email', '' );

		wp_remote_post( $registry_url, [
			'timeout'  => 1,
			'blocking' => false,
			'headers'  => [
				'X-Apeiron-Agent-ID'  => 'anonymous',
				'X-Apeiron-API-Key'   => 'anonymous',
				'X-Content-URL'       => get_permalink( $post_id ),
				'X-Content-Title'     => get_the_title( $post_id ),
				'X-Publisher-Email'   => $publisher_email,
				'X-Agent-IP'          => $_SERVER['REMOTE_ADDR'] ?? '',
				'X-Agent-User-Agent'  => substr( $user_agent, 0, 500 ),
				'X-Notify-Publisher'  => 'false',
			],
		] );
	}

	// ── Filtro contenuto ─────────────────────────────────────────────────────

	public function maybe_apply_paywall( string $content ): string {
		if ( ! is_singular( 'post' ) || is_admin() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$mode    = $this->get_mode( $post_id );

		if ( 'full' !== $mode ) {
			return $content;
		}

		$preview_paras = (int) ( get_post_meta( $post_id, '_apeiron_preview_paras', true ) ?: 4 );
		$preview       = $this->extract_preview( $content, $preview_paras );
		$paywall_html  = $this->get_paywall_html( $post_id );

		return sprintf(
			'%s%s<div id="apeiron-full-content" style="display:none">%s</div>',
			$preview,
			$paywall_html,
			$content
		);
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function get_mode( int $post_id ): string {
		$mode = get_post_meta( $post_id, '_apeiron_mode', true );
		if ( $mode ) {
			return $mode;
		}
		return get_post_meta( $post_id, '_apeiron_protected', true ) === '1' ? 'full' : 'disabled';
	}

	private function extract_preview( string $content, int $num_paras = 4 ): string {
		preg_match_all( '/<p[^>]*>.*?<\/p>/is', $content, $matches );
		if ( ! empty( $matches[0] ) ) {
			return implode( "\n", array_slice( $matches[0], 0, $num_paras ) );
		}
		return '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $content ), $num_paras * 60 ) ) . '</p>';
	}

	private function get_paywall_html( int $post_id ): string {
		$human_price     = get_post_meta( $post_id, '_apeiron_human_price', true ) ?: '0.10';
		$ai_price        = get_post_meta( $post_id, '_apeiron_ai_price', true )    ?: '1.00';
		$content_id      = get_post_meta( $post_id, '_apeiron_content_id', true )  ?: '';
		$gateway_address = get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY );
		$usdc_address    = get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC );
		$show_branding   = (bool) get_option( 'apeiron_show_branding', '1' );
		ob_start();
		include APEIRON_PATH . 'templates/paywall-template.php';
		return ob_get_clean();
	}

	// ── Assets frontend ──────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$mode    = $this->get_mode( $post_id );

		if ( 'full' !== $mode ) {
			return;
		}

		wp_enqueue_style( 'apeiron-paywall', APEIRON_URL . 'assets/css/apeiron-paywall.css', [], APEIRON_VERSION );

		wp_enqueue_script( 'ethers',
			APEIRON_URL . 'assets/js/ethers.umd.min.js',
			[], '6.13.4', true
		);

		wp_enqueue_script( 'apeiron-paywall',
			APEIRON_URL . 'assets/js/apeiron-paywall.js',
			[ 'ethers' ], APEIRON_VERSION, true
		);

		wp_localize_script( 'apeiron-paywall', 'apeironData', [
			'contentId'      => get_post_meta( $post_id, '_apeiron_content_id', true ) ?: '',
			'humanPrice'     => get_post_meta( $post_id, '_apeiron_human_price', true ) ?: '0.10',
			'aiPrice'        => get_post_meta( $post_id, '_apeiron_ai_price', true )    ?: '1.00',
			'gatewayAddress' => get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ),
			'usdcAddress'    => get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC ),
			'rpcUrl'         => get_option( 'apeiron_rpc_url',         APEIRON_DEFAULT_RPC ),
			'chainId'        => APEIRON_CHAIN_ID,
			'verifyEndpoint' => rest_url( 'apeiron/v1/verify' ),
			'postUrl'        => get_permalink( $post_id ),
			'publisherWallet'=> strtolower( get_option( 'apeiron_publisher_wallet', '' ) ),
			'i18n'           => [
				'connecting'  => __( 'Connecting…', 'apeiron-ai-bot-tracker' ),
				'checking'    => __( 'Checking access…', 'apeiron-ai-bot-tracker' ),
				'approving'   => __( 'Approve USDC in MetaMask…', 'apeiron-ai-bot-tracker' ),
				'paying'      => __( 'Confirm payment in MetaMask…', 'apeiron-ai-bot-tracker' ),
				'unlocking'   => __( 'Unlocking…', 'apeiron-ai-bot-tracker' ),
				'noMetaMask'  => __( 'MetaMask not found. Install it at metamask.io', 'apeiron-ai-bot-tracker' ),
				'wrongChain'  => __( 'Switch to Base Mainnet in MetaMask (Chain ID 8453).', 'apeiron-ai-bot-tracker' ),
				'error'       => __( 'Error: ', 'apeiron-ai-bot-tracker' ),
				'alreadyPaid' => __( 'Access found — loading…', 'apeiron-ai-bot-tracker' ),
			],
		] );
	}
}
