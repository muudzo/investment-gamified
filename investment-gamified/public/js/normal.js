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

async function loadStocks() {
    const data = await api.getStocks();

    if (!data.success) {
        console.error('Failed to load stocks:', data.message);
        document.getElementById('stocksList').innerHTML = '<p class="text-red-500">Failed to load stocks. Please refresh.</p>';
        return;
    }

    const stocksList = document.getElementById('stocksList');
    stocksList.innerHTML = data.data.map(stock => `
        <div class="border rounded-xl p-4 hover:shadow-md transition">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h4 class="font-bold text-lg">${stock.symbol}</h4>
                    <p class="text-sm text-gray-600">${stock.name}</p>
                </div>
                <div class="text-right">
                    <p class="font-bold text-xl">$${stock.current_price}</p>
                    <p class="text-sm ${stock.change_percentage >= 0 ? 'text-green-600' : 'text-red-600'}">
                        ${stock.change_percentage >= 0 ? '+' : ''}${stock.change_percentage}%
                    </p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-3">${stock.kid_friendly_description || stock.description || ''}</p>
            ${stock.fun_fact ? `<p class="text-xs text-purple-600 mb-3">💡 ${stock.fun_fact}</p>` : ''}
            <div class="flex gap-2">
                <button onclick="openTradeModal('${stock.symbol}', 'buy')" 
                        class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 font-semibold">
                    Buy
                </button>
                <button onclick="openTradeModal('${stock.symbol}', 'sell')" 
                        class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 font-semibold">
                    Sell
                </button>
            </div>
        </div>
    `).join('');
}

async function loadPortfolio() {
    const data = await api.getPortfolio();

    const portfolioList = document.getElementById('portfolioList');
    if (!data.success || data.data.length === 0) {
        portfolioList.innerHTML = '<p class="text-gray-500 text-sm">No stocks yet. Start trading!</p>';
    } else {
        portfolioList.innerHTML = data.data.map(item => `
            <div class="border rounded-lg p-3">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-semibold">${item.stock_symbol}</p>
                        <p class="text-xs text-gray-600">${item.quantity} shares</p>
                    </div>
                    <p class="text-sm font-bold ${item.profit_loss >= 0 ? 'text-green-600' : 'text-red-600'}">
                        ${item.profit_loss >= 0 ? '+' : ''}$${parseFloat(item.profit_loss).toFixed(2)}
                    </p>
                </div>
            </div>
        `).join('');
    }
}

async function loadAchievements() {
    const data = await api.getAchievements();

    if (!data.success) {
        console.error('Failed to load achievements:', data.message);
        return;
    }

    const achievementsList = document.getElementById('achievementsList');
    achievementsList.innerHTML = data.data.map(achievement => `
        <div class="flex items-center gap-3 p-2 rounded-lg ${achievement.unlocked ? 'bg-yellow-50' : 'bg-gray-50'}">
            <span class="text-2xl ${achievement.unlocked ? '' : 'grayscale opacity-50'}">${achievement.icon}</span>
            <div class="flex-1">
                <p class="text-sm font-semibold">${achievement.name}</p>
                <p class="text-xs text-gray-600">${achievement.xp_reward} XP</p>
            </div>
            ${achievement.unlocked ? '<span class="text-xs text-green-600 font-bold">✓</span>' : ''}
        </div>
    `).join('');
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

// Check for existing token on load
window.onload = () => {
    if (api.token) {
        showDashboard();
    }
};

// Expose functions to global scope for HTML onclick handlers
window.login = login;
window.register = register;
window.toggleAuthMode = toggleAuthMode;
window.logout = logout;
window.openTradeModal = openTradeModal;
window.closeTradeModal = closeTradeModal;
window.confirmTrade = confirmTrade;
