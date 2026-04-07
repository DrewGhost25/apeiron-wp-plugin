=== Apeiron — Web3 Content Paywall ===
Contributors:      apeiron
Tags:              paywall, web3, crypto, usdc, blockchain, monetization, base, metamask
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.2.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Web3 content paywall for WordPress. Charge readers in USDC on Base blockchain. AI agents pay automatically via the x402 protocol.

== Description ==

**Apeiron** adds a crypto paywall to your WordPress articles. Readers pay once in USDC (a 1:1 USD stablecoin) and get permanent access verified directly on Base Mainnet — no subscriptions, no login walls, no intermediaries.

Access is verified **on-chain**: once a wallet pays, it has permanent access forever. No backend database, no cookies, no trust required.

AI agents (LLM scrapers, RAG pipelines, automated readers) are detected via User-Agent and priced separately — so your content works for the machine economy too.

= Protection Modes =

Each article can be set to one of three modes from the Apeiron Paywall meta box:

* **Disabled** — No protection, free for everyone
* **AI Only** — Humans read free, AI bots receive HTTP 402 with payment instructions
* **Full** — Paywall for everyone (preview + USDC payment flow)

= Features =

* Three protection modes per article: Disabled, AI Only, Full
* USDC payments on Base Mainnet (near-zero gas fees, 1:1 with USD)
* Separate pricing for human readers and AI agents
* On-chain access verification — no database, no sessions
* Configurable preview paragraphs before the paywall (1–20, default: 4)
* Analytics dashboard — revenue, human readers and AI bots per article
* Publisher bypass — the article owner sees full content for free
* x402 protocol endpoint for AI agents
* Works with any public Base RPC

= Per-Article Settings =

Each protected article has an **Apeiron Paywall** meta box with:

* **Protection Mode** — Disabled / AI Only / Full
* **AI agent price** — amount in USDC (default: $1.00)
* **Human reader price** — amount in USDC (default: $0.10, Full mode only)
* **Preview paragraphs** — how many paragraphs to show before the paywall (Full mode only)
* **Registration status** — shows whether the content is registered on-chain
* **Register on blockchain** — sends the registration transaction via MetaMask

= Smart Contract =

The plugin integrates with the X402GatewayV3 smart contract on Base Mainnet:
`0x6De5e0273428B14d88a690b200870f17888b0d77`

= Fee Structure =

Platform fees are applied automatically by the smart contract based on transaction size:

* Up to $10 USDC: 10% platform fee (publisher receives 90%)
* $10 – $100 USDC: 5% platform fee (publisher receives 95%)
* Above $100 USDC: 2% platform fee (publisher receives 98%)

= Requirements =

* WordPress 6.0+
* PHP 8.0+
* MetaMask or any EIP-1193 compatible browser wallet
* A publisher wallet address on Base Mainnet
* USDC on Base (for readers to pay)

== Installation ==

1. Upload the `apeiron-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin from the **Plugins** screen in WordPress
3. Go to **Settings → Apeiron** and configure:
   - Your **Publisher Wallet Address** (where you receive payments)
   - Gateway Contract Address (pre-filled)
   - USDC Token Address (pre-filled)
   - Base RPC URL (pre-filled with `https://mainnet.base.org`)
4. Edit any post and open the **Apeiron Paywall** meta box in the sidebar
5. Select a **Protection Mode**, set the price, then click **Register on blockchain**
6. MetaMask will open — confirm the `registerContent` transaction

> **Important:** Always publish the article before registering on-chain. The content ID is derived from the public URL of the post.

== Frequently Asked Questions ==

= Do readers need a crypto wallet? =

