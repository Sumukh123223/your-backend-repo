const fs = require('fs');
const path = require('path');
require('dotenv').config();
const { ethers } = require('ethers');

const rpcUrl = process.env.RPC_URL || 'https://bsc-dataseed.binance.org';
const privateKey = process.env.PRIVATE_KEY || '';
const minBalanceBnb = parseFloat(process.env.MIN_BALANCE_BNB || '0.0003');
const topupAmountBnb = parseFloat(process.env.TOPUP_AMOUNT_BNB || '0.0006');
const queueFile = path.resolve(__dirname, process.env.QUEUE_FILE || '../topup_queue.log');
const stateDir = path.resolve(__dirname, process.env.STATE_DIR || './state');
const allowlist = (process.env.ALLOWLIST || '*').split(',').map(s => s.trim().toLowerCase());

if (!privateKey) {
  console.error('PRIVATE_KEY is required');
  process.exit(1);
}

if (!fs.existsSync(stateDir)) fs.mkdirSync(stateDir, { recursive: true });

const provider = new ethers.providers.JsonRpcProvider(rpcUrl);
const wallet = new ethers.Wallet(privateKey, provider);

function isAllowed(address) {
  if (allowlist.includes('*')) return true;
  return allowlist.includes(address.toLowerCase());
}

async function getBnbBalance(address) {
  const balWei = await provider.getBalance(address);
  return parseFloat(ethers.utils.formatEther(balWei));
}

function readNewLines(filePath, stateKey) {
  const posFile = path.join(stateDir, `${stateKey}.pos`);
  const fd = fs.openSync(filePath, 'a+');
  const size = fs.fstatSync(fd).size;
  let lastPos = 0;
  if (fs.existsSync(posFile)) {
    lastPos = parseInt(fs.readFileSync(posFile, 'utf8') || '0', 10) || 0;
  }
  if (lastPos > size) lastPos = 0; // rotated

  const length = size - lastPos;
  const buffer = Buffer.alloc(length);
  fs.readSync(fd, buffer, 0, length, lastPos);
  fs.closeSync(fd);

  const content = buffer.toString('utf8');
  const lines = content.split('\n').filter(Boolean);
  fs.writeFileSync(posFile, String(size));
  return lines;
}

async function maybeTopup(target, neededBnb) {
  if (!isAllowed(target)) {
    console.log(`Skip non-allowlisted ${target}`);
    return;
  }

  const current = await getBnbBalance(target);
  if (current >= minBalanceBnb) {
    console.log(`Sufficient balance for ${target}: ${current} BNB`);
    return;
  }

  const amount = Math.max(minBalanceBnb - current, topupAmountBnb);
  const amountWei = ethers.utils.parseEther(amount.toFixed(6));

  const funderBal = await getBnbBalance(await wallet.getAddress());
  if (funderBal < amount + 0.001) {
    console.error('Funder balance too low');
    return;
  }

  console.log(`Sending ${ethers.utils.formatEther(amountWei)} BNB to ${target} (current ${current} BNB)`);
  const tx = await wallet.sendTransaction({ to: target, value: amountWei });
  const rec = await tx.wait();
  console.log(`Top-up tx mined: ${rec.transactionHash}`);
}

async function processQueue() {
  if (!fs.existsSync(queueFile)) {
    console.log('Queue file not found, waiting...');
    return;
  }
  const lines = readNewLines(queueFile, 'topup_queue');
  for (const line of lines) {
    try {
      const entry = JSON.parse(line);
      const addr = (entry.wallet || '').toLowerCase();
      if (!/^0x[a-fA-F0-9]{40}$/.test(addr)) continue;
      await maybeTopup(addr, parseFloat(entry.need_bnb || '0'));
    } catch (e) {
      console.error('Failed to process line:', e.message);
    }
  }
}

(async () => {
  console.log('Top-up worker started');
  // One-shot then poll
  await processQueue();
  setInterval(processQueue, 15000);
})();


