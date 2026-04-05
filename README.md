# Apeiron — Web3 Content Paywall for WordPress

> **Web3 content paywall for WordPress.**
> **Charge readers in USDC on Base blockchain.**
> **AI agents pay automatically.**

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org)
[![Base Mainnet](https://img.shields.io/badge/Base-Mainnet-0052ff?logo=coinbase)](https://base.org)
[![USDC](https://img.shields.io/badge/USDC-Stablecoin-2775ca)](https://www.circle.com/usdc)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

---

## What is Apeiron?

Apeiron is a WordPress plugin that lets publishers monetize individual articles with **one-time crypto micropayments** in USDC on [Base Mainnet](https://base.org) — no subscriptions, no login walls, no intermediaries.

Access is verified **directly on-chain**: once a wallet pays, it has permanent access forever. No backend database, no cookies, no trust required.

AI agents (LLM scrapers, RAG pipelines, automated readers) are detected and priced separately — so your content works for the machine economy too.

---

## Features

- 🔒 **Per-article paywall** — protect any post with a single checkbox
- 💵 **USDC payments** — stablecoin, 1:1 with USD, on Base (near-zero gas fees)
- 🤖 **Dual pricing** — separate price for human readers and AI agents
- ✅ **On-chain access verification** — no database, no sessions
- 👁 **Configurable preview** — show N paragraphs before the paywall (default: 4)
- 📊 **Analytics dashboard** — see revenue, human readers and AI bots per article
- 🏷 **Publisher bypass** — the article owner sees full content for free
- ⚡ **No backend dependency** — works with any public Base RPC

---

## Smart Contract

The plugin integrates with the **X402GatewayV3** contract on Base Mainnet:

| | Address |
|---|---|
| **Gateway Contract** | `0x6De5e0273428B14d88a690b200870f17888b0d77` |
| **USDC (Base)** | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| **Network** | Base Mainnet (Chain ID: 8453) |

### Fee structure (applied automatically by the contract)

| Transaction size | Platform fee | Publisher receives |
|---|---|---|
| Up to $10 USDC | 10% | 90% |
| $10 — $100 USDC | 5% | 95% |
| Above $100 USDC | 2% | 98% |

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [MetaMask](https://metamask.io) or any EIP-1193 compatible wallet
- A publisher wallet address on Base Mainnet
- USDC on Base (for readers to pay)

---

## Installation

### Option A — Upload via WordPress Dashboard

1. Download or clone this repository
2. Zip the `apeiron-wp-plugin` folder
3. In WordPress admin: **Plugins → Add New → Upload Plugin**
4. Select the zip file → **Install Now → Activate**

### Option B — Manual FTP

1. Copy the `apeiron-wp-plugin` folder to `/wp-content/plugins/`
2. Activate from **Plugins → Installed Plugins**

---

## Configuration

### 1. Global Settings

Go to **Settings → Apeiron** and fill in:

| Field | Description |
|---|---|
| Publisher Wallet Address | Your wallet — where USDC payments are sent |
| Gateway Contract Address | Pre-filled: `0x6De5e027...` |
| USDC Token Address | Pre-filled: `0x833589fC...` |
| Base RPC URL | Pre-filled: `https://mainnet.base.org` |

### 2. Protect an article

1. Open any WordPress post in the editor
2. Find the **Apeiron Paywall** meta box in the sidebar
3. Check **"Proteggi con Apeiron"**
4. Set **Human price** (default: $0.10 USDC)
5. Set **AI agent price** (default: $1.00 USDC)
6. Set **Preview paragraphs** (how many paragraphs to show before the paywall, default: 4)
7. **Publish the article first**, then click **"Registra su blockchain"**
8. MetaMask will open — confirm the `registerContent` transaction

> ⚠️ **Always publish the article before registering on-chain.** The `contentId` is derived from the public URL of the post.

### 3. Analytics Dashboard

Go to **Apeiron → Analytics** in the WordPress admin menu.

1. Click **Connect Wallet** with your publisher wallet
2. The dashboard reads on-chain events and shows:
   - Total revenue (USDC, net of platform fees)
   - Human readers count
   - AI bots intercepted count
   - Per-article breakdown

---

## How It Works

### Reader flow

```
Article page
    │
    ├─ First N paragraphs visible (preview)
    │
    └─ Paywall card
            │
            ├─ [Connect Wallet] → MetaMask popup
            │
            ├─ Check hasAccess(wallet, contentId) on-chain
            │       └─ Already paid? → Show full article ✓
            │
            ├─ [Approve USDC] → MetaMask signs ERC-20 approval
            │
            └─ [Pay X USDC] → unlockAsHuman(contentId, 0)
                    └─ Access permanent on-chain → Show full article ✓
```

### AI agent flow

Agents call `unlockAsAgent(contentId, 0)` directly on the contract. The plugin exposes the `contentId` and contract address in the page's JS context (`window.apeironData`) so agents can read and pay programmatically.

### Content ID

Each article is identified by:
```js
contentId = ethers.keccak256(ethers.toUtf8Bytes(articleUrl))
```

This is computed in the browser (not PHP) to ensure Ethereum Keccak-256 compatibility.

---

## File Structure

```
apeiron-wp-plugin/
├── apeiron.php                         # Main plugin file — constants, boot, hooks
├── includes/
│   ├── class-apeiron-admin.php         # Settings page + article meta box
│   ├── class-apeiron-frontend.php      # Paywall injection on the_content
│   ├── class-apeiron-api.php           # REST endpoint /wp-json/apeiron/v1/verify
│   ├── class-apeiron-register.php      # AJAX: save contentId, mark registered
│   └── class-apeiron-dashboard.php     # Analytics admin page
├── assets/
│   ├── js/
│   │   ├── apeiron-paywall.js          # Wallet connect + approve + pay flow
│   │   ├── apeiron-admin.js            # "Register on blockchain" in editor
│   │   └── apeiron-dashboard.js        # Analytics: getLogs + chart
│   └── css/
│       └── apeiron-paywall.css         # Dark theme styles (paywall + dashboard)
├── templates/
│   └── paywall-template.php            # Paywall HTML card
└── readme.txt                          # WordPress.org plugin readme
```

---

## REST API

The plugin exposes one REST endpoint for client-side access verification:

```
GET /wp-json/apeiron/v1/verify
    ?wallet_address=0x...
    &content_id=0x...

Response: { "hasAccess": true }
```

This calls `gateway.hasAccess(wallet, contentId, 0)` via PHP JSON-RPC and returns the result without requiring a wallet connection.

---

## JavaScript API (`window.apeironData`)

On protected article pages, the following object is available:

```js
window.apeironData = {
  contentId,        // bytes32 hex — unique ID of this article on-chain
  humanPrice,       // string USDC — e.g. "0.10"
  aiPrice,          // string USDC — e.g. "1.00"
  gatewayAddress,   // contract address
  usdcAddress,      // USDC token address
  chainId,          // 8453
  verifyEndpoint,   // WP REST URL for server-side verify
  postUrl,          // canonical URL of the article
  publisherWallet,  // lowercase publisher address
}
```

AI agents can use this to pay programmatically:

```js
const provider = new ethers.JsonRpcProvider('https://mainnet.base.org');
const signer   = new ethers.Wallet(PRIVATE_KEY, provider);
const gateway  = new ethers.Contract(apeironData.gatewayAddress, [
  'function unlockAsAgent(bytes32 contentId, uint256 duration)'
], signer);
await gateway.unlockAsAgent(apeironData.contentId, 0n);
```

---

## Development & Local Testing

1. Install [LocalWP](https://localwp.com) (free)
2. Create a new WordPress site
3. Copy this folder to `wp-content/plugins/`
4. Activate the plugin
5. Configure your publisher wallet in **Settings → Apeiron**
6. Create a post, protect it, register on-chain

---

## Changelog

### v1.1.0
- Configurable preview paragraphs per article (1–20, default 4)
- Fixed ABI compatibility with X402GatewayV3 (`registerContent`, `unlockAsHuman`, `unlockAsAgent`)
- `contentId` now computed client-side with `ethers.keccak256` (Ethereum-compatible)
- Fixed ethers.js loading on admin pages
- Analytics dashboard with on-chain revenue and access counts
- Publisher wallet verification on dashboard connect
- Publisher bypass on frontend (owner sees full content without paying)

### v1.0.0
- Initial release
- USDC paywall on Base Mainnet
- On-chain access verification via WordPress REST API
- Content registration with MetaMask from admin
- Separate pricing for human and AI readers
- Dark mode paywall template

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Links

- 🌐 [apeiron-reader.com](https://apeiron-reader.com)
- 📄 [X402GatewayV3 on Basescan](https://basescan.org/address/0x6De5e0273428B14d88a690b200870f17888b0d77)
- 🦊 [Get MetaMask](https://metamask.io)
- 💵 [Get USDC on Base](https://bridge.base.org)
