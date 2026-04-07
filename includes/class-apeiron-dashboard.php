<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dashboard Analytics del publisher.
 * Pagina admin che mostra articoli protetti, revenue, accessi umani/AI.
 * I dati on-chain vengono letti lato browser via ethers.js (getLogs).
 */
class Apeiron_Dashboard {

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		// Menu top-level Apeiron
		add_menu_page(
			__( 'Apeiron', 'apeiron' ),
			__( 'Apeiron', 'apeiron' ),
			'edit_posts',
			'apeiron-dashboard',
			[ $this, 'render_page' ],
			APEIRON_URL . 'assets/images/apeiron-icon.svg',
			30
		);

		// Unico sottomenu: Analytics
		add_submenu_page(
			'apeiron-dashboard',
			__( 'Analytics', 'apeiron' ),
			__( 'Analytics', 'apeiron' ),
			'edit_posts',
			'apeiron-dashboard',
			[ $this, 'render_page' ]
		);
		// Le impostazioni restano raggiungibili da Impostazioni → Apeiron (registrate da Apeiron_Admin)
	}

	// ── Render pagina ────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$articles = $this->get_protected_articles();
		?>
		<div class="wrap apeiron-dashboard-wrap">

			<!-- Header -->
			<div class="apeiron-dash-header">
				<div>
					<h1 class="apeiron-dash-title">Analytics</h1>
					<p class="apeiron-dash-subtitle">
						<?php esc_html_e( 'Connect your wallet to see readers, bots and earnings.', 'apeiron' ); ?>
					</p>
				</div>
				<div class="apeiron-dash-actions">
					<button id="apeiron-dash-connect" class="apeiron-dash-btn apeiron-dash-btn-connect">
						<?php esc_html_e( 'Connect Wallet', 'apeiron' ); ?>
					</button>
				</div>
			</div>

			<!-- Errore wallet -->
			<div id="apeiron-dash-wallet-error" style="display:none" class="apeiron-dash-wallet-error"></div>

			<!-- KPI cards -->
			<div class="apeiron-dash-kpis">
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-revenue" id="dash-total-revenue">—</span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'TOTAL REVENUE (USDC)', 'apeiron' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value" id="dash-total-humans">—</span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'HUMAN READERS', 'apeiron' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi apeiron-dash-kpi-ai">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-ai-val" id="dash-total-bots">—</span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'AI BOTS INTERCEPTED', 'apeiron' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-muted" id="dash-total-articles">
						<?php echo absint( count( $articles ) ); ?>
					</span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'PUBLISHED ARTICLES', 'apeiron' ); ?></span>
				</div>
			</div>

			<!-- Loading state -->
			<div id="apeiron-dash-loading" style="display:none" class="apeiron-dash-loading">
				<span class="apeiron-dash-spinner"></span>
				<?php esc_html_e( 'Reading on-chain data…', 'apeiron' ); ?>
			</div>

			<!-- Tabella articoli -->
			<div class="apeiron-dash-table-wrap">
				<table class="apeiron-dash-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ARTICLE', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'HUMANS', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'BOTS', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'REVENUE', 'apeiron' ); ?></th>
							<th><?php esc_html_e( 'LINK', 'apeiron' ); ?></th>
						</tr>
					</thead>
					<tbody id="apeiron-dash-tbody">
						<?php foreach ( $articles as $article ) : ?>
							<tr data-content-id="<?php echo esc_attr( $article['content_id'] ); ?>"
							    data-post-id="<?php echo esc_attr( $article['id'] ); ?>">
								<td class="apeiron-dash-article-title">
									<?php echo esc_html( wp_trim_words( $article['title'], 8 ) ); ?>
								</td>
								<td class="apeiron-dash-humans">—</td>
								<td class="apeiron-dash-bots">—</td>
								<td class="apeiron-dash-revenue">—</td>
								<td>
									<a href="<?php echo esc_url( $article['url'] ); ?>"
									   target="_blank" class="apeiron-dash-view-link">
										<?php esc_html_e( 'View →', 'apeiron' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $articles ) ) : ?>
							<tr>
								<td colspan="5" class="apeiron-dash-empty">
									<?php esc_html_e( 'No protected articles found. Create and register your first article.', 'apeiron' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

		</div>
		<?php
	}

	// ── Dati articoli ────────────────────────────────────────────────────────

	private function get_protected_articles(): array {
		$query = new WP_Query( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'   => '_apeiron_protected',
					'value' => '1',
				],
			],
		] );

		$articles = [];
		foreach ( $query->posts as $post ) {
			$content_id = get_post_meta( $post->ID, '_apeiron_content_id', true );
			if ( ! $content_id ) {
				continue; // salta articoli non ancora registrati on-chain
			}
			$articles[] = [
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'url'        => get_permalink( $post->ID ),
				'content_id' => $content_id,
			];
		}

		return $articles;
	}

	// ── Assets ───────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_apeiron-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'apeiron-dashboard',
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
			'apeiron-dashboard',
			APEIRON_URL . 'assets/js/apeiron-dashboard.js',
			[ 'ethers' ],
			APEIRON_VERSION,
			true
		);

		$articles = $this->get_protected_articles();

		wp_localize_script( 'apeiron-dashboard', 'apeironDash', [
			'gatewayAddress'  => get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ),
			'publisherWallet' => get_option( 'apeiron_publisher_wallet', '' ),
			'rpcUrl'          => get_option( 'apeiron_rpc_url', APEIRON_DEFAULT_RPC ),
			'chainId'         => APEIRON_CHAIN_ID,
			'articles'        => $articles,
		] );
	}
}
