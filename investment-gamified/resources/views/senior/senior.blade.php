<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Investment — Pro Trading</title>
  <meta name="app-url" content="{{ url('/') }}">
  <link rel="stylesheet" href="{{ asset('css/senior.css') }}">
</head>
<body>

  <!-- ============ Auth ============ -->
  <section id="authScreen" class="auth page-fade">
    <div class="auth__card">
      <div class="auth__brand">
        <span class="logomark">I</span>
        <b>Investment</b>
        <span class="tag">PRO · LIVE</span>
      </div>
      <h1 class="auth__title">Sign in to trade</h1>
      <p class="auth__sub">The real trading desk. Positions, order entry, live P/L.</p>

      <div id="loginBox">
        <div class="field"><label for="liEmail">Email</label><input id="liEmail" type="email" autocomplete="email"></div>
        <div class="field"><label for="liPass">Password</label><input id="liPass" type="password" autocomplete="current-password"></div>
        <button id="liBtn" class="btn">Sign in</button>
        <p class="auth__alt">No account? <button id="toReg" class="link">Open one</button></p>
      </div>

      <div id="regBox" class="hidden">
        <div class="field"><label for="rgName">Name</label><input id="rgName" type="text" autocomplete="name"></div>
        <div class="field"><label for="rgEmail">Email</label><input id="rgEmail" type="email" autocomplete="email"></div>
        <div class="field"><label for="rgPass">Password</label><input id="rgPass" type="password" autocomplete="new-password"></div>
        <div class="field"><label for="rgConfirm">Confirm password</label><input id="rgConfirm" type="password" autocomplete="new-password"></div>
        <button id="rgBtn" class="btn">Create account</button>
        <p class="auth__alt">Already trading? <button id="toLogin" class="link">Sign in</button></p>
      </div>

      <div id="authMsg" class="msg"></div>
    </div>
  </section>

  <!-- ============ Cockpit ============ -->
  <main id="cockpit" class="wrap hidden">
    <div class="topbar">
      <div class="brand"><span class="logomark">I</span> Investment <span class="tag">PRO</span></div>
      <div class="topbar__spacer"></div>
      <nav class="switcher" aria-label="Choose experience">
        <a href="{{ url('/') }}" data-switch>Playground</a>
        <a href="{{ url('/pro') }}" class="is-active" aria-current="page">Pro</a>
      </nav>
      <button id="proLogout" class="logout">Sign out</button>
    </div>

    <section class="kpis" aria-label="Account summary">
      <div class="kpi"><div class="kpi__label">Equity</div><div class="kpi__value" id="kpiEquity">$0.00</div><div class="kpi__sub eyebrow" id="acctName">—</div></div>
      <div class="kpi"><div class="kpi__label">Buying Power</div><div class="kpi__value" id="kpiBuyingPower">$0.00</div><div class="kpi__sub" style="color:var(--faint)">cash available</div></div>
      <div class="kpi"><div class="kpi__label">Invested</div><div class="kpi__value" id="kpiInvested">$0.00</div><div class="kpi__sub" style="color:var(--faint)">at cost</div></div>
      <div class="kpi"><div class="kpi__label">Total P/L</div><div class="kpi__value num" id="kpiPL">$0.00</div><div class="kpi__sub num" id="kpiPLpct">0.00%</div></div>
    </section>

    <div class="cols">
      <!-- Left: positions + market -->
      <div class="stack">
        <div class="card">
          <div class="card__head"><h3>Positions</h3><span class="eyebrow" id="positionsCount">0 holdings</span></div>
          <div class="card__body">
            <table>
              <thead><tr><th>Symbol</th><th>Qty</th><th>Avg</th><th>Last</th><th>Value</th><th>P/L</th></tr></thead>
              <tbody id="positionsBody"></tbody>
            </table>
            <p class="empty hidden" id="positionsEmpty">No open positions. Place your first order.</p>
          </div>
        </div>

        <div class="card">
          <div class="card__head"><h3>Market</h3><span class="eyebrow">tap to trade</span></div>
          <div class="card__body" id="watchlist"></div>
        </div>
      </div>

      <!-- Right: order ticket + leaderboard -->
      <div class="stack">
        <div class="card sticky" aria-label="Order ticket">
          <div class="card__head"><h3>Order Ticket</h3></div>
          <div class="ticket__sym"><b id="otSymbol">—</b><span class="last num" id="otLast">$0.00</span></div>
          <div class="ticket__name" id="otName">Select a symbol from the market</div>

          <div class="side" role="tablist" aria-label="Order side">
            <button data-side="buy" class="on" id="sideBuy">Buy</button>
            <button data-side="sell" id="sideSell">Sell</button>
          </div>

          <div class="ticket__field">
            <label for="otQty">Quantity (shares)</label>
            <input id="otQty" type="number" min="1" value="1" inputmode="numeric">
          </div>

          <div class="ticket__est"><span class="k" id="otEstLabel">Est. cost</span><span class="v num" id="otEst">$0.00</span></div>
          <div class="ticket__hint" id="otHint"></div>

          <div class="ticket__submit"><button id="otSubmit" class="buy">Buy —</button></div>
        </div>

        <div class="card">
          <div class="card__head"><h3>Leaderboard</h3><span class="eyebrow">by level</span></div>
          <div class="card__body" id="leaderboardBody"></div>
        </div>
      </div>
    </div>
  </main>

  <script src="{{ asset('js/services/InvestmentApi.js') }}"></script>
  <script src="{{ asset('js/senior.js') }}"></script>
</body>
</html>
