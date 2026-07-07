// Investment Game — "Arcade Bank" kids experience.
// All dynamic values are written via textContent (never innerHTML) and all
// handlers are attached with addEventListener, so the strict script-src CSP
// (no 'unsafe-inline') is satisfied and there is no XSS surface.

const appUrl = document.querySelector('meta[name="app-url"]').content;
const api = new InvestmentApi(appUrl + '/api');

let currentStock = null;
let tradeType = null;
let pollTimer = null;

const $ = (id) => document.getElementById(id);

/* ---------------- Smooth experience switch ---------------- */
document.querySelectorAll('[data-switch]').forEach((link) => {
	link.addEventListener('click', (e) => {
		e.preventDefault();
		const app = $('app');
		app.classList.add('page-leaving');
		setTimeout(() => { window.location.href = link.href; }, 240);
	});
});

/* ---------------- Auth ---------------- */
async function login() {
	const email = $('loginEmail').value;
	const password = $('loginPassword').value;
	const data = await api.login(email, password);
	if (data.success) { showDashboard(); }
	else { showError(data.message || 'Login failed'); }
}

async function register() {
	const name = $('registerName').value;
	const email = $('registerEmail').value;
	const password = $('registerPassword').value;
	const passwordConfirm = $('registerPasswordConfirm').value;

	if (!name || !email || !password || !passwordConfirm) { return showError('Please fill in all fields'); }
	if (password.length < 8) { return showError('Password must be at least 8 characters'); }
	if (password !== passwordConfirm) { return showError('Passwords do not match'); }

	const data = await api.register(name, email, password, passwordConfirm);
	if (data.success) {
		showSuccess('Account created! Loading your game...');
		setTimeout(() => showDashboard(), 1200);
	} else if (data.errors) {
		showError(Object.values(data.errors).flat().join(', '));
	} else {
		showError(data.message || 'Registration failed');
	}
}

function toggleAuthMode() {
	$('loginForm').classList.toggle('hidden');
	$('registerForm').classList.toggle('hidden');
	hideMessages();
}

function showError(message) {
	$('authError').textContent = message;
	$('authError').classList.remove('hidden');
	$('authSuccess').classList.add('hidden');
}
function showSuccess(message) {
	$('authSuccess').textContent = message;
	$('authSuccess').classList.remove('hidden');
	$('authError').classList.add('hidden');
}
function hideMessages() {
	$('authError').classList.add('hidden');
	$('authSuccess').classList.add('hidden');
}

function logout() {
	api.clearToken();
	if (pollTimer) clearInterval(pollTimer);
	$('dashboardScreen').classList.add('hidden');
	$('loginScreen').classList.remove('hidden');
	$('loginEmail').value = '';
	$('loginPassword').value = '';
	hideMessages();
}

/* ---------------- Dashboard ---------------- */
async function showDashboard() {
	$('loginScreen').classList.add('hidden');
	$('dashboardScreen').classList.remove('hidden');

	await Promise.all([loadUserData(), loadStocks(), loadPortfolio(), loadAchievements()]);

	if (pollTimer) clearInterval(pollTimer);
	pollTimer = setInterval(() => {
		loadStocks();
		loadPortfolio();
		loadUserData();
	}, 4000);
}

async function loadUserData() {
	const data = await api.getSummary();
	if (!data.success) return;
	const d = data.data;

	$('userName').textContent = d.name || 'friend';
	$('userLevel').textContent = d.level;
	$('userBalance').textContent = parseFloat(d.balance).toFixed(2);
	$('portfolioValue').textContent = parseFloat(d.total_value).toFixed(2);
	$('userXP').textContent = d.experience_points;

	const next = d.next_level_xp || (d.level * 1000);
	const pct = Math.max(0, Math.min(100, (d.experience_points / next) * 100));
	$('xpFill').style.width = pct + '%';
	$('xpMeta').textContent = `${d.experience_points} / ${next} XP`;
}

