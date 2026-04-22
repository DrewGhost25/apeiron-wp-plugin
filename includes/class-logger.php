<?php
defined( 'ABSPATH' ) || exit;

/**
 * Apeiron_Logger — registra e interroga i log dei bot AI.
 */
class Apeiron_Logger {

	/**
	 * Inserisce un record nel log dei bot.
	 *
	 * @param array  $bot        Dati bot da Apeiron_Detector::detect()
	 * @param int    $post_id    ID del post visitato (0 se non applicabile)
	 * @param string $user_agent User-Agent completo
	 * @param string $ip         IP del visitatore
	 * @param string $agent_id   ID agente Apeiron Registry (può essere '')
	 * @param bool   $verified   Se l'agente è verificato
	 * @param string $action     'allowed'|'blocked'|'registry_verified'|'logged'
	 */
	public function log( array $bot, int $post_id, string $user_agent, string $ip, string $agent_id, bool $verified, string $action ): int {
		global $wpdb;

		$request_url = $post_id > 0 ? ( get_permalink( $post_id ) ?: sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' ) ) : sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'apeiron_bot_log',
			[
				'bot_key'             => sanitize_text_field( $bot['bot_key'] ?? '' ),
				'bot_name'            => sanitize_text_field( $bot['name']    ?? '' ),
				'bot_company'         => sanitize_text_field( $bot['company'] ?? '' ),
				'bot_purpose'         => sanitize_text_field( $bot['purpose'] ?? '' ),
				'ip_address'          => sanitize_text_field( $ip ),
				'request_url'         => esc_url_raw( $request_url ),
				'post_id'             => $post_id > 0 ? $post_id : null,
				'is_registered_agent' => $verified ? 1 : 0,
				'agent_id'            => sanitize_text_field( $agent_id ),
				'action_taken'        => sanitize_text_field( $action ),
				'created_at'          => current_time( 'mysql', true ),
			],
			[
				'%s', '%s', '%s', '%s',
				'%s', '%s', '%d',
				'%d', '%s', '%s', '%s',
			]
		);

		if ( false === $result ) {
			error_log( 'Apeiron Logger: DB insert failed — ' . $wpdb->last_error );
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Aggiorna un log entry esistente come verificato da Apeiron Registry.
	 * Se company_name è fornito, sovrascrive bot_name e bot_company con i dati reali dell'agente.
	 */
	public function mark_verified( int $log_id, string $agent_id, string $company_name = '' ): void {
		global $wpdb;
		if ( $log_id <= 0 ) return;

		$data   = [
			'is_registered_agent' => 1,
			'agent_id'            => sanitize_text_field( $agent_id ),
			'action_taken'        => 'registry_verified',
		];
		$format = [ '%d', '%s', '%s' ];

		if ( $company_name ) {
			$data['bot_name']    = sanitize_text_field( $company_name );
			$data['bot_company'] = sanitize_text_field( $company_name );
			$format[]            = '%s';
			$format[]            = '%s';
		}

		$wpdb->update(
			$wpdb->prefix . 'apeiron_bot_log',
			$data,
			[ 'id' => $log_id ],
			$format,
			[ '%d' ]
		);
	}

	/**
	 * Ritorna statistiche aggregate per gli ultimi $days giorni.
	 *
	 * @param int $days
	 * @return array
	 */
	public function get_stats( int $days = 7 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'apeiron_bot_log';

		$total_requests = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		$unique_bots = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bot_key) FROM `{$table}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND bot_key != ''",
				$days
			)
		);

		$verified_agents = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND is_registered_agent = 1",
				$days
			)
		);

		$top_bots_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, bot_key, bot_company, COUNT(*) AS `count`
				 FROM `{$table}`
				 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND bot_key != ''
				 GROUP BY bot_name, bot_key, bot_company
				 ORDER BY `count` DESC
				 LIMIT 10",
				$days
			),
			ARRAY_A
		);
		$top_bots = is_array( $top_bots_raw ) ? $top_bots_raw : [];

		$top_content_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, COUNT(*) AS `count`
				 FROM `{$table}`
				 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND post_id IS NOT NULL
				 GROUP BY post_id
				 ORDER BY `count` DESC
				 LIMIT 10",
				$days
			),
			ARRAY_A
		);

		$top_content = [];
		if ( is_array( $top_content_raw ) ) {
			foreach ( $top_content_raw as $row ) {
				$pid             = (int) $row['post_id'];
				$row['title']    = $pid ? get_the_title( $pid ) : '';
				$row['url']      = $pid ? get_permalink( $pid ) : '';
				$top_content[]   = $row;
			}
		}

		return [
			'total_requests'  => $total_requests,
			'unique_bots'     => $unique_bots,
			'verified_agents' => $verified_agents,
			'top_bots'        => $top_bots,
			'top_content'     => $top_content,
		];
	}

	/**
	 * Ritorna righe dalla tabella bot_log con paginazione e filtri opzionali.
	 *
	 * @param int   $limit
	 * @param int   $offset
	 * @param array $filters Chiavi supportate: bot_key, is_registered_agent, post_id
	 * @return array
	 */
	public function get_activity( int $limit = 50, int $offset = 0, array $filters = [] ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'apeiron_bot_log';
		$where  = [];
		$values = [];

		if ( ! empty( $filters['bot_key'] ) ) {
			$where[]  = 'bot_key = %s';
			$values[] = sanitize_text_field( $filters['bot_key'] );
		}

		if ( isset( $filters['is_registered_agent'] ) ) {
			$where[]  = 'is_registered_agent = %d';
			$values[] = (int) $filters['is_registered_agent'];
		}

		if ( ! empty( $filters['post_id'] ) ) {
			$where[]  = 'post_id = %d';
			$values[] = (int) $filters['post_id'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$values[] = $limit;
		$values[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$values
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Ritorna i bot che appaiono per la prima volta negli ultimi $days giorni.
	 *
	 * @param int $days
	 * @return array
	 */
	public function get_first_time_bots( int $days = 7 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'apeiron_bot_log';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_key, bot_name, bot_company, MIN(created_at) AS first_seen
				 FROM `{$table}`
				 WHERE bot_key != ''
				 GROUP BY bot_key, bot_name, bot_company
				 HAVING MIN(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 ORDER BY first_seen DESC",
				$days
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Elimina i log più vecchi di $days giorni.
	 *
	 * @param int $days
	 */
	public function cleanup_old_logs( int $days = 90 ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}apeiron_bot_log` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Pianifica l'email settimanale se non già schedulata.
	 */
	public function schedule_weekly_email(): void {
		if ( ! wp_next_scheduled( 'apeiron_weekly_email' ) ) {
			$next_monday = strtotime( 'next monday 08:00' );
			wp_schedule_event( $next_monday, 'weekly', 'apeiron_weekly_email' );
		}
	}

	/**
	 * Callback per il cron 'apeiron_weekly_email'.
	 * Invia il report settimanale al publisher.
	 */
	public function send_weekly_email(): void {
		$to = get_option( 'apeiron_publisher_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		$stats_7d  = $this->get_stats( 7 );
		$stats_14d = $this->get_stats( 14 );

		// Calcolo richieste settimana precedente (14d - 7d)
		$prev_total = max( 0, $stats_14d['total_requests'] - $stats_7d['total_requests'] );

		$site_name = get_bloginfo( 'name' );
		$subject   = "\xF0\x9F\xA4\x96 Weekly AI Bot Report \xe2\x80\x94 " . $site_name;

		// Costruzione top 5 bot con variazione
		$top5_html = '';
		$top5_bots = array_slice( $stats_7d['top_bots'], 0, 5 );
		foreach ( $top5_bots as $bot ) {
			// Trova count nella settimana precedente dallo stats 14d
			$prev_count = 0;
			foreach ( $stats_14d['top_bots'] as $prev_bot ) {
				if ( $prev_bot['bot_key'] === $bot['bot_key'] ) {
					$prev_count = (int) $prev_bot['count'] - (int) $bot['count'];
					$prev_count = max( 0, $prev_count );
					break;
				}
			}
			$change = '';
			if ( $prev_count > 0 ) {
				$pct    = round( ( ( (int) $bot['count'] - $prev_count ) / $prev_count ) * 100 );
				$arrow  = $pct >= 0 ? '+' : '';
				$change = " ({$arrow}{$pct}%)";
			}
			$top5_html .= '<tr><td>' . esc_html( $bot['bot_name'] ) . '</td><td>' . esc_html( $bot['bot_company'] ) . '</td><td>' . intval( $bot['count'] ) . esc_html( $change ) . '</td></tr>';
		}

		// Top 5 contenuti
		$top5_content_html = '';
		foreach ( array_slice( $stats_7d['top_content'], 0, 5 ) as $c ) {
			$title              = $c['title'] ?: __( '(no title)', 'apeiron-ai-bot-tracker' );
			$url                = $c['url']   ?: '';
			$top5_content_html .= '<tr><td>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td><td>' . intval( $c['count'] ) . '</td></tr>';
		}

		// Nuovi bot
		$new_bots      = $this->get_first_time_bots( 7 );
		$new_bots_html = '';
		if ( $new_bots ) {
			$new_bots_html = '<h3 style="color:#c8a96e">New bots detected this week</h3><ul>';
			foreach ( $new_bots as $nb ) {
				$new_bots_html .= '<li>' . esc_html( $nb['bot_name'] ) . ' (' . esc_html( $nb['bot_company'] ) . ') — first seen: ' . esc_html( $nb['first_seen'] ) . '</li>';
			}
			$new_bots_html .= '</ul>';
		}

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="background:#0d1117;color:#c9d1d9;font-family:Arial,sans-serif;padding:20px">
<h2 style="color:#c8a96e">' . "\xF0\x9F\xA4\x96 Weekly AI Bot Report &mdash; " . esc_html( $site_name ) . '</h2>
<table style="width:100%;max-width:600px;border-collapse:collapse;margin-bottom:20px">
  <tr><td style="padding:12px;background:#161b22;border:1px solid #30363d"><strong>Total requests (7d)</strong></td><td style="padding:12px;background:#161b22;border:1px solid #30363d">' . intval( $stats_7d['total_requests'] ) . '</td></tr>
  <tr><td style="padding:12px;background:#0d1117;border:1px solid #30363d"><strong>Unique bots</strong></td><td style="padding:12px;background:#0d1117;border:1px solid #30363d">' . intval( $stats_7d['unique_bots'] ) . '</td></tr>
  <tr><td style="padding:12px;background:#161b22;border:1px solid #30363d"><strong>Verified agents</strong></td><td style="padding:12px;background:#161b22;border:1px solid #30363d">' . intval( $stats_7d['verified_agents'] ) . '</td></tr>
</table>
<h3 style="color:#c8a96e">Top 5 Bots</h3>
<table style="width:100%;max-width:600px;border-collapse:collapse;margin-bottom:20px">
<thead><tr style="background:#161b22"><th style="padding:8px;border:1px solid #30363d;text-align:left">Bot</th><th style="padding:8px;border:1px solid #30363d;text-align:left">Company</th><th style="padding:8px;border:1px solid #30363d;text-align:left">Requests</th></tr></thead>
<tbody>' . $top5_html . '</tbody></table>
<h3 style="color:#c8a96e">Top 5 Articles</h3>
<table style="width:100%;max-width:600px;border-collapse:collapse;margin-bottom:20px">
<thead><tr style="background:#161b22"><th style="padding:8px;border:1px solid #30363d;text-align:left">Article</th><th style="padding:8px;border:1px solid #30363d;text-align:left">Requests</th></tr></thead>
<tbody>' . $top5_content_html . '</tbody></table>
' . $new_bots_html . '
<p style="color:#6e7681;font-size:12px;margin-top:30px">Sent by Apeiron AI Bot Tracker &mdash; <a href="' . esc_url( admin_url( 'admin.php?page=apeiron-dashboard' ) ) . '" style="color:#c8a96e">View Dashboard</a></p>
</body></html>';

		$text = "Weekly AI Bot Report -- {$site_name}\n\n"
			. "Total requests (7d): {$stats_7d['total_requests']}\n"
			. "Unique bots: {$stats_7d['unique_bots']}\n"
			. "Verified agents: {$stats_7d['verified_agents']}\n\n"
			. "View your dashboard: " . admin_url( 'admin.php?page=apeiron-dashboard' );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
		];

		wp_mail( $to, $subject, $html, $headers );
	}
}
