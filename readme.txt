=== Apeiron — Web3 Content Paywall ===
Contributors:      apeiron
Tags:              paywall, web3, crypto, usdc, base, metamask, blockchain, monetization
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Monetizza i tuoi contenuti con pagamenti crypto in USDC su Base Mainnet. Nessun intermediario, accesso permanente on-chain.

== Description ==

**Apeiron** aggiunge un paywall crypto ai tuoi articoli WordPress. I lettori pagano una volta sola in USDC (una stablecoin 1:1 con il dollaro) e ottengono accesso permanente verificato direttamente su blockchain Base Mainnet — nessun abbonamento, nessun login.

= Come funziona =

1. Connetti MetaMask
2. Approva la spesa USDC (firma in wallet)
3. Paga il contenuto (firma in wallet)
4. Accesso permanente garantito on-chain

= Configurazione per articolo =

Ogni articolo protetto ha una meta box con:
* **Proteggi con Apeiron** — attiva/disattiva il paywall
* **Prezzo lettori umani** — importo in USDC (default: $0.10)
* **Prezzo agenti AI** — importo in USDC (default: $1.00)
* **Paragrafi in anteprima** — quanti paragrafi mostrare prima del paywall (default: 4, range: 1–20)
* **Stato registrazione** — indica se il contenuto è registrato on-chain
* **Registra su blockchain** — invia la transazione di registrazione via MetaMask

= Caratteristiche =

* Paywall per singoli articoli con prezzi personalizzati
* Prezzo separato per lettori umani e agenti AI
* Numero di paragrafi in anteprima configurabile per ogni articolo (default: 4)
* Verifica accesso on-chain tramite smart contract su Base Mainnet
* Stile dark elegante, personalizzabile via CSS
* Nessun servizio esterno: tutto funziona con un RPC pubblico
* REST endpoint WordPress per verifica accesso senza ricaricare la pagina

= Smart Contract =

Il plugin si integra con lo smart contract Apeiron Gateway su Base Mainnet:
`0x6De5e0273428B14d88a690b200870f17888b0d77`

= Requisiti =

* WordPress 6.0+
* PHP 8.0+
* MetaMask o un wallet compatibile EIP-1193 installato nel browser
* Un indirizzo wallet publisher per ricevere i pagamenti

== Installation ==

1. Carica la cartella `apeiron-plugin` nella directory `/wp-content/plugins/`
2. Attiva il plugin dalla schermata "Plugin" di WordPress
3. Vai su **Impostazioni → Apeiron** e configura:
   - Il tuo **Publisher Wallet Address** (dove ricevi i pagamenti)
   - Gateway Contract Address (pre-compilato)
   - USDC Address (pre-compilato)
   - RPC URL (pre-compilato con `https://mainnet.base.org`)
4. Modifica un articolo e spunta **"Proteggi con Apeiron"** nella meta box laterale
5. Imposta il prezzo e clicca **"Registra su blockchain"** per registrare il contenuto on-chain

== Frequently Asked Questions ==

= Serve un wallet crypto? =

Sì, i lettori devono avere MetaMask (o un wallet compatibile EIP-1193) installato nel browser. MetaMask è gratuito e disponibile su [metamask.io](https://metamask.io).

= Quali criptovalute accetta? =

Solo USDC su Base Mainnet. L'USDC è una stablecoin 1:1 con il dollaro americano emessa da Circle, ed è la valuta più stabile e diffusa per i micropagamenti on-chain.

= Come ottengo USDC su Base? =

Puoi acquistare USDC direttamente su [Coinbase](https://coinbase.com) e trasferirlo su Base, oppure usare bridge come [bridge.base.org](https://bridge.base.org).

= I miei lettori devono pagare ogni volta? =

No. L'accesso è permanente: una volta pagato, il wallet del lettore risulta autorizzato on-chain per sempre. Non servono abbonamenti né rinnovi.

= Cosa succede se un lettore perde il wallet? =

L'accesso è legato all'indirizzo wallet. Se il lettore non ha più accesso al wallet, dovrà pagare nuovamente con un nuovo indirizzo.

= Il contenuto è davvero protetto? =

Il contenuto completo viene incluso nel DOM (nascosto via CSS) per evitare problemi SEO e velocità di caricamento. La vera sicurezza è la verifica on-chain: solo chi ha il wallet autorizzato può dimostrare di aver pagato. Per protezione lato server completa, considera soluzioni aggiuntive.

= Quanto sono le commissioni? =

Le fee di piattaforma sono applicate automaticamente dallo smart contract:
* Lettori umani: 10% di commissione
* Agenti AI: 5% di commissione
* Micro-accessi (< $0.05): 2% di commissione

= Funziona con i bot AI? =

Sì. Il plugin supporta un prezzo separato per agenti AI tramite la funzione `unlockAsAgent`. Gli agenti devono avere un wallet e inviare la transazione come qualsiasi altro utente.

= Devo installare qualcosa sul server? =

No. Il plugin usa il RPC pubblico di Base Mainnet per le verifiche server-side, e ethers.js via CDN per il frontend. Nessuna dipendenza da installare.

== Screenshots ==

1. Meta box nell'editor articolo — attiva il paywall e imposta i prezzi
2. Schermata impostazioni globali — configura wallet e contratto
3. Paywall frontend — card dark con bottone MetaMask
4. Flusso di pagamento — Connect → Approve → Read

== Changelog ==

= 1.1.0 =
* Paragrafi anteprima configurabili per ogni articolo (1–20, default 4)
* Corretta compatibilità ABI con X402GatewayV3 (registerContent, unlockAsHuman, unlockAsAgent)
* contentId calcolato lato browser con ethers.keccak256 per piena compatibilità Ethereum
* Fix caricamento ethers.js nelle pagine admin

= 1.0.0 =
* Prima release pubblica
* Paywall USDC su Base Mainnet
* Verifica on-chain via REST API WordPress
* Registrazione contenuti con MetaMask dall'admin
* Supporto prezzi separati umani / AI
* Template paywall dark mode

== Upgrade Notice ==

= 1.0.0 =
Prima versione stabile. Configura il tuo Publisher Wallet Address nelle impostazioni dopo l'installazione.
