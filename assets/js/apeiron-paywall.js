/**
 * Apeiron Paywall — logica wallet + pagamento
 *
 * Dipende da: ethers.js v6 (UMD) caricato prima via CDN
 * Dati iniettati da PHP: window.apeironData
 */

( function () {
	'use strict';

	const {
		humanPrice,
		gatewayAddress,
		usdcAddress,
		chainId,
		verifyEndpoint,
		postUrl,
		publisherWallet,
		i18n,
	} = window.apeironData;

	// Se il contentId non è stato ancora registrato on-chain, lo calcoliamo
	// client-side da keccak256(postUrl) — identico a quanto fa il server PHP.
	let contentId = window.apeironData.contentId;
	if ( ! contentId ) {
		contentId = ethers.keccak256( ethers.toUtf8Bytes( postUrl ) );
	}

	// ── ABI minimi ──────────────────────────────────────────────────────────

	// Firme reali da X402GatewayV3
	const GATEWAY_ABI = [
		'function hasAccess(address user, bytes32 contentId, uint8 accessType) view returns (bool)',
		'function unlockAsHuman(bytes32 contentId, uint256 duration)',
		'function unlockAsAgent(bytes32 contentId, uint256 duration)',
	];

	const ERC20_ABI = [
		// balanceOf(address) → uint256
		'function balanceOf(address owner) view returns (uint256)',
		// allowance(address owner, address spender) → uint256
		'function allowance(address owner, address spender) view returns (uint256)',
		// approve(address spender, uint256 amount) → bool
		'function approve(address spender, uint256 amount) returns (bool)',
	];

	// ── Stato ────────────────────────────────────────────────────────────────

	let provider = null;
	let signer   = null;
	let wallet   = null;

	// ── DOM helpers ──────────────────────────────────────────────────────────

	const $ = id => document.getElementById( id );

	function setStatus( msg, isError = false ) {
		const el = $( 'apeiron-status' );
		if ( el ) {
			el.textContent = msg;
			el.className   = isError ? 'apeiron-status apeiron-error' : 'apeiron-status';
			el.style.display = msg ? 'block' : 'none';
		}
	}

	function setButtonState( btnId, text, disabled ) {
		const btn = $( btnId );
		if ( btn ) {
			btn.textContent = text;
			btn.disabled    = disabled;
		}
	}

	// ── Mostra contenuto completo ────────────────────────────────────────────

	function showContent() {
		const paywall = $( 'apeiron-paywall' );
		const full    = $( 'apeiron-full-content' );
		const preview = document.querySelector( '.apeiron-preview' );

		if ( paywall ) paywall.style.display  = 'none';
		if ( preview ) preview.style.display  = 'none';
		if ( full )    full.style.display      = 'block';
	}

	// ── Connessione wallet ───────────────────────────────────────────────────

	async function connectWallet() {
		if ( ! window.ethereum ) {
			setStatus( i18n.noMetaMask, true );
			return false;
		}

		setStatus( i18n.connecting );
		setButtonState( 'apeiron-connect-btn', i18n.connecting, true );

		try {
			provider = new ethers.BrowserProvider( window.ethereum );
			await provider.send( 'eth_requestAccounts', [] );
			signer  = await provider.getSigner();
			wallet  = await signer.getAddress();

			// Verifica chain
			const network = await provider.getNetwork();
			if ( Number( network.chainId ) !== Number( chainId ) ) {
				await switchToBase();
			}

			return true;
		} catch ( err ) {
			setStatus( i18n.error + ( err.reason || err.message ), true );
			setButtonState( 'apeiron-connect-btn', 'Connect Wallet', false );
			return false;
		}
	}

	async function switchToBase() {
		try {
			await window.ethereum.request( {
				method: 'wallet_switchEthereumChain',
				params: [ { chainId: '0x' + Number( chainId ).toString( 16 ) } ],
			} );
		} catch ( switchErr ) {
			if ( switchErr.code === 4902 ) {
				// Aggiungi Base Mainnet se non presente
				await window.ethereum.request( {
					method: 'wallet_addEthereumChain',
					params: [ {
						chainId:         '0x2105',
						chainName:       'Base Mainnet',
						nativeCurrency:  { name: 'Ether', symbol: 'ETH', decimals: 18 },
						rpcUrls:         [ 'https://mainnet.base.org' ],
						blockExplorerUrls: [ 'https://basescan.org' ],
					} ],
				} );
			} else {
				throw new Error( i18n.wrongChain );
			}
		}
	}

	// ── Verifica accesso on-chain ────────────────────────────────────────────

	async function checkExistingAccess( walletAddr ) {
		setStatus( i18n.checking );

		// Prima via REST endpoint WordPress (veloce, no tx)
		try {
			const res  = await fetch(
				`${verifyEndpoint}?wallet_address=${encodeURIComponent( walletAddr )}&content_id=${encodeURIComponent( contentId )}`
			);
			const data = await res.json();
			if ( data.hasAccess ) return true;
		} catch ( _ ) {
			// fallback a lettura diretta on-chain
		}

		// Fallback: lettura diretta dal contratto
		try {
			const gateway = new ethers.Contract( gatewayAddress, GATEWAY_ABI, provider );
			return await gateway.hasAccess( walletAddr, contentId, 0 );
		} catch ( err ) {
			console.warn( 'Apeiron: fallback hasAccess failed', err );
			return false;
		}
	}

	// ── Approve USDC ─────────────────────────────────────────────────────────

	async function approveUSDC( amountWei ) {
		setStatus( i18n.approving );
		const usdc = new ethers.Contract( usdcAddress, ERC20_ABI, signer );

		// Controlla allowance esistente
		const allowance = await usdc.allowance( wallet, gatewayAddress );
		if ( allowance >= amountWei ) return; // già approvato

		const tx = await usdc.approve( gatewayAddress, amountWei );
		setStatus( 'Approving… (tx: ' + tx.hash.substring( 0, 12 ) + '…)' );
		await tx.wait();
	}

	// ── Pagamento ─────────────────────────────────────────────────────────────

	async function unlockAsHuman() {
		// Prezzo in wei (USDC ha 6 decimali)
		const priceWei = ethers.parseUnits( humanPrice, 6 );

		setStatus( i18n.approving );
		await approveUSDC( priceWei );

		setStatus( i18n.paying );
		const gateway = new ethers.Contract( gatewayAddress, GATEWAY_ABI, signer );
		const tx      = await gateway.unlockAsHuman( contentId, 0n ); // 0 = permanent access

		setStatus( i18n.unlocking + ' tx: ' + tx.hash.substring( 0, 12 ) + '…' );
		await tx.wait();
	}

	async function unlockAsAgent() {
		const { aiPrice } = window.apeironData;
		const priceWei    = ethers.parseUnits( aiPrice, 6 );
		await approveUSDC( priceWei );

		const gateway = new ethers.Contract( gatewayAddress, GATEWAY_ABI, signer );
		const tx      = await gateway.unlockAsAgent( contentId, 0n );
		await tx.wait();
	}

	// ── Flow principale ──────────────────────────────────────────────────────

	async function handleConnectClick() {
		const connected = await connectWallet();
		if ( ! connected ) return;

		// Publisher bypass — se il wallet connesso è il publisher, accesso diretto
		if ( publisherWallet && wallet.toLowerCase() === publisherWallet.toLowerCase() ) {
			setStatus( 'Publisher recognized — full access.' );
			setTimeout( showContent, 600 );
			return;
		}

		// Verifica accesso esistente
		const hasAccess = await checkExistingAccess( wallet );
		if ( hasAccess ) {
			setStatus( i18n.alreadyPaid );
			setTimeout( showContent, 800 );
			return;
		}

		// Mostra pulsante pay
		setStatus( '' );
		const payBtn = $( 'apeiron-pay-btn' );
		if ( payBtn ) {
			payBtn.style.display = 'inline-flex';
			const walletShort    = wallet.substring( 0, 6 ) + '…' + wallet.slice( -4 );
			const connectBtn     = $( 'apeiron-connect-btn' );
			if ( connectBtn ) {
				connectBtn.textContent = walletShort;
				connectBtn.disabled    = true;
				connectBtn.classList.add( 'connected' );
			}
		}
	}

	async function handlePayClick() {
		setButtonState( 'apeiron-pay-btn', i18n.paying, true );
		setStatus( '' );

		try {
			await unlockAsHuman();
			setStatus( 'Access unlocked!' );
			setTimeout( showContent, 600 );
		} catch ( err ) {
			const msg = err.reason || err.message || String( err );
			setStatus( i18n.error + msg, true );
			setButtonState( 'apeiron-pay-btn', 'Pay ' + humanPrice + ' USDC', false );
		}
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function init() {
		const connectBtn = $( 'apeiron-connect-btn' );
		const payBtn     = $( 'apeiron-pay-btn' );

		if ( connectBtn ) {
			connectBtn.addEventListener( 'click', handleConnectClick );
		}

		if ( payBtn ) {
			payBtn.addEventListener( 'click', handlePayClick );
		}

		// Se ethers non è disponibile, mostra avviso
		if ( typeof ethers === 'undefined' ) {
			setStatus( 'Error: Web3 library not loaded. Reload the page.', true );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
