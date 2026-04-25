=== Apeiron — AI Bot Tracker ===
Contributors:      drewghost25
Tags:              ai, bots, tracker, chatgpt, claude, crawler, analytics
Requires at least: 5.8
Tested up to:      6.9
Stable tag:        2.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Know which AI bots read your content. ChatGPT, Claude, Gemini, Perplexity — detect them all, log every visit, and optionally block or monetize access.

== Description ==

**Apeiron AI Bot Tracker** shows you exactly which AI bots are visiting your WordPress site — automatically, from the moment you activate it.

Every week, bots from OpenAI, Anthropic, Google, Perplexity and more visit millions of websites to collect training data or power real-time answers. Most publishers have no idea this is happening.

**Apeiron AI Bot Tracker shows you the truth.**

= Detected bots (21+) =

OpenAI (GPTBot, ChatGPT-User, OAI-SearchBot), Anthropic (ClaudeBot, Claude-Web), Google (Google-Extended, GoogleOther), Perplexity, Meta (FacebookBot, Meta-ExternalAgent), Microsoft (Bingbot), Apple (Applebot, Applebot-Extended), Amazon (Amazonbot), TikTok/ByteDance (ByteSpider), Common Crawl (CCBot), Diffbot, You.com, Cohere, Mistral, and more.

= Features =

* Detect 21+ AI bots automatically — zero configuration required
* Dashboard with bot statistics: top bots, most-scanned articles, recent activity
* Per-bot settings: Allow, Block, or Require Apeiron Registry ID
* Apeiron Registry integration — identify registered AI agents by company name
* Weekly email report every Monday with bot activity summary
* USDC paywall per article (optional, Base Mainnet) — charge AI bots and/or human readers
* x402 protocol endpoint for AI agents
* Stats API for integration with Apeiron Registry dashboard

= Protection Modes (per article) =

* **Disabled** — No protection, DETECT mode still logs bots
* **AI Only** — Humans read free, AI bots receive HTTP 402 with USDC payment instructions
* **Full** — USDC paywall for everyone (preview + payment flow)
* **Registry Log** — Allow all, log verified Apeiron Registry agents
* **Registry Block** — Require Apeiron Registry ID, block anonymous bots

> **Important note on Full mode protection:** In Full mode, the complete article HTML is present in the page source (hidden via CSS) to preserve SEO and avoid layout issues. The security layer is USDC on-chain verification. For server-side content protection, use Registry Block mode combined with Apeiron Registry.

= Requirements =

* WordPress 5.8+
* PHP 7.4+
* For paywall features: MetaMask or any EIP-1193 compatible browser wallet + USDC on Base Mainnet

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate from the **Plugins** screen
3. Bot detection starts immediately — visit **Apeiron → Dashboard** to see your first data
4. Optional: Go to **Settings → Apeiron** to configure publisher email, registry API URL, and paywall settings
5. Optional: Edit any post and open the **Apeiron Paywall** meta box to enable per-article protection

== Frequently Asked Questions ==

= Does this slow down my site? =
No. Detection is a single string comparison on the User-Agent header. The performance impact is negligible.

= Will this block Google Search or Bing Search bots? =
No, by default. The plugin logs all known AI bots but only blocks bots that you explicitly set to "Block" in the per-bot settings.

= Is the content really protected in Full mode? =
In Full mode, the complete article text is present in the page DOM (hidden via CSS) to avoid SEO and performance issues. Real protection uses on-chain USDC verification — only wallets that have paid can prove access. For full server-side protection, use Registry Block mode.

= What is Apeiron Registry? =
An open protocol for AI agents to identify themselves with a verified company name, VAT number, and declared purpose. Publishers can require registry identification to grant content access.

= Do readers need a crypto wallet? =
Only for the optional USDC paywall. Bot detection and logging work without any wallet or blockchain interaction.

= What is the x402 protocol? =
A machine-readable payment protocol using HTTP 402 (Payment Required). When a bot hits a paywalled article, it receives JSON instructions to pay in USDC and retry.

= How does the Stats API work? =
The plugin exposes a REST endpoint `GET /wp-json/apeiron/v1/bot-stats` authenticated with a Stats API Key (shown in Settings → Apeiron). This allows Apeiron Registry to display your publisher stats in its dashboard.

== Screenshots ==

1. Dashboard — bot statistics, top bots, recent activity
2. Per-bot settings — Allow, Block, or Require Registry ID
3. Settings page — email, registry integration, paywall config
4. Weekly email report
5. USDC paywall (optional, Full mode)

== External Services ==

This plugin connects to the following external services:

