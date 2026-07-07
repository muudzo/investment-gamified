// Initialize API
const api = new InvestmentApi(document.querySelector('meta[name="app-url"]').content + '/api');
let currentStock = null;
let tradeType = null;

// Login
async function login() {
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    const data = await api.login(email, password);

    if (data.success) {
        showDashboard();
    } else {
        showError(data.message || 'Login failed');
    }
}

// Register
async function register() {
    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const passwordConfirm = document.getElementById('registerPasswordConfirm').value;

    // Client-side validation
    if (!name || !email || !password || !passwordConfirm) {
        showError('Please fill in all fields');
        return;
    }

    if (password.length < 8) {
        showError('Password must be at least 8 characters');
        return;
    }

    if (password !== passwordConfirm) {
        showError('Passwords do not match');
        return;
    }

    const data = await api.register(name, email, password, passwordConfirm);

    if (data.success) {
        showSuccess('Account created successfully! Logging you in...');
        setTimeout(() => showDashboard(), 1500);
    } else {
        // Handle Laravel validation errors
        if (data.errors) {
            const errorMessages = Object.values(data.errors).flat().join(', ');
            showError(errorMessages);
        } else {
            showError(data.message || 'Registration failed');
        }
    }
}

// Toggle between login and register forms
function toggleAuthMode() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    loginForm.classList.toggle('hidden');
    registerForm.classList.toggle('hidden');

    // Clear error/success messages
    hideMessages();
}

function showError(message) {
    const errorDiv = document.getElementById('authError');
    const successDiv = document.getElementById('authSuccess');
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    successDiv.classList.add('hidden');
}

function showSuccess(message) {
    const errorDiv = document.getElementById('authError');
    const successDiv = document.getElementById('authSuccess');
    successDiv.textContent = message;
    successDiv.classList.remove('hidden');
    errorDiv.classList.add('hidden');
}

function hideMessages() {
    document.getElementById('authError').classList.add('hidden');
    document.getElementById('authSuccess').classList.add('hidden');
}

function logout() {
    api.clearToken();
    document.getElementById('loginScreen').classList.remove('hidden');
    document.getElementById('dashboardScreen').classList.add('hidden');

    // Clear forms
    document.getElementById('loginEmail').value = '';
    document.getElementById('loginPassword').value = '';
    hideMessages();
}

async function showDashboard() {
    document.getElementById('loginScreen').classList.add('hidden');
    document.getElementById('dashboardScreen').classList.remove('hidden');

    await loadUserData();
    await loadStocks();
    await loadPortfolio();
    await loadAchievements();

    // Poll for updates every 3 seconds
    setInterval(async () => {
        await loadStocks();
        await loadPortfolio();
        await loadUserData(); // Also update user data to reflect portfolio value changes
    }, 3000);
}

async function loadUserData() {
    const data = await api.getSummary();

    if (data.success) {
        document.getElementById('userName').textContent = data.data.name || 'Trader';
        document.getElementById('userLevel').textContent = data.data.level;
        document.getElementById('userBalance').textContent = parseFloat(data.data.balance).toFixed(2);
        document.getElementById('portfolioValue').textContent = parseFloat(data.data.total_value).toFixed(2);
        document.getElementById('userXP').textContent = data.data.experience_points;
    }
}

