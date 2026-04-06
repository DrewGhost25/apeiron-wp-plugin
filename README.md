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

- 🔒 **Three protection modes** per article — Full, AI Only, Disabled
- 💵 **USDC payments** — stablecoin, 1:1 with USD, on Base (near-zero gas fees)
- 🤖 **Dual pricing** — separate price for human readers and AI agents
- ✅ **On-chain access verification** — no database, no sessions
- 👁 **Configurable preview** — show N paragraphs before the paywall (default: 4)
- 📊 **Analytics dashboard** — revenue, human readers and AI bots per article
- 🏷 **Publisher bypass** — the article owner sees full content for free
- 🌐 **x402 protocol** — machine-readable payment endpoint for AI agents
- ⚡ **No backend dependency** — works with any public Base RPC

---

## Protection Modes

Each article can be set to one of three modes from the **Apeiron Paywall** meta box:

| Mode | Human readers | AI bots | Use case |
|---|---|---|---|
| 🔓 **Disabled** | Free | Free | Public content |
| 🤖 **AI Only** | Free | HTTP 402 + payment instructions | Monetize AI scraping only |
| 🔒 **Full** | Paywall (USDC) | HTTP 402 + payment instructions | Premium content |

### AI Only mode — how it works

In `ai_only` mode, human readers access the article for free as normal.
When a known bot (`GPTBot`, `ClaudeBot`, `X402-Agent`, etc.) hits the page:

1. WordPress intercepts the request via `User-Agent` detection
2. Returns **HTTP 402** with x402 payment instructions (JSON)
3. The bot approves USDC + calls `unlockAsAgent()` on-chain
4. Retries the request with `x-wallet-address` header → gets the content

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

### 2. Set protection mode on an article

1. Open any WordPress post in the editor
2. Find the **Apeiron Paywall** meta box in the sidebar
3. Select a **protection mode** from the dropdown:
   - 🔓 Disabled
   - 🤖 AI Only — humans read free, bots pay
   - 🔒 Full — paywall for everyone
4. Set **AI agent price** (default: $1.00 USDC)
5. *(Full mode only)* Set **Human price** (default: $0.10 USDC) and **Preview paragraphs**
6. **Publish the article first**, then click **"Registra su blockchain"**
7. MetaMask will open — confirm the `registerContent` transaction

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

### Full mode — human reader flow

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

### AI Only / Full mode — AI agent flow (x402 protocol)

```
Agent → GET article URL  (User-Agent: GPTBot / X402-Agent)
       ← HTTP 402 + JSON {
           protocol: "x402",
           contentId, gatewayAddress, usdcAddress,
           accessType: "AI_LICENSE",
           price: "1000000",        ← 1.00 USDC in wei
           instructions: {
             step1: "USDC.approve(...)",
             step2: "gateway.unlockAsAgent(...)"
           }
         }

Agent approves USDC + calls unlockAsAgent() on Base Mainnet

Agent → GET article URL
       header: x-wallet-address: 0x...
       ← HTTP 200 + { title, body, accessType: "AI_LICENSE" }
```

Alternatively, agents can use the dedicated REST endpoint directly:

```
GET /wp-json/apeiron/v1/content/<post_id>
```

### Content ID

Each article is identified by:
```js
contentId = ethers.keccak256(ethers.toUtf8Bytes(articleUrl))
```

Computed in the browser to ensure Ethereum Keccak-256 compatibility.

---

## File Structure

```
apeiron-wp-plugin/
├── apeiron.php                         # Main plugin file — constants, boot, hooks
├── agent-wp.mjs                        # Example AI agent using x402 on WordPress
├── includes/
│   ├── class-apeiron-admin.php         # Settings page + article meta box (3 modes)
│   ├── class-apeiron-frontend.php      # Paywall + bot interception (template_redirect)
│   ├── class-apeiron-api.php           # REST: /verify + /content/<id> (x402)
│   ├── class-apeiron-register.php      # AJAX: save contentId, mark registered
│   └── class-apeiron-dashboard.php     # Analytics admin page
├── assets/
│   ├── js/
│   │   ├── apeiron-paywall.js          # Wallet connect + approve + pay flow
│   │   ├── apeiron-admin.js            # "Register on blockchain" in editor
│   │   └── apeiron-dashboard.js        # Analytics: getLogs + aggregation
│   └── css/
│       └── apeiron-paywall.css         # Dark theme (paywall + dashboard)
├── templates/
│   └── paywall-template.php            # Paywall HTML card
└── readme.txt                          # WordPress.org plugin readme
```