Yes, readers must have MetaMask (or any EIP-1193 compatible wallet) installed in their browser. MetaMask is free and available at [metamask.io](https://metamask.io).

= Which cryptocurrencies are accepted? =

Only USDC on Base Mainnet. USDC is a stablecoin pegged 1:1 to the US Dollar, issued by Circle.

= How do readers get USDC on Base? =

Readers can buy USDC directly on [Coinbase](https://coinbase.com) and transfer it to Base, or use a bridge like [bridge.base.org](https://bridge.base.org).

= Do readers pay every time they visit? =

No. Access is permanent: once a wallet pays, it is authorized on-chain forever. No subscriptions or renewals needed.

= What happens if a reader loses access to their wallet? =

Access is tied to the wallet address. If a reader loses their wallet, they would need to pay again with a new address.

= Is the content really protected server-side? =

In Full mode, the complete article text is included in the DOM (hidden via CSS) to avoid SEO and performance issues. The real security layer is on-chain verification: only wallets that have paid can prove access. For complete server-side protection, consider additional solutions.

In AI Only mode, bot requests are intercepted at the server level before the page is rendered and receive HTTP 402 with payment instructions.

= What AI bots are detected? =

GPTBot, ChatGPT-User, ClaudeBot, Claude-Web, Google-Extended, Googlebot, PerplexityBot, YouBot, Diffbot, CCBot, FacebookBot, Applebot, BingBot, and generic bot/crawler/spider patterns.

= Do I need to install anything on the server? =

No. The plugin uses the public Base Mainnet RPC for server-side access checks, and loads ethers.js via CDN for the frontend. No dependencies to install.

= What is the x402 protocol? =

x402 is a machine-readable payment protocol built on HTTP 402 (Payment Required). When an AI agent hits a protected endpoint without payment, the plugin returns a structured JSON response with payment instructions. The agent approves USDC, calls the smart contract, then retries the request with its wallet address — and receives the content.

== Screenshots ==

1. Apeiron Paywall meta box in the post editor — select mode and set prices
2. Global settings page — configure wallet address and contract
3. Frontend paywall card — dark theme with MetaMask button
4. Payment flow — Connect → Approve → Read
5. Analytics dashboard — revenue, human readers and AI bots per article

== External Services ==

This plugin connects to the following external services:

**1. Base Mainnet RPC (`https://mainnet.base.org`)**
Used by the WordPress server to verify on-chain access (`eth_call` via JSON-RPC).
Also used by the browser to read analytics events and send payment transactions.
This is a public, unauthenticated endpoint operated by Coinbase / Base.
[Base Terms of Service](https://base.org/terms-of-service)

**2. Apeiron Smart Contract on Base Mainnet**
Contract address: `0x6De5e0273428B14d88a690b200870f17888b0d77`
All payments (USDC) flow through this contract. It handles access grants and fee distribution.
Interaction only occurs when the publisher registers content or when a reader pays.
[Basescan](https://basescan.org/address/0x6De5e0273428B14d88a690b200870f17888b0d77)

**3. ethers.js via cdnjs (`https://cdnjs.cloudflare.com`)**
Loaded on article pages (Full mode) and admin pages to handle wallet connections and transaction signing.
This is a standard open-source library (MIT license) served via Cloudflare's CDN.
[cdnjs Terms of Service](https://cdnjs.com/terms)
[ethers.js on GitHub](https://github.com/ethers-io/ethers.js)

No data is sent to Apeiron servers. All blockchain interactions happen directly between the user's browser and the Base Mainnet RPC.

== Changelog ==

= 1.2.0 =
* Three protection modes per article: Disabled, AI Only, Full
* AI Only mode: humans read free, bots intercepted at server level with HTTP 402
* x402 REST endpoint for AI agents (`/wp-json/apeiron/v1/content/<post_id>`)
* Expanded bot detection: GPTBot, ChatGPT-User, ClaudeBot, Claude-Web, Google-Extended, Googlebot, PerplexityBot, YouBot, Diffbot, CCBot, FacebookBot, Applebot, BingBot
* Analytics dashboard with on-chain revenue and access counts per article
* Publisher wallet bypass — article owner sees full content for free
* All user-facing strings translated to English
* Fixed: inline script moved to wp_add_inline_script()
* Fixed: Stable tag and version number aligned

= 1.1.0 =
* Configurable preview paragraphs per article (1–20, default 4)
* Fixed ABI compatibility with X402GatewayV3
* contentId computed client-side with ethers.keccak256
* Fixed ethers.js loading on admin pages
* Fixed hasAccess() selector: 4a0f4a07 → 24ea5704

= 1.0.0 =
* Initial release
* USDC paywall on Base Mainnet
* On-chain access verification via WordPress REST API
* Content registration with MetaMask from admin
* Separate pricing for human and AI readers
* Dark mode paywall template

== Upgrade Notice ==

= 1.2.0 =
New protection modes (Disabled, AI Only, Full) replace the old on/off checkbox. Existing protected articles are automatically migrated to Full mode.

= 1.0.0 =
First stable version. Configure your Publisher Wallet Address in Settings → Apeiron after activation.
