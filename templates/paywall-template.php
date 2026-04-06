<?php
/**
 * Template HTML del paywall Apeiron.
 *
 * Variabili disponibili (iniettate da Apeiron_Frontend::get_paywall_html):
 *   $human_price     — string, es. "0.10"
 *   $ai_price        — string, es. "1.00"
 *   $content_id      — string, bytes32 hex
 *   $gateway_address — string, indirizzo contratto
 *   $usdc_address    — string, indirizzo USDC
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Fade sull'anteprima -->
<div class="apeiron-fade" aria-hidden="true"></div>

<!-- Paywall card -->
<div id="apeiron-paywall" role="complementary" aria-label="<?php esc_attr_e( 'Paywall Apeiron', 'apeiron' ); ?>">
	<div class="apeiron-card">

		<!-- Logo -->
		<div class="apeiron-logo">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M12 2L2 19h20L12 2zm0 3.5L19.5 18h-15L12 5.5z"/>
			</svg>
			<span>Apeiron</span>
		</div>

		<!-- Titolo -->
		<h3 class="apeiron-title">
			<?php esc_html_e( 'Continue reading', 'apeiron' ); ?>
		</h3>

		<!-- Prezzo -->
		<p class="apeiron-price">
			$<?php echo esc_html( number_format( (float) $human_price, 2 ) ); ?> USDC
			&middot; <?php esc_html_e( 'Permanent access', 'apeiron' ); ?>
		</p>

		<!-- Bottone Connect -->
		<button id="apeiron-connect-btn"
		        class="apeiron-btn apeiron-btn-primary"
		        type="button">
			<!-- MetaMask fox icon -->
			<svg class="apeiron-mm-icon" viewBox="0 0 35 33" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M32.958 1L19.47 10.824l2.52-5.944L32.958 1z" fill="#E2761B" stroke="#E2761B" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M2.03 1l13.376 9.913-2.397-5.033L2.03 1zM28.15 23.533l-3.59 5.5 7.678 2.115 2.206-7.49-6.294-.125zM.577 23.658l2.194 7.49 7.678-2.115-3.59-5.5-6.282.125z" fill="#E4761B" stroke="#E4761B" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<?php esc_html_e( 'Connect Wallet', 'apeiron' ); ?>
		</button>

		<!-- Bottone Pay (visibile dopo connect) -->
		<button id="apeiron-pay-btn"
		        class="apeiron-btn apeiron-btn-primary"
		        type="button">
			<?php
			printf(
				/* translators: %s: price in USDC */
				esc_html__( 'Pay %s USDC', 'apeiron' ),
				esc_html( number_format( (float) $human_price, 2 ) )
			);
			?>
		</button>

		<!-- Status / feedback -->
		<p id="apeiron-status" class="apeiron-status" aria-live="polite"></p>

		<!-- Steps -->
		<ol class="apeiron-steps" aria-label="<?php esc_attr_e( 'Steps', 'apeiron' ); ?>">
			<li>
				<span class="step-num">1</span>
				<?php esc_html_e( 'Connect', 'apeiron' ); ?>
			</li>
			<li>
				<span class="step-num">2</span>
				<?php esc_html_e( 'Approve', 'apeiron' ); ?>
			</li>
			<li>
				<span class="step-num">3</span>
				<?php esc_html_e( 'Read', 'apeiron' ); ?>
			</li>
		</ol>

		<!-- Footer -->
		<p class="apeiron-footer">
			<?php esc_html_e( 'Powered by', 'apeiron' ); ?>
			<a href="https://apeiron-reader.com" target="_blank" rel="noopener noreferrer">Apeiron</a>
			&middot; Base Mainnet
			&middot;
			<a href="https://www.circle.com/usdc" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'What is USDC?', 'apeiron' ); ?>
			</a>
		</p>

	</div>
</div>
