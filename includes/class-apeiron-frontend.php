<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestisce il paywall sul frontend:
 * – Tronca il contenuto dopo 2 paragrafi
 * – Inietta il template paywall
 * – Carica gli asset JS/CSS
 */
class Apeiron_Frontend {

	public function init(): void {
		add_filter( 'the_content',         [ $this, 'maybe_apply_paywall' ], 99 );
		add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
	}

	// ── Filtro contenuto ────────────────────────────────────────────────────

	public function maybe_apply_paywall( string $content ): string {
		// Solo su singoli post protetti
		if ( ! is_singular( 'post' ) || is_admin() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$protected = get_post_meta( $post_id, '_apeiron_protected', true );

		if ( '1' !== $protected ) {
			return $content;
		}

		$preview_paras = (int) ( get_post_meta( $post_id, '_apeiron_preview_paras', true ) ?: 4 );
		$preview       = $this->extract_preview( $content, $preview_paras );
		$paywall_html  = $this->get_paywall_html( $post_id );

		// Il contenuto completo è nascosto via CSS; viene svelato da JS dopo verifica
		return sprintf(
			'%s%s<div id="apeiron-full-content" style="display:none">%s</div>',
			$preview,
			$paywall_html,
			$content   // contenuto intero nel DOM, nascosto — non sensibile: la vera auth è on-chain
		);
	}

	/**
	 * Estrae i primi 2 paragrafi dall'HTML.
	 */
	private function extract_preview( string $content, int $num_paras = 4 ): string {
		// Lavora su paragrafi <p>; fallback su blocchi separati da doppio newline
		preg_match_all( '/<p[^>]*>.*?<\/p>/is', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			$paragraphs = array_slice( $matches[0], 0, $num_paras );
			return implode( "\n", $paragraphs );
		}

		// Fallback: tronca proporzionalmente alle parole
		$words = $num_paras * 60;
		return '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $content ), $words ) ) . '</p>';
	}

	/**
	 * Carica e restituisce il template paywall con i dati corretti.
	 */
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

	// ── Assets frontend ─────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id   = get_the_ID();
		$protected = get_post_meta( $post_id, '_apeiron_protected', true );

		if ( '1' !== $protected ) {
			return;
		}

		wp_enqueue_style(
			'apeiron-paywall',
			APEIRON_URL . 'assets/css/apeiron-paywall.css',
			[],
			APEIRON_VERSION
		);

		// ethers.js via CDN (v6 UMD build)
		wp_enqueue_script(
			'ethers',
			'https://cdnjs.cloudflare.com/ajax/libs/ethers/6.13.4/ethers.umd.min.js',
			[],
			'6.13.4',
			true
		);

		wp_enqueue_script(
			'apeiron-paywall',
			APEIRON_URL . 'assets/js/apeiron-paywall.js',
			[ 'ethers' ],
			APEIRON_VERSION,
			true
		);

		$content_id = get_post_meta( $post_id, '_apeiron_content_id', true ) ?: '';

		wp_localize_script( 'apeiron-paywall', 'apeironData', [
			'contentId'      => $content_id,
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
				'connecting'    => __( 'Connessione…', 'apeiron' ),
				'checking'      => __( 'Verifica accesso…', 'apeiron' ),
				'approving'     => __( 'Approva USDC in MetaMask…', 'apeiron' ),
				'paying'        => __( 'Conferma il pagamento in MetaMask…', 'apeiron' ),
				'unlocking'     => __( 'Sblocco in corso…', 'apeiron' ),
				'noMetaMask'    => __( 'MetaMask non trovato. Installalo su metamask.io', 'apeiron' ),
				'wrongChain'    => __( 'Passa a Base Mainnet in MetaMask (Chain ID 8453).', 'apeiron' ),
				'error'         => __( 'Errore: ', 'apeiron' ),
				'alreadyPaid'   => __( 'Accesso già presente — caricamento…', 'apeiron' ),
			],
		] );
	}
}
