<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pannello admin: impostazioni globali + meta box per articoli.
 */
class Apeiron_Admin {

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'add_meta_boxes',        [ $this, 'add_meta_box' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_apeiron_regenerate_stats_key', [ $this, 'regenerate_stats_key' ] );
	}

	public function register_menu(): void {
		// Registra come sottomenu del menu Apeiron (non sotto Settings)
		add_submenu_page(
			'apeiron-dashboard',
			__( 'Apeiron Settings', 'apeiron-ai-bot-tracker' ),
			__( 'Settings', 'apeiron-ai-bot-tracker' ),
			'manage_options',
			'apeiron-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	// ── Settings globali ────────────────────────────────────────────────────

	public function register_settings(): void {
		// ── Bot Tracker Settings ──────────────────────────────────────────────
		add_settings_section(
			'apeiron_tracker',
			__( 'Bot Tracker Settings', 'apeiron-ai-bot-tracker' ),
			null,
			'apeiron-settings'
		);

		register_setting( 'apeiron_settings', 'apeiron_publisher_email', [
			'sanitize_callback' => 'sanitize_email',
		] );
		add_settings_field(
			'apeiron_publisher_email',
			__( 'Publisher Email (for weekly report)', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_text_field' ],
			'apeiron-settings',
			'apeiron_tracker',
			[ 'key' => 'publisher_email' ]
		);

		register_setting( 'apeiron_settings', 'apeiron_show_branding', [
			'sanitize_callback' => 'absint',
		] );
		add_settings_field(
			'apeiron_show_branding',
			__( 'Paywall Branding', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_show_branding_field' ],
			'apeiron-settings',
			'apeiron_tracker'
		);

		register_setting( 'apeiron_settings', 'apeiron_control_mode', [
			'sanitize_callback' => 'absint',
		] );
		add_settings_field(
			'apeiron_control_mode',
			__( 'Control Mode', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_control_mode_field' ],
			'apeiron-settings',
			'apeiron_tracker'
		);

		add_settings_field(
			'apeiron_stats_api_key',
			__( 'Stats API Key', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_stats_api_key_field' ],
			'apeiron-settings',
			'apeiron_tracker'
		);

		// ── Blockchain settings ───────────────────────────────────────────────
		$fields = [
			'publisher_wallet' => __( 'Publisher Wallet Address', 'apeiron-ai-bot-tracker' ),
			'gateway_address'  => __( 'Gateway Contract Address', 'apeiron-ai-bot-tracker' ),
			'usdc_address'     => __( 'USDC Token Address', 'apeiron-ai-bot-tracker' ),
			'rpc_url'          => __( 'Base RPC URL', 'apeiron-ai-bot-tracker' ),
		];

		add_settings_section(
			'apeiron_main',
			__( 'Blockchain Settings (x402)', 'apeiron-ai-bot-tracker' ),
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

		// ── Registry settings ─────────────────────────────────────────────────
		add_settings_section(
			'apeiron_registry',
			__( 'Apeiron Registry', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_registry_section_desc' ],
			'apeiron-settings'
		);

		register_setting( 'apeiron_settings', 'apeiron_registry_url', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field(
			'apeiron_registry_url',
			__( 'Registry API URL', 'apeiron-ai-bot-tracker' ),
			[ $this, 'render_text_field' ],
			'apeiron-settings',
			'apeiron_registry',
			[ 'key' => 'registry_url' ]
		);
	}

	public function render_show_branding_field(): void {
		$value = get_option( 'apeiron_show_branding', '1' );
		printf(
			'<label><input type="checkbox" name="apeiron_show_branding" value="1" %s /> %s</label>',
			checked( 1, (int) $value, false ),
			esc_html__( 'Show \'Secured by Apeiron\' on paywall (optional)', 'apeiron-ai-bot-tracker' )
		);
	}

	public function render_control_mode_field(): void {
		$value = get_option( 'apeiron_control_mode', '0' );
		printf(
			'<label><input type="checkbox" name="apeiron_control_mode" value="1" %s /> %s</label>',
			checked( 1, (int) $value, false ),
			esc_html__( 'Enable CONTROL mode (block/require registry per bot)', 'apeiron-ai-bot-tracker' )
		);
	}

	public function render_stats_api_key_field(): void {
		$key = get_option( 'apeiron_stats_api_key', '' );
		printf(
			'<input type="text" id="apeiron_stats_api_key_display" value="%s" class="regular-text" readonly style="font-family:monospace" />
			 <button type="button" id="apeiron-regenerate-key" class="button button-secondary" style="margin-left:8px">%s</button>
			 <span id="apeiron-regen-status" style="margin-left:8px"></span>',
			esc_attr( $key ),
			esc_html__( 'Regenerate', 'apeiron-ai-bot-tracker' )
		);
	}

	public function regenerate_stats_key(): void {
		check_ajax_referer( 'apeiron_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$new_key = wp_generate_password( 32, false );
		update_option( 'apeiron_stats_api_key', $new_key );
		wp_send_json_success( [ 'key' => $new_key ] );
	}

	public function render_registry_section_desc(): void {
		$default = get_option( 'apeiron_registry_url', '' );
		if ( ! $default ) {
			update_option( 'apeiron_registry_url', 'https://www.apeiron-registry.com/api/registry/verify' );
		}
		echo '<p style="color:#888;font-size:13px">'
			. esc_html__( 'Enable per-article agent identification with Apeiron Registry. Publishers get email notifications when registered AI companies read their content.', 'apeiron-ai-bot-tracker' )
			. ' <a href="https://www.apeiron-registry.com/protect" target="_blank">Learn more →</a></p>';
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
			<h1><?php esc_html_e( 'Apeiron — AI Bot Tracker', 'apeiron-ai-bot-tracker' ); ?></h1>

			<div class="apeiron-fee-notice">
				<strong><?php esc_html_e( 'Apeiron uses a tiered fee model — fairer for small publishers, competitive for enterprise:', 'apeiron-ai-bot-tracker' ); ?></strong>
				<table class="apeiron-fee-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Transaction size', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'Platform fee', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'Publisher receives', 'apeiron-ai-bot-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Up to $10 USDC', 'apeiron-ai-bot-tracker' ); ?></td>
							<td>10%</td>
							<td>90%</td>
						</tr>
						<tr>
							<td><?php esc_html_e( '$10 — $100 USDC', 'apeiron-ai-bot-tracker' ); ?></td>
							<td>5%</td>
							<td>95%</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Above $100 USDC', 'apeiron-ai-bot-tracker' ); ?></td>
							<td>2%</td>
							<td>98%</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="apeiron-fee-notice" style="margin-top:20px">
				<strong><?php esc_html_e( 'Tracked AI Bots', 'apeiron-ai-bot-tracker' ); ?></strong>
				<p style="margin:4px 0 8px;color:#888;font-size:13px"><?php esc_html_e( 'These User-Agent patterns are automatically detected, logged, and optionally blocked or monetized.', 'apeiron-ai-bot-tracker' ); ?></p>
				<table class="apeiron-fee-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User-Agent', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'Name', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'Company', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'Purpose', 'apeiron-ai-bot-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( Apeiron_Detector::KNOWN_BOTS as $ua => $info ) : ?>
							<tr>
								<td><code><?php echo esc_html( $ua ); ?></code></td>
								<td><?php echo esc_html( $info['name'] ); ?></td>
								<td><?php echo esc_html( $info['company'] ); ?></td>
								<td><?php echo esc_html( $info['purpose'] ); ?></td>
							</tr>
						<?php endforeach; ?>
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
			__( 'Apeiron Paywall', 'apeiron-ai-bot-tracker' ),
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
			'disabled'       => __( '🔓 Disabled — no protection', 'apeiron-ai-bot-tracker' ),
			'ai_only'        => __( '🤖 AI Only — humans free, bots pay (x402)', 'apeiron-ai-bot-tracker' ),
			'full'           => __( '🔒 Full — paywall for everyone (x402)', 'apeiron-ai-bot-tracker' ),
			'registry_log'   => __( '📋 Registry Log — allow all, log verified agents', 'apeiron-ai-bot-tracker' ),
			'registry_block' => __( '🛡 Registry Block — require Apeiron Registry ID', 'apeiron-ai-bot-tracker' ),
		];
		?>
		<div class="apeiron-meta-box">

			<p style="color:#888;font-size:12px;margin-bottom:8px;background:#1a2a3a;border-left:3px solid #c8a96e;padding:6px 8px;border-radius:3px">
				<?php esc_html_e( 'DETECT mode is always active regardless of per-article setting — every AI bot visit is logged.', 'apeiron-ai-bot-tracker' ); ?>
			</p>

			<p>
				<label for="apeiron_mode"><strong><?php esc_html_e( 'Protection Mode', 'apeiron-ai-bot-tracker' ); ?></strong></label><br>
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
						<?php esc_html_e( 'AI agent price (USDC)', 'apeiron-ai-bot-tracker' ); ?>
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
						ℹ️ <?php esc_html_e( 'The smart contract requires a human price at registration. Human readers will not be wallet-checked — they access content freely. Only AI bots are gated.', 'apeiron-ai-bot-tracker' ); ?>
					</div>
					<p>
						<label for="apeiron_human_price">
							<?php esc_html_e( 'Human reader price (USDC)', 'apeiron-ai-bot-tracker' ); ?>
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
								<?php esc_html_e( 'Preview paragraphs', 'apeiron-ai-bot-tracker' ); ?>
							</label><br>
							<input type="number"
							       id="apeiron_preview_paras"
							       name="apeiron_preview_paras"
							       value="<?php echo esc_attr( $preview_paras ); ?>"
							       step="1" min="1" max="20"
							       style="width:100%" />
							<small style="color:#888"><?php esc_html_e( 'Full mode only (default: 4)', 'apeiron-ai-bot-tracker' ); ?></small>
						</p>
					</div>
				</div>

				<hr>

				<p class="apeiron-status">
					<?php if ( $registered ) : ?>
						<span class="apeiron-registered">&#10003; <?php esc_html_e( 'Registered on-chain', 'apeiron-ai-bot-tracker' ); ?></span>
						<?php if ( $content_id ) : ?>
							<br><small>ID: <code><?php echo esc_html( substr( $content_id, 0, 12 ) . '…' ); ?></code></small>
						<?php endif; ?>
					<?php else : ?>
						<span class="apeiron-not-registered">&#9679; <?php esc_html_e( 'Not yet registered', 'apeiron-ai-bot-tracker' ); ?></span>
					<?php endif; ?>
				</p>

				<button type="button"
				        id="apeiron-register-btn"
				        class="button button-secondary"
				        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				        data-post-url="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
				        data-human-price="<?php echo esc_attr( $human_price ); ?>"
				        data-ai-price="<?php echo esc_attr( $ai_price ); ?>">
					<?php esc_html_e( 'Register on blockchain', 'apeiron-ai-bot-tracker' ); ?>
				</button>
				<span id="apeiron-register-status" style="margin-left:8px;"></span>

			</div><!-- /#apeiron-price-fields -->

		</div>

		<?php
		// Inline script via wp_add_inline_script (no direct <script> tags in templates)
		wp_add_inline_script( 'apeiron-admin', '
			( function() {
				const sel     = document.getElementById( "apeiron_mode" );
				const fields  = document.getElementById( "apeiron-price-fields" );
				const notice  = document.getElementById( "apeiron-ai-only-notice" );
				const preview = document.getElementById( "apeiron-preview-field" );
				if ( ! sel ) return;
				sel.addEventListener( "change", function() {
					var isDisabled = this.value === "disabled";
					var isAiOnly   = this.value === "ai_only";
					fields.style.display  = isDisabled ? "none" : "";
					notice.style.display  = isAiOnly   ? ""     : "none";
					preview.style.display = isAiOnly   ? "none" : "";
				} );
			} )();
		' );
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

		$allowed_modes = [ 'disabled', 'ai_only', 'full', 'registry_log', 'registry_block' ];
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
		// Carica su pagine post, settings Apeiron e dashboard Apeiron
		$allowed = [
			'post.php',
			'post-new.php',
			'apeiron_page_apeiron-settings',
			'toplevel_page_apeiron-dashboard',
			'apeiron_page_apeiron-bot-activity',
		];
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
			APEIRON_URL . 'assets/js/ethers.umd.min.js', // bundled locally
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
			'adminNonce'      => wp_create_nonce( 'apeiron_admin_nonce' ),
			'gatewayAddress'  => get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ),
			'usdcAddress'     => get_option( 'apeiron_usdc_address',    APEIRON_DEFAULT_USDC ),
			'publisherWallet' => get_option( 'apeiron_publisher_wallet', '' ),
			'chainId'         => APEIRON_CHAIN_ID,
			'i18n'            => [
				'connecting'  => __( 'Connecting wallet…', 'apeiron-ai-bot-tracker' ),
				'registering' => __( 'Preparing transaction…', 'apeiron-ai-bot-tracker' ),
				'success'     => __( 'Registered! TX: ', 'apeiron-ai-bot-tracker' ),
				'error'       => __( 'Error: ', 'apeiron-ai-bot-tracker' ),
				'noMetaMask'  => __( 'MetaMask not found. Install it and try again.', 'apeiron-ai-bot-tracker' ),
				'wrongChain'  => __( 'Switch to Base Mainnet (Chain ID 8453) in MetaMask.', 'apeiron-ai-bot-tracker' ),
			],
		] );

		// Inline JS per rigenerare la Stats API Key dalla pagina settings
		if ( 'settings_page_apeiron-settings' === $hook ) {
			wp_add_inline_script( 'apeiron-admin', '
			( function() {
				var btn = document.getElementById( "apeiron-regenerate-key" );
				if ( ! btn ) return;
				btn.addEventListener( "click", function() {
					var status = document.getElementById( "apeiron-regen-status" );
					status.textContent = "Regenerating…";
					var data = new FormData();
					data.append( "action", "apeiron_regenerate_stats_key" );
					data.append( "nonce", apeironAdmin.adminNonce );
					fetch( apeironAdmin.ajaxUrl, { method: "POST", body: data } )
						.then( function(r) { return r.json(); } )
						.then( function(res) {
							if ( res.success ) {
								document.getElementById( "apeiron_stats_api_key_display" ).value = res.data.key;
								status.textContent = "Key updated.";
							} else {
								status.textContent = "Error.";
							}
						} )
						.catch( function() { status.textContent = "Error."; } );
				} );
			} )();
			' );
		}
	}
}
