<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Investment Game — Learn to Invest</title>
	<meta name="app-url" content="{{ url('/') }}">
	<link rel="preconnect" href="{{ url('/') }}">
	<link rel="stylesheet" href="{{ asset('css/kids.css') }}">
</head>
<body>
	<div id="app" class="page-fade">

		<!-- ============ Auth ============ -->
		<section id="loginScreen" class="auth-wrap" aria-label="Sign in">
			<div class="auth-card">
				<div class="brand">
					<div class="brand__mark" aria-hidden="true">🎮</div>
					<h1>Investment Game</h1>
					<p>Grow your coins. Level up. Learn to invest!</p>
				</div>

				<div id="loginForm">
					<div class="field"><input type="email" id="loginEmail" placeholder="Email" autocomplete="email"></div>
					<div class="field"><input type="password" id="loginPassword" placeholder="Password" autocomplete="current-password"></div>
					<button id="loginBtn" class="btn btn--wide">Let's Play →</button>
					<p class="auth-switch">New here? <button id="showRegisterBtn" class="linkish">Make an account</button></p>
				</div>

				<div id="registerForm" class="hidden">
					<div class="field"><input type="text" id="registerName" placeholder="Your name" autocomplete="name"></div>
					<div class="field"><input type="email" id="registerEmail" placeholder="Email" autocomplete="email"></div>
					<div class="field"><input type="password" id="registerPassword" placeholder="Password (min 8 characters)" autocomplete="new-password"></div>
					<div class="field"><input type="password" id="registerPasswordConfirm" placeholder="Confirm password" autocomplete="new-password"></div>
					<button id="registerBtn" class="btn btn--play btn--wide">Create my account 🚀</button>
					<p class="auth-switch">Already playing? <button id="showLoginBtn" class="linkish">Log in</button></p>
				</div>

				<div id="authError" class="alert alert--error hidden" role="alert"></div>
				<div id="authSuccess" class="alert alert--success hidden" role="status"></div>
			</div>
		</section>

		<!-- ============ Dashboard ============ -->
		<main id="dashboardScreen" class="app hidden">
			<header class="topbar">
				<div class="topbar__row">
					<div class="who">
						<div class="avatar" aria-hidden="true">🦊</div>
						<div>
							<h2>Hi, <span id="userName">friend</span>!</h2>
							<p>Level <span id="userLevel">1</span> Investor</p>
						</div>
					</div>
					<div class="topbar__actions">
						<nav class="switcher" aria-label="Choose experience">
							<a href="{{ url('/') }}" class="is-active" aria-current="page">🎮 Playground</a>
							<a href="{{ url('/pro') }}" data-switch>📈 Pro</a>
						</nav>
						<button id="logoutBtn" class="btn btn--ghost">Log out</button>
					</div>
				</div>

				<div class="stats">
					<div class="stat stat--balance">
						<div class="stat__label">Coins to spend</div>
						<div class="stat__value">$<span id="userBalance">0</span></div>
						<div class="stat__emoji" aria-hidden="true">💰</div>
					</div>
					<div class="stat stat--value">
						<div class="stat__label">My stuff is worth</div>
						<div class="stat__value">$<span id="portfolioValue">0</span></div>
						<div class="stat__emoji" aria-hidden="true">📦</div>
					</div>
					<div class="stat stat--xp">
						<div class="stat__label">Experience</div>
						<div class="stat__value"><span id="userXP">0</span> XP</div>
						<div class="stat__emoji" aria-hidden="true">⭐</div>
					</div>
				</div>

				<div class="xp">
					<div class="xp__meta"><span>Next level</span><span id="xpMeta">0 / 1000 XP</span></div>
					<div class="xp__track"><div class="xp__fill" id="xpFill"></div></div>
				</div>
			</header>

			<div class="grid">
				<section class="panel" aria-label="Stocks to buy">
					<h3 class="panel__title">🛒 Stock Shop</h3>
					<div id="stocksList" class="stocks"></div>
				</section>

				<div class="side">
					<section class="panel" aria-label="My portfolio">
						<h3 class="panel__title">🎒 My Backpack</h3>
						<div id="portfolioList">
							<p class="empty">No stocks yet. Buy your first one!</p>
						</div>
					</section>

					<section class="panel" aria-label="Achievements">
						<h3 class="panel__title">🏅 Stickers</h3>
						<div id="achievementsList" class="stickers"></div>
					</section>
				</div>
			</div>
		</main>
	</div>

	<!-- ============ Trade modal ============ -->
	<div id="tradeModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
		<div class="modal__card">
			<h3 class="modal__title" id="modalTitle"></h3>
			<p class="modal__sub" id="modalSub"></p>

			<label class="xp__meta" for="tradeQuantity"><span>How many?</span></label>
			<div class="qty">
				<button class="qty__btn" id="qtyMinus" aria-label="Fewer">−</button>
				<input type="number" id="tradeQuantity" min="1" value="1" inputmode="numeric" aria-label="Quantity">
				<button class="qty__btn" id="qtyPlus" aria-label="More">+</button>
			</div>

			<div class="total"><span>Total</span><span id="totalCost">$0.00</span></div>

			<div class="modal__actions">
				<button id="cancelTradeBtn" class="btn btn--ghost">Cancel</button>
				<button id="confirmTradeBtn" class="btn">Confirm</button>
			</div>
		</div>
	</div>

	<div class="coin-layer" id="coinLayer" aria-hidden="true"></div>

	<script src="{{ asset('js/services/InvestmentApi.js') }}"></script>
	<script src="{{ asset('js/normal.js') }}"></script>
</body>
</html>
