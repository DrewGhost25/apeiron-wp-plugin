# Apeiron — AI Bot Tracker

> **Know which AI agents are reading your content.**
> **Block anonymous bots. Charge verified ones.**
> **Get notified when a verified AI company reads your articles.**

WordPress plugin slug: **`apeiron-ai-bot-tracker`**

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)](https://wordpress.org)
[![Apeiron Registry](https://img.shields.io/badge/Apeiron-Registry-c8a96e)](https://www.apeiron-registry.com)
[![Base Mainnet](https://img.shields.io/badge/Base-Mainnet-0052ff?logo=coinbase)](https://base.org)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

---

## What is Apeiron?

A free WordPress plugin that gives you **full visibility and control over AI agents** scraping your content.

Out of the box, every visit from `GPTBot`, `ClaudeBot`, `PerplexityBot`, `Googlebot`, `CCBot` and 30+ other crawlers is **logged with timestamp, IP, user-agent and target URL**. From there you choose your level of protection per article — from "log silently" to "require Apeiron Registry identification" to "USDC paywall on Base".

The plugin pairs with the free [**Apeiron Registry**](https://www.apeiron-registry.com) so verified AI companies (CarAI, ResearchBot, etc.) authenticate themselves to your site with a cryptographic HMAC signature — no shared secrets, no API keys travelling over the wire.

---

## Features

### 🤖 AI Bot Tracking (always on)
- Detects 30+ AI bots automatically by User-Agent (`GPTBot`, `ChatGPT-User`, `ClaudeBot`, `Claude-Web`, `Google-Extended`, `Googlebot`, `PerplexityBot`, `YouBot`, `Diffbot`, `CCBot`, `FacebookBot`, `Applebot`, `BingBot`, `X402-Agent`, …)
- Logs every hit: bot name, company, IP, request URL, post ID, timestamp
- **Apeiron Dashboard** in WP admin — top bots, top articles, new bots detected this week, full activity log
- **Weekly email report** — automatic Monday digest with top bots, top articles and trends

### 🛡 Five protection modes per article
| Mode | Humans | AI bots | Use case |
|---|---|---|---|
| 🔓 **Disabled** | Free | Free + logged | Public content, just want analytics |
| 📊 **Registry log** | Free | Free + identified via Apeiron Registry | Know who's scraping, charge nobody |
| 🚫 **Registry block** | Free | **401** unless registered with Apeiron Registry | Stop anonymous scrapers cold |
| 🤖 **AI Only** (x402) | Free | **402** until they pay USDC on Base | Monetize AI training, free for humans |
| 🔒 **Full** (x402) | USDC paywall | **402** until they pay USDC on Base | Premium content for everyone |

### 🔑 Apeiron Registry integration (HMAC auth)
- Verified agents authenticate with **HMAC-SHA256 signatures** — the bot's signing secret never travels over the network
- Headers sent by the bot: `X-Apeiron-Agent-ID`, `X-Apeiron-Timestamp`, `X-Apeiron-Signature`
- Plugin forwards them to `apeiron-registry.com/api/registry/verify`
- Cache + email debounce + fail-open — zero added latency on most requests
- Backwards-compatible with legacy `X-Apeiron-API-Key` (deprecated)

### 📧 Email notifications
- Get notified the **first time** each verified AI company reads any of your articles
- 24h debounce per agent — no spam if the same bot scrapes 50 articles
- Weekly summary email every Monday morning

### 💵 USDC payments on Base (x402)
- One-time micropayments per article — no subscriptions
- AI agents pay automatically via the [x402 protocol](https://x402.org)
- On-chain access verification — no database, no sessions
- Dual pricing: separate price for humans and AI agents

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- (Optional, for x402 modes) Publisher wallet on Base Mainnet
- (Optional, for x402 modes) [MetaMask](https://metamask.io) or any EIP-1193 wallet

The Registry features (`registry_log`, `registry_block`) work without any wallet or crypto setup.

---

## Installation

### Option A — Upload via WordPress Dashboard

1. Download the latest release from [GitHub](https://github.com/DrewGhost25/apeiron-wp-plugin)
2. In WordPress admin: **Plugins → Add New → Upload Plugin**
3. Select the zip file → **Install Now → Activate**

### Option B — Manual FTP

1. Copy the `apeiron-wp-plugin` folder to `/wp-content/plugins/`
2. Activate from **Plugins → Installed Plugins**

---

## Configuration

### 1. Global settings — `Settings → Apeiron`

| Field | Required for | Description |
|---|---|---|
| **Publisher Email** | Registry modes | Where you receive access notifications + weekly digest |
| **Apeiron Registry URL** | Registry modes | Default: `https://www.apeiron-registry.com/api/registry/verify` |
| **Publisher Wallet Address** | x402 modes | Your Base wallet — where USDC payments are sent |
| **Gateway Contract** | x402 modes | Pre-filled: `0x6De5e0273428B14d88a690b200870f17888b0d77` |
| **USDC Token** | x402 modes | Pre-filled: `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| **Base RPC URL** | x402 modes | Pre-filled: `https://mainnet.base.org` |

### 2. Per-article protection — `Apeiron Paywall` meta box

Open any post in the editor. In the sidebar:

1. Pick a **mode** from the dropdown (Disabled, Registry log, Registry block, AI Only, Full)
2. *(x402 modes only)* Set the **AI agent price** and **human price**, choose preview paragraphs
3. *(x402 modes only)* Click **"Register on blockchain"** after publishing — MetaMask will prompt for a `registerContent` transaction

### 3. Bot tracking dashboard — `Apeiron Dashboard`

Open the **Apeiron Dashboard** menu item in the WP admin.

You'll see (over the last 7 days, configurable):
- Total bot requests
- Unique bots
- Verified Apeiron Registry agents
- Top 10 bots
- Top 10 most-scraped articles
- New bots detected this week
- Full activity log with filters

---

## How agents authenticate (HMAC)

When a verified Apeiron agent visits your site, its bot computes:

```js
const sig = HMAC_SHA256(signing_secret,
  `v1\n${unix_timestamp}\n${agent_id}\n${request_url}`);
```

Then sends:

```http
GET /article HTTP/1.1
User-Agent:           ApeironAgent/1.0 (CarAI)
X-Apeiron-Agent-ID:   ag_a0jia5ta
X-Apeiron-Timestamp:  1735689600
X-Apeiron-Signature:  3a7c1f...   (hex)
X-Apeiron-Purpose:    inference
```

The plugin forwards these headers to the Apeiron Registry which verifies the signature against the agent's stored secret. **The signing secret never leaves the bot** — publishers and the network in transit never see it. If an attacker captures the headers, the signature is bound to that timestamp + URL and expires in ±5 minutes, so it can't be replayed against a different article.

The bot's **dashboard API key** (used to log into apeiron-registry.com/dashboard) is a **different credential** and is **never** sent in any request to publishers.

---

## How It Works — protection mode flows

### Registry Log mode

```
Bot → GET /article
  ├─ Apeiron headers present (HMAC)?
  │     └─ Forward to registry → verified ✓ → log as "registry_verified" + serve content
  │     └─ Invalid signature → 401
  │
  └─ No Apeiron headers
        └─ Log as anonymous → serve content
```

### Registry Block mode

```
Bot → GET /article
  ├─ Apeiron headers present + valid → serve content + log
  ├─ Apeiron headers present + invalid → 401 INVALID
  └─ No Apeiron headers → 401 with registration instructions
```

### AI Only mode (x402)

```
Bot → GET /article  (User-Agent: GPTBot)
   ← HTTP 402 + JSON {protocol: "x402", price, contractInstructions}

Bot → USDC.approve() + gateway.unlockAsAgent()  on Base Mainnet

Bot → GET /article  (header: x-wallet-address: 0x...)
   ← HTTP 200 + content
```

(Verified Apeiron agents with valid HMAC headers are also allowed through and logged as registry_verified, regardless of x402 payment.)

### Full mode

Same as AI Only, plus a USDC paywall card for human readers (MetaMask flow). Verified Apeiron agents bypass the human paywall via their HMAC signature.

---

## REST API

### Apeiron Registry verify (used internally by the plugin)

```
POST https://www.apeiron-registry.com/api/registry/verify

X-Apeiron-Agent-ID:   ag_xxx
X-Apeiron-Timestamp:  1735689600
X-Apeiron-Signature:  <hex>
X-Content-URL:        https://yoursite.com/article
X-Publisher-Email:    you@yoursite.com

→ 200 { verified: true, agent_name: "CarAI", company_name: null, purpose: "inference" }
→ 401 { verified: false, code: "INVALID_SIGNATURE" }
```

### x402 endpoints (for AI agents in `ai_only` / `full` modes)

```
GET /wp-json/apeiron/v1/content/<post_id>
  → 402  { protocol, contentId, price, accessType, instructions, ... }
  → 200  { title, body, accessType, ... }   (with valid x-wallet-address)

GET /wp-json/apeiron/v1/verify?wallet_address=0x...&content_id=0x...
  → 200  { hasAccess: true|false }
```

---

## What anonymous agents receive (Registry block mode)

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/json
X-Apeiron-Protocol: registry-v1

{
  "error": "Agent identification required",
  "protocol": "Apeiron Registry v1.0",
  "register_url": "https://www.apeiron-registry.com/register",
  "standard_headers": {
    "X-Apeiron-Agent-ID":  "your_agent_id",
    "X-Apeiron-Timestamp": "unix_seconds",
    "X-Apeiron-Signature": "hmac_sha256_hex(v1\n{ts}\n{agent_id}\n{url})",
    "X-Apeiron-Purpose":   "training|inference|search"
  },
  "message": "Register your AI agent at www.apeiron-registry.com to access this content legally"
}
```

---

## File Structure

```
apeiron-wp-plugin/
├── apeiron.php                         # Main plugin file — constants, boot, hooks
├── includes/
│   ├── class-apeiron-admin.php         # Settings page + article meta box
│   ├── class-apeiron-frontend.php      # Bot interception + 5-mode router
│   ├── class-apeiron-api.php           # REST: x402 verify + content endpoints
│   ├── class-apeiron-detector.php      # User-Agent → bot key/name/company
│   ├── class-apeiron-logger.php        # DB log table + stats + weekly email
│   ├── class-apeiron-dashboard.php     # WP admin dashboard (bot analytics)
│   └── class-apeiron-register.php      # AJAX for x402 contentId registration
├── assets/
│   ├── js/
│   │   ├── apeiron-paywall.js          # Wallet connect + approve + pay
│   │   ├── apeiron-admin.js            # "Register on blockchain" in editor
│   │   └── apeiron-dashboard.js        # x402 analytics
│   └── css/
│       └── apeiron-paywall.css         # Paywall + dashboard styles
├── templates/
│   └── paywall-template.php            # Paywall HTML card
└── readme.txt                          # WordPress.org plugin readme
```

---

## Smart Contract (x402 modes only)

| | Address |
|---|---|
| **Gateway Contract** | `0x6De5e0273428B14d88a690b200870f17888b0d77` |
| **USDC (Base)** | `0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913` |
| **Network** | Base Mainnet (Chain ID: 8453) |

### Fee structure (handled by the contract)

| Transaction size | Platform fee | Publisher receives |
|---|---|---|
| Up to $10 USDC | 10% | 90% |
| $10 — $100 USDC | 5% | 95% |
| Above $100 USDC | 2% | 98% |

---

## Detected AI bots

| User-Agent pattern | Company |
|---|---|
| `GPTBot`, `ChatGPT-User` | OpenAI |
| `ClaudeBot`, `Claude-Web`, `anthropic` | Anthropic |
| `Google-Extended`, `Googlebot` | Google |
| `PerplexityBot` | Perplexity AI |
| `YouBot` | You.com |
| `Diffbot` | Diffbot |
| `CCBot` | Common Crawl |
| `FacebookBot` | Meta |
| `Applebot` | Apple |
| `BingBot` | Microsoft |
| `X402-Agent`, `ApeironAgent` | x402 / Apeiron protocol |

Generic fallback: `bot`, `crawler`, `spider`, `openai`. In Registry modes, any UA is accepted as long as valid Apeiron headers are present — so registered agents can use any User-Agent string.

---

## Development

1. Install [LocalWP](https://localwp.com) (free)
2. Create a WP site, copy this folder to `wp-content/plugins/`
3. Activate the plugin
4. (Registry modes) Set publisher email in **Settings → Apeiron**
5. (x402 modes) Set publisher wallet, register an article on-chain
6. Test with `curl` using HMAC headers (see `/protect` page on apeiron-registry.com for a copy-paste example)

---

## Changelog

### v2.1.0 (current)
- **HMAC authentication** for Apeiron Registry agents — signing secret stays on the bot
- New headers: `X-Apeiron-Timestamp`, `X-Apeiron-Signature` (deprecates `X-Apeiron-API-Key`)
- Universal registry-header detection — any User-Agent is accepted with valid Apeiron headers
- Bot tracking works in **all** modes (Disabled, Registry log/block, AI Only, Full)
- `disabled` mode now truly inert — no Registry calls, just passive UA logging

### v2.0.0
- **Apeiron Registry integration** — `registry_log` and `registry_block` modes
- **Bot tracking dashboard** in WP admin
- **Weekly email digest** — top bots, top articles, new bots
- Per-agent first-access email notifications (24h debounce)
- HTTPS enforcement on Registry calls — refuses to send credentials over plaintext
- Fail-open on Registry unreachable (configurable per mode)
- Detector for 30+ AI bots

### v1.2.0
- Three protection modes: `disabled`, `ai_only`, `full`
- `ai_only`: humans free, bots intercepted with HTTP 402
- x402 REST endpoint respects protection mode

### v1.1.0
- Configurable preview paragraphs (1–20)
- Analytics dashboard with on-chain revenue
- Publisher wallet verification
- x402 REST endpoint for AI agents

### v1.0.0
- Initial release: USDC paywall on Base Mainnet, MetaMask integration

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Links

- 🌐 [apeiron-registry.com](https://www.apeiron-registry.com) — register your AI agent
- 📖 [Protect your content](https://www.apeiron-registry.com/protect) — publisher guide
- 📄 [X402 Gateway on Basescan](https://basescan.org/address/0x6De5e0273428B14d88a690b200870f17888b0d77)
- 🦊 [Get MetaMask](https://metamask.io) (x402 modes only)
- 💵 [USDC on Base](https://bridge.base.org) (x402 modes only)
