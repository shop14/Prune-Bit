(function () {
  var card = document.getElementById('changenowCard');
  if (!card) return;

  var TOKEN = localStorage.getItem('wallet_token');
  if (!TOKEN) return;

  var IMG_BASE = '../img/logos/';
  var state = {
    currencies: [],
    balances: {},
    walletCoins: [],
    from: null,
    to: null,
    minAmount: null,
    maxAmount: null,
    estimateTimer: null,
    statusTimer: null,
    currentExchangeId: null,
    currentStep: 1,
    estimate: null,
    busy: false,
    mediumFee: null,
    senderAddr: null,
    senderType: 'P2PKH',
    preparedFor: null
  };

  var COIN_LABELS = {
    BTC: 'Bitcoin', ETH: 'Ethereum', USDT: 'Tether USDT', LTC: 'Litecoin',
    DOGE: 'Dogecoin', BCH: 'Bitcoin Cash', DGB: 'DigiByte', RVN: 'Ravencoin',
    ZEC: 'Zcash', BSV: 'Bitcoin SV', XVG: 'Verge', QTUM: 'Qtum',
    ETC: 'Ethereum Classic', KASPA: 'Kaspa', XRP: 'Ripple',
    POLYGON: 'Polygon', BSC: 'BNB Chain'
  };

  function coinLogoFile(coin) {
    var map = { POLYGON: 'matic', BSC: 'bnb', KASPA: 'kaspa' };
    return (map[coin] || (coin || '').toLowerCase()) + '.png';
  }

  var STATUS_STYLES = {
    new: { label: 'New', cls: 'secondary' },
    waiting: { label: 'Waiting for deposit', cls: 'warning' },
    confirming: { label: 'Confirming', cls: 'info' },
    exchanging: { label: 'Exchanging', cls: 'primary' },
    sending: { label: 'Sending', cls: 'primary' },
    finished: { label: 'Finished', cls: 'success' },
    failed: { label: 'Failed', cls: 'danger' },
    refunded: { label: 'Refunded', cls: 'secondary' },
    verifying: { label: 'Verifying', cls: 'warning' },
    expired: { label: 'Expired', cls: 'secondary' }
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key) {
    return (typeof window.__ === 'function') ? window.__(key) : key;
  }

  function tpl(template, params) {
    var s = t(template);
    if (params) {
      Object.keys(params).forEach(function (k) {
        s = s.split('{' + k + '}').join(String(params[k]));
      });
    }
    return s;
  }

  function fmtAmount(n, maxDecimals) {
    var num = parseFloat(n);
    if (isNaN(num)) return '0';
    var dec = (maxDecimals == null ? 6 : maxDecimals);
    if (num >= 1000) dec = 2;
    else if (num >= 1) dec = Math.min(dec, 4);
    else if (num >= 0.0001) dec = Math.min(dec, 6);
    else dec = 8;
    return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: dec });
  }

  function statusBadge(status) {
    var st = STATUS_STYLES[status] || { label: status || 'unknown', cls: 'secondary' };
    return '<span class="badge rounded-pill text-bg-' + st.cls + '" style="font-size:.72rem">' + esc(t(st.label)) + '</span>';
  }

  function coinImg(coin, size) {
    return '<img src="' + IMG_BASE + coinLogoFile(coin) + '" alt="' + esc(coin) + '" style="width:' + (size || 18) + 'px;height:' + (size || 18) + 'px;object-fit:contain;border-radius:50%;">';
  }

  function coinOption(coin) {
    return '<option value="' + esc(coin) + '">' + esc(coin) + ' - ' + esc(COIN_LABELS[coin] || coin) + '</option>';
  }

  function byId(id) { return document.getElementById(id); }

  function showCard() { card.style.display = 'block'; }

  function setBody(html) {
    var body = byId('cnBody');
    if (body) {
      body.innerHTML = html;
      if (window.CryptoVaultI18n && typeof window.CryptoVaultI18n.translatePage === 'function') {
        window.CryptoVaultI18n.translatePage(body);
      }
    }
  }

  function renderSkeleton() {
    setBody('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>' + t('Loading exchange options...') + '</div>');
    showCard();
  }

  async function load() {
    renderSkeleton();
    try {
      var results = await Promise.all([
        fetch('/api/dashboard', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: TOKEN })
        }).then(function (r) { return r.json(); }),
        fetch('/api/changenow/currencies').then(function (r) { return r.json(); })
      ]);

      var dash = results[0];
      var cur = results[1];

      if (dash.error || !dash.success) {
        setBody('<div class="alert alert-warning mb-0">' + esc(dash.error || t('Could not load your wallet')) + '</div>');
        return;
      }
      if (!cur || !cur.success) {
        setBody('<div class="alert alert-warning mb-0">' + t('Exchange service is temporarily unavailable. Please try again later.') + '</div>');
        return;
      }

      state.currencies = cur.currencies || [];
      state.balances = dash.balances || {};

      var coinSet = {};
      (dash.addresses || []).forEach(function (a) { coinSet[a.coin] = true; });
      state.walletCoins = Object.keys(coinSet);

      buildForm();
    } catch (e) {
      setBody('<div class="alert alert-danger mb-0">' + t('Failed to load the exchange widget.') + '</div>');
    }
  }

  function getCurr(walletCoin) {
    for (var i = 0; i < state.currencies.length; i++) {
      if (state.currencies[i].walletCoin === walletCoin) return state.currencies[i];
    }
    return null;
  }

  function fromCoins() {
    return state.walletCoins.filter(function (c) {
      var cur = getCurr(c);
      return cur && cur.sell;
    });
  }

  function toCoins() {
    return state.currencies.filter(function (c) { return c.buy; }).map(function (c) { return c.walletCoin; });
  }

  function queryParam(name) {
    try {
      if (typeof location === 'undefined' || !location.search) return null;
      var sp = new URLSearchParams(location.search);
      var v = sp.get(name);
      return v ? v.trim().toUpperCase() : null;
    } catch (e) {
      return null;
    }
  }

  function buildForm() {
    var fromList = fromCoins().slice().sort(function (a, b) {
      return (parseFloat(state.balances[b] || 0) || 0) - (parseFloat(state.balances[a] || 0) || 0);
    });
    var toList = toCoins();

    if (fromList.length === 0) {
      setBody('<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>' + t('Add a coin with a balance to your wallet to start exchanging.') + '</div>');
      return;
    }

    var from = fromList[0];
    var to = toList[0] === from ? toList[1] : toList[0];
    var fromParam = queryParam('from');
    if (fromParam && fromList.indexOf(fromParam) !== -1) from = fromParam;
    var toParam = queryParam('to');
    if (toParam && toList.indexOf(toParam) !== -1) to = toParam;
    if (to === from) to = toList[0] === from ? toList[1] : toList[0];
    state.from = from;
    state.to = to;

    var fromOpts = fromList.map(coinOption).join('');
    var toOpts = toList.map(coinOption).join('');

    setBody(
        '<div class="mb-4">' +
          '<div class="d-flex align-items-center gap-2">' +
            '<div class="cn-step-dot" id="cnDot1" data-step="1">1</div>' +
            '<div class="flex-grow-1" id="cnLine1" style="height:2px;border-top:2px solid var(--bs-border-color)"></div>' +
            '<div class="cn-step-dot" id="cnDot2" data-step="2">2</div>' +
            '<div class="flex-grow-1" id="cnLine2" style="height:2px;border-top:2px solid var(--bs-border-color)"></div>' +
            '<div class="cn-step-dot" id="cnDot3" data-step="3">3</div>' +
            '<div class="flex-grow-1" id="cnLine3" style="height:2px;border-top:2px solid var(--bs-border-color)"></div>' +
            '<div class="cn-step-dot" id="cnDot4" data-step="4">4</div>' +
          '</div>' +
          '<div class="d-flex justify-content-between small text-secondary mt-2">' +
            '<span id="cnLabel1" data-i18n="Send">Send</span>' +
            '<span id="cnLabel2" data-i18n="Receive">Receive</span>' +
            '<span id="cnLabel3" data-i18n="Review">Review</span>' +
            '<span id="cnLabel4" data-i18n="Confirm">Confirm</span>' +
          '</div>' +
        '</div>' +

        '<div class="cn-step" id="cnStep1">' +
          '<div class="d-flex align-items-center justify-content-between mb-2">' +
            '<label class="form-label small fw-semibold text-secondary mb-0" data-i18n="Step 1 - Choose coin to exchange"><i class="bi bi-send me-1"></i>Step 1 - Choose coin to exchange</label>' +
            '<button type="button" id="cnSwapBtn" class="btn btn-outline-secondary rounded-circle" style="width:34px;height:34px;padding:0;border-width:1.5px;flex-shrink:0" title="Swap coins" aria-label="Swap coins" data-i18n-title="Swap coins" data-i18n-aria-label="Swap coins"><i class="bi bi-arrow-left-right"></i></button>' +
          '</div>' +
          '<div class="input-group input-group-lg">' +
            '<span class="input-group-text" style="padding:.5rem .6rem"><img src="' + IMG_BASE + coinLogoFile(from) + '" alt="" id="cnFromLogo" style="width:20px;height:20px;object-fit:contain"></span>' +
            '<select class="form-select" id="cnFrom" aria-label="From coin" data-i18n-aria-label="From coin">' + fromOpts + '</select>' +
            '<input type="text" class="form-control" id="cnFromAmount" inputmode="decimal" placeholder="0.00" style="font-weight:700;min-width:130px" aria-label="Amount to send" data-i18n-aria-label="Amount to send">' +
          '</div>' +
          '<div class="small mt-1 d-flex align-items-center justify-content-between gap-2 text-secondary">' +
            '<span id="cnFromBal"></span>' +
            '<button type="button" id="cnMaxBtn" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size:.75rem;font-weight:700" data-i18n="Max">Max</button>' +
          '</div>' +
          '<div class="small mt-2 d-flex align-items-center gap-2" style="color:var(--bs-secondary-color)"><i class="bi bi-sliders2-vertical"></i><span id="cnRangeInfo"></span></div>' +
          '<div class="d-none alert alert-danger mt-2 py-2" id="cnStepError1"></div>' +
          '<button type="button" class="btn btn-primary w-100 mt-3" id="cnNext1" style="border-radius:12px;font-weight:600" data-i18n="Continue"><i class="bi bi-arrow-right me-1"></i>Continue</button>' +
        '</div>' +

        '<div class="cn-step d-none" id="cnStep2">' +
          '<label class="form-label small fw-semibold text-secondary mb-2" data-i18n="Step 2 - Choose coin to receive"><i class="bi bi-arrow-down-left-circle me-1"></i>Step 2 - Choose coin to receive</label>' +
          '<div class="input-group input-group-lg">' +
            '<span class="input-group-text" style="padding:.5rem .6rem"><img src="' + IMG_BASE + coinLogoFile(to) + '" alt="" id="cnToLogo" style="width:20px;height:20px;object-fit:contain"></span>' +
            '<select class="form-select" id="cnTo" aria-label="To coin" data-i18n-aria-label="To coin">' + toOpts + '</select>' +
          '</div>' +
          '<div class="mt-3">' +
            '<label class="form-label small fw-semibold text-secondary mb-1" data-i18n="You receive (estimated)">You receive (estimated)</label>' +
            '<input type="text" class="form-control form-control-lg" id="cnToAmount" readonly placeholder="-" style="font-weight:700;background:var(--bs-tertiary-bg);font-size:1.1rem" aria-label="Amount to receive" data-i18n-aria-label="Amount to receive">' +
          '</div>' +
          '<div class="small mt-2"><span id="cnRate" class="d-inline-block"></span></div>' +
          '<div class="d-flex gap-2 mt-3">' +
            '<button type="button" class="btn btn-outline-secondary flex-fill" id="cnBack2" style="border-radius:12px;font-weight:600" data-i18n="Back">Back</button>' +
            '<button type="button" class="btn btn-primary flex-fill" id="cnNext2" style="border-radius:12px;font-weight:600" data-i18n="Continue"><i class="bi bi-arrow-right me-1"></i>Continue</button>' +
          '</div>' +
        '</div>' +

        '<div class="cn-step d-none" id="cnStep3">' +
          '<label class="form-label small fw-semibold text-secondary mb-2" data-i18n="Step 3 - Review details &amp; costs"><i class="bi bi-receipt me-1"></i>Step 3 - Review details &amp; costs</label>' +
          '<div class="border rounded-3 p-3 mb-3" id="cnDetails" style="background:var(--bs-tertiary-bg)"></div>' +
          '<div class="mb-3">' +
            '<label class="form-label small fw-semibold text-secondary" id="cnAddressLabel" for="cnAddress">' + tpl('Receive {coin} to this address', { coin: esc(to) }) + '</label>' +
            '<div class="input-group">' +
              '<span class="input-group-text"><i class="bi bi-qr-code"></i></span>' +
              '<input type="text" class="form-control" id="cnAddress" placeholder="' + tpl('Enter {coin} payout address', { coin: esc(to) }) + '">' +
              '<button type="button" class="btn btn-outline-secondary" id="cnPasteBtn" title="Paste address" aria-label="Paste address" data-i18n-title="Paste address" data-i18n-aria-label="Paste address"><i class="bi bi-clipboard"></i></button>' +
            '</div>' +
            '<div class="form-text" data-i18n="Auto-filled with your wallet address. You can change it to any valid address."><i class="bi bi-info-circle me-1"></i>Auto-filled with your wallet address. You can change it to any valid address.</div>' +
          '</div>' +
          '<div class="mb-3 d-none" id="cnExtraWrap">' +
            '<label class="form-label small fw-semibold text-secondary" id="cnExtraLabel" data-i18n="Destination tag (memo)">Destination tag (memo)</label>' +
            '<input type="text" class="form-control" id="cnExtraId" placeholder="Required for XRP" data-i18n-placeholder="Required for XRP">' +
            '<div class="form-text" data-i18n="The destination tag is required to receive XRP.">The destination tag is required to receive XRP.</div>' +
          '</div>' +
          '<div class="d-none alert alert-danger mt-2 py-2" id="cnStepError3"></div>' +
          '<div class="d-flex gap-2 mt-2">' +
            '<button type="button" class="btn btn-outline-secondary flex-fill" id="cnBack3" style="border-radius:12px;font-weight:600" data-i18n="Back">Back</button>' +
            '<button type="button" class="btn btn-primary flex-fill" id="cnNext3" style="border-radius:12px;font-weight:600" data-i18n="Continue"><i class="bi bi-arrow-right me-1"></i>Continue</button>' +
          '</div>' +
        '</div>' +

        '<div class="cn-step d-none" id="cnStep4">' +
          '<label class="form-label small fw-semibold text-secondary mb-2" data-i18n="Step 4 - Confirm exchange"><i class="bi bi-check2-square me-1"></i>Step 4 - Confirm exchange</label>' +
          '<div class="border rounded-3 p-3 mb-3" id="cnConfirm" style="background:var(--bs-tertiary-bg)"></div>' +
          '<button type="button" class="btn btn-primary w-100 py-2" id="cnCreateBtn" style="border-radius:12px;font-weight:600;font-size:1rem" data-i18n="Create Exchange"><i class="bi bi-lightning-charge-fill me-2"></i>Create Exchange</button>' +
          '<div class="form-text mt-2 text-center" id="cnCreateHint" data-i18n="On confirm, the exact amount is sent automatically from your balance with medium network fees."><i class="bi bi-info-circle me-1"></i>On confirm, the exact amount is sent automatically from your balance with medium network fees.</div>' +
          '<div class="d-none border rounded-3 p-3 mt-3" id="cnPinPanel">' +
            '<div class="small fw-semibold text-secondary mb-1" data-i18n="Enter your PIN to sign and send automatically"><i class="bi bi-shield-lock me-1"></i>Enter your PIN to sign and send automatically</div>' +
            '<div id="cnPinContainer"></div>' +
            '<div class="d-none alert alert-danger mt-2 py-2" id="cnPinError"></div>' +
            '<button type="button" class="btn btn-primary w-100 mt-2" id="cnConfirmSendBtn" style="border-radius:12px;font-weight:600" data-i18n="Confirm & Exchange"><i class="bi bi-lightning-charge-fill me-2"></i>Confirm &amp; Exchange</button>' +
            '<button type="button" class="btn btn-outline-secondary w-100 mt-2" id="cnPinCancel" style="border-radius:12px;font-weight:600" data-i18n="Cancel">Cancel</button>' +
          '</div>' +
          '<button type="button" class="btn btn-outline-secondary w-100 mt-2" id="cnBack4" style="border-radius:12px;font-weight:600" data-i18n="Back">Back</button>' +
        '</div>' +

        '<div class="d-none mt-4" id="cnResult"></div>' +

        '<div class="mt-4 pt-3" style="border-top:1px solid var(--bs-border-color)">' +
          '<h3 class="fs-6 fw-bold mb-2" data-i18n="Recent Exchanges"><i class="bi bi-clock-history me-1"></i> Recent Exchanges</h3>' +
          '<div id="cnRecent" class="text-center text-muted small py-3"><div class="spinner-border spinner-border-sm"></div></div>' +
        '</div>' +
      '</div>');

    var fromSel = byId('cnFrom');
    var toSel = byId('cnTo');
    fromSel.value = from;
    toSel.value = to;

    fromSel.addEventListener('change', function () {
      state.from = fromSel.value;
      if (state.to === state.from) {
        var idx = toList.indexOf(state.to);
        state.to = toList[idx + 1] || toList[0] || '';
        toSel.value = state.to;
        buildAddressFields();
      }
      clearEstimate();
      updateFromInfo();
      fetchRange();
      maybeEstimate();
      invalidateAutoSend();
    });

    toSel.addEventListener('change', function () {
      state.to = toSel.value;
      buildAddressFields();
      clearEstimate();
      fetchRange();
      maybeEstimate();
    });

    byId('cnFromAmount').addEventListener('input', function () { maybeEstimate(); });

    var swapBtn = byId('cnSwapBtn');
    if (swapBtn) {
      swapBtn.addEventListener('click', function () {
        if (fromList.indexOf(state.to) === -1) return;
        var f = state.to;
        var t = state.from;
        state.from = f;
        state.to = t;
        fromSel.value = f;
        toSel.value = t;
        buildAddressFields();
        clearEstimate();
        updateFromInfo();
        fetchRange();
        maybeEstimate();
        invalidateAutoSend();
      });
    }

    var maxBtn = byId('cnMaxBtn');
    if (maxBtn) {
      maxBtn.addEventListener('click', function () {
        var bal = parseFloat(state.balances[state.from] || 0) || 0;
        byId('cnFromAmount').value = bal > 0 ? String(bal) : '';
        maybeEstimate();
      });
    }

    var pasteBtn = byId('cnPasteBtn');
    if (pasteBtn) {
      pasteBtn.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.readText) {
          navigator.clipboard.readText().then(function (text) {
            if (text) byId('cnAddress').value = text.trim();
          }).catch(function () {});
        }
      });
    }

    byId('cnNext1').addEventListener('click', function () {
      var amount = parseFloat(byId('cnFromAmount').value);
      if (!amount || amount <= 0) {
        showStepError('cnStepError1', t('Enter an amount to exchange.'));
        return;
      }
      hideStepError('cnStepError1');
      stepGo(2);
    });

    byId('cnBack2').addEventListener('click', function () { stepGo(1); });

    byId('cnNext2').addEventListener('click', function () {
      fetchRange();
      fetchEstimate();
      stepGo(3);
    });

    byId('cnBack3').addEventListener('click', function () { stepGo(2); });

    byId('cnNext3').addEventListener('click', function () {
      var errors = [];
      var address = byId('cnAddress').value.trim();
      var extraId = byId('cnExtraId') ? byId('cnExtraId').value.trim() : '';
      if (address.length < 10) errors.push(t('Enter a valid payout address.'));
      if (state.to === 'XRP' && !extraId) errors.push(t('XRP requires a destination tag (memo).'));
      if (errors.length) {
        showStepError('cnStepError3', errors.join(' '));
        return;
      }
      hideStepError('cnStepError3');
      stepGo(4);
    });

    byId('cnBack4').addEventListener('click', function () { stepGo(3); });

    byId('cnCreateBtn').addEventListener('click', createExchange);

    byId('cnConfirmSendBtn').addEventListener('click', function () {
      if (state.busy) return;
      var pin = '';
      if (typeof PinPad !== 'undefined' && PinPad.getValue) {
        pin = String(PinPad.getValue('cnExchangePin') || '');
      }
      var hiddenPin = byId('cnExchangePin');
      if (!pin && hiddenPin) pin = String(hiddenPin.value || '');
      if (pin.length < 4) {
        showStepError('cnPinError', t('Enter your 4-digit PIN to sign the deposit.'));
        return;
      }
      hideStepError('cnPinError');
      confirmAndCreate(pin);
    });

    byId('cnPinCancel').addEventListener('click', function () {
      hideStepError('cnPinError');
      hidePinPanel();
    });

    updateFromInfo();
    buildAddressFields();
    fetchRange();
    maybeEstimate();
    stepGo(1);
    loadRecent();
  }

  function stepGo(n) {
    state.currentStep = n;
    for (var i = 1; i <= 4; i++) {
      var s = byId('cnStep' + i);
      if (s) s.classList.toggle('d-none', i !== n);
    }
    updateSteps(n);
    if (n !== 4) hidePinPanel();
    if (n === 3) renderDetails();
    if (n === 4) { renderConfirm(); prepareAutoSend(); }
  }

  function updateSteps(n) {
    for (var i = 1; i <= 4; i++) {
      var dot = byId('cnDot' + i);
      if (!dot) continue;
      var cls = i < n ? 'btn-primary' : (i === n ? 'btn-outline-primary' : 'btn-outline-secondary');
      dot.className = 'cn-step-dot btn rounded-circle ' + cls;
      dot.style.width = '30px';
      dot.style.height = '30px';
      dot.style.fontSize = '.8rem';
      dot.style.fontWeight = '700';
      dot.style.display = 'flex';
      dot.style.alignItems = 'center';
      dot.style.justifyContent = 'center';
      dot.style.cursor = 'pointer';
      var lbl = byId('cnLabel' + i);
      if (lbl) {
        lbl.style.fontWeight = i === n ? '700' : '400';
        lbl.style.color = i <= n ? 'var(--bs-body-color)' : 'var(--bs-secondary-color)';
      }
    }
    for (var j = 1; j <= 3; j++) {
      var line = byId('cnLine' + j);
      if (line) line.style.borderTopColor = j < n ? 'var(--bs-primary)' : 'var(--bs-border-color)';
    }
  }

  function showStepError(id, msg) {
    var el = byId(id);
    if (!el) return;
    el.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + esc(msg);
    el.classList.remove('d-none');
  }

  function hideStepError(id) {
    var el = byId(id);
    if (el) el.classList.add('d-none');
  }

  function detailRow(label, valueHtml) {
    return '<div style="display:flex;justify-content:space-between;gap:.5rem;padding:.3rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + esc(label) + '</span><span class="text-end fw-semibold small">' + valueHtml + '</span></div>';
  }

  function estimateFeeText() {
    var est = state.estimate;
    if (!est) return '';
    var fees = [];
    if (parseFloat(est.depositFee) > 0) fees.push(tpl('deposit ~{amount} {coin}', { amount: fmtAmount(est.depositFee, 8), coin: state.from }));
    if (parseFloat(est.withdrawalFee) > 0) fees.push(tpl('withdrawal ~{amount} {coin}', { amount: fmtAmount(est.withdrawalFee, 8), coin: state.to }));
    return fees.join(', ');
  }

  function renderDetails() {
    var box = byId('cnDetails');
    if (!box) return;
    var amount = parseFloat(byId('cnFromAmount').value) || 0;
    var est = state.estimate;
    var toAmt = est ? fmtAmount(est.toAmount) : '...';
    var rate = (est && amount) ? fmtAmount(parseFloat(est.toAmount) / amount) : '...';
    var rows = '';
    rows += detailRow(t('You send'), coinImg(state.from, 16) + ' ' + esc(fmtAmount(amount, 8)) + ' ' + esc(state.from));
    rows += detailRow(t('You receive'), coinImg(state.to, 16) + ' ' + esc(toAmt) + ' ' + esc(state.to));
    rows += detailRow(t('Estimated rate'), '1 ' + esc(state.from) + ' ≈ ' + esc(rate) + ' ' + esc(state.to));
    rows += detailRow(t('Network fees'), est ? (estimateFeeText() || t('None')) : t('Computing...'));
    var rangeParts = [];
    if (state.minAmount != null) rangeParts.push(tpl('Min {amount} {coin}', { amount: fmtAmount(state.minAmount, 8), coin: state.from }));
    if (state.maxAmount != null) rangeParts.push(tpl('Max {amount} {coin}', { amount: fmtAmount(state.maxAmount, 8), coin: state.from }));
    if (rangeParts.length) rows += detailRow(t('Limit'), esc(rangeParts.join(' / ')));
    box.innerHTML = rows;
  }

  function renderConfirm() {
    var box = byId('cnConfirm');
    if (!box) return;
    var amount = parseFloat(byId('cnFromAmount').value) || 0;
    var est = state.estimate;
    var toAmt = est ? fmtAmount(est.toAmount) : '...';
    var rows = '';
    rows += detailRow(t('Exchange'), coinImg(state.from, 16) + ' ' + esc(fmtAmount(amount, 8)) + ' ' + esc(state.from) + ' <i class="bi bi-arrow-right"></i> ' + coinImg(state.to, 16) + ' ' + esc(toAmt) + ' ' + esc(state.to));
    rows += detailRow(t('Network fees'), est ? (estimateFeeText() || t('None')) : t('Computing...'));
    if (state.mediumFee != null) rows += detailRow(t('Network fee (medium)'), esc(fmtAmount(state.mediumFee, 8)) + ' ' + esc(state.from));
    if (state.senderAddr) rows += detailRow(t('Sending from'), '<span style="word-break:break-all">' + esc(shortAddr(state.senderAddr)) + '</span>');
    rows += detailRow(t('Payout address'), '<span style="word-break:break-all">' + esc(byId('cnAddress').value || '-') + '</span>');
    box.innerHTML = rows;
  }

  function invalidateAutoSend() {
    state.preparedFor = null;
    state.mediumFee = null;
    state.senderAddr = null;
    state.senderType = 'P2PKH';
  }

  function shortAddr(a) {
    a = String(a || '');
    return a.length > 22 ? a.slice(0, 11) + '...' + a.slice(-9) : a;
  }

  function prepareAutoSend() {
    if (!state.walletCoins || state.walletCoins.indexOf(state.from) === -1) return;
    if (state.preparedFor === state.from && state.mediumFee != null) { renderConfirm(); return; }
    state.preparedFor = state.from;
    var bal = parseFloat(state.balances[state.from] || 0) || 0;

    fetch('/api/fee_estimation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ coin: state.from })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.success && data.fees && data.fees.medium && parseFloat(data.fees.medium.fee) > 0) {
        state.mediumFee = parseFloat(data.fees.medium.fee);
        if (state.mediumFee >= bal) state.mediumFee = null;
      }
      renderConfirm();
    }).catch(function () { renderConfirm(); });

    fetch('/api/addresses_with_balance', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN })
    }).then(function (r) { return r.json(); }).then(function (data) {
      state.senderAddr = null;
      state.senderType = 'P2PKH';
      if (data.success && data.addresses) {
        var cands = data.addresses.filter(function (a) {
          return a.coin === state.from && parseFloat(a.balance || 0) > 0;
        });
        if (cands.length) {
          var pref = null;
          for (var i = 0; i < cands.length; i++) {
            if (cands[i].type === 'P2WPKH' || cands[i].type === 'BECH32') { pref = cands[i]; break; }
          }
          var chosen = pref || cands[0];
          state.senderAddr = chosen.address;
          state.senderType = chosen.type === 'BECH32' ? 'P2WPKH' : (chosen.type || 'P2PKH');
        }
      }
      renderConfirm();
    }).catch(function () { renderConfirm(); });
  }

  function revealPinPanel() {
    var panel = byId('cnPinPanel');
    if (!panel) return;
    panel.classList.remove('d-none');
    var createBtn = byId('cnCreateBtn');
    if (createBtn) { createBtn.disabled = true; createBtn.classList.add('d-none'); }
    var hint = byId('cnCreateHint');
    if (hint) hint.classList.add('d-none');
    if (typeof PinPad !== 'undefined' && PinPad.create && !PinPad.instances['cnExchangePin']) {
      PinPad.create({ id: 'cnExchangePin', container: 'cnPinContainer', maxLength: 4 });
    }
    if (typeof PinPad !== 'undefined' && PinPad.clear) {
      try { PinPad.clear('cnExchangePin'); } catch (e) {}
    }
    var pinInput = byId('cnExchangePin');
    if (pinInput) pinInput.value = '';
  }

  function hidePinPanel() {
    var panel = byId('cnPinPanel');
    if (panel) panel.classList.add('d-none');
    var createBtn = byId('cnCreateBtn');
    if (createBtn) { createBtn.disabled = false; createBtn.classList.remove('d-none'); }
    var hint = byId('cnCreateHint');
    if (hint) hint.classList.remove('d-none');
  }

  function renderSummaryPanels() {
    if (state.currentStep === 3) renderDetails();
    if (state.currentStep === 4) renderConfirm();
  }

  function setLogo(id, coin) {
    var el = byId(id);
    if (el) el.src = IMG_BASE + coinLogoFile(coin);
  }

  function updateFromInfo() {
    var bal = parseFloat(state.balances[state.from] || 0) || 0;
    byId('cnFromBal').innerHTML = tpl('Available: {value}', { value: '<strong>' + fmtAmount(bal) + ' ' + esc(state.from) + '</strong>' });
    setLogo('cnFromLogo', state.from);
  }

  function buildAddressFields() {
    var wrap = byId('cnExtraWrap');
    if (state.to === 'XRP') {
      wrap.classList.remove('d-none');
    } else {
      wrap.classList.add('d-none');
      byId('cnExtraId').value = '';
    }

    var addr = byId('cnAddress');
    addr.value = '';
    addr.placeholder = tpl('Enter {coin} payout address', { coin: esc(state.to) });

    var lbl = byId('cnAddressLabel');
    if (lbl) lbl.innerHTML = '<i class="bi bi-qr-code me-1"></i>' + tpl('Receive {coin} to this address', { coin: '<span class="text-primary fw-bold">' + esc(state.to) + '</span>' });
    setLogo('cnToLogo', state.to);

    fetch('/api/get_addresses', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN, coin: state.to })
    }).then(function (r) { return r.json(); }).then(function (data) {
      var list = data.addresses && data.addresses[state.to];
      if (list && list.length) {
        var preferred = list[0];
        for (var i = 0; i < list.length; i++) {
          if (list[i].type === 'P2WPKH' || list[i].type === 'BECH32') { preferred = list[i]; break; }
        }
        addr.value = preferred.address;
      }
    }).catch(function () {});
  }

  function clearEstimate() {
    if (state.estimateTimer) { clearTimeout(state.estimateTimer); state.estimateTimer = null; }
    state.estimate = null;
    byId('cnToAmount').value = '';
    byId('cnRate').textContent = '';
    renderSummaryPanels();
  }

  function maybeEstimate() {
    var amount = parseFloat(byId('cnFromAmount').value);
    if (!amount || amount <= 0) {
      clearEstimate();
      return;
    }
    if (state.estimateTimer) clearTimeout(state.estimateTimer);
    state.estimateTimer = setTimeout(fetchEstimate, 400);
  }

  function fetchEstimate() {
    var amount = parseFloat(byId('cnFromAmount').value);
    if (!amount || amount <= 0) return;
    byId('cnToAmount').value = '...';

    fetch('/api/changenow/estimate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN, from: state.from, to: state.to, amount: amount })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.success && data.estimate) {
        state.estimate = data.estimate;
        byId('cnToAmount').value = fmtAmount(data.estimate.toAmount);
        var feeText = estimateFeeText();
        byId('cnRate').textContent = '1 ' + state.from + ' ≈ ' + fmtAmount(amount ? parseFloat(data.estimate.toAmount) / amount : 0) + ' ' + state.to + (feeText ? '  |  ' + tpl('Network fees: {fees}', { fees: feeText }) : '');
        byId('cnRate').style.color = '';
        renderSummaryPanels();
      } else {
        state.estimate = null;
        byId('cnToAmount').value = '';
        byId('cnRate').textContent = data.message || t('Estimate unavailable');
        byId('cnRate').style.color = data.error === 'deposit_too_small' ? 'var(--bs-warning)' : 'var(--bs-danger)';
        renderSummaryPanels();
      }
    }).catch(function () {
      byId('cnToAmount').value = '';
      renderSummaryPanels();
    });
  }

  function fetchRange() {
    byId('cnRangeInfo').textContent = t('Loading limits...');
    fetch('/api/changenow/range?token=' + encodeURIComponent(TOKEN) + '&from=' + encodeURIComponent(state.from) + '&to=' + encodeURIComponent(state.to))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          state.minAmount = data.minAmount;
          state.maxAmount = data.maxAmount;
          var parts = [];
          if (data.minAmount != null) parts.push(tpl('Min: {amount} {coin}', { amount: fmtAmount(data.minAmount, 8), coin: state.from }));
          if (data.maxAmount != null) parts.push(tpl('Max: {amount} {coin}', { amount: fmtAmount(data.maxAmount, 8), coin: state.from }));
          byId('cnRangeInfo').textContent = parts.join('  |  ');
        } else {
          byId('cnRangeInfo').textContent = data.message || '';
        }
      })
      .catch(function () {
        byId('cnRangeInfo').textContent = '';
      });
  }

  function createExchange() {
    if (state.busy) return;
    var amount = parseFloat(byId('cnFromAmount').value);
    var address = byId('cnAddress').value.trim();
    var extraId = byId('cnExtraId') ? byId('cnExtraId').value.trim() : '';
    var errors = [];

    if (!amount || amount <= 0) errors.push(t('Enter an amount to send.'));
    if (state.minAmount != null && amount < state.minAmount) errors.push(tpl('Minimum amount is {amount} {coin}.', { amount: fmtAmount(state.minAmount, 8), coin: state.from }));
    var bal = parseFloat(state.balances[state.from] || 0) || 0;
    if (amount > bal) errors.push(tpl('Insufficient {coin} balance ({amount} available).', { coin: state.from, amount: fmtAmount(bal) }));
    if (state.mediumFee != null && amount + state.mediumFee > bal) {
      errors.push(tpl('Insufficient {coin} to cover the amount and the medium network fee ({amount} available).', { coin: state.from, amount: fmtAmount(bal) }));
    }
    if (address.length < 10) errors.push(t('Enter a valid payout address.'));
    if (state.to === 'XRP' && !extraId) errors.push(t('XRP requires a destination tag (memo).'));

    var resultBox = byId('cnResult');
    if (errors.length) {
      resultBox.classList.remove('d-none');
      resultBox.innerHTML = '<div class="alert alert-danger mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i>' + errors.map(esc).join('<br>') + '</div>';
      return;
    }

    hideStepError('cnStepError3');
    revealPinPanel();
  }

  function confirmAndCreate(pin) {
    var amount = parseFloat(byId('cnFromAmount').value);
    var address = byId('cnAddress').value.trim();
    var extraId = byId('cnExtraId') ? byId('cnExtraId').value.trim() : '';
    var resultBox = byId('cnResult');
    var btn = byId('cnConfirmSendBtn');
    state.busy = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + t('Creating exchange...');

    function fail(msg) {
      resultBox.classList.remove('d-none');
      resultBox.innerHTML = '<div class="alert alert-danger mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i>' + esc(msg || t('Failed to create exchange.')) + '</div>';
      state.busy = false;
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i>' + t('Confirm & Exchange');
    }

    function done() {
      state.busy = false;
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i>' + t('Confirm & Exchange');
      hidePinPanel();
    }

    fetch('/api/changenow/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN, from: state.from, to: state.to, amount: amount, address: address, extraId: extraId })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!(data.success && data.exchange)) { fail(data.message || t('Failed to create exchange.')); return; }
      var ex = data.exchange;
      state.currentExchangeId = ex.exchangeId;

      if (state.mediumFee != null && state.senderAddr) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + tpl('Sending {amount} {coin}...', { amount: fmtAmount(amount, 8), coin: esc(state.from) });
        var sendBody = {
          token: TOKEN,
          coin: state.from,
          from_address: state.senderAddr,
          to_address: ex.payinAddress,
          amount: String(amount),
          fee: String(state.mediumFee),
          password: pin,
          address_type: state.senderType
        };
        if (state.from === 'XRP' && ex.payinExtraId) sendBody.extraId = ex.payinExtraId;

        fetch('/api/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(sendBody)
        }).then(function (r) {
          return r.json().then(function (j) { return { ok: r.ok, body: j }; });
        }).then(function (res) {
          if (res.ok && res.body && res.body.success) {
            ex.autoSent = true;
            ex.depositTxId = (res.body.result && res.body.result.txid) || '';
            ex.depositFrom = (res.body.result && res.body.result.fromAddress) || state.senderAddr;
            ex.depositFee = (res.body.result && res.body.result.fee) || state.mediumFee;
          } else {
            ex.autoSent = false;
            ex.sendError = (res.body && res.body.error) || t('Could not broadcast the deposit transaction.');
          }
          renderResult(ex);
          startStatusPolling(ex.exchangeId);
          loadRecent();
          done();
        }).catch(function () {
          ex.autoSent = false;
          ex.sendError = t('Network error while sending the deposit.');
          renderResult(ex);
          startStatusPolling(ex.exchangeId);
          loadRecent();
          done();
        });
      } else {
        renderResult(ex);
        startStatusPolling(ex.exchangeId);
        loadRecent();
        done();
      }
    }).catch(function () {
      fail(t('Network error. Please try again.'));
    });
  }

  function renderResult(ex) {
    var qr = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(ex.payinAddress || '');
    var head = ex.autoSent
      ? '<div class="alert alert-success mb-2 py-2"><i class="bi bi-check-circle me-1"></i>' + t('Exchange created. Deposit sent automatically with medium network fees.') + '</div>'
      : (ex.sendError
          ? '<div class="alert alert-warning mb-2 py-2"><i class="bi bi-exclamation-triangle me-1"></i>' + tpl('Exchange created, but the automatic deposit could not be broadcast ({error}). Send the exact amount manually to the deposit address below.', { error: esc(ex.sendError) }) + '</div>'
          : '<div class="alert alert-success mb-2 py-2"><i class="bi bi-check-circle me-1"></i>' + t('Exchange created. Send the exact amount to the deposit address.') + '</div>');
    var rows =
      '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('Exchange ID') + '</span><span class="text-end fw-semibold small" style="word-break:break-all">' + esc(ex.exchangeId) + '</span></div>' +
      '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('Send') + '</span><span class="text-end fw-semibold small">' + esc(fmtAmount(ex.fromAmount, 8)) + ' ' + esc(String(ex.from).toUpperCase()) + '</span></div>' +
      '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('You receive') + '</span><span class="text-end fw-semibold small">≈ ' + esc(fmtAmount(ex.toAmount, 8)) + ' ' + esc(String(ex.to).toUpperCase()) + '</span></div>' +
      (ex.depositTxId ? '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('Deposit TX') + '</span><span class="text-end fw-semibold small" style="word-break:break-all">' + esc(ex.depositTxId) + '</span></div>' : '') +
      (ex.autoSent && ex.depositFee != null ? '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('Deposit fee (medium)') + '</span><span class="text-end fw-semibold small">' + esc(fmtAmount(ex.depositFee, 8)) + ' ' + esc(String(ex.from).toUpperCase()) + '</span></div>' : '') +
      '<div class="swap-info-row" style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--bs-border-color)"><span class="text-secondary small">' + t('Status') + '</span><span class="text-end">' + statusBadge(ex.status) + '</span></div>';

    var extraHtml = '';
    if (ex.payinExtraId) {
      extraHtml = '<div class="mt-2"><div class="small fw-semibold text-secondary mb-1">' + t('Deposit memo / tag') + '</div><code id="cnPayinExtra" style="display:block;background:var(--bs-tertiary-bg);border:1px dashed var(--bs-border-color);border-radius:.5rem;padding:.5rem;word-break:break-all;cursor:pointer">' + esc(ex.payinExtraId) + '</code></div>';
    }

    byId('cnResult').classList.remove('d-none');
    byId('cnResult').innerHTML =
      head +
      '<div class="border rounded-3 p-3" style="background:var(--bs-body-bg);border-color:var(--bs-border-color)!important">' +
        '<div class="text-center mb-2"><img src="' + qr + '" alt="QR" width="160" height="160" loading="lazy" style="background:#fff;padding:6px;border-radius:.5rem"></div>' +
        '<div class="small fw-semibold text-secondary text-center mb-1">' + tpl('Deposit Address ({coin})', { coin: esc(String(ex.from).toUpperCase()) }) + '</div>' +
        '<code id="cnPayinAddr" style="display:block;background:var(--bs-tertiary-bg);border:1px dashed var(--bs-border-color);border-radius:.5rem;padding:.6rem;word-break:break-all;text-align:center;cursor:pointer">' + esc(ex.payinAddress || '') + '</code>' +
        extraHtml +
        '<div class="mt-3">' + rows + '</div>' +
        '<div class="mt-2 text-center small text-secondary">' + tpl('Exact amount required: {amount} {coin}', { amount: esc(fmtAmount(ex.fromAmount, 8)), coin: esc(String(ex.from).toUpperCase()) }) + '</div>' +
      '</div>' +
      '<div class="mt-2 text-center" id="cnStatusLine"></div>';

    var addrEl = byId('cnPayinAddr');
    if (addrEl) {
      addrEl.addEventListener('click', function () {
        if (navigator.clipboard) navigator.clipboard.writeText(ex.payinAddress || '').then(function () {
          addrEl.textContent = t('Copied!');
          setTimeout(function () { addrEl.textContent = ex.payinAddress || ''; }, 1500);
        });
      });
    }
    var extraEl = byId('cnPayinExtra');
    if (extraEl) {
      extraEl.addEventListener('click', function () {
        if (navigator.clipboard) navigator.clipboard.writeText(ex.payinExtraId || '').then(function () {
          extraEl.textContent = t('Copied!');
          setTimeout(function () { extraEl.textContent = ex.payinExtraId || ''; }, 1500);
        });
      });
    }
  }

  function stopStatusPolling() {
    if (state.statusTimer) { clearInterval(state.statusTimer); state.statusTimer = null; }
  }

  function startStatusPolling(exchangeId) {
    stopStatusPolling();
    state.currentExchangeId = exchangeId;
    var terminal = { finished: 1, failed: 1, refunded: 1 };
    var poll = function () {
      fetch('/api/changenow/status/' + encodeURIComponent(exchangeId) + '?token=' + encodeURIComponent(TOKEN))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            var line = byId('cnStatusLine');
            if (line) line.innerHTML = tpl('Status: {status}', { status: statusBadge(data.status) });
            if (terminal[data.status]) {
              stopStatusPolling();
              loadRecent();
            }
          }
        }).catch(function () {});
    };
    poll();
    state.statusTimer = setInterval(poll, 15000);
  }

  function loadRecent() {
    var box = byId('cnRecent');
    if (!box) return;
    box.innerHTML = '<div class="spinner-border spinner-border-sm"></div>';

    fetch('/api/changenow/list?token=' + encodeURIComponent(TOKEN))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          box.innerHTML = '<span class="text-muted">' + t('Could not load exchanges.') + '</span>';
          return;
        }
        if (!data.exchanges || data.exchanges.length === 0) {
          box.innerHTML = '<span class="text-muted"><i class="bi bi-inbox me-1"></i>' + t('No exchanges yet.') + '</span>';
          return;
        }
        var rows = data.exchanges.map(function (ex, idx) {
          return '<div class="d-flex align-items-center justify-content-between gap-2 py-2" style="border-bottom:1px solid var(--bs-border-color)">' +
            '<div class="d-flex align-items-center gap-2 min-w-0">' +
              coinImg(ex.from_currency) +
              '<div class="min-w-0">' +
                '<div class="small fw-semibold text-truncate">' + esc(fmtAmount(ex.from_amount, 8)) + ' ' + esc(String(ex.from_currency).toUpperCase()) + ' <i class="bi bi-arrow-right text-muted"></i> ' + esc(fmtAmount(ex.to_amount, 8)) + ' ' + esc(String(ex.to_currency).toUpperCase()) + '</div>' +
                '<div class="text-xs text-muted text-truncate" style="font-size:.72rem">' + esc(ex.exchange_id) + ' &middot; ' + esc((ex.created_at || '').replace('T', ' ')) + '</div>' +
              '</div>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2 flex-shrink-0">' + statusBadge(ex.status) +
              '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" data-refresh-idx="' + idx + '" title="' + t('Refresh status') + '"><i class="bi bi-arrow-clockwise"></i></button>' +
            '</div>' +
          '</div>';
        }).join('');
        box.innerHTML = rows;

        box.querySelectorAll('[data-refresh-idx]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var ex = data.exchanges[parseInt(btn.getAttribute('data-refresh-idx'), 10)];
            if (!ex) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.7rem;height:.7rem"></span>';
            fetch('/api/changenow/status/' + encodeURIComponent(ex.exchange_id) + '?token=' + encodeURIComponent(TOKEN))
              .then(function (r) { return r.json(); })
              .then(function () { loadRecent(); })
              .catch(function () { loadRecent(); });
          });
        });
      })
      .catch(function () {
        box.innerHTML = '<span class="text-muted">' + t('Could not load exchanges.') + '</span>';
      });
  }

  load();
})();
