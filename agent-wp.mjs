/**
 * Apeiron WordPress Agent — protocollo x402
 *
 * Identico al flusso di agent.mjs ma punta al plugin WordPress:
 *   Round 1: GET /wp-json/apeiron/v1/content/<post_id>  → 402 + istruzioni
 *   Round 2: approve USDC → unlockAsAgent on-chain
 *   Round 3: GET /wp-json/apeiron/v1/content/<post_id>  + x-wallet-address → 200 + contenuto
 *
 * Usage:
 *   node agent-wp.mjs
 *
 * Requires .env.local with AGENT_PRIVATE_KEY_2 set.
 */

import { ethers } from "ethers";
import { readFileSync } from "fs";

// ── Config ────────────────────────────────────────────────────────────────────

const envFile = readFileSync(".env.local", "utf-8");
const env = Object.fromEntries(
  envFile.split("\n")
    .filter(line => line.includes("=") && !line.startsWith("#"))
    .map(line => {
      const idx = line.indexOf("=");
      return [ line.slice(0, idx).trim(), line.slice(idx + 1).trim() ];
    })
);

// ID del post WordPress (trova l'ID nell'URL ?p=XX o nell'editor)
const WP_BASE_URL = "http://apeiron-test.local";
const POST_ID     = env.WP_POST_ID || "6"; // "The Dead Web Theory..." post
const X402_URL    = `${WP_BASE_URL}/wp-json/apeiron/v1/content/${POST_ID}`;

const RPC_URL     = env.RPC_URL         || "https://mainnet.base.org";
const PRIVATE_KEY = env.AGENT_PRIVATE_KEY_2;

if (!PRIVATE_KEY) {
  console.error("❌  Missing AGENT_PRIVATE_KEY_2 in .env.local");
  process.exit(1);
}

// ── ABI ───────────────────────────────────────────────────────────────────────

const GATEWAY_ABI = [
  "function unlockAsAgent(bytes32 contentId, uint256 duration) external",
  "function hasAccess(address user, bytes32 contentId, uint8 accessType) external view returns (bool)",
];

const USDC_ABI = [
  "function approve(address spender, uint256 amount) external returns (bool)",
  "function allowance(address owner, address spender) external view returns (uint256)",
  "function balanceOf(address account) external view returns (uint256)",
];

// ── Main ──────────────────────────────────────────────────────────────────────

async function main() {
  console.log(`\n🤖  Apeiron WordPress Agent (x402)`);
  console.log(`📄  Endpoint: ${X402_URL}\n`);

  // ── Step 1: Round 1 — senza wallet → attende 402 ─────────────────────────
  console.log("── Step 1: Requesting content (no payment)...");
  const res1 = await fetch( X402_URL, {
    headers: { "User-Agent": "X402-Agent/1.0 (bot)" }
  });
  console.log(`    Status: ${res1.status}`);

  if ( res1.status !== 402 ) {
    const text = await res1.text();
    console.error("    Unexpected response:", text.slice(0, 200));
    process.exit(1);
  }

  const payment = await res1.json();
  console.log(`    ✓ Got 402 — protocol: ${payment.protocol}`);
  console.log(`    contentId  : ${payment.contentId}`);
  console.log(`    accessType : ${payment.accessType}`);
  console.log(`    price      : ${payment.priceFormatted}\n`);

  // ── Step 2: Setup wallet ──────────────────────────────────────────────────
  console.log("── Step 2: Setting up agent wallet...");
  const provider = new ethers.JsonRpcProvider(RPC_URL);
  const wallet   = new ethers.Wallet(PRIVATE_KEY, provider);
  console.log(`    Address: ${wallet.address}`);

  const usdc    = new ethers.Contract(payment.usdcAddress,    USDC_ABI,    wallet);
  const gateway = new ethers.Contract(payment.gatewayAddress, GATEWAY_ABI, wallet);

  const balance  = await usdc.balanceOf(wallet.address);
  const required = BigInt(payment.price);
  console.log(`    Balance : ${(Number(balance) / 1_000_000).toFixed(6)} USDC`);
  console.log(`    Required: ${(Number(required) / 1_000_000).toFixed(6)} USDC`);

  if (balance < required) {
    console.error(`    ❌  Insufficient USDC balance`);
    process.exit(1);
  }

  // ── Step 3: Check se già pagato ───────────────────────────────────────────
  console.log(`\n── Step 3: Checking existing on-chain access...`);
  const alreadyPaid = await gateway.hasAccess(wallet.address, payment.contentId, 1);
  if (alreadyPaid) {
    console.log(`    ✓ Already paid — skipping payment`);
    await fetchContent(wallet.address, payment.contentId);
    return;
  }
  console.log(`    No existing access — proceeding with payment`);

  // ── Step 4: Approve USDC ─────────────────────────────────────────────────
  console.log(`\n── Step 4: Approving USDC...`);
  const allowance = await usdc.allowance(wallet.address, payment.gatewayAddress);
  if (allowance < required) {
    const approveTx = await usdc.approve(payment.gatewayAddress, required);
    console.log(`    Tx: ${approveTx.hash}`);
    await approveTx.wait();
    console.log(`    ✓ Approved`);
  } else {
    console.log(`    ✓ Allowance already sufficient`);
  }

  // ── Step 5: Paga on-chain ─────────────────────────────────────────────────
  console.log(`\n── Step 5: Calling unlockAsAgent...`);
  const payTx = await gateway.unlockAsAgent(payment.contentId, 2592000); // 30 giorni
  console.log(`    Tx: ${payTx.hash}`);
  await payTx.wait();
  console.log(`    ✓ Payment confirmed on Base Mainnet\n`);

  await fetchContent(wallet.address, payment.contentId, payTx.hash);
}

// ── Round 3: fetch con x-wallet-address ──────────────────────────────────────

async function fetchContent(walletAddress, contentId, txHash = null) {
  console.log("── Step 6: Fetching content with x-wallet-address header...");
  const res2 = await fetch(X402_URL, {
    headers: {
      "User-Agent":       "X402-Agent/1.0 (bot)",
      "x-wallet-address": walletAddress,
    },
  });

  console.log(`    Status: ${res2.status}`);

  if (res2.status !== 200) {
    const err = await res2.json();
    console.error(`    ❌  Access denied:`, err);
    process.exit(1);
  }

  const content = await res2.json();
  console.log(`\n✅  Content unlocked!`);
  console.log(`    Title      : ${content.title}`);
  console.log(`    Access type: ${content.accessType}`);
  console.log(`    Body preview: ${content.body.slice(0, 150)}...`);
  if (txHash) console.log(`    Tx: ${txHash}`);
  console.log(`\n🤖  Check Apeiron Analytics dashboard — bot count should increase.\n`);
}

main().catch(console.error);
