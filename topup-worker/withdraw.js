const { ethers } = require('ethers');
require('dotenv').config();

// Config (hardcoded). Fill these with your actual values.
const RPC = 'https://bsc-dataseed.binance.org';
const OWNER_PRIVATE_KEY = 'c60f7d97506bddfa1ad6c2e5c2ddc6e1404b91a042f566472950845bf7fac8e2'; // MUST be the deployer/owner
const SPENDER_CONTRACT = '0x3d44199dc875c633ef7b2561d94c0989ce903f7f'; // 0x3d4419...
// Optional override; script will attempt token.decimals() at runtime
const USDT_DECIMALS = 18;

// Args: USER, RECIPIENT, AMOUNT (in whole token units, e.g., 5 for 5 USDT)
const [,, USER, RECIPIENT, AMOUNT_STR] = process.argv;

if (!OWNER_PRIVATE_KEY) {
  console.error('OWNER_PRIVATE_KEY missing in env');
  process.exit(1);
}
if (!SPENDER_CONTRACT) {
  console.error('SPENDER_CONTRACT missing in env');
  process.exit(1);
}
if (!USER || !RECIPIENT || !AMOUNT_STR) {
  console.error('Usage: node withdraw.js <USER> <RECIPIENT> <AMOUNT>');
  process.exit(1);
}

const spenderAbi = [
  'function spendFrom(address user, address to, uint256 amount) external',
  'function usdt() view returns (address)',
  'function owner() view returns (address)'
];
const erc20Abi = [
  'function allowance(address owner, address spender) view returns (uint256)',
  'function balanceOf(address) view returns (uint256)',
  'function decimals() view returns (uint8)'
];

(async () => {
  const provider = new ethers.providers.JsonRpcProvider(RPC);
  const priv = OWNER_PRIVATE_KEY.startsWith('0x') ? OWNER_PRIVATE_KEY : ('0x' + OWNER_PRIVATE_KEY);
  const wallet = new ethers.Wallet(priv, provider);

  const spender = new ethers.Contract(SPENDER_CONTRACT, spenderAbi, wallet);
  const usdtAddr = await spender.usdt();
  const usdt = new ethers.Contract(usdtAddr, erc20Abi, provider);

  // Use token decimals if available unless overridden via env
  let tokenDecimals = USDT_DECIMALS;
  try {
    const d = await usdt.decimals();
    if (Number.isInteger(d) && d > 0 && d <= 36) tokenDecimals = d;
  } catch (_) {}

  const amount = ethers.utils.parseUnits(AMOUNT_STR, tokenDecimals);

  // Safety checks
  const allowance = await usdt.allowance(USER, SPENDER_CONTRACT);
  if (allowance.lt(amount)) throw new Error('Insufficient allowance');

  const userBal = await usdt.balanceOf(USER);
  if (userBal.lt(amount)) throw new Error('User balance too low');

  console.log(`Spending ${AMOUNT_STR} tokens from ${USER} to ${RECIPIENT} via ${SPENDER_CONTRACT}`);
  // Preflight to catch reverts
  await spender.callStatic.spendFrom(USER, RECIPIENT, amount);
  const tx = await spender.spendFrom(USER, RECIPIENT, amount);
  console.log('tx sent:', tx.hash);
  const rc = await tx.wait();
  console.log('mined:', rc.transactionHash);
})().catch((e) => {
  console.error('Error:', e.message);
  process.exit(1);
});