function createStockCard(stock) {
	const card = document.createElement('div');
	card.className = 'stock';

	const head = document.createElement('div');
	head.className = 'stock__head';

	const left = document.createElement('div');
	const sym = document.createElement('div');
	sym.className = 'stock__sym';
	sym.textContent = stock.symbol;
	const name = document.createElement('div');
	name.className = 'stock__name';
	name.textContent = stock.name;
	left.append(sym, name);

	const right = document.createElement('div');
	const price = document.createElement('div');
	price.className = 'stock__price';
	price.textContent = `$${stock.current_price}`;
	const up = Number(stock.change_percentage) >= 0;
	const chip = document.createElement('div');
	chip.className = `chip ${up ? 'chip--up' : 'chip--down'}`;
	chip.textContent = `${up ? '▲' : '▼'} ${Math.abs(Number(stock.change_percentage)).toFixed(2)}%`;
	right.append(price, chip);

	head.append(left, right);
	card.appendChild(head);

	const desc = document.createElement('p');
	desc.className = 'stock__desc';
	desc.textContent = stock.kid_friendly_description || stock.description || '';
	card.appendChild(desc);

	if (stock.fun_fact) {
		const fact = document.createElement('p');
		fact.className = 'stock__fact';
		fact.textContent = `💡 ${stock.fun_fact}`;
		card.appendChild(fact);
	}

	const actions = document.createElement('div');
	actions.className = 'stock__actions';
	const buy = document.createElement('button');
	buy.className = 'pill pill--buy';
	buy.textContent = 'Buy';
	buy.addEventListener('click', () => openTradeModal(stock.symbol, 'buy'));
	const sell = document.createElement('button');
	sell.className = 'pill pill--sell';
	sell.textContent = 'Sell';
	sell.addEventListener('click', () => openTradeModal(stock.symbol, 'sell'));
	actions.append(buy, sell);
	card.appendChild(actions);

	return card;
}

async function loadStocks() {
	const data = await api.getStocks();
	const list = $('stocksList');
	list.textContent = '';
	if (!data.success) {
		const err = document.createElement('p');
		err.className = 'empty';
		err.textContent = 'Could not load stocks. Please refresh.';
		list.appendChild(err);
		return;
	}
	data.data.forEach((stock) => list.appendChild(createStockCard(stock)));
}

function createHolding(item) {
	const wrap = document.createElement('div');
	wrap.className = 'holding';

	const left = document.createElement('div');
	const sym = document.createElement('div');
	sym.className = 'holding__sym';
	sym.textContent = item.stock_symbol;
	const qty = document.createElement('div');
	qty.className = 'holding__qty';
	qty.textContent = `${item.quantity} shares`;
	left.append(sym, qty);

	const up = Number(item.profit_loss) >= 0;
	const pl = document.createElement('div');
	pl.className = `pl ${up ? 'pl--up' : 'pl--down'}`;
	pl.textContent = `${up ? '+' : ''}$${parseFloat(item.profit_loss).toFixed(2)}`;

	wrap.append(left, pl);
	return wrap;
}

async function loadPortfolio() {
	const data = await api.getPortfolio();
	const list = $('portfolioList');
	list.textContent = '';
	if (!data.success || data.data.length === 0) {
		const empty = document.createElement('p');
		empty.className = 'empty';
		empty.textContent = 'No stocks yet. Buy your first one!';
		list.appendChild(empty);
		return;
	}
	data.data.forEach((item) => list.appendChild(createHolding(item)));
}

function createSticker(a) {
	const wrap = document.createElement('div');
	wrap.className = `sticker ${a.unlocked ? '' : 'sticker--locked'}`;

	const icon = document.createElement('div');
	icon.className = 'sticker__icon';
	icon.textContent = a.icon;

	const body = document.createElement('div');
	body.className = 'sticker__body';
	const name = document.createElement('div');
	name.className = 'sticker__name';
	name.textContent = a.name;
	const xp = document.createElement('div');
	xp.className = 'sticker__xp';
	xp.textContent = `${a.xp_reward} XP`;
	body.append(name, xp);

	wrap.append(icon, body);
	if (a.unlocked) {
		const check = document.createElement('div');
		check.className = 'sticker__check';
		check.textContent = '✓';
		wrap.appendChild(check);
	}
	return wrap;
}

async function loadAchievements() {
	const data = await api.getAchievements();
	if (!data.success) return;
	const list = $('achievementsList');
	list.textContent = '';
	data.data.forEach((a) => list.appendChild(createSticker(a)));
}

