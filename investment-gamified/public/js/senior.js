// Initialize API
const api = new InvestmentApi(document.querySelector('meta[name="app-url"]').content + '/api');
let currentStock = null;
// In-memory cache of the last stocks fetch. The wizard rows reference this
// array by index (data-idx) instead of ever writing untrusted stock JSON
// into a DOM attribute.
let stocksCache = [];
const root = document.documentElement;

// Toggle senior mode: enlarge text and touch targets
const seniorToggle = document.getElementById('senior-toggle');
seniorToggle.addEventListener('change', (e) => {
    if (e.target.checked) {
        root.style.setProperty('--base-font', '20px');
        root.style.setProperty('--min-touch', '56px');
        announce('Senior mode enabled');
    } else {
        root.style.setProperty('--base-font', '18px');
        root.style.setProperty('--min-touch', '48px');
        announce('Senior mode disabled');
    }
});

// Wizard controls
let curStep = 1;
const totalSteps = 3;
const showStep = (n) => {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    const el = document.querySelector('[data-step="' + n + '"]');
    if (el) el.classList.add('active');
    // back button visibility
    document.getElementById('btn-back').style.display = (n > 1) ? 'inline-flex' : 'none';
    // change button text
    document.getElementById('btn-next').textContent = (n === totalSteps) ? 'Confirm Investment' : 'Continue';
};

document.getElementById('btn-next').addEventListener('click', async () => {
    if (curStep < totalSteps) {
        curStep++;
        showStep(curStep);

        // Load stocks when entering step 2
        if (curStep === 2) {
            await loadStocksForWizard();
        }
    } else {
        // confirm
        await confirmInvestment();
    }
});

document.getElementById('btn-back').addEventListener('click', () => {
    if (curStep > 1) { curStep--; showStep(curStep) }
});

// chips quick set
document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (ev) => {
    document.getElementById('amount').value = ev.target.dataset.value;
}));

// main tiles actions
document.querySelectorAll('.tile').forEach(t => t.addEventListener('click', () => {
    const act = t.dataset.action;
    if (act === 'put-in') {
        // open wizard at step 1
        document.getElementById('wizard-area').classList.remove('hidden');
        curStep = 1;
        showStep(curStep);
        t.setAttribute('aria-pressed', 'true');
        window.scrollTo({ top: document.getElementById('wizard-area').offsetTop - 20, behavior: 'smooth' });
    } else if (act === 'take-out') {
        // simple withdraw confirmation flow (placeholder)
        if (confirm('Are you sure you want to withdraw money? This feature is coming soon.')) {
            alert('Withdrawal requested. We will process it shortly.');
        }
    }
}));

