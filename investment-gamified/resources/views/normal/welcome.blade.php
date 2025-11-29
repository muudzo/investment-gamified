<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Kid Investment Game</title>
	<script src="https://cdn.tailwindcss.com"></script>
	{{-- Allow JS to read the app URL for API calls --}}
	<meta name="app-url" content="{{ url('/') }}">
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen">
	<div id="app">
		<!-- Login Screen -->
		<div id="loginScreen" class="min-h-screen flex items-center justify-center p-4">
			<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
				<h1 class="text-3xl font-bold text-center mb-2 text-purple-600">🎮 Investment Game</h1>
				<p class="text-gray-600 text-center mb-6">Learn to invest with fun!</p>
                
				<div id="loginForm">
					<input type="email" id="loginEmail" placeholder="Email" 
						   class="w-full p-3 border rounded-lg mb-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<input type="password" id="loginPassword" placeholder="Password" 
						   class="w-full p-3 border rounded-lg mb-4 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<button onclick="login()" 
							class="w-full bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
						Login
					</button>
					<p class="text-center mt-4 text-sm text-gray-600">
						Test account: test@example.com / password
					</p>
				</div>
                
				<div id="loginError" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg hidden"></div>
			</div>
		</div>

		<!-- Dashboard Screen -->
		<div id="dashboardScreen" class="hidden min-h-screen p-4">
			<!-- Header -->
			<div class="max-w-6xl mx-auto mb-6">
				<div class="bg-white rounded-2xl shadow-lg p-6">
					<div class="flex justify-between items-center">
						<div>
							<h2 class="text-2xl font-bold text-gray-800">Welcome, <span id="userName"></span>!</h2>
							<p class="text-gray-600">Level <span id="userLevel"></span> Trader</p>
						</div>
						<div class="flex gap-3 items-center">
							<a href="{{ url('/toggle-ui') }}" class="text-sm text-purple-600 hover:text-purple-700 underline">
								Switch to Senior Mode
							</a>
							<button onclick="logout()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
								Logout
							</button>
						</div>
					</div>
                    
					<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
						<div class="bg-gradient-to-br from-green-400 to-green-600 rounded-xl p-4 text-white">
							<p class="text-sm opacity-90">Balance</p>
							<p class="text-3xl font-bold">$<span id="userBalance">0</span></p>
						</div>
						<div class="bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl p-4 text-white">
							<p class="text-sm opacity-90">Portfolio Value</p>
							<p class="text-3xl font-bold">$<span id="portfolioValue">0</span></p>
						</div>
						<div class="bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl p-4 text-white">
							<p class="text-sm opacity-90">Total XP</p>
							<p class="text-3xl font-bold"><span id="userXP">0</span> XP</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Main Content -->
			<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
				<!-- Stocks List -->
				<div class="lg:col-span-2">
					<div class="bg-white rounded-2xl shadow-lg p-6">
						<h3 class="text-xl font-bold mb-4">Available Stocks</h3>
						<div id="stocksList" class="space-y-3">
							<!-- Stocks will be loaded here -->
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="space-y-6">
					<!-- Portfolio -->
					<div class="bg-white rounded-2xl shadow-lg p-6">
						<h3 class="text-xl font-bold mb-4">My Portfolio</h3>
						<div id="portfolioList" class="space-y-2">
							<p class="text-gray-500 text-sm">No stocks yet. Start trading!</p>
						</div>
					</div>

					<!-- Achievements -->
					<div class="bg-white rounded-2xl shadow-lg p-6">
						<h3 class="text-xl font-bold mb-4">Achievements</h3>
						<div id="achievementsList" class="space-y-2">
							<!-- Achievements will be loaded here -->
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Trading Modal -->
	<div id="tradeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
		<div class="bg-white rounded-2xl p-6 max-w-md w-full">
			<h3 class="text-2xl font-bold mb-4" id="modalTitle"></h3>
			<p class="text-gray-600 mb-4" id="modalDescription"></p>
            
			<div class="mb-4">
				<label class="block text-sm font-semibold mb-2">Quantity</label>
				<input type="number" id="tradeQuantity" min="1" value="1" 
					   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:outline-none">
			</div>
            
			<div class="mb-6">
				<p class="text-sm text-gray-600">Total Cost: <span class="font-bold text-xl" id="totalCost">$0.00</span></p>
			</div>
            
			<div class="flex gap-3">
				<button onclick="closeTradeModal()" 
						class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg font-semibold hover:bg-gray-300">
					Cancel
				</button>
				<button id="confirmTradeBtn" 
						class="flex-1 bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700">
					Confirm
				</button>
			</div>
		</div>
	</div>

	<script>
		// Use the application's URL helper to build a correct API base URL
		const API_URL = "{{ url('/api') }}"; // -> e.g. http://localhost:8000/api
		let authToken = null;
		let currentStock = null;
		let tradeType = null;

		// Login
		async function login() {
			const email = document.getElementById('loginEmail').value;
			const password = document.getElementById('loginPassword').value;
            
			try {
				const response = await fetch(`${API_URL}/auth/login`, {
					method: 'POST',
					headers: { 
						'Content-Type': 'application/json',
						'Accept': 'application/json'
					},
					body: JSON.stringify({ email, password })
				});
                
				const data = await response.json();
                
				if (data.success) {
					authToken = data.token;
					localStorage.setItem('authToken', authToken);
					showDashboard();
				} else {
					showError(data.message || 'Login failed');
				}
			} catch (error) {
				console.error('Login error:', error);
				showError('Connection error: ' + error.message);
			}
		}

		function showError(message) {
			const errorDiv = document.getElementById('loginError');
			errorDiv.textContent = message;
			errorDiv.classList.remove('hidden');
		}

		function logout() {
			localStorage.removeItem('authToken');
			authToken = null;
			document.getElementById('loginScreen').classList.remove('hidden');
			document.getElementById('dashboardScreen').classList.add('hidden');
		}

		async function showDashboard() {
			document.getElementById('loginScreen').classList.add('hidden');
			document.getElementById('dashboardScreen').classList.remove('hidden');
            
			await loadUserData();
			await loadStocks();
			await loadPortfolio();
			await loadAchievements();
		}

		async function loadUserData() {
			try {
				const response = await fetch(`${API_URL}/portfolio/summary`, {
					headers: { 
						'Authorization': `Bearer ${authToken}`,
						'Accept': 'application/json'
					}
				});
				const data = await response.json();
            
				if (data.success) {
					document.getElementById('userName').textContent = 'Trader';
					document.getElementById('userLevel').textContent = data.data.level;
					document.getElementById('userBalance').textContent = parseFloat(data.data.balance).toFixed(2);
					document.getElementById('portfolioValue').textContent = parseFloat(data.data.total_value).toFixed(2);
					document.getElementById('userXP').textContent = data.data.experience_points;
				}
			} catch (error) {
				console.error('Error loading user data:', error);
			}
		}

		async function loadStocks() {
			try {
				const response = await fetch(`${API_URL}/stocks`);
				const data = await response.json();
				
				if (!data.success) {
					console.error('Failed to load stocks:', data.message);
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
			} catch (error) {
				console.error('Error loading stocks:', error);
				document.getElementById('stocksList').innerHTML = '<p class="text-red-500">Failed to load stocks. Please refresh.</p>';
			}
		}

		async function loadPortfolio() {
			try {
				const response = await fetch(`${API_URL}/portfolio`, {
					headers: { 
						'Authorization': `Bearer ${authToken}`,
						'Accept': 'application/json'
					}
				});
				const data = await response.json();
            
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
			} catch (error) {
				console.error('Error loading portfolio:', error);
			}
		}

		async function loadAchievements() {
			try {
				const response = await fetch(`${API_URL}/achievements`, {
					headers: { 
						'Authorization': `Bearer ${authToken}`,
						'Accept': 'application/json'
					}
				});
				const data = await response.json();
            
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
			} catch (error) {
				console.error('Error loading achievements:', error);
			}
		}

		async function openTradeModal(symbol, type) {
			const response = await fetch(`${API_URL}/stocks/${symbol}`);
			const data = await response.json();
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
            
			const response = await fetch(`${API_URL}/portfolio/${tradeType}`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Authorization': `Bearer ${authToken}`
				},
				body: JSON.stringify({
					stock_symbol: currentStock.symbol,
					quantity: quantity
				})
			});
            
			const data = await response.json();
            
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
			const token = localStorage.getItem('authToken');
			if (token) {
				authToken = token;
				showDashboard();
			}
		};
	</script>
</body>
</html>

