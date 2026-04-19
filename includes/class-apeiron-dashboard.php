<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dashboard Apeiron AI Bot Tracker.
 *
 * Menu:
 *  - Apeiron (top-level) → Dashboard (default)
 *  - Apeiron → Bot Activity  (tabella filtrabile)
 *  - Apeiron → Settings      (punta a apeiron-settings, registrato da Apeiron_Admin)
 *
 * Sezione on-chain mantenuta in una sezione collassabile per chi usa il wallet.
 */
class Apeiron_Dashboard {

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// ── Menu ─────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		// Menu top-level Apeiron
		add_menu_page(
			__( 'Apeiron', 'apeiron-ai-bot-tracker' ),
			__( 'Apeiron', 'apeiron-ai-bot-tracker' ),
			'edit_posts',
			'apeiron-dashboard',
			[ $this, 'render_page' ],
			APEIRON_URL . 'assets/images/apeiron-icon.svg',
			30
		);

		// Sottomenu: Dashboard
		add_submenu_page(
			'apeiron-dashboard',
			__( 'Dashboard', 'apeiron-ai-bot-tracker' ),
			__( 'Dashboard', 'apeiron-ai-bot-tracker' ),
			'edit_posts',
			'apeiron-dashboard',
			[ $this, 'render_page' ]
		);

		// Sottomenu: Bot Activity
		add_submenu_page(
			'apeiron-dashboard',
			__( 'Bot Activity', 'apeiron-ai-bot-tracker' ),
			__( 'Bot Activity', 'apeiron-ai-bot-tracker' ),
			'edit_posts',
			'apeiron-bot-activity',
			[ $this, 'render_activity_page' ]
		);

