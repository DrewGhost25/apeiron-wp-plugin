/**
 * Apeiron Admin — gestione registrazione on-chain dal pannello WordPress.
 *
 * Dipende da: ethers.js v6 (caricato via CDN)
 * Dati iniettati da PHP: window.apeironAdmin
 */

( function ( $ ) {
	'use strict';

	const {
		ajaxUrl,
		nonce,
		gatewayAddress,
		usdcAddress,
		publisherWallet,
		chainId,
		i18n,
	} = window.apeironAdmin;

	// ABI minimo per registerContent (firme reali da X402GatewayV3)
	const GATEWAY_ABI = [
		'function registerContent(bytes32 contentId, uint256 humanPrice, uint256 agentPrice, string calldata contentURI)',
	];

	// ── Bottone "Registra su blockchain" ─────────────────────────────────────

	$( document ).on( 'click', '#apeiron-register-btn', async function () {
		const btn      = $( this );
		const postId   = btn.data( 'post-id' );
		const postUrl  = btn.data( 'post-url' );
		const humanPr  = btn.data( 'human-price' );
		const aiPr     = btn.data( 'ai-price' );
		const statusEl = $( '#apeiron-register-status' );

		btn.prop( 'disabled', true );
		statusEl.text( i18n.connecting ).css( 'color', '#c8a96e' );

		if ( ! window.ethereum ) {
			statusEl.text( i18n.noMetaMask ).css( 'color', '#e74c3c' );
			btn.prop( 'disabled', false );
			return;
		}

		try {
			// 1. Connetti wallet
			const provider = new ethers.BrowserProvider( window.ethereum );
			await provider.send( 'eth_requestAccounts', [] );

			// 2. Verifica chain
			const network = await provider.getNetwork();
			if ( Number( network.chainId ) !== Number( chainId ) ) {
				statusEl.text( i18n.wrongChain ).css( 'color', '#e74c3c' );
				btn.prop( 'disabled', false );
				return;
			}

			const signer        = await provider.getSigner();
			const connectedAddr = ( await signer.getAddress() ).toLowerCase();

			// ── Reminder wallet ──────────────────────────────────────────────
			if ( publisherWallet && connectedAddr !== publisherWallet.toLowerCase() ) {
				const shortExpected  = publisherWallet.substring( 0, 6 ) + '…' + publisherWallet.slice( -4 );
				const shortConnected = connectedAddr.substring( 0, 6 ) + '…' + connectedAddr.slice( -4 );

				const confirmed = window.confirm(
					'⚠️ Wallet diverso dal Publisher Wallet configurato nelle impostazioni!\n\n' +
					'Configurato:  ' + shortExpected + '\n' +
					'Connesso ora: ' + shortConnected + '\n\n' +
					'I pagamenti verranno inviati al wallet connesso, NON a quello configurato.\n\n' +
					'Vuoi continuare comunque?'
				);

				if ( ! confirmed ) {
					statusEl.text( 'Operazione annullata. Cambia wallet in MetaMask e riprova.' ).css( 'color', '#e74c3c' );
					btn.prop( 'disabled', false );
					return;
				}
			}

			statusEl.text( i18n.registering ).css( 'color', '#c8a96e' );

			// 3. Calcola contentId in JS (keccak256 Ethereum-compatibile)
			const cId = ethers.keccak256( ethers.toUtf8Bytes( postUrl ) );

			// Converti prezzi USDC → wei (6 decimali)
			const humanPriceWei = ethers.parseUnits( String( humanPr ), 6 );
			const aiPriceWei    = ethers.parseUnits( String( aiPr ),    6 );

			// Salva contentId su WordPress prima di inviare la tx
			await $.post( ajaxUrl, {
				action:     'apeiron_save_content_id',
				nonce:      nonce,
				post_id:    postId,
				content_id: cId,
			} );

			// 4. Invia transazione via MetaMask
			// registerContent(bytes32 contentId, uint256 humanPrice, uint256 agentPrice, string contentURI)
			const gateway = new ethers.Contract( gatewayAddress, GATEWAY_ABI, signer );
			const tx      = await gateway.registerContent(
				cId,
				humanPriceWei,
				aiPriceWei,
				postUrl  // contentURI = URL dell'articolo
			);

			statusEl.text( 'Tx inviata: ' + tx.hash.substring( 0, 16 ) + '… (in attesa)' ).css( 'color', '#c8a96e' );

			// tx.wait() può fallire con nonce:undefined su alcuni RPC — polling manuale
			let receipt = null;
			while ( ! receipt ) {
				await new Promise( r => setTimeout( r, 3000 ) );
				receipt = await provider.getTransactionReceipt( tx.hash );
			}

			// 5. Aggiorna stato su WordPress
			const markRes = await $.post( ajaxUrl, {
				action:  'apeiron_mark_registered',
				nonce:   nonce,
				post_id: postId,
				tx_hash: tx.hash,
			} );

			if ( markRes.success ) {
				statusEl.text( i18n.success + tx.hash.substring( 0, 12 ) + '…' ).css( 'color', '#27ae60' );
				// Aggiorna lo stato nella meta box senza ricaricare
				$( '.apeiron-not-registered' ).replaceWith(
					'<span class="apeiron-registered">&#10003; Registrato on-chain</span>' +
					'<br><small>ID: <code>' + cId.substring( 0, 12 ) + '…</code></small>'
				);
			}

		} catch ( err ) {
			const msg = err.reason || err.message || String( err );
			statusEl.text( i18n.error + msg ).css( 'color', '#e74c3c' );
		} finally {
			btn.prop( 'disabled', false );
		}
	} );

} )( jQuery );
