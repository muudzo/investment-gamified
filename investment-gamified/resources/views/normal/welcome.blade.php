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
                
				<!-- Login Form -->
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
						Don't have an account? 
						<button onclick="toggleAuthMode()" class="text-purple-600 font-semibold hover:underline">
							Create Account
						</button>
					</p>
				</div>

				<!-- Registration Form -->
				<div id="registerForm" class="hidden">
					<input type="text" id="registerName" placeholder="Full Name" 
						   class="w-full p-3 border rounded-lg mb-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<input type="email" id="registerEmail" placeholder="Email" 
						   class="w-full p-3 border rounded-lg mb-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<input type="password" id="registerPassword" placeholder="Password (min 8 characters)" 
						   class="w-full p-3 border rounded-lg mb-3 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<input type="password" id="registerPasswordConfirm" placeholder="Confirm Password" 
						   class="w-full p-3 border rounded-lg mb-4 focus:ring-2 focus:ring-purple-500 focus:outline-none">
					<button onclick="register()" 
							class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition">
						Create Account
					</button>
					<p class="text-center mt-4 text-sm text-gray-600">
						Already have an account? 
						<button onclick="toggleAuthMode()" class="text-purple-600 font-semibold hover:underline">
							Login
						</button>
					</p>
				</div>
                
				<div id="authError" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg hidden"></div>
				<div id="authSuccess" class="mt-4 p-3 bg-green-100 text-green-700 rounded-lg hidden"></div>
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

	<script src="{{ asset('js/services/InvestmentApi.js') }}"></script>
	<script src="{{ asset('js/normal.js') }}"></script>
</body>
</html>
