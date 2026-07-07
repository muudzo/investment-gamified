// Investment — "Terminal Luxe" pro trading cockpit.
// Real order entry over the same API/account as the kids game. Dynamic values
// are written via textContent only, handlers via addEventListener (strict CSP).

const appUrl = document.querySelector('meta[name="app-url"]').content;
const api = new InvestmentApi(appUrl + '/api');
const $ = (id) => document.getElementById(id);

const fmtMoney = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
const money = (n) => fmtMoney.format(Number(n) || 0);
const signed = (n) => (Number(n) >= 0 ? '+' : '') + money(n);
const pct = (n) => (Number(n) >= 0 ? '+' : '') + (Number(n) || 0).toFixed(2) + '%';

let stocks = [];
let selected = null;
let side = 'buy';
let lastPrices = {};
let pollTimer = null;

/* ---------------- Smooth experience switch ---------------- */
document.querySelectorAll('[data-switch]').forEach((link) => {
	link.addEventListener('click', (e) => {
		e.preventDefault();
		document.body.classList.add('page-leaving');
		setTimeout(() => { window.location.href = link.href; }, 240);
	});
});

/* ---------------- Auth ---------------- */
function showMsg(text, kind) {
	const el = $('authMsg');
	el.textContent = text;
	el.className = 'msg ' + (kind === 'ok' ? 'msg--ok' : 'msg--err');
}
function toggleAuth() {
	$('loginBox').classList.toggle('hidden');
	$('regBox').classList.toggle('hidden');
	$('authMsg').className = 'msg';
}
async function login() {
	const data = await api.login($('liEmail').value, $('liPass').value);
	if (data.success) { enterCockpit(); }
	else { showMsg(data.message || 'Sign in failed', 'err'); }
}
async function register() {
	const name = $('rgName').value, email = $('rgEmail').value;
	const p = $('rgPass').value, c = $('rgConfirm').value;
	if (!name || !email || !p || !c) return showMsg('Please fill in all fields', 'err');
	if (p.length < 8) return showMsg('Password must be at least 8 characters', 'err');
	if (p !== c) return showMsg('Passwords do not match', 'err');
	const data = await api.register(name, email, p, c);
	if (data.success) { enterCockpit(); }
	else if (data.errors) { showMsg(Object.values(data.errors).flat().join(', '), 'err'); }
	else { showMsg(data.message || 'Could not open account', 'err'); }
}
function logout() {
	api.clearToken();
	if (pollTimer) clearInterval(pollTimer);
	$('cockpit').classList.add('hidden');
	$('authScreen').classList.remove('hidden');
}

/* ---------------- Cockpit ---------------- */
async function enterCockpit() {
	$('authScreen').classList.add('hidden');
	$('cockpit').classList.remove('hidden');
	await Promise.all([loadSummary(), loadStocks(), loadPositions(), loadLeaderboard()]);
	if (pollTimer) clearInterval(pollTimer);
	pollTimer = setInterval(() => {
		loadStocks();
		loadPositions();
		loadSummary();
	}, 4000);
}

async function loadSummary() {
	const data = await api.getSummary();
	if (!data.success) return;
	const d = data.data;
	const equity = (Number(d.balance) || 0) + (Number(d.total_value) || 0);

	$('acctName').textContent = d.name || 'Account';
	$('kpiEquity').textContent = money(equity);
	$('kpiBuyingPower').textContent = money(d.balance);
	$('kpiInvested').textContent = money(d.total_invested);

	const pl = Number(d.profit_loss) || 0;
	const plEl = $('kpiPL');
	plEl.textContent = signed(pl);
	plEl.className = 'kpi__value num ' + (pl >= 0 ? 'gain' : 'loss');
	const pctEl = $('kpiPLpct');
	pctEl.textContent = pct(d.profit_loss_percentage);
	pctEl.className = 'kpi__sub num ' + (pl >= 0 ? 'gain' : 'loss');
}

function priceCell(symbol, price) {
	const el = document.createElement('div');
	el.className = 'p';
	el.textContent = money(price);
	const prev = lastPrices[symbol];
	if (prev !== undefined && Number(price) !== Number(prev)) {
		el.classList.add(Number(price) > Number(prev) ? 'flash-up' : 'flash-down');
	}
	return el;
}

function createWatchRow(stock, idx) {
	const row = document.createElement('div');
	row.className = 'wl-row' + (selected && selected.symbol === stock.symbol ? ' is-selected' : '');
	row.setAttribute('role', 'button');
	row.setAttribute('tabindex', '0');

	const left = document.createElement('div');
	const s = document.createElement('div');
	s.className = 's';
	s.textContent = stock.symbol;
	const n = document.createElement('div');
	n.className = 'n';
	n.textContent = stock.name;
	left.append(s, n);

	const right = document.createElement('div');
	right.appendChild(priceCell(stock.symbol, stock.current_price));
	const up = Number(stock.change_percentage) >= 0;
	const c = document.createElement('div');
	c.className = 'c ' + (up ? 'gain' : 'loss');
	c.textContent = pct(stock.change_percentage);
	right.appendChild(c);

	row.append(left, right);
	const select = () => selectSymbol(idx);
	row.addEventListener('click', select);
	row.addEventListener('keydown', (e) => { if (e.key === 'Enter') select(); });
	return row;
}

async function loadStocks() {
	const data = await api.getStocks();
	if (!data.success) return;

	const list = $('watchlist');
	list.textContent = '';
	stocks.forEach((s) => { lastPrices[s.symbol] = s.current_price; });
	stocks = data.data;
	stocks.forEach((stock, idx) => list.appendChild(createWatchRow(stock, idx)));

	if (selected) {
		const fresh = stocks.find((s) => s.symbol === selected.symbol);
		if (fresh) { selected = fresh; $('otLast').textContent = money(fresh.current_price); updateEst(); }
	}
}

