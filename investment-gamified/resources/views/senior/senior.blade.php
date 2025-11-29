<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Senior Mode — Investment App</title>
  {{-- Allow JS to read the app URL for API calls --}}
  <meta name="app-url" content="{{ url('/') }}">
  <style>
    /* Base variables (easy to override from PHP/CSS) */
    :root{
      --bg: #fbfbfa;
      --card: #ffffff;
      --text: #111214;
      --muted: #6b6f72;
      --accent: #0b6d3a; /* primary action color */
      --danger: #a42323;
      --surface-shadow: 0 8px 18px rgba(17,18,20,0.06);
      --radius: 14px;
      --min-touch: 48px;
      --base-font: 18px; /* recommended min */
    }

    /* Reset (small, careful) */
    *,*::before,*::after{box-sizing:border-box}
    html,body{height:100%;margin:0;font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; background:var(--bg); color:var(--text);}

    /* Container so it's embeddable */
    #senior-ui{max-width:980px;margin:18px auto;padding:18px}

    /* Card style */
    .card{background:var(--card);border-radius:var(--radius);box-shadow:var(--surface-shadow);padding:18px}

    /* Typography (bold, calm) */
    h1{font-size:28px;margin:0 0 8px;font-weight:600}
    h2{font-size:22px;margin:0 0 8px;font-weight:600}
    p{font-size:var(--base-font);line-height:1.4;margin:8px 0;color:var(--muted)}

    /* Balance card */
    .balance{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .balance .amount{font-size:28px;font-weight:700}
    .balance .tag{font-size:14px;padding:6px 10px;border-radius:10px;background:#eef7ef;color:var(--accent)}

    /* Action tiles */
    .action-row{display:flex;gap:12px;margin-top:14px}
    .tile{flex:1;border-radius:12px;padding:16px;display:flex;flex-direction:column;align-items:flex-start;gap:10px;min-height:92px;cursor:pointer}
    .tile .label{font-size:18px;font-weight:600}
    .tile .desc{font-size:14px;color:var(--muted)}
    .tile.in{background:linear-gradient(180deg,#f6fff5,#ffffff);border:1px solid rgba(11,109,58,0.06)}
    .tile.out{background:linear-gradient(180deg,#fff6f6,#ffffff);border:1px solid rgba(164,35,35,0.06)}

    /* Bottom fixed action (for wizard confirm) */
    .fixed-action{position:sticky;bottom:12px;display:flex;gap:12px;padding:12px 0;background:transparent}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:12px 18px;min-height:var(--min-touch);font-size:18px;font-weight:600;cursor:pointer;border:0}
    .btn-primary{background:var(--accent);color:#fff}
    .btn-ghost{background:transparent;border:2px solid #e6e6e6;color:var(--text)}
    .btn-danger{background:var(--danger);color:#fff}

    /* Bottom navigation */
    .bottom-nav{display:flex;gap:8px;position:sticky;bottom:14px;background:transparent;padding-top:10px}
    .nav-item{flex:1;background:var(--card);border-radius:12px;padding:12px;text-align:center;font-weight:600;min-height:52px;display:flex;flex-direction:column;justify-content:center;cursor:pointer}
    .nav-item.active{outline:3px solid rgba(11,109,58,0.12)}

    /* Wizard */
    .wizard{margin-top:16px}
    .step{display:none}
    .step.active{display:block}
    .amount-input{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .chips{display:flex;gap:8px;flex-wrap:wrap}
    .chip{padding:10px 12px;border-radius:12px;background:#f4f4f4;cursor:pointer;font-weight:600;border:none}
    .numeric{font-size:22px;padding:12px;border-radius:12px;border:1px solid #e9e9e9;min-width:160px}

    /* List rows */
    .row{display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:linear-gradient(180deg,#fff,#fbfbfb);margin-top:10px;cursor:pointer;border:1px solid transparent}
    .row:hover{border-color:#e9e9e9}
    .row .meta{font-size:16px}
    .row .sub{font-size:14px;color:var(--muted)}

    /* Help large buttons */
    .help-grid{display:flex;gap:8px;margin-top:12px}
    .help-btn{flex:1;padding:14px;border-radius:12px;background:#f6f9ff;font-weight:700;min-height:48px;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer}

    /* Accessibility helpers */
    .sr-only{position:absolute;left:-9999px}
    
    /* Hidden utility */
    .hidden{display:none!important}

    /* Reduced motion preference honored */
    @media (prefers-reduced-motion: reduce){*{transition:none!important}}

    /* Responsive */
    @media (max-width:720px){
      #senior-ui{padding:14px}
      h1{font-size:24px}
      .balance .amount{font-size:24px}
      .action-row{flex-direction:column}
      .amount-input{flex-direction:column;align-items:stretch}
    }

  </style>
</head>
<body>
  <main id="senior-ui" aria-live="polite">

    <header class="card" role="banner" aria-label="Senior mode header">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <h1>Your Money at a Glance</h1>
          <p>Protected & Trackable — simplified for confidence</p>
        </div>
        <div style="text-align:right">
          <div style="font-size:14px;margin-bottom:8px">Easy View Mode</div>
          <label style="display:inline-flex;align-items:center;gap:8px;background:#fff;padding:8px;border-radius:999px;border:1px solid #eee;">
            <input id="senior-toggle" type="checkbox" checked aria-label="Toggle Senior Mode"/>
            <span style="font-weight:700">ON</span>
          </label>
          <div style="margin-top:8px">
            <a href="{{ url('/toggle-ui') }}" style="font-size:14px;color:var(--accent);text-decoration:underline">Switch to Normal View</a>
          </div>
        </div>
      </div>

      <div style="margin-top:12px" class="balance card">
        <div>
          <div class="amount" id="userBalance">$0</div>
          <div class="tag">Protected & Trackable</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:14px;color:var(--muted)">Account status</div>
          <div style="font-weight:700;margin-top:6px">Active</div>
        </div>
      </div>

      <div class="action-row" aria-hidden="false">
        <div class="tile in card" data-action="put-in" tabindex="0" role="button" aria-pressed="false">
          <div class="label">Put Money In</div>
          <div class="desc">Quickly add funds to an investment</div>
        </div>
        <div class="tile out card" data-action="take-out" tabindex="0" role="button" aria-pressed="false">
          <div class="label">Take Money Out</div>
          <div class="desc">Withdraw money back to your bank</div>
        </div>
      </div>

    </header>

    <!-- Wizard area -->
    <section id="wizard-area" class="card wizard hidden" aria-live="polite">
      <!-- Steps are hidden/shown by JS -->
      <div id="step-1" class="step active" data-step="1">
        <h2>Step 1 — Choose Amount</h2>
        <p>Tap a quick amount or type your own.</p>
        <div class="amount-input">
          <input id="amount" class="numeric" type="number" min="1" value="100" aria-label="Amount to invest" />
          <div class="chips" role="list">
            <button class="chip" data-value="100">100</button>
            <button class="chip" data-value="300">300</button>
            <button class="chip" data-value="500">500</button>
            <button class="chip" data-value="1000">1000</button>
          </div>
        </div>
      </div>

      <div id="step-2" class="step" data-step="2">
        <h2>Step 2 — Choose Stock</h2>
        <p>Pick a stock with a single tap.</p>
        <div id="stock-list-wizard">
          <!-- Stocks will be loaded here -->
        </div>
      </div>

      <div id="step-3" class="step" data-step="3">
        <h2>Step 3 — Confirm</h2>
        <div class="card" style="padding:14px">
          <div style="font-size:18px;font-weight:700">Summary</div>
          <p id="confirm-summary">You are investing <strong>$100</strong> into <strong>Stock</strong>.</p>
        </div>
        <p style="margin-top:12px">If everything looks correct, press Confirm. You can cancel anytime.</p>
      </div>

      <div class="fixed-action">
        <button class="btn btn-ghost" id="btn-back" style="display:none">Back</button>
        <button class="btn btn-primary" id="btn-next">Continue</button>
      </div>
    </section>

    <!-- Activities -->
    <section class="card" aria-label="Activities" style="margin-top:16px">
      <h2>Latest Activity</h2>
      <div id="activities-list">
        <p class="text-gray-500 text-sm">No recent activity</p>
      </div>
    </section>

    <!-- Help -->
    <section class="card" aria-label="Help" style="margin-top:16px">
      <h2>Help</h2>
      <p>If you need help, choose an option below.</p>
      <div class="help-grid">
        <button class="help-btn" id="call-support">Call Support</button>
        <button class="help-btn" id="chat-support">Chat With Us</button>
      </div>
      <div style="margin-top:12px">
        <details>
          <summary style="font-size:18px;font-weight:700;cursor:pointer">How do I invest?</summary>
          <p style="font-size:16px">Tap "Put Money In" on the main screen, choose an amount, select a stock, and confirm.</p>
        </details>
        <details style="margin-top:8px">
          <summary style="font-size:18px;font-weight:700;cursor:pointer">How do I withdraw funds?</summary>
          <p style="font-size:16px">Tap "Take Money Out" on the main screen, choose an amount and confirm withdrawal.</p>
        </details>
      </div>
    </section>

    <!-- Bottom navigation -->
    <nav class="bottom-nav" aria-label="Primary">
      <div class="nav-item active" data-tab="home" role="button" tabindex="0">Home</div>
      <div class="nav-item" data-tab="activities" role="button" tabindex="0">Activities</div>
      <div class="nav-item" data-tab="help" role="button" tabindex="0">Help</div>
      <div class="nav-item" id="logout-btn" role="button" tabindex="0">Logout</div>
    </nav>

  </main>

  <script>
    // Use the application's URL helper to build a correct API base URL
    const API_URL = "{{ url('/api') }}";
    let authToken = localStorage.getItem('authToken') || null;
    let currentStock = null;
    let tradeType = 'buy';
    const root = document.documentElement;

    // Toggle senior mode: enlarge text and touch targets
    const seniorToggle = document.getElementById('senior-toggle');
    seniorToggle.addEventListener('change', (e)=>{
      if(e.target.checked){
        root.style.setProperty('--base-font','20px');
        root.style.setProperty('--min-touch','56px');
        announce('Senior mode enabled');
      } else {
        root.style.setProperty('--base-font','18px');
        root.style.setProperty('--min-touch','48px');
        announce('Senior mode disabled');
      }
    });

    // Wizard controls
    let curStep = 1;
    const totalSteps = 3;
    const showStep = (n)=>{
      document.querySelectorAll('.step').forEach(s=>s.classList.remove('active'));
      const el = document.querySelector('[data-step="'+n+'"]');
      if(el) el.classList.add('active');
      // back button visibility
      document.getElementById('btn-back').style.display = (n>1)?'inline-flex':'none';
      // change button text
      document.getElementById('btn-next').textContent = (n===totalSteps)?'Confirm Investment':'Continue';
    };

    document.getElementById('btn-next').addEventListener('click', async ()=>{
      if(curStep < totalSteps){
        curStep++;
        showStep(curStep);
        
        // Load stocks when entering step 2
        if(curStep === 2){
          await loadStocksForWizard();
        }
      } else {
        // confirm
        await confirmInvestment();
      }
    });

    document.getElementById('btn-back').addEventListener('click', ()=>{
      if(curStep>1){curStep--; showStep(curStep)}
    });

    // chips quick set
    document.querySelectorAll('.chip').forEach(c=>c.addEventListener('click',(ev)=>{
      document.getElementById('amount').value = ev.target.dataset.value;
    }));

    // main tiles actions
    document.querySelectorAll('.tile').forEach(t=>t.addEventListener('click', ()=>{
      const act = t.dataset.action;
      if(act==='put-in'){
        // open wizard at step 1
        document.getElementById('wizard-area').classList.remove('hidden');
        curStep = 1; 
        showStep(curStep);
        t.setAttribute('aria-pressed','true');
        window.scrollTo({top: document.getElementById('wizard-area').offsetTop - 20, behavior:'smooth'});
      } else if(act==='take-out'){
        // simple withdraw confirmation flow (placeholder)
        if(confirm('Are you sure you want to withdraw money? This feature is coming soon.')){
          alert('Withdrawal requested. We will process it shortly.');
        }
      }
    }));

    // bottom nav
    document.querySelectorAll('.nav-item').forEach(n=>n.addEventListener('click', ()=>{
      if(n.id === 'logout-btn'){
        logout();
        return;
      }
      
      document.querySelectorAll('.nav-item').forEach(x=>x.classList.remove('active'));
      n.classList.add('active');
      announce(n.textContent + ' tab');
      
      if(n.dataset.tab==='help'){
        document.querySelector('[aria-label="Help"]').scrollIntoView({behavior:'smooth'});
      } else if(n.dataset.tab==='activities'){
        document.querySelector('[aria-label="Activities"]').scrollIntoView({behavior:'smooth'});
      } else {
        window.scrollTo({top:0,behavior:'smooth'});
      }
    }));

    // help buttons
    document.getElementById('call-support').addEventListener('click', ()=>{
      alert('Support: +1-800-INVEST\n\nThis is a demo. In production, this would initiate a phone call.');
    });
    
    document.getElementById('chat-support').addEventListener('click', ()=>{
      alert('Opening chat support...\n\nThis is a demo. In production, this would open a live chat window.');
    });

    // Load user data
    async function loadUserData() {
      if(!authToken){
        window.location.href = "{{ url('/') }}";
        return;
      }
      
      try {
        const response = await fetch(`${API_URL}/portfolio/summary`, {
          headers: { 
            'Authorization': `Bearer ${authToken}`,
            'Accept': 'application/json'
          }
        });
        const data = await response.json();
        
        if (data.success) {
          document.getElementById('userBalance').textContent = '$' + parseFloat(data.data.balance).toFixed(2);
        } else {
          console.error('Failed to load user data:', data.message);
        }
      } catch (error) {
        console.error('Error loading user data:', error);
      }
    }

    // Load stocks for wizard
    async function loadStocksForWizard() {
      try {
        const response = await fetch(`${API_URL}/stocks`);
        const data = await response.json();
        
        if (!data.success) {
          console.error('Failed to load stocks:', data.message);
          return;
        }
        
        const stockList = document.getElementById('stock-list-wizard');
        stockList.innerHTML = data.data.map(stock => `
          <div class="row" role="button" tabindex="0" data-stock='${JSON.stringify(stock)}'>
            <div style="flex:1">
              <div class="meta">${stock.symbol} - ${stock.name}</div>
              <div class="sub">$${stock.current_price} per share</div>
            </div>
            <div style="font-weight:700;color:${stock.change_percentage >= 0 ? 'green' : 'red'}">
              ${stock.change_percentage >= 0 ? '+' : ''}${stock.change_percentage}%
            </div>
          </div>
        `).join('');
        
        // Add click handlers
        document.querySelectorAll('#stock-list-wizard .row').forEach(r=>{
          r.addEventListener('click', (ev)=>{
            currentStock = JSON.parse(r.dataset.stock);
            // visually indicate selection
            document.querySelectorAll('#stock-list-wizard .row').forEach(x=>x.style.outline='none');
            r.style.outline = '3px solid rgba(11,109,58,0.12)';
            announce(currentStock.symbol + ' selected');
          });
        });
      } catch (error) {
        console.error('Error loading stocks:', error);
      }
    }

    // Confirm investment
    async function confirmInvestment() {
      if(!currentStock){
        alert('Please select a stock first');
        curStep = 2;
        showStep(curStep);
        return;
      }
      
      const amount = parseInt(document.getElementById('amount').value);
      const quantity = Math.floor(amount / currentStock.current_price);
      
      if(quantity < 1){
        alert('Amount too small. Please increase the amount.');
        return;
      }
      
      // Update summary
      document.getElementById('confirm-summary').innerHTML = 
        `You are investing <strong>$${amount}</strong> into <strong>${currentStock.symbol}</strong> (${quantity} shares at $${currentStock.current_price} each).`;
      
      try {
        const response = await fetch(`${API_URL}/portfolio/buy`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`,
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            stock_symbol: currentStock.symbol,
            quantity: quantity
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          showSuccess(amount, currentStock.symbol, quantity);
          await loadUserData();
        } else {
          alert(data.message || 'Investment failed. Please try again.');
        }
      } catch (error) {
        console.error('Error confirming investment:', error);
        alert('Connection error. Please try again.');
      }
    }

    function showSuccess(amount, symbol, quantity){
      const area = document.getElementById('wizard-area');
      area.innerHTML = `
        <div class="card">
          <h2>Success! 🎉</h2>
          <p style="font-size:18px">You invested <strong>$${amount}</strong> into <strong>${symbol}</strong> (${quantity} shares).</p>
          <p style="color:var(--muted)">Your investment is protected & trackable.</p>
          <div style="margin-top:12px">
            <button class="btn btn-primary" id="done">Done</button>
          </div>
        </div>
      `;
      document.getElementById('done').addEventListener('click', ()=>{
        window.scrollTo({top:0,behavior:'smooth'});
        area.classList.add('hidden');
        area.innerHTML = `
          <div id="step-1" class="step active" data-step="1">
            <h2>Step 1 — Choose Amount</h2>
            <p>Tap a quick amount or type your own.</p>
            <div class="amount-input">
              <input id="amount" class="numeric" type="number" min="1" value="100" aria-label="Amount to invest" />
              <div class="chips" role="list">
                <button class="chip" data-value="100">100</button>
                <button class="chip" data-value="300">300</button>
                <button class="chip" data-value="500">500</button>
                <button class="chip" data-value="1000">1000</button>
              </div>
            </div>
          </div>
          <div id="step-2" class="step" data-step="2">
            <h2>Step 2 — Choose Stock</h2>
            <p>Pick a stock with a single tap.</p>
            <div id="stock-list-wizard"></div>
          </div>
          <div id="step-3" class="step" data-step="3">
            <h2>Step 3 — Confirm</h2>
            <div class="card" style="padding:14px">
              <div style="font-size:18px;font-weight:700">Summary</div>
              <p id="confirm-summary">You are investing <strong>$100</strong> into <strong>Stock</strong>.</p>
            </div>
            <p style="margin-top:12px">If everything looks correct, press Confirm. You can cancel anytime.</p>
          </div>
          <div class="fixed-action">
            <button class="btn btn-ghost" id="btn-back" style="display:none">Back</button>
            <button class="btn btn-primary" id="btn-next">Continue</button>
          </div>
        `;
        // Re-initialize wizard
        initWizard();
      });
    }

    function logout() {
      localStorage.removeItem('authToken');
      authToken = null;
      window.location.href = "{{ url('/') }}";
    }

    function announce(text){
      const live = document.querySelector('#senior-ui');
      if(live) live.setAttribute('aria-busy','true');
      setTimeout(()=>{if(live) live.setAttribute('aria-busy','false')},400);
      console.log('[announce]',text);
    }

    function initWizard() {
      // Re-attach event listeners after wizard reset
      document.querySelectorAll('.chip').forEach(c=>c.addEventListener('click',(ev)=>{
        document.getElementById('amount').value = ev.target.dataset.value;
      }));
      
      document.getElementById('btn-next').addEventListener('click', async ()=>{
        if(curStep < totalSteps){
          curStep++;
          showStep(curStep);
          if(curStep === 2){
            await loadStocksForWizard();
          }
        } else {
          await confirmInvestment();
        }
      });
      
      document.getElementById('btn-back').addEventListener('click', ()=>{
        if(curStep>1){curStep--; showStep(curStep)}
      });
    }

    // keyboard focus affordances for accessibility
    document.addEventListener('keydown', (e)=>{
      if(e.key==='Enter'){ 
        const el = document.activeElement; 
        if(el && el.click && el.tagName !== 'INPUT') el.click(); 
      }
    });

    // Initialize on load
    window.onload = async () => {
      if(!authToken){
        window.location.href = "{{ url('/') }}";
        return;
      }
      await loadUserData();
    };
  </script>
</body>
</html>
