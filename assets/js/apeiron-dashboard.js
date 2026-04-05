/**
 * Apeiron Dashboard Analytics
 *
 * Fix applicati:
 * - Verifica che il wallet connesso sia il publisher configurato
 * - getLogs con chunk piccoli (2000 blocchi) per compatibilità RPC pubblici
 * - Conteggio corretto humans (AccessType=0) e AI (AccessType=1)
 * - Revenue aggregata su tutti gli articoli
 */

( function () {
	'use strict';

	const { gatewayAddress, publisherWallet, rpcUrl, chainId, articles } = window.apeironDash;

	// AccessGranted(address,bytes32,uint8,uint256,uint256,uint256,uint256)
	const ACCESS_GRANTED_TOPIC = ethers.id(
		'AccessGranted(address,bytes32,uint8,uint256,uint256,uint256,uint256)'
	);

	// AccessType enum
	const ACCESS_HUMAN = 0; // READ_ONLY
	const ACCESS_AI    = 1; // DATA_MINING_LICENSED

	// USDC = 6 decimali
	const USDC_DIV = 10n ** 6n;

	// Base Mainnet: ~2s per blocco
	// 10.000 blocchi ≈ 5,5 ore | 50 chunk × 10k = 500k blocchi ≈ 11 giorni
	// Prima prova con chunk grande, fallback a 2000 se il RPC lo rifiuta
	const BLOCK_CHUNK_LARGE = 10_000;
	const BLOCK_CHUNK_SMALL = 2_000;

	// Lookback: ~500.000 blocchi ≈ 11 giorni a 2s/block su Base
	// Aumenta se hai contenuti più vecchi
	const BLOCKS_LOOKBACK = 500_000;

	// ── DOM ──────────────────────────────────────────────────────────────────

	const $   = id => document.getElementById( id );
	const btn = $( 'apeiron-dash-connect' );

	function setKpi( id, val ) {
		const el = $( id );
		if ( el ) el.textContent = val;
	}

	function setLoading( show ) {
		const el = $( 'apeiron-dash-loading' );
		if ( el ) el.style.display = show ? 'flex' : 'none';
	}

	function showWalletError( msg ) {
		const el = $( 'apeiron-dash-wallet-error' );
		if ( el ) {
			el.textContent = msg;
			el.style.display = 'block';
		}
	}

	function hideWalletError() {
		const el = $( 'apeiron-dash-wallet-error' );
		if ( el ) el.style.display = 'none';
	}

	// ── Connessione e verifica wallet ────────────────────────────────────────

	async function connectWallet() {
		hideWalletError();
		btn.textContent = 'Connessione…';
		btn.disabled    = true;

		if ( ! window.ethereum ) {
			showWalletError( '⚠ MetaMask non trovato. Installa l\'estensione e ricarica.' );
			btn.textContent = 'Connect Wallet';
			btn.disabled    = false;
			return;
		}

		try {
			const provider = new ethers.BrowserProvider( window.ethereum );
			await provider.send( 'eth_requestAccounts', [] );

			// Verifica chain
			const network = await provider.getNetwork();
			if ( Number( network.chainId ) !== Number( chainId ) ) {
				showWalletError( '⚠ Rete errata. Passa a Base Mainnet (Chain ID 8453) in MetaMask.' );
				btn.textContent = 'Connect Wallet';
				btn.disabled    = false;
				return;
			}

			const signer  = await provider.getSigner();
			const address = await signer.getAddress();

			// ── Verifica publisher ───────────────────────────────────────────
			if ( publisherWallet && address.toLowerCase() !== publisherWallet.toLowerCase() ) {
				const shortExpected  = publisherWallet.substring( 0, 6 ) + '…' + publisherWallet.slice( -4 );
				const shortConnected = address.substring( 0, 6 ) + '…' + address.slice( -4 );
				showWalletError(
					'⚠ Wallet non corrispondente.\n' +
					'Atteso: ' + shortExpected + ' — Connesso: ' + shortConnected + '\n' +
					'Seleziona il wallet publisher in MetaMask e riprova.'
				);
				btn.textContent = 'Connect Wallet';
				btn.disabled    = false;
				return;
			}

			// Connesso e verificato
			const short = address.substring( 0, 6 ) + '…' + address.slice( -4 );
			btn.textContent = '✓ ' + short;
			btn.classList.add( 'apeiron-dash-btn-connected' );

			await loadAnalytics();

		} catch ( err ) {
			showWalletError( '⚠ Errore: ' + ( err.message || String( err ) ) );
			btn.textContent = 'Connect Wallet';
			btn.disabled    = false;
		}
	}

	// ── Lettura eventi on-chain ──────────────────────────────────────────────

	async function loadAnalytics() {
		if ( ! articles || articles.length === 0 ) return;

		setLoading( true );
		setKpi( 'dash-total-revenue', '…' );
		setKpi( 'dash-total-humans',  '…' );
		setKpi( 'dash-total-bots',    '…' );

		try {
			const rpcProvider  = new ethers.JsonRpcProvider( rpcUrl );
			const currentBlock = await rpcProvider.getBlockNumber();
			const fromBlock    = Math.max( 0, currentBlock - BLOCKS_LOOKBACK );

			const loadingEl = $( 'apeiron-dash-loading' );

			// Determina chunk size ottimale: prima prova grande, poi fallback piccolo
			let chunkSize = BLOCK_CHUNK_LARGE;
			try {
				await rpcProvider.getLogs( {
					address:   gatewayAddress,
					topics:    [ ACCESS_GRANTED_TOPIC ],
					fromBlock: currentBlock - BLOCK_CHUNK_LARGE,
					toBlock:   currentBlock,
				} );
			} catch {
				chunkSize = BLOCK_CHUNK_SMALL;
			}

			const totalChunks = Math.ceil( ( currentBlock - fromBlock ) / chunkSize );
			let   doneChunks  = 0;
			let   allLogs     = [];

			for ( let from = fromBlock; from <= currentBlock; from += chunkSize ) {
				const to = Math.min( from + chunkSize - 1, currentBlock );
				try {
					const logs = await rpcProvider.getLogs( {
						address:   gatewayAddress,
						topics:    [ ACCESS_GRANTED_TOPIC ],
						fromBlock: from,
						toBlock:   to,
					} );
					allLogs = allLogs.concat( logs );
				} catch ( chunkErr ) {
					console.warn( 'Apeiron: chunk ' + from + '-' + to + ' failed', chunkErr );
				}
				doneChunks++;
				if ( loadingEl ) {
					const pct = Math.round( ( doneChunks / totalChunks ) * 100 );
					loadingEl.querySelector( 'span' )
						? null
						: null;
					loadingEl.lastChild.textContent = ' Lettura dati on-chain… ' + pct + '%';
				}
			}

			// Indice contentId → stats
			const statsMap = {};
			articles.forEach( a => {
				statsMap[ a.content_id.toLowerCase() ] = { humans: 0, bots: 0, revenue: 0n };
			} );

			// Decodifica ogni log
			const abiCoder = ethers.AbiCoder.defaultAbiCoder();
			for ( const log of allLogs ) {
				// topics[2] = contentId (indexed bytes32, già 0x-padded 66 chars)
				const rawContentId = log.topics[2].toLowerCase();

				if ( ! statsMap[ rawContentId ] ) continue; // non appartiene a questo publisher

				// data = accessType (uint8) | amountPaid (uint256) | platformFee (uint256)
				//        | publisherAmount (uint256) | expiresAt (uint256)
				const decoded = abiCoder.decode(
					[ 'uint8', 'uint256', 'uint256', 'uint256', 'uint256' ],
					log.data
				);
				const accessType      = Number( decoded[0] );
				const publisherAmount = decoded[3]; // wei USDC incassati dal publisher

				if ( accessType === ACCESS_HUMAN ) {
					statsMap[ rawContentId ].humans++;
				} else {
					statsMap[ rawContentId ].bots++;
				}
				statsMap[ rawContentId ].revenue += publisherAmount;
			}

			// Aggiorna tabella
			let totalRevenue = 0n;
			let totalHumans  = 0;
			let totalBots    = 0;

			const rows = document.querySelectorAll( '#apeiron-dash-tbody tr[data-content-id]' );
			rows.forEach( row => {
				const cid   = row.dataset.contentId.toLowerCase();
				const stats = statsMap[ cid ] || { humans: 0, bots: 0, revenue: 0n };

				row.querySelector( '.apeiron-dash-humans' ).textContent  = stats.humans;
				row.querySelector( '.apeiron-dash-bots' ).textContent    = stats.bots;
				row.querySelector( '.apeiron-dash-revenue' ).textContent = '$' + fmtUSDC( stats.revenue );

				if ( stats.bots > 0 ) {
					row.querySelector( '.apeiron-dash-bots' ).style.color = '#4ecb71';
				}

				totalRevenue += stats.revenue;
				totalHumans  += stats.humans;
				totalBots    += stats.bots;
			} );

			// KPI globali
			setKpi( 'dash-total-revenue', '$' + fmtUSDC( totalRevenue ) );
			setKpi( 'dash-total-humans',  String( totalHumans ) );
			setKpi( 'dash-total-bots',    String( totalBots ) );

		} catch ( err ) {
			console.error( 'Apeiron dashboard getLogs:', err );
			setKpi( 'dash-total-revenue', 'Errore' );
			showWalletError( '⚠ Errore lettura blockchain: ' + ( err.message || String( err ) ) );
		} finally {
			setLoading( false );
		}
	}

	// ── Formatta bigint wei USDC → "0.20" ───────────────────────────────────

	function fmtUSDC( wei ) {
		const whole = wei / USDC_DIV;
		const frac  = ( wei % USDC_DIV ).toString().padStart( 6, '0' ).slice( 0, 2 );
		return whole.toString() + '.' + frac;
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function init() {
		if ( btn ) btn.addEventListener( 'click', connectWallet );

		// Mostra wallet atteso nel subtitle
		if ( publisherWallet ) {
			const short = publisherWallet.substring( 0, 6 ) + '…' + publisherWallet.slice( -4 );
			const sub   = document.querySelector( '.apeiron-dash-subtitle' );
			if ( sub ) sub.textContent = 'Connect your wallet (' + short + ') to see readers, bots and earnings.';
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
