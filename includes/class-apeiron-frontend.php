<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestisce il paywall sul frontend.
 *
 * Modalità:
 *  - 'full'     → paywall JS per umani + bot bloccati via x402 endpoint
 *  - 'ai_only'  → umani leggono gratis, bot intercettati da template_redirect con 402
 *  - 'disabled' → nessuna protezione
 */
class Apeiron_Frontend {

	// Bot User-Agent regex — identico a class-apeiron-api.php e SDK Node.js
	const KNOWN_BOTS = '/GPTBot|ChatGPT-User|ClaudeBot|Claude-Web|Google-Extended|Googlebot|PerplexityBot|YouBot|Diffbot|CCBot|FacebookBot|Applebot|BingBot|anthropic|openai|bot|crawler|spider|X402-Agent/i';

	public function init(): void {
		add_action( 'template_redirect',   [ $this, 'maybe_intercept_bot' ], 1 );
		add_filter( 'the_content',         [ $this, 'maybe_apply_paywall' ], 99 );
		add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	// ── Intercettazione bot (ai_only) ────────────────────────────────────────

	/**
	 * Intercetta richieste di bot su articoli in modalità ai_only.
	 * Risponde HTTP 402 JSON prima che WordPress carichi il template.
	 */
	public function maybe_intercept_bot(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$mode    = $this->get_mode( $post_id );

		if ( 'ai_only' !== $mode ) {
			return;
		}

		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( ! preg_match( self::KNOWN_BOTS, $user_agent ) ) {
			return; // umano → lascia passare normalmente
		}

		// Bot rilevato — verifica se ha già accesso tramite header
		$wallet = $_SERVER['HTTP_X_WALLET_ADDRESS'] ?? '';
		if ( $wallet && preg_match( '/^0x[0-9a-fA-F]{40}$/', $wallet ) ) {
			// Rimanda alla verifica on-chain tramite REST endpoint
			$redirect = rest_url( 'apeiron/v1/content/' . $post_id );
			wp_redirect( $redirect, 302 );
			exit;
		}

		// Nessun wallet — restituisce 402 con istruzioni pagamento
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

	// ── Filtro contenuto ─────────────────────────────────────────────────────

	public function maybe_apply_paywall( string $content ): string {
		if ( ! is_singular( 'post' ) || is_admin() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$mode    = $this->get_mode( $post_id );

		if ( 'full' !== $mode ) {
			return $content; // ai_only e disabled → contenuto intero per gli umani
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

	/**
	 * Ritorna la modalità del post con retrocompatibilità.
	 */
	private function get_mode( int $post_id ): string {
		$mode = get_post_meta( $post_id, '_apeiron_mode', true );
		if ( $mode ) {
			return $mode;
		}
		// Retrocompatibilità: vecchio campo _apeiron_protected
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
			return; // nessun asset paywall per ai_only e disabled
		}

		wp_enqueue_style( 'apeiron-paywall', APEIRON_URL . 'assets/css/apeiron-paywall.css', [], APEIRON_VERSION );

		wp_enqueue_script( 'ethers',
			'https://cdnjs.cloudflare.com/ajax/libs/ethers/6.13.4/ethers.umd.min.js',
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
				'connecting'  => __( 'Connecting…', 'apeiron' ),
				'checking'    => __( 'Checking access…', 'apeiron' ),
				'approving'   => __( 'Approve USDC in MetaMask…', 'apeiron' ),
				'paying'      => __( 'Confirm payment in MetaMask…', 'apeiron' ),
				'unlocking'   => __( 'Unlocking…', 'apeiron' ),
				'noMetaMask'  => __( 'MetaMask not found. Install it at metamask.io', 'apeiron' ),
				'wrongChain'  => __( 'Switch to Base Mainnet in MetaMask (Chain ID 8453).', 'apeiron' ),
				'error'       => __( 'Error: ', 'apeiron' ),
				'alreadyPaid' => __( 'Access found — loading…', 'apeiron' ),
			],
		] );
	}
}