		// Settings è registrato da Apeiron_Admin::register_menu()
	}

	// ── Dashboard principale ─────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$logger    = new Apeiron_Logger();
		$stats     = $logger->get_stats( 7 );
		$new_bots  = $logger->get_first_time_bots( 7 );
		$recent    = $logger->get_activity( 20, 0 );
		$articles  = $this->get_all_articles();

		$total_requests  = (int) $stats['total_requests'];
		$unique_bots     = (int) $stats['unique_bots'];
		$verified_agents = (int) $stats['verified_agents'];
		$total_articles  = count( $articles );
		?>
		<div class="wrap apeiron-dashboard-wrap">

			<!-- Header -->
			<div class="apeiron-dash-header">
				<div>
					<h1 class="apeiron-dash-title"><?php esc_html_e( 'AI Bot Tracker', 'apeiron-ai-bot-tracker' ); ?></h1>
					<p class="apeiron-dash-subtitle">
						<?php esc_html_e( 'Automatic detection and logging of AI bots visiting your content.', 'apeiron-ai-bot-tracker' ); ?>
					</p>
				</div>
			</div>

			<?php if ( $new_bots ) : ?>
			<!-- Nuovi bot alert -->
			<div class="apeiron-dash-wallet-error" style="display:block;background:#1a2a1a;border-color:#4caf50;color:#4caf50;margin-bottom:16px;padding:10px 14px;border-left:3px solid #4caf50;border-radius:3px">
				<strong><?php esc_html_e( 'New bots detected this week:', 'apeiron-ai-bot-tracker' ); ?></strong>
				<?php
				$names = array_map( function( $b ) {
					return esc_html( $b['bot_name'] );
				}, $new_bots );
				echo implode( ', ', $names );
				?>
			</div>
			<?php endif; ?>

			<!-- KPI cards -->
			<div class="apeiron-dash-kpis">
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-revenue"><?php echo esc_html( number_format( $total_requests ) ); ?></span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'TOTAL BOT REQUESTS (7D)', 'apeiron-ai-bot-tracker' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value"><?php echo esc_html( $unique_bots ); ?></span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'UNIQUE BOTS', 'apeiron-ai-bot-tracker' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi apeiron-dash-kpi-ai">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-ai-val"><?php echo esc_html( $verified_agents ); ?></span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'VERIFIED AGENTS', 'apeiron-ai-bot-tracker' ); ?></span>
				</div>
				<div class="apeiron-dash-kpi">
					<span class="apeiron-dash-kpi-value apeiron-dash-kpi-muted"><?php echo esc_html( $total_articles ); ?></span>
					<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'PUBLISHED ARTICLES', 'apeiron-ai-bot-tracker' ); ?></span>
				</div>
			</div>

			<!-- Top bots -->
			<?php if ( ! empty( $stats['top_bots'] ) ) : ?>
			<div class="apeiron-dash-table-wrap" style="margin-top:24px">
				<h2 style="margin-bottom:12px"><?php esc_html_e( 'Top Bots (7 days)', 'apeiron-ai-bot-tracker' ); ?></h2>
				<table class="apeiron-dash-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'BOT', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'COMPANY', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'REQUESTS', 'apeiron-ai-bot-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['top_bots'] as $bot ) : ?>
						<tr>
							<td><?php echo esc_html( $bot['bot_name'] ); ?></td>
							<td><?php echo esc_html( $bot['bot_company'] ); ?></td>
							<td><?php echo esc_html( number_format( (int) $bot['count'] ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- Recent activity -->
			<div class="apeiron-dash-table-wrap" style="margin-top:24px">
				<h2 style="margin-bottom:12px">
					<?php esc_html_e( 'Recent Activity', 'apeiron-ai-bot-tracker' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=apeiron-bot-activity' ) ); ?>" style="font-size:13px;font-weight:normal;margin-left:12px"><?php esc_html_e( 'View all →', 'apeiron-ai-bot-tracker' ); ?></a>
				</h2>
				<?php if ( $recent ) : ?>
				<table class="apeiron-dash-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'DATE', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'BOT', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'COMPANY', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'CONTENT', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'VERIFIED', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'ACTION', 'apeiron-ai-bot-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $row ) :
							$title = $row['post_id'] ? get_the_title( (int) $row['post_id'] ) : '';
							$url   = $row['post_id'] ? get_permalink( (int) $row['post_id'] ) : '';
						?>
						<tr>
							<td style="white-space:nowrap"><?php echo esc_html( substr( $row['created_at'], 0, 16 ) ); ?></td>
							<td><?php echo esc_html( $row['bot_name'] ); ?></td>
							<td><?php echo esc_html( $row['bot_company'] ); ?></td>
							<td><?php
								if ( $title && $url ) {
									echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_trim_words( $title, 6 ) ) . '</a>';
								} else {
									echo esc_html( wp_trim_words( $row['request_url'], 5 ) );
								}
							?></td>
							<td><?php echo $row['is_registered_agent'] ? '<span style="color:#4caf50">&#10003;</span>' : '<span style="color:#888">&mdash;</span>'; ?></td>
							<td><code><?php echo esc_html( $row['action_taken'] ); ?></code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
				<p style="color:#888"><?php esc_html_e( 'No bot activity recorded yet. Bots will appear here automatically once they visit your content.', 'apeiron-ai-bot-tracker' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- On-chain analytics (sezione collassabile) -->
			<details style="margin-top:32px">
				<summary style="cursor:pointer;color:#c8a96e;font-size:16px;font-weight:600;padding:12px 0">
					<?php esc_html_e( 'On-Chain Analytics (requires wallet)', 'apeiron-ai-bot-tracker' ); ?>
				</summary>
				<div style="margin-top:16px">
					<div class="apeiron-dash-header" style="margin-bottom:16px">
						<div>
							<p class="apeiron-dash-subtitle">
								<?php esc_html_e( 'Connect your wallet to see on-chain revenue, readers and AI bot payments.', 'apeiron-ai-bot-tracker' ); ?>
							</p>
						</div>
						<div class="apeiron-dash-actions">
							<button id="apeiron-dash-connect" class="apeiron-dash-btn apeiron-dash-btn-connect">
								<?php esc_html_e( 'Connect Wallet', 'apeiron-ai-bot-tracker' ); ?>
							</button>
						</div>
					</div>

					<div id="apeiron-dash-wallet-error" style="display:none" class="apeiron-dash-wallet-error"></div>

					<div class="apeiron-dash-kpis">
						<div class="apeiron-dash-kpi">
							<span class="apeiron-dash-kpi-value apeiron-dash-kpi-revenue" id="dash-total-revenue">—</span>
							<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'TOTAL REVENUE (USDC)', 'apeiron-ai-bot-tracker' ); ?></span>
						</div>
						<div class="apeiron-dash-kpi">
							<span class="apeiron-dash-kpi-value" id="dash-total-humans">—</span>
							<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'HUMAN READERS', 'apeiron-ai-bot-tracker' ); ?></span>
						</div>
						<div class="apeiron-dash-kpi apeiron-dash-kpi-ai">
							<span class="apeiron-dash-kpi-value apeiron-dash-kpi-ai-val" id="dash-total-bots">—</span>
							<span class="apeiron-dash-kpi-label"><?php esc_html_e( 'AI BOTS INTERCEPTED (on-chain)', 'apeiron-ai-bot-tracker' ); ?></span>
						</div>
					</div>

					<div id="apeiron-dash-loading" style="display:none" class="apeiron-dash-loading">
						<span class="apeiron-dash-spinner"></span>
						<?php esc_html_e( 'Reading on-chain data…', 'apeiron-ai-bot-tracker' ); ?>
					</div>

					<div class="apeiron-dash-table-wrap" style="margin-top:16px">
						<table class="apeiron-dash-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'ARTICLE', 'apeiron-ai-bot-tracker' ); ?></th>
									<th><?php esc_html_e( 'HUMANS', 'apeiron-ai-bot-tracker' ); ?></th>
									<th><?php esc_html_e( 'BOTS', 'apeiron-ai-bot-tracker' ); ?></th>
									<th><?php esc_html_e( 'REVENUE', 'apeiron-ai-bot-tracker' ); ?></th>
									<th><?php esc_html_e( 'LINK', 'apeiron-ai-bot-tracker' ); ?></th>
								</tr>
							</thead>
							<tbody id="apeiron-dash-tbody">
								<?php
								$protected_articles = $this->get_protected_articles();
								foreach ( $protected_articles as $article ) : ?>
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
												<?php esc_html_e( 'View →', 'apeiron-ai-bot-tracker' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
								<?php if ( empty( $protected_articles ) ) : ?>
									<tr>
										<td colspan="5" class="apeiron-dash-empty">
											<?php esc_html_e( 'No on-chain registered articles found.', 'apeiron-ai-bot-tracker' ); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</details>

		</div>
		<?php
	}

	// ── Bot Activity page ────────────────────────────────────────────────────

	public function render_activity_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$logger = new Apeiron_Logger();

		// Filtri dal form
		$filters = [];
		if ( ! empty( $_GET['bot_key'] ) ) {
			$filters['bot_key'] = sanitize_text_field( wp_unslash( $_GET['bot_key'] ) );
		}
		if ( isset( $_GET['is_registered_agent'] ) && $_GET['is_registered_agent'] !== '' ) {
			$filters['is_registered_agent'] = (int) $_GET['is_registered_agent'];
		}
		if ( ! empty( $_GET['post_id'] ) ) {
			$filters['post_id'] = (int) $_GET['post_id'];
		}

		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;

		$rows     = $logger->get_activity( $limit, $offset, $filters );
		$detector = new Apeiron_Detector();
		$all_bots = $detector->get_known_bots();
		?>
		<div class="wrap apeiron-dashboard-wrap">
			<h1><?php esc_html_e( 'Bot Activity Log', 'apeiron-ai-bot-tracker' ); ?></h1>

			<!-- Form filtri -->
			<form method="get" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
				<input type="hidden" name="page" value="apeiron-bot-activity" />
				<div>
					<label style="display:block;font-size:12px;color:#888;margin-bottom:3px"><?php esc_html_e( 'Bot', 'apeiron-ai-bot-tracker' ); ?></label>
					<select name="bot_key">
						<option value=""><?php esc_html_e( 'All bots', 'apeiron-ai-bot-tracker' ); ?></option>
						<?php foreach ( $all_bots as $key => $info ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['bot_key'] ?? '', $key ); ?>>
							<?php echo esc_html( $info['name'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label style="display:block;font-size:12px;color:#888;margin-bottom:3px"><?php esc_html_e( 'Registry', 'apeiron-ai-bot-tracker' ); ?></label>
					<select name="is_registered_agent">
						<option value=""><?php esc_html_e( 'All', 'apeiron-ai-bot-tracker' ); ?></option>
						<option value="1" <?php selected( isset( $filters['is_registered_agent'] ) && $filters['is_registered_agent'] === 1, true ); ?>><?php esc_html_e( 'Verified', 'apeiron-ai-bot-tracker' ); ?></option>
						<option value="0" <?php selected( isset( $filters['is_registered_agent'] ) && $filters['is_registered_agent'] === 0, true ); ?>><?php esc_html_e( 'Anonymous', 'apeiron-ai-bot-tracker' ); ?></option>
					</select>
				</div>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'apeiron-ai-bot-tracker' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=apeiron-bot-activity' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Reset', 'apeiron-ai-bot-tracker' ); ?></a>
			</form>

			<?php if ( $rows ) : ?>
			<div class="apeiron-dash-table-wrap">
				<table class="apeiron-dash-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'DATE', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'BOT', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'COMPANY', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'PURPOSE', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'CONTENT', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'IP', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'VERIFIED', 'apeiron-ai-bot-tracker' ); ?></th>
							<th><?php esc_html_e( 'ACTION', 'apeiron-ai-bot-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) :
							$title = $row['post_id'] ? get_the_title( (int) $row['post_id'] ) : '';
							$url   = $row['post_id'] ? get_permalink( (int) $row['post_id'] ) : '';
						?>
						<tr>
							<td style="white-space:nowrap"><?php echo esc_html( substr( $row['created_at'], 0, 16 ) ); ?></td>
							<td><?php echo esc_html( $row['bot_name'] ); ?></td>
							<td><?php echo esc_html( $row['bot_company'] ); ?></td>
							<td><?php echo esc_html( $row['bot_purpose'] ); ?></td>
							<td><?php
								if ( $title && $url ) {
									echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_trim_words( $title, 6 ) ) . '</a>';
								} else {
									echo esc_html( wp_trim_words( $row['request_url'], 5 ) );
								}
							?></td>
							<td><code><?php echo esc_html( $row['ip_address'] ); ?></code></td>
							<td><?php echo $row['is_registered_agent'] ? '<span style="color:#4caf50">&#10003;</span>' : '<span style="color:#888">&mdash;</span>'; ?></td>
							<td><code><?php echo esc_html( $row['action_taken'] ); ?></code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( count( $rows ) === $limit ) : ?>
			<div style="margin-top:12px">
				<a href="<?php echo esc_url( add_query_arg( array_merge( $_GET, [ 'paged' => $page + 1 ] ) ) ); ?>" class="button"><?php esc_html_e( 'Next page →', 'apeiron-ai-bot-tracker' ); ?></a>
			</div>
			<?php endif; ?>

			<?php else : ?>
			<p style="color:#888"><?php esc_html_e( 'No activity found for the selected filters.', 'apeiron-ai-bot-tracker' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function get_all_articles(): array {
		$query = new WP_Query( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		return is_array( $query->posts ) ? $query->posts : [];
	}

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
				continue;
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
		$dashboard_hooks = [ 'toplevel_page_apeiron-dashboard', 'apeiron_page_apeiron-bot-activity' ];
		if ( ! in_array( $hook, $dashboard_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'apeiron-dashboard',
			APEIRON_URL . 'assets/css/apeiron-paywall.css',
			[],
			APEIRON_VERSION
		);

		// On-chain assets caricati solo sulla dashboard principale
		if ( 'toplevel_page_apeiron-dashboard' === $hook ) {
			wp_enqueue_script(
				'ethers',
				APEIRON_URL . 'assets/js/ethers.umd.min.js', // bundled locally
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

			$protected_articles = $this->get_protected_articles();

			wp_localize_script( 'apeiron-dashboard', 'apeironDash', [
				'gatewayAddress'  => get_option( 'apeiron_gateway_address', APEIRON_DEFAULT_GATEWAY ),
				'publisherWallet' => get_option( 'apeiron_publisher_wallet', '' ),
				'rpcUrl'          => get_option( 'apeiron_rpc_url', APEIRON_DEFAULT_RPC ),
				'chainId'         => APEIRON_CHAIN_ID,
				'articles'        => $protected_articles,
			] );
		}
	}
}