**1. Apeiron Registry API (`https://apeiron-registry.com/api/registry/verify`)**
Used to verify the identity of AI agents that present an Apeiron Registry ID when accessing your content. Only called when an AI agent sends `X-Apeiron-Agent-ID` and `X-Apeiron-API-Key` headers AND the article is set to "Registry Log" or "Registry Block" mode.
Data sent: agent ID, API key, content URL, content title, publisher email (if configured), visitor IP address.
This feature is optional and inactive unless you configure the Registry API URL in settings.
[Apeiron Registry Terms of Service](https://apeiron-registry.com/terms) · [Privacy Policy](https://apeiron-registry.com/privacy)

**2. Base Mainnet RPC (`https://mainnet.base.org`)**
Used by the WordPress server to verify on-chain USDC payments (`eth_call` via JSON-RPC). Only called when a visitor requests a paywalled article and provides a wallet address.
Also used by the browser for wallet interactions when the USDC paywall is active.
This is a public, unauthenticated endpoint operated by Coinbase / Base.
No personal data is sent — only wallet addresses and smart contract call data.
[Base Terms of Service](https://base.org/terms-of-service) · [Coinbase Privacy Policy](https://www.coinbase.com/legal/privacy)

**3. Apeiron Smart Contract on Base Mainnet**
Contract address: `0x6De5e0273428B14d88a690b200870f17888b0d77`
All USDC payments flow through this smart contract. Interactions only occur when the publisher registers content on-chain or when a reader/bot pays for access.
[View on Basescan](https://basescan.org/address/0x6De5e0273428B14d88a690b200870f17888b0d77)

**4. ethers.js v6 (bundled locally)**
JavaScript library included in the plugin package at `assets/js/ethers.umd.min.js`. Used on protected article pages and admin screens to handle wallet connections and transaction signing. **No CDN requests are made** — the file is served directly from your WordPress installation.
License: MIT · [Source on GitHub](https://github.com/ethers-io/ethers.js)
Note: ethers.js internally contains fallback URLs for Etherscan and other block explorers (e.g., `api.basescan.org`) for network detection purposes. These URLs are only contacted if you use ethers.js provider features that explicitly request blockchain data — the plugin does not trigger these calls automatically.

== Changelog ==

= 2.1.0 =
* HMAC request signing for Apeiron Registry agents — bot signs each request locally with a signing secret that is never sent over the wire (replaces legacy API key in headers; legacy path kept for backward compatibility)
* New headers forwarded to the registry: `X-Apeiron-Timestamp` and `X-Apeiron-Signature` (HMAC-SHA256)
* Universal Apeiron header detection — registered agents are recognized in any mode regardless of User-Agent string
* `mark_verified` always overwrites `bot_company` so private agents show "Private" instead of a misleading detector guess
* New "Payments" admin submenu — dedicated page for on-chain analytics (revenue, human readers, AI bot payments) instead of being collapsed at the bottom of the Dashboard
* Settings page cleanup — removed the three empty preset fields (Gateway, USDC, RPC). They are protocol constants and the code already falls back to them when the option is empty
* Fix (Guideline 10): "Secured by Apeiron" branding is now opt-in (off by default)
* ethers.js bundle updated to v6.16.0
* Documentation: complete README and readme.txt rewrite to reflect all v2.x features (Apeiron Registry, HMAC, 5 protection modes, bot tracking dashboard, weekly email digest)

= 2.0.0 =
* Complete rebranding: Apeiron — AI Bot Tracker
* DETECT mode: automatic bot detection and logging on all articles (no configuration needed)
* New database tables: bot_log (per-access log) and bot_settings (per-bot actions)
* Bot database expanded to 21 known AI bots
* New dashboard with DB-driven statistics (no wallet connection required)
* Bot Activity page with filterable access log
* Per-bot settings: Allow, Block, Require Registry ID
* Weekly email report (every Monday)
* Stats API endpoint for Apeiron Registry integration
* Fix: "Powered by" link now opt-in via settings (Guideline 10 compliance)
* Fix: ethers.js documentation clarified (bundled, not CDN)
* Requires PHP 7.4+ (reduced from 8.0)
* Requires WordPress 5.8+ (reduced from 6.0)

= 1.2.0 =
* Three protection modes per article: Disabled, AI Only, Full
* AI Only mode: humans read free, bots intercepted with HTTP 402
* Registry Log and Registry Block modes with Apeiron Registry integration
* Analytics dashboard with on-chain data

= 1.0.0 =
* Initial release — USDC paywall on Base Mainnet

== Upgrade Notice ==

= 2.1.0 =
HMAC request signing for Apeiron Registry agents (signing secret never leaves the bot), new dedicated Payments admin page, opt-in branding for Guideline 10 compliance, ethers.js updated to 6.16.0. Backward compatible — existing settings preserved.

= 2.0.0 =
Major update. New database tables are created automatically on activation. All existing settings and per-article configurations are preserved.
