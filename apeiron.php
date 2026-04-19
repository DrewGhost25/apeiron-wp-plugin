<?php
/**
 * Plugin Name:       Apeiron — AI Bot Tracker
 * Plugin URI:        https://apeiron-registry.com
 * Description:       Know which AI bots read your content. Detect, log, and optionally block or monetize AI bot access. Integrates with Apeiron Registry for agent identity verification.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            drewghost25
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apeiron-ai-bot-tracker
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'APEIRON_VERSION',  '2.0.0' );
define( 'APEIRON_PATH',     plugin_dir_path( __FILE__ ) );
define( 'APEIRON_URL',      plugin_dir_url( __FILE__ ) );
define( 'APEIRON_BASENAME', plugin_basename( __FILE__ ) );

// Contract defaults
define( 'APEIRON_DEFAULT_GATEWAY', '0x6De5e0273428B14d88a690b200870f17888b0d77' );
define( 'APEIRON_DEFAULT_USDC',    '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913' );
define( 'APEIRON_DEFAULT_RPC',     'https://mainnet.base.org' );
define( 'APEIRON_CHAIN_ID',        8453 ); // Base Mainnet

// ── Load classes ─────────────────────────────────────────────────────────────
require_once APEIRON_PATH . 'includes/class-detector.php';
require_once APEIRON_PATH . 'includes/class-logger.php';
require_once APEIRON_PATH . 'includes/class-apeiron-admin.php';
require_once APEIRON_PATH . 'includes/class-apeiron-frontend.php';
require_once APEIRON_PATH . 'includes/class-apeiron-api.php';
require_once APEIRON_PATH . 'includes/class-apeiron-register.php';
require_once APEIRON_PATH . 'includes/class-apeiron-dashboard.php';

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'apeiron_boot' );

function apeiron_boot(): void {
	( new Apeiron_Admin() )->init();
	( new Apeiron_Frontend() )->init();
	( new Apeiron_Api() )->init();
	( new Apeiron_Register() )->init();
	( new Apeiron_Dashboard() )->init();

	$logger = new Apeiron_Logger();
	add_action( 'apeiron_weekly_email', [ $logger, 'send_weekly_email' ] );
}

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'apeiron_activate' );
register_deactivation_hook( __FILE__, 'apeiron_deactivate' );

function apeiron_activate(): void {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Tabella log accessi bot
	$sql_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}apeiron_bot_log (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		bot_key VARCHAR(100) NOT NULL DEFAULT '',
		bot_name VARCHAR(100) NOT NULL DEFAULT '',
		bot_company VARCHAR(100) NOT NULL DEFAULT '',
		bot_purpose VARCHAR(50) NOT NULL DEFAULT '',
		ip_address VARCHAR(45) NOT NULL DEFAULT '',
		request_url TEXT NOT NULL,
		post_id BIGINT UNSIGNED DEFAULT NULL,
		is_registered_agent TINYINT(1) NOT NULL DEFAULT 0,
		agent_id VARCHAR(100) NOT NULL DEFAULT '',
		action_taken VARCHAR(20) NOT NULL DEFAULT 'logged',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		KEY idx_bot_key (bot_key),
		KEY idx_created (created_at),
		KEY idx_post_id (post_id)
	) {$charset_collate};";

	// Tabella impostazioni per-bot
	$sql_settings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}apeiron_bot_settings (
		bot_key VARCHAR(100) NOT NULL PRIMARY KEY,
		action VARCHAR(20) NOT NULL DEFAULT 'allow',
		custom_message TEXT
	) {$charset_collate};";

	dbDelta( $sql_log );
	dbDelta( $sql_settings );

	// Opzioni default
	$defaults = [
		'apeiron_publisher_email'          => get_option( 'admin_email' ),
		'apeiron_gateway_address'          => APEIRON_DEFAULT_GATEWAY,
		'apeiron_usdc_address'             => APEIRON_DEFAULT_USDC,
		'apeiron_rpc_url'                  => APEIRON_DEFAULT_RPC,
		'apeiron_publisher_wallet'         => '',
		'apeiron_registry_url'             => 'https://www.apeiron-registry.com/api/registry/verify',
		'apeiron_registry_publisher_email' => '',
	];

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value );
		}
	}

	// Stats API key
	if ( false === get_option( 'apeiron_stats_api_key' ) ) {
		add_option( 'apeiron_stats_api_key', wp_generate_password( 32, false ) );
	}

	// Pianifica email settimanale
	( new Apeiron_Logger() )->schedule_weekly_email();

	flush_rewrite_rules();
}

function apeiron_deactivate(): void {
	wp_clear_scheduled_hook( 'apeiron_weekly_email' );
	flush_rewrite_rules();
}
