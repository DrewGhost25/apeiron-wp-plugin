<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pannello admin: impostazioni globali + meta box per articoli.
 */
class Apeiron_Admin {

	public function init(): void {
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'add_meta_boxes',        [ $this, 'add_meta_box' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	// ── Settings globali ────────────────────────────────────────────────────

	public function register_settings(): void {
		$fields = [
			'publisher_wallet' => __( 'Publisher Wallet Address', 'apeiron' ),
			'gateway_address'  => __( 'Gateway Contract Address', 'apeiron' ),
			'usdc_address'     => __( 'USDC Token Address', 'apeiron' ),
			'rpc_url'          => __( 'Base RPC URL', 'apeiron' ),
		];

		add_settings_section(
			'apeiron_main',
			__( 'Blockchain Settings', 'apeiron' ),
			null,
			'apeiron-settings'
		);

		foreach ( $fields as $key => $label ) {
			register_setting( 'apeiron_settings', "apeiron_{$key}", [
				'sanitize_callback' => 'sanitize_text_field',
			] );

			add_settings_field(
				"apeiron_{$key}",
				$label,
				[ $this, 'render_text_field' ],
				'apeiron-settings',
				'apeiron_main',
				[ 'key' => $key ]
			);
		}
	}

	public function render_text_field( array $args ): void {
		$key   = $args['key'];
		$value = get_option( "apeiron_{$key}", '' );
		printf(
			'<input type="text" name="apeiron_%s" id="apeiron_%s" value="%s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap apeiron-settings-wrap">
			<h1><?php esc_html_e( 'Apeiron — Web3 Paywall', 'apeiron' ); ?></h1>

			<div class="apeiron-fee-notice">
				<strong><?php esc_html_e( 'Apeiron uses a tiered fee model — fairer for small publishers, competitive for enterprise:', 'apeiron' ); ?></strong>
				<table class="apeiron-fee-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Transaction size', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'Platform fee', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'Publisher receives', 'apeiron' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Up to $10 USDC', 'apeiron' ); ?></td>
							<td>10%</td>
							<td>90%</td>
						</tr>
						<tr>
							<td><?php esc_html_e( '$10 — $100 USDC', 'apeiron' ); ?></td>
							<td>5%</td>
							<td>95%</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Above $100 USDC', 'apeiron' ); ?></td>
							<td>2%</td>
							<td>98%</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="apeiron-fee-notice" style="margin-top:20px">
				<strong><?php esc_html_e( 'Interceptable AI Bots', 'apeiron' ); ?></strong>
				<p style="margin:4px 0 8px;color:#888;font-size:13px"><?php esc_html_e( 'These User-Agent patterns are automatically detected and subject to the x402 paywall in AI Only and Full modes.', 'apeiron' ); ?></p>
				<table class="apeiron-fee-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Bot / User-Agent', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'Company', 'apeiron' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td>GPTBot</td><td>OpenAI</td></tr>
						<tr><td>ChatGPT-User</td><td>OpenAI</td></tr>
						<tr><td>ClaudeBot</td><td>Anthropic</td></tr>
						<tr><td>Claude-Web</td><td>Anthropic</td></tr>
						<tr><td>Google-Extended</td><td>Google</td></tr>
						<tr><td>Googlebot</td><td>Google</td></tr>
						<tr><td>PerplexityBot</td><td>Perplexity AI</td></tr>
						<tr><td>YouBot</td><td>You.com</td></tr>
						<tr><td>Diffbot</td><td>Diffbot</td></tr>
						<tr><td>CCBot</td><td>Common Crawl</td></tr>
						<tr><td>FacebookBot</td><td>Meta</td></tr>
						<tr><td>Applebot</td><td>Apple</td></tr>
						<tr><td>BingBot</td><td>Microsoft</td></tr>
						<tr><td>X402-Agent</td><td>x402 Protocol</td></tr>
					</tbody>
				</table>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'apeiron_settings' );
				do_settings_sections( 'apeiron-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// ── Meta box articoli ───────────────────────────────────────────────────

	public function add_meta_box(): void {
		add_meta_box(
			'apeiron_meta',
			__( 'Apeiron Paywall', 'apeiron' ),
			[ $this, 'render_meta_box' ],
			'post',
			'side',
			'high'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'apeiron_save_meta', 'apeiron_nonce' );

		// Retrocompatibilità: vecchio checkbox → full
		$mode = get_post_meta( $post->ID, '_apeiron_mode', true );
		if ( ! $mode ) {
			$old_protected = get_post_meta( $post->ID, '_apeiron_protected', true );
			$mode = $old_protected === '1' ? 'full' : 'disabled';
		}

		$human_price   = get_post_meta( $post->ID, '_apeiron_human_price', true )   ?: '0.10';
		$ai_price      = get_post_meta( $post->ID, '_apeiron_ai_price', true )      ?: '1.00';
		$preview_paras = get_post_meta( $post->ID, '_apeiron_preview_paras', true ) ?: '4';
		$registered    = get_post_meta( $post->ID, '_apeiron_registered', true );
		$content_id    = get_post_meta( $post->ID, '_apeiron_content_id', true );

		$modes = [
			'disabled' => __( '🔓 Disabled — no protection', 'apeiron' ),
			'ai_only'  => __( '🤖 AI Only — humans free, bots pay', 'apeiron' ),
			'full'     => __( '🔒 Full — paywall for everyone', 'apeiron' ),
		];
		?>
		<div class="apeiron-meta-box">

			<p>
				<label for="apeiron_mode"><strong><?php esc_html_e( 'Protection Mode', 'apeiron' ); ?></strong></label><br>
				<select id="apeiron_mode" name="apeiron_mode" style="width:100%;margin-top:4px">
					<?php foreach ( $modes as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $mode, $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<div id="apeiron-price-fields" <?php echo $mode === 'disabled' ? 'style="display:none"' : ''; ?>>

				<p>
					<label for="apeiron_ai_price">
						<?php esc_html_e( 'AI agent price (USDC)', 'apeiron' ); ?>
					</label><br>
					<input type="number"
					       id="apeiron_ai_price"
					       name="apeiron_ai_price"
					       value="<?php echo esc_attr( $ai_price ); ?>"
					       step="0.01" min="0.01"
					       style="width:100%" />
				</p>

				<div id="apeiron-human-price-field">
					<div id="apeiron-ai-only-notice" style="<?php echo $mode !== 'ai_only' ? 'display:none;' : ''; ?>background:#1a2a3a;border-left:3px solid #c8a96e;padding:8px 10px;margin-bottom:8px;border-radius:3px;font-size:12px;color:#c8a96e;line-height:1.4">
						ℹ️ <?php esc_html_e( 'The smart contract requires a human price at registration. Human readers will not be wallet-checked — they access content freely. Only AI bots are gated.', 'apeiron' ); ?>
					</div>
					<p>
						<label for="apeiron_human_price">
							<?php esc_html_e( 'Human reader price (USDC)', 'apeiron' ); ?>
						</label><br>
						<input type="number"
						       id="apeiron_human_price"
						       name="apeiron_human_price"
						       value="<?php echo esc_attr( $human_price ); ?>"
						       step="0.01" min="0.01"
						       style="width:100%" />
					</p>
					<div id="apeiron-preview-field" <?php echo $mode === 'ai_only' ? 'style="display:none"' : ''; ?>>
						<p>
							<label for="apeiron_preview_paras">
								<?php esc_html_e( 'Preview paragraphs', 'apeiron' ); ?>
							</label><br>
							<input type="number"
							       id="apeiron_preview_paras"
							       name="apeiron_preview_paras"
							       value="<?php echo esc_attr( $preview_paras ); ?>"
							       step="1" min="1" max="20"
							       style="width:100%" />
							<small style="color:#888"><?php esc_html_e( 'Full mode only (default: 4)', 'apeiron' ); ?></small>
						</p>
					</div>
				</div>

				<hr>

				<p class="apeiron-status">
					<?php if ( $registered ) : ?>
						<span class="apeiron-registered">&#10003; <?php esc_html_e( 'Registered on-chain', 'apeiron' ); ?></span>
						<?php if ( $content_id ) : ?>
							<br><small>ID: <code><?php echo esc_html( substr( $content_id, 0, 12 ) . '…' ); ?></code></small>
						<?php endif; ?>
					<?php else : ?>
						<span class="apeiron-not-registered">&#9679; <?php esc_html_e( 'Not yet registered', 'apeiron' ); ?></span>
					<?php endif; ?>
				</p>

				<button type="button"
				        id="apeiron-register-btn"
				        class="button button-secondary"
				        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				        data-post-url="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
				        data-human-price="<?php echo esc_attr( $human_price ); ?>"
				        data-ai-price="<?php echo esc_attr( $ai_price ); ?>">
					<?php esc_html_e( 'Register on blockchain', 'apeiron' ); ?>
				</button>
				<span id="apeiron-register-status" style="margin-left:8px;"></span>

			</div><!-- /#apeiron-price-fields -->

		</div>

		<script>
		( function() {
			const sel     = document.getElementById( 'apeiron_mode' );
			const fields  = document.getElementById( 'apeiron-price-fields' );
			const notice  = document.getElementById( 'apeiron-ai-only-notice' );
			const preview = document.getElementById( 'apeiron-preview-field' );

			sel.addEventListener( 'change', function() {
				const isDisabled = this.value === 'disabled';
				const isAiOnly   = this.value === 'ai_only';
				fields.style.display  = isDisabled ? 'none' : '';
				notice.style.display  = isAiOnly   ? ''     : 'none';
				preview.style.display = isAiOnly   ? 'none' : '';
			} );
		} )();
		</script>
		<?php
	}

	public function save_meta( int $post_id, WP_Post $post ): void {
		if (
			! isset( $_POST['apeiron_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apeiron_nonce'] ) ), 'apeiron_save_meta' ) ||
			defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
			! current_user_can( 'edit_post', $post_id ) ||
			'post' !== $post->post_type
		) {
			return;
		}

		$allowed_modes = [ 'disabled', 'ai_only', 'full' ];
		$mode          = isset( $_POST['apeiron_mode'] )
			? sanitize_text_field( wp_unslash( $_POST['apeiron_mode'] ) )
			: 'disabled';
		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'disabled';
		}

		// Retrocompatibilità campo _apeiron_protected
		$protected = ( $mode === 'full' ) ? '1' : '0';

		$human_price = isset( $_POST['apeiron_human_price'] )
			? (string) round( (float) sanitize_text_field( wp_unslash( $_POST['apeiron_human_price'] ) ), 6 )
			: '0.10';
		$ai_price    = isset( $_POST['apeiron_ai_price'] )
			? (string) round( (float) sanitize_text_field( wp_unslash( $_POST['apeiron_ai_price'] ) ), 6 )
			: '1.00';

		$preview_paras = isset( $_POST['apeiron_preview_paras'] )
			? max( 1, min( 20, absint( $_POST['apeiron_preview_paras'] ) ) )
			: 4;

		update_post_meta( $post_id, '_apeiron_mode',          $mode );
		update_post_meta( $post_id, '_apeiron_protected',     $protected ); // retrocompatibilità
		update_post_meta( $post_id, '_apeiron_human_price',   $human_price );
		update_post_meta( $post_id, '_apeiron_ai_price',      $ai_price );
		update_post_meta( $post_id, '_apeiron_preview_paras', $preview_paras );

		if ( $mode === 'disabled' ) {
			update_post_meta( $post_id, '_apeiron_registered', '0' );
		}
	}

	// ── Assets admin ────────────────────────────────────────────────────────

	public function enqueue_admin_assets( string $hook ): void {
		// Solo sulle pagine post e settings Apeiron
		$allowed = [ 'post.php', 'post-new.php', 'settings_page_apeiron-settings' ];
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		wp_enqueue_style(
			'apeiron-admin',
			APEIRON_URL . 'assets/css/apeiron-paywall.css',
			[],
			APEIRON_VERSION
		);

		wp_enqueue_script(
			'ethers',
			'https://cdnjs.cloudflare.com/ajax/libs/ethers/6.13.4/ethers.umd.min.js',
			[],
			'6.13.4',
			true
		);

		wp_enqueue_script(
			'apeiron-admin',
			APEIRON_URL . 'assets/js/apeiron-admin.js',
			[ 'jquery', 'ethers' ],
			APEIRON_VERSION,
			true
		);

		wp_localize_script( 'apeiron-admin', 'apeironAdmin', [
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'apeiron_register' ),
			'gatewayAddress'  => get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ),
			'usdcAddress'     => get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC ),
			'publisherWallet' => get_option( 'apeiron_publisher_wallet', '' ),
			'chainId'         => APEIRON_CHAIN_ID,
			'i18n'            => [
				'connecting'  => __( 'Connecting wallet…', 'apeiron' ),
				'registering' => __( 'Preparing transaction…', 'apeiron' ),
				'success'     => __( 'Registered! TX: ', 'apeiron' ),
				'error'       => __( 'Error: ', 'apeiron' ),
				'noMetaMask'  => __( 'MetaMask not found. Install it and try again.', 'apeiron' ),
				'wrongChain'  => __( 'Switch to Base Mainnet (Chain ID 8453) in MetaMask.', 'apeiron' ),
			],
		] );
	}
}