// bottom nav
document.querySelectorAll('.nav-item').forEach(n => n.addEventListener('click', () => {
    if (n.id === 'logout-btn') {
        logout();
        return;
    }

    document.querySelectorAll('.nav-item').forEach(x => x.classList.remove('active'));
    n.classList.add('active');
    announce(n.textContent + ' tab');

    if (n.dataset.tab === 'help') {
        document.querySelector('[aria-label="Help"]').scrollIntoView({ behavior: 'smooth' });
    } else if (n.dataset.tab === 'activities') {
        document.querySelector('[aria-label="Activities"]').scrollIntoView({ behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}));

// help buttons
document.getElementById('call-support').addEventListener('click', () => {
    alert('Support: +1-800-INVEST\n\nThis is a demo. In production, this would initiate a phone call.');
});

document.getElementById('chat-support').addEventListener('click', () => {
    alert('Opening chat support...\n\nThis is a demo. In production, this would open a live chat window.');
});

// Load user data
async function loadUserData() {
    if (!api.token) {
        window.location.href = document.querySelector('meta[name="app-url"]').content;
        return;
    }

    const data = await api.getSummary();

    if (data.success) {
        document.getElementById('userBalance').textContent = '$' + parseFloat(data.data.balance).toFixed(2);
    } else {
        // console.error('Failed to load user data:', data.message);
    }
}

// Build a single wizard stock row as real DOM nodes. Every dynamic field
// (symbol, name, price, change_percentage) is set via textContent only, and
// the row is linked back to the in-memory stocksCache array by index
// (data-idx) instead of embedding JSON.stringify(stock) in an attribute,
// which used to let a single quote in any stock field break out of the
// attribute and inject markup/script.
function createStockRow(stock, idx) {
    const row = document.createElement('div');
    row.className = 'row';
    row.setAttribute('role', 'button');
    row.setAttribute('tabindex', '0');
    row.dataset.idx = String(idx);

    const metaWrap = document.createElement('div');
    metaWrap.style.flex = '1';

    const metaEl = document.createElement('div');
    metaEl.className = 'meta';
    metaEl.textContent = `${stock.symbol} - ${stock.name}`;

    const subEl = document.createElement('div');
    subEl.className = 'sub';
    subEl.textContent = `$${stock.current_price} per share`;

    metaWrap.appendChild(metaEl);
    metaWrap.appendChild(subEl);

    const isPositive = stock.change_percentage >= 0;
    const changeEl = document.createElement('div');
    changeEl.style.fontWeight = '700';
    changeEl.style.color = isPositive ? 'green' : 'red';
    changeEl.textContent = `${isPositive ? '+' : ''}${stock.change_percentage}%`;

    row.appendChild(metaWrap);
    row.appendChild(changeEl);

    row.addEventListener('click', () => {
        const i = parseInt(row.dataset.idx, 10);
        currentStock = stocksCache[i];
        // visually indicate selection
        document.querySelectorAll('#stock-list-wizard .row').forEach(x => x.style.outline = 'none');
        row.style.outline = '3px solid rgba(11,109,58,0.12)';
        announce(currentStock.symbol + ' selected');
    });

    return row;
}

// Load stocks for wizard
async function loadStocksForWizard() {
    const data = await api.getStocks();

    if (!data.success) {
        // console.error('Failed to load stocks:', data.message);
        return;
    }

    stocksCache = data.data;

    const stockList = document.getElementById('stock-list-wizard');
    stockList.textContent = '';

    stocksCache.forEach((stock, idx) => {
        stockList.appendChild(createStockRow(stock, idx));
    });
}

// Render the confirm-step summary using DOM nodes so the stock symbol
// (untrusted, third-party data) can never be interpreted as markup.
function renderConfirmSummary(amount, stock, quantity) {
    const container = document.getElementById('confirm-summary');
    container.textContent = '';

    container.appendChild(document.createTextNode('You are investing '));

    const amountEl = document.createElement('strong');
    amountEl.textContent = `$${amount}`;
    container.appendChild(amountEl);

    container.appendChild(document.createTextNode(' into '));

    const symbolEl = document.createElement('strong');
    symbolEl.textContent = stock.symbol;
    container.appendChild(symbolEl);

    container.appendChild(document.createTextNode(` (${quantity} shares at $${stock.current_price} each).`));
}

// Confirm investment
async function confirmInvestment() {
    if (!currentStock) {
        alert('Please select a stock first');
        curStep = 2;
        showStep(curStep);
        return;
    }

    const amount = parseInt(document.getElementById('amount').value);
    const quantity = Math.floor(amount / currentStock.current_price);

    if (quantity < 1) {
        alert('Amount too small. Please increase the amount.');
        return;
    }

    // Update summary
    renderConfirmSummary(amount, currentStock, quantity);

    const data = await api.buyStock(currentStock.symbol, quantity);

    if (data.success) {
        showSuccess(amount, currentStock.symbol, quantity);
        await loadUserData();
    } else {
        alert(data.message || 'Investment failed. Please try again.');
    }
}

// Rebuild the success screen using DOM nodes. `symbol` originates from the
// stocks API and is only ever assigned via textContent here.
function showSuccess(amount, symbol, quantity) {
    const area = document.getElementById('wizard-area');
    area.textContent = '';

    const card = document.createElement('div');
    card.className = 'card';

    const heading = document.createElement('h2');
    heading.textContent = 'Success! \u{1F389}';
    card.appendChild(heading);

    const summary = document.createElement('p');
    summary.style.fontSize = '18px';
    summary.appendChild(document.createTextNode('You invested '));

    const amountEl = document.createElement('strong');
    amountEl.textContent = `$${amount}`;
    summary.appendChild(amountEl);

    summary.appendChild(document.createTextNode(' into '));

    const symbolEl = document.createElement('strong');
    symbolEl.textContent = symbol;
    summary.appendChild(symbolEl);

    summary.appendChild(document.createTextNode(` (${quantity} shares).`));
    card.appendChild(summary);

    const protectedText = document.createElement('p');
    protectedText.style.color = 'var(--muted)';
    protectedText.textContent = 'Your investment is protected & trackable.';
    card.appendChild(protectedText);

    const actionsWrap = document.createElement('div');
    actionsWrap.style.marginTop = '12px';

    const doneBtn = document.createElement('button');
    doneBtn.className = 'btn btn-primary';
    doneBtn.id = 'done';
    doneBtn.textContent = 'Done';
    actionsWrap.appendChild(doneBtn);
    card.appendChild(actionsWrap);

    area.appendChild(card);

    doneBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        area.classList.add('hidden');
        resetWizardArea(area);
        initWizard();
    });
}

// Restore the original wizard markup after a successful investment. This
// block is 100% static markup with no interpolated/dynamic values (it
// mirrors the initial #wizard-area markup in senior.blade.php), so it is
// not an XSS surface even though it is assigned via innerHTML.
function resetWizardArea(area) {
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
}

function logout() {
    api.clearToken();
    window.location.href = document.querySelector('meta[name="app-url"]').content;
}

function announce(text) {
    const live = document.querySelector('#senior-ui');
    if (live) live.setAttribute('aria-busy', 'true');
    setTimeout(() => { if (live) live.setAttribute('aria-busy', 'false') }, 400);

}

function initWizard() {
    // Re-attach event listeners after wizard reset
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (ev) => {
        document.getElementById('amount').value = ev.target.dataset.value;
    }));

    document.getElementById('btn-next').addEventListener('click', async () => {
        if (curStep < totalSteps) {
            curStep++;
            showStep(curStep);
            if (curStep === 2) {
                await loadStocksForWizard();
            }
        } else {
            await confirmInvestment();
        }
    });

    document.getElementById('btn-back').addEventListener('click', () => {
        if (curStep > 1) { curStep--; showStep(curStep) }
    });
}

// keyboard focus affordances for accessibility
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const el = document.activeElement;
        if (el && el.click && el.tagName !== 'INPUT') el.click();
    }
});

// Initialize on load
window.onload = async () => {
    if (!api.token) {
        window.location.href = document.querySelector('meta[name="app-url"]').content;
        return;
    }
    await loadUserData();
};