// Build a single stock card as real DOM nodes so that any attacker-controlled
// field coming back from the stocks API (name, description, fun_fact, etc.)
// is only ever assigned via textContent and can never be parsed as markup.
function createStockCard(stock) {
    const card = document.createElement('div');
    card.className = 'border rounded-xl p-4 hover:shadow-md transition';

    const header = document.createElement('div');
    header.className = 'flex justify-between items-start mb-2';

    const left = document.createElement('div');
    const symbolEl = document.createElement('h4');
    symbolEl.className = 'font-bold text-lg';
    symbolEl.textContent = stock.symbol;

    const nameEl = document.createElement('p');
    nameEl.className = 'text-sm text-gray-600';
    nameEl.textContent = stock.name;

    left.appendChild(symbolEl);
    left.appendChild(nameEl);

    const right = document.createElement('div');
    right.className = 'text-right';

    const priceEl = document.createElement('p');
    priceEl.className = 'font-bold text-xl';
    priceEl.textContent = `$${stock.current_price}`;

    const isPositive = stock.change_percentage >= 0;
    const changeEl = document.createElement('p');
    changeEl.className = `text-sm ${isPositive ? 'text-green-600' : 'text-red-600'}`;
    changeEl.textContent = `${isPositive ? '+' : ''}${stock.change_percentage}%`;

    right.appendChild(priceEl);
    right.appendChild(changeEl);

    header.appendChild(left);
    header.appendChild(right);
    card.appendChild(header);

    const descEl = document.createElement('p');
    descEl.className = 'text-sm text-gray-600 mb-3';
    descEl.textContent = stock.kid_friendly_description || stock.description || '';
    card.appendChild(descEl);

    if (stock.fun_fact) {
        const funFactEl = document.createElement('p');
        funFactEl.className = 'text-xs text-purple-600 mb-3';
        funFactEl.textContent = `💡 ${stock.fun_fact}`;
        card.appendChild(funFactEl);
    }

    const btnRow = document.createElement('div');
    btnRow.className = 'flex gap-2';

    const buyBtn = document.createElement('button');
    buyBtn.className = 'flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 font-semibold';
    buyBtn.textContent = 'Buy';
    buyBtn.addEventListener('click', () => openTradeModal(stock.symbol, 'buy'));

    const sellBtn = document.createElement('button');
    sellBtn.className = 'flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 font-semibold';
    sellBtn.textContent = 'Sell';
    sellBtn.addEventListener('click', () => openTradeModal(stock.symbol, 'sell'));

    btnRow.appendChild(buyBtn);
    btnRow.appendChild(sellBtn);
    card.appendChild(btnRow);

    return card;
}

async function loadStocks() {
    const data = await api.getStocks();
    const stocksList = document.getElementById('stocksList');

    // Clear previous content without ever touching innerHTML
    stocksList.textContent = '';

    if (!data.success) {
        console.error('Failed to load stocks:', data.message);
        const errEl = document.createElement('p');
        errEl.className = 'text-red-500';
        errEl.textContent = 'Failed to load stocks. Please refresh.';
        stocksList.appendChild(errEl);
        return;
    }

    data.data.forEach(stock => {
        stocksList.appendChild(createStockCard(stock));
    });
}

function createPortfolioItem(item) {
    const wrap = document.createElement('div');
    wrap.className = 'border rounded-lg p-3';

    const row = document.createElement('div');
    row.className = 'flex justify-between items-center';

    const left = document.createElement('div');
    const symbolEl = document.createElement('p');
    symbolEl.className = 'font-semibold';
    symbolEl.textContent = item.stock_symbol;

    const qtyEl = document.createElement('p');
    qtyEl.className = 'text-xs text-gray-600';
    qtyEl.textContent = `${item.quantity} shares`;

    left.appendChild(symbolEl);
    left.appendChild(qtyEl);

    const isPositive = item.profit_loss >= 0;
    const plEl = document.createElement('p');
    plEl.className = `text-sm font-bold ${isPositive ? 'text-green-600' : 'text-red-600'}`;
    plEl.textContent = `${isPositive ? '+' : ''}$${parseFloat(item.profit_loss).toFixed(2)}`;

    row.appendChild(left);
    row.appendChild(plEl);
    wrap.appendChild(row);

    return wrap;
}

async function loadPortfolio() {
    const data = await api.getPortfolio();
    const portfolioList = document.getElementById('portfolioList');

    portfolioList.textContent = '';

    if (!data.success || data.data.length === 0) {
        const emptyEl = document.createElement('p');
        emptyEl.className = 'text-gray-500 text-sm';
        emptyEl.textContent = 'No stocks yet. Start trading!';
        portfolioList.appendChild(emptyEl);
        return;
    }

    data.data.forEach(item => {
        portfolioList.appendChild(createPortfolioItem(item));
    });
}

