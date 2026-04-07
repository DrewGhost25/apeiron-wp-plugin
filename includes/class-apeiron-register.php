<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gestisce la registrazione on-chain di un contenuto.
 *
 * Il contentId è calcolato lato browser (ethers.keccak256) per garantire
 * compatibilità con Ethereum (Keccak-256 != SHA3-256).
 * Il server salva il contentId ricevuto dal browser e gestisce lo stato.
 */
class Apeiron_Register {

	public function init(): void {
		add_action( 'wp_ajax_apeiron_save_content_id', [ $this, 'save_content_id' ] );
		add_action( 'wp_ajax_apeiron_mark_registered', [ $this, 'mark_registered' ] );
	}

	// ── AJAX: salva contentId calcolato dal browser ──────────────────────────

	public function save_content_id(): void {
		check_ajax_referer( 'apeiron_register', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'apeiron' ) ], 403 );
		}

		$post_id    = isset( $_POST['post_id'] )    ? absint( $_POST['post_id'] ) : 0;
		$content_id = isset( $_POST['content_id'] ) ? sanitize_text_field( wp_unslash( $_POST['content_id'] ) ) : '';

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Post not found.', 'apeiron' ) ], 404 );
		}

		if ( ! preg_match( '/^0x[0-9a-fA-F]{64}$/', $content_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid contentId.', 'apeiron' ) ], 400 );
		}

		update_post_meta( $post_id, '_apeiron_content_id', $content_id );

		wp_send_json_success( [ 'contentId' => $content_id ] );
	}

	// ── AJAX: mark as registered after tx confirmation ───────────────────────

	public function mark_registered(): void {
		check_ajax_referer( 'apeiron_register', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'apeiron' ) ], 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$tx_hash = isset( $_POST['tx_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['tx_hash'] ) ) : '';

		if ( ! $post_id || ! preg_match( '/^0x[0-9a-fA-F]{64}$/', $tx_hash ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid data.', 'apeiron' ) ], 400 );
		}

		update_post_meta( $post_id, '_apeiron_registered', '1' );
		update_post_meta( $post_id, '_apeiron_register_tx', $tx_hash );

		wp_send_json_success( [ 'message' => __( 'Registration saved.', 'apeiron' ), 'txHash' => $tx_hash ] );
	}

}