/* ---------------- Trade modal ---------------- */
async function openTradeModal(symbol, type) {
	const data = await api.getStock(symbol);
	currentStock = data.data;
	tradeType = type;

	$('modalTitle').textContent = `${type === 'buy' ? 'Buy' : 'Sell'} ${currentStock.symbol}`;
	$('modalSub').textContent = currentStock.kid_friendly_description || currentStock.name || '';
	$('tradeQuantity').value = 1;
	updateTotalCost();
	$('confirmTradeBtn').className = type === 'buy' ? 'btn btn--play' : 'btn btn--danger';
	$('confirmTradeBtn').textContent = type === 'buy' ? 'Buy now' : 'Sell now';
	$('tradeModal').classList.remove('hidden');
}

function updateTotalCost() {
	const qty = Math.max(1, parseInt($('tradeQuantity').value) || 1);
	$('totalCost').textContent = `$${(currentStock.current_price * qty).toFixed(2)}`;
}

function stepQty(delta) {
	const input = $('tradeQuantity');
	input.value = Math.max(1, (parseInt(input.value) || 1) + delta);
	updateTotalCost();
}

async function confirmTrade() {
	const quantity = Math.max(1, parseInt($('tradeQuantity').value) || 1);
	const data = tradeType === 'buy'
		? await api.buyStock(currentStock.symbol, quantity)
		: await api.sellStock(currentStock.symbol, quantity);

	if (data.success) {
		closeTradeModal();
		coinBurst();
		const xp = data.data && data.data.xp_earned ? ` +${data.data.xp_earned} XP` : '';
		toast(`${tradeType === 'buy' ? 'Bought' : 'Sold'} ${quantity} ${currentStock.symbol}!${xp}`, 'win');
		await Promise.all([loadUserData(), loadPortfolio(), loadAchievements()]);
	} else {
		toast(data.message || 'Trade failed', 'err');
	}
}

function closeTradeModal() { $('tradeModal').classList.add('hidden'); }

/* ---------------- Celebration ---------------- */
function coinBurst() {
	const layer = $('coinLayer');
	const coins = ['🪙', '💰', '⭐', '🎉'];
	for (let i = 0; i < 14; i++) {
		const coin = document.createElement('div');
		coin.className = 'coin';
		coin.textContent = coins[i % coins.length];
		coin.style.left = (40 + Math.random() * 20) + 'vw';
		coin.style.top = (55 + Math.random() * 10) + 'vh';
		coin.style.animationDelay = (Math.random() * 0.25) + 's';
		layer.appendChild(coin);
		setTimeout(() => coin.remove(), 1300);
	}
}

let toastTimer = null;
function toast(message, kind) {
	document.querySelectorAll('.toast').forEach((t) => t.remove());
	const el = document.createElement('div');
	el.className = `toast ${kind === 'win' ? 'toast--win' : kind === 'err' ? 'toast--err' : ''}`;
	el.textContent = message;
	document.body.appendChild(el);
	if (toastTimer) clearTimeout(toastTimer);
	toastTimer = setTimeout(() => el.remove(), 2600);
}

/* ---------------- Wire controls ---------------- */
$('loginBtn').addEventListener('click', login);
$('showRegisterBtn').addEventListener('click', toggleAuthMode);
$('registerBtn').addEventListener('click', register);
$('showLoginBtn').addEventListener('click', toggleAuthMode);
$('logoutBtn').addEventListener('click', logout);
$('cancelTradeBtn').addEventListener('click', closeTradeModal);
$('confirmTradeBtn').addEventListener('click', confirmTrade);
$('qtyMinus').addEventListener('click', () => stepQty(-1));
$('qtyPlus').addEventListener('click', () => stepQty(1));
$('tradeQuantity').addEventListener('input', updateTotalCost);

// Submit auth forms on Enter for a snappier feel.
['loginPassword', 'registerPasswordConfirm'].forEach((id) => {
	const el = $(id);
	if (el) el.addEventListener('keydown', (e) => { if (e.key === 'Enter') (id === 'loginPassword' ? login() : register()); });
});

window.addEventListener('load', () => { if (api.token) showDashboard(); });
