<?php
/**
 * Plugin Name:       Apeiron — Web3 Content Paywall
 * Plugin URI:        https://apeiron-reader.com
 * Description:       Crypto paywall per articoli WordPress su Base Mainnet. Pagamenti in USDC via MetaMask.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Apeiron
 * Author URI:        https://apeiron-reader.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apeiron
 */

defined( 'ABSPATH' ) || exit;

// ── Costanti ────────────────────────────────────────────────────────────────
define( 'APEIRON_VERSION',  '1.1.0' );
define( 'APEIRON_PATH',     plugin_dir_path( __FILE__ ) );
define( 'APEIRON_URL',      plugin_dir_url( __FILE__ ) );
define( 'APEIRON_BASENAME', plugin_basename( __FILE__ ) );

// Defaults contratto
define( 'APEIRON_DEFAULT_GATEWAY', '0x6De5e0273428B14d88a690b200870f17888b0d77' );
define( 'APEIRON_DEFAULT_USDC',    '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913' );
define( 'APEIRON_DEFAULT_RPC',     'https://mainnet.base.org' );
define( 'APEIRON_CHAIN_ID',        8453 ); // Base Mainnet

// ── Carica classi ───────────────────────────────────────────────────────────
require_once APEIRON_PATH . 'includes/class-apeiron-admin.php';
require_once APEIRON_PATH . 'includes/class-apeiron-frontend.php';
require_once APEIRON_PATH . 'includes/class-apeiron-api.php';
require_once APEIRON_PATH . 'includes/class-apeiron-register.php';
require_once APEIRON_PATH . 'includes/class-apeiron-dashboard.php';

// ── Boot ────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'apeiron_boot' );

function apeiron_boot(): void {
	( new Apeiron_Admin() )->init();
	( new Apeiron_Frontend() )->init();
	( new Apeiron_Api() )->init();
	( new Apeiron_Register() )->init();
	( new Apeiron_Dashboard() )->init();
}

// ── Attivazione / Disattivazione ────────────────────────────────────────────
register_activation_hook( __FILE__, 'apeiron_activate' );
register_deactivation_hook( __FILE__, 'apeiron_deactivate' );

function apeiron_activate(): void {
	// Imposta opzioni default solo al primo avvio
	$defaults = [
		'gateway_address' => APEIRON_DEFAULT_GATEWAY,
		'usdc_address'    => APEIRON_DEFAULT_USDC,
		'rpc_url'         => APEIRON_DEFAULT_RPC,
		'publisher_wallet'=> '',
	];
	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( "apeiron_{$key}" ) ) {
			add_option( "apeiron_{$key}", $value );
		}
	}
	flush_rewrite_rules();
}

function apeiron_deactivate(): void {
	flush_rewrite_rules();
}