function createPositionRow(item) {
	const tr = document.createElement('tr');
	const cells = [
		{ v: item.stock_symbol, sym: true },
		{ v: item.quantity },
		{ v: money(item.average_price) },
		{ v: money(item.current_price) },
		{ v: money(item.total_value) },
		{ v: signed(item.profit_loss), cls: Number(item.profit_loss) >= 0 ? 'gain' : 'loss' },
	];
	cells.forEach((c) => {
		const td = document.createElement('td');
		if (c.sym) { td.className = 'sym'; td.textContent = c.v; }
		else { td.textContent = c.v; if (c.cls) td.classList.add(c.cls); }
		tr.appendChild(td);
	});
	return tr;
}

async function loadPositions() {
	const data = await api.getPortfolio();
	const body = $('positionsBody');
	const empty = $('positionsEmpty');
	body.textContent = '';

	if (!data.success || data.data.length === 0) {
		empty.classList.remove('hidden');
		$('positionsCount').textContent = '0 holdings';
		return;
	}
	empty.classList.add('hidden');
	$('positionsCount').textContent = `${data.data.length} holding${data.data.length === 1 ? '' : 's'}`;
	data.data.forEach((item) => body.appendChild(createPositionRow(item)));
}

async function loadLeaderboard() {
	const data = await api.getLeaderboard(1, 10);
	const box = $('leaderboardBody');
	box.textContent = '';
	if (!data.success || !data.data || data.data.length === 0) {
		const p = document.createElement('p');
		p.className = 'empty';
		p.textContent = 'No rankings yet.';
		box.appendChild(p);
		return;
	}
	data.data.forEach((u) => {
		const row = document.createElement('div');
		row.className = 'lb-row';
		const rank = document.createElement('div');
		rank.className = 'rank' + (u.rank <= 3 ? ' top' : '');
		rank.textContent = '#' + u.rank;
		const nm = document.createElement('div');
		nm.className = 'nm';
		nm.textContent = u.name;
		const lv = document.createElement('div');
		lv.className = 'lv';
		lv.textContent = 'Lv ' + u.level;
		row.append(rank, nm, lv);
		box.appendChild(row);
	});
}

/* ---------------- Order ticket ---------------- */
function selectSymbol(idx) {
	selected = stocks[idx];
	$('otSymbol').textContent = selected.symbol;
	$('otName').textContent = selected.name;
	$('otLast').textContent = money(selected.current_price);
	document.querySelectorAll('.wl-row').forEach((r, i) => r.classList.toggle('is-selected', i === idx));
	updateEst();
}

function setSide(next) {
	side = next;
	$('sideBuy').classList.toggle('on', side === 'buy');
	$('sideSell').classList.toggle('on', side === 'sell');
	$('otEstLabel').textContent = side === 'buy' ? 'Est. cost' : 'Est. proceeds';
	const submit = $('otSubmit');
	submit.className = side;
	submit.textContent = `${side === 'buy' ? 'Buy' : 'Sell'} ${selected ? selected.symbol : '—'}`;
	updateEst();
}

function updateEst() {
	const qty = Math.max(1, parseInt($('otQty').value) || 1);
	const price = selected ? Number(selected.current_price) : 0;
	$('otEst').textContent = money(qty * price);
	$('otSubmit').textContent = `${side === 'buy' ? 'Buy' : 'Sell'} ${selected ? selected.symbol : '—'}`;

	const hint = $('otHint');
	hint.textContent = '';
	if (!selected) { hint.textContent = 'Select a symbol to begin.'; }
}

async function submitOrder() {
	if (!selected) { $('otHint').textContent = 'Select a symbol from the market first.'; return; }
	const qty = Math.max(1, parseInt($('otQty').value) || 1);
	const data = side === 'buy'
		? await api.buyStock(selected.symbol, qty)
		: await api.sellStock(selected.symbol, qty);

	if (data.success) {
		toast(`${side === 'buy' ? 'Bought' : 'Sold'} ${qty} ${selected.symbol}`, 'ok');
		await Promise.all([loadSummary(), loadPositions(), loadStocks()]);
	} else {
		toast(data.message || 'Order rejected', 'err');
	}
}

/* ---------------- Toast ---------------- */
let toastTimer = null;
function toast(message, kind) {
	document.querySelectorAll('.toast').forEach((t) => t.remove());
	const el = document.createElement('div');
	el.className = 'toast ' + (kind || '');
	el.textContent = message;
	document.body.appendChild(el);
	if (toastTimer) clearTimeout(toastTimer);
	toastTimer = setTimeout(() => el.remove(), 2600);
}

/* ---------------- Wire controls ---------------- */
$('liBtn').addEventListener('click', login);
$('rgBtn').addEventListener('click', register);
$('toReg').addEventListener('click', toggleAuth);
$('toLogin').addEventListener('click', toggleAuth);
$('proLogout').addEventListener('click', logout);
$('sideBuy').addEventListener('click', () => setSide('buy'));
$('sideSell').addEventListener('click', () => setSide('sell'));
$('otQty').addEventListener('input', updateEst);
$('otSubmit').addEventListener('click', submitOrder);
$('liPass').addEventListener('keydown', (e) => { if (e.key === 'Enter') login(); });
$('rgConfirm').addEventListener('keydown', (e) => { if (e.key === 'Enter') register(); });

window.addEventListener('load', () => { if (api.token) enterCockpit(); });