function createAchievementItem(achievement) {
    const wrap = document.createElement('div');
    wrap.className = `flex items-center gap-3 p-2 rounded-lg ${achievement.unlocked ? 'bg-yellow-50' : 'bg-gray-50'}`;

    const iconEl = document.createElement('span');
    iconEl.className = `text-2xl ${achievement.unlocked ? '' : 'grayscale opacity-50'}`;
    iconEl.textContent = achievement.icon;

    const textWrap = document.createElement('div');
    textWrap.className = 'flex-1';

    const nameEl = document.createElement('p');
    nameEl.className = 'text-sm font-semibold';
    nameEl.textContent = achievement.name;

    const xpEl = document.createElement('p');
    xpEl.className = 'text-xs text-gray-600';
    xpEl.textContent = `${achievement.xp_reward} XP`;

    textWrap.appendChild(nameEl);
    textWrap.appendChild(xpEl);

    wrap.appendChild(iconEl);
    wrap.appendChild(textWrap);

    if (achievement.unlocked) {
        const checkEl = document.createElement('span');
        checkEl.className = 'text-xs text-green-600 font-bold';
        checkEl.textContent = '✓';
        wrap.appendChild(checkEl);
    }

    return wrap;
}

async function loadAchievements() {
    const data = await api.getAchievements();

    if (!data.success) {
        console.error('Failed to load achievements:', data.message);
        return;
    }

    const achievementsList = document.getElementById('achievementsList');
    achievementsList.textContent = '';

    data.data.forEach(achievement => {
        achievementsList.appendChild(createAchievementItem(achievement));
    });
}

async function openTradeModal(symbol, type) {
    const data = await api.getStock(symbol);
    currentStock = data.data;
    tradeType = type;

    document.getElementById('modalTitle').textContent =
        `${type === 'buy' ? 'Buy' : 'Sell'} ${currentStock.symbol}`;
    document.getElementById('modalDescription').textContent = currentStock.kid_friendly_description;
    document.getElementById('tradeQuantity').value = 1;
    updateTotalCost();

    document.getElementById('tradeModal').classList.remove('hidden');

    document.getElementById('confirmTradeBtn').onclick = confirmTrade;
}

function updateTotalCost() {
    const quantity = parseInt(document.getElementById('tradeQuantity').value) || 1;
    const total = currentStock.current_price * quantity;
    document.getElementById('totalCost').textContent = `$${total.toFixed(2)}`;
}

document.getElementById('tradeQuantity').addEventListener('input', updateTotalCost);

async function confirmTrade() {
    const quantity = parseInt(document.getElementById('tradeQuantity').value);

    let data;
    if (tradeType === 'buy') {
        data = await api.buyStock(currentStock.symbol, quantity);
    } else {
        data = await api.sellStock(currentStock.symbol, quantity);
    }

    if (data.success) {
        closeTradeModal();
        await loadUserData();
        await loadPortfolio();
        await loadAchievements();
        alert(`${tradeType === 'buy' ? 'Bought' : 'Sold'} successfully! +${data.data.xp_earned} XP`);
    } else {
        alert(data.message || 'Trade failed');
    }
}

function closeTradeModal() {
    document.getElementById('tradeModal').classList.add('hidden');
}

// Wire up controls that used to rely on inline onclick="" attributes in the
// blade view. Inline event handler attributes are blocked by a strict
// script-src CSP (no 'unsafe-inline'), so every handler is attached here
// instead via addEventListener.
document.getElementById('loginBtn').addEventListener('click', login);
document.getElementById('showRegisterBtn').addEventListener('click', toggleAuthMode);
document.getElementById('registerBtn').addEventListener('click', register);
document.getElementById('showLoginBtn').addEventListener('click', toggleAuthMode);
document.getElementById('logoutBtn').addEventListener('click', logout);
document.getElementById('cancelTradeBtn').addEventListener('click', closeTradeModal);

// Check for existing token on load
window.onload = () => {
    if (api.token) {
        showDashboard();
    }
};