---

## REST API

### Verify access (used by JS frontend)

```
GET /wp-json/apeiron/v1/verify
    ?wallet_address=0x...
    &content_id=0x...

→ 200 { "hasAccess": true }
```

### x402 content endpoint (for AI agents)

```
GET /wp-json/apeiron/v1/content/<post_id>

→ 402  { protocol, contentId, price, accessType, instructions, ... }
       (no x-wallet-address header, or unverified)

→ 200  { title, body, accessType, accessedBy, ... }
       (with valid x-wallet-address header, access verified on-chain)
```

Bot detection is automatic via `User-Agent`. See the full list below.

---

## Interceptable AI Bots

The following User-Agent patterns are detected automatically. In **AI Only** and **Full** modes, matching requests receive HTTP 402 with x402 payment instructions.

| Bot / User-Agent | Company |
|---|---|
| `GPTBot` | OpenAI |
| `ChatGPT-User` | OpenAI |
| `ClaudeBot` | Anthropic |
| `Claude-Web` | Anthropic |
| `Google-Extended` | Google |
| `Googlebot` | Google |
| `PerplexityBot` | Perplexity AI |
| `YouBot` | You.com |
| `Diffbot` | Diffbot |
| `CCBot` | Common Crawl |
| `FacebookBot` | Meta |
| `Applebot` | Apple |
| `BingBot` | Microsoft |
| `X402-Agent` | x402 Protocol |

Generic patterns (`bot`, `crawler`, `spider`, `anthropic`, `openai`) are also matched as a fallback.

---

## JavaScript API (`window.apeironData`)

Available on **Full mode** article pages:

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

---

## Example AI Agent (`agent-wp.mjs`)

```bash
# Add to .env.local
AGENT_PRIVATE_KEY_2=0x...
WP_POST_ID=6

node agent-wp.mjs
```

```
🤖  Apeiron WordPress Agent (x402)
── Step 1: GET /wp-json/apeiron/v1/content/6   → 402 AI_LICENSE 1.00 USDC
── Step 2: Setup wallet 0x6cBB...
── Step 3: Check existing access → none
── Step 4: Approve USDC ✓
── Step 5: unlockAsAgent() → tx confirmed ✓
── Step 6: GET + x-wallet-address              → 200 content unlocked ✓
```

---

## Development & Local Testing

1. Install [LocalWP](https://localwp.com) (free)
2. Create a new WordPress site
3. Copy this folder to `wp-content/plugins/`
4. Activate the plugin
5. Configure your publisher wallet in **Settings → Apeiron**
6. Create a post, set protection mode, register on-chain
7. Test with `node agent-wp.mjs`

---

## Changelog

### v1.2.0
- **Three protection modes**: `disabled`, `ai_only`, `full`
- `ai_only`: humans read free, bots intercepted at `template_redirect` level with HTTP 402
- x402 REST endpoint (`/wp-json/apeiron/v1/content/<post_id>`) respects protection mode
- `ai_only` mode: humans get free content via REST endpoint too
- Meta box UI: dropdown replaces checkbox, fields show/hide dynamically

### v1.1.0
- Configurable preview paragraphs per article (1–20, default 4)
- Fixed ABI compatibility with X402GatewayV3
- `contentId` computed client-side with `ethers.keccak256`
- Fixed ethers.js loading on admin pages
- Analytics dashboard with on-chain revenue and access counts
- Publisher wallet verification on dashboard connect
- Publisher bypass on frontend
- x402 REST endpoint for AI agents
- Fixed `hasAccess()` selector: `4a0f4a07` → `24ea5704`

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
