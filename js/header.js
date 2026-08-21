// PruneBit - Header & Footer Navigation
document.addEventListener('error', function(e) {
  if (e.target && e.target.tagName === 'IMG') e.target.style.display = 'none';
}, true);
document.body.classList.add('bitcoin-style');
class CryptoVaultNav {
  constructor() {
    this.token = localStorage.getItem('wallet_token');
    this.walletId = localStorage.getItem('walletId');
    this.isAdmin = !!localStorage.getItem('admin_token');
    this.currentPage = this.getCurrentPage();
    this.privatePages = ['dashboard', 'send', 'receive', 'history', 'exchanges', 'profile'];
    this.walletStatus = 'disconnected';
    this.init();
  }

  getCurrentPage() {
    const path = window.location.pathname;
    const page = path.split('/').pop() || 'index.html';
    return page.replace('.html', '');
  }

  static delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  async loadI18n() {
    if (window.CryptoVaultI18n && typeof window.CryptoVaultI18n.init === 'function') {
      window.CryptoVaultI18n.init();
      return;
    }

    await new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = '../js/i18n.js?v=20260801t';
      script.onload = () => {
        if (window.CryptoVaultI18n && typeof window.CryptoVaultI18n.init === 'function') {
          window.CryptoVaultI18n.init();
        }
        resolve();
      };
      script.onerror = () => resolve();
      setTimeout(resolve, 1000);
      document.head.appendChild(script);
    });
  }

  async init() {
    if (!document.querySelector('link[href*="bootstrap-icons"]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
      document.head.appendChild(link);
    }
    await this.loadI18n();

    if (this.token && this.walletId) {
      this.walletStatus = 'connected';
      try {
        const response = await fetch('/api/get_wallets', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: this.token }),
        });
        if (response.ok) {
          const data = await response.json();
          if (data.success) {
            this.walletStatus = 'active';
            this.walletInfo = data.wallet;
          }
        }
      } catch (error) {
        console.error('Error loading wallet info');
      }
    }

    await CryptoVaultNav.delay(300);
    this.applyTheme();
    this.injectNavigation();
    this.injectMobileNav();
    if (this.token) this.injectLogoutModal();
    this.attachListeners();
    this.translatePage();
    window.addEventListener('cv-page-translated', () => {
      this.attachListeners();
      document.querySelectorAll('.language-selector').forEach(sel => {
        sel.value = CryptoVaultI18n.currentLanguage;
      });
    });
    if (this.token) this.startInactivityMonitor();
    var ds = document.createElement('script');
    ds.src = '../js/download-buttons.js?v=3';
    document.body.appendChild(ds);
  }

  translatePage() {
    if (window.CryptoVaultI18n && typeof window.CryptoVaultI18n.translatePage === 'function') {
      window.CryptoVaultI18n.translatePage();
    }
  }

  applyTheme() {
    const theme = localStorage.getItem('cv_theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
  }

  getThemeToggle() {
    const current = document.documentElement.getAttribute('data-bs-theme') || 'auto';
    const icons = { auto: 'bi-circle-half', light: 'bi-sun', dark: 'bi-moon' };
    return `<button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Theme" aria-label="Toggle theme">
      <i class="bi ${icons[current] || icons.auto}"></i>
    </button>`;
  }

  getLanguageSelector() {
    if (window.CryptoVaultI18n && typeof window.CryptoVaultI18n.getSelector === 'function') {
      return window.CryptoVaultI18n.getSelector();
    }
    return `<select class="language-selector" data-no-i18n="true" aria-label="Language" title="Language"><option value="en">English</option><option value="fr">Français</option><option value="de">Deutsch</option><option value="ru">Русский</option><option value="es">Español</option><option value="it">Italiano</option></select>`;
  }

  getStatusDot() {
    const map = {
      active: '<span class="status-dot active"></span>',
      connected: '<span class="status-dot connected"></span>',
      disconnected: '<span class="status-dot disconnected"></span>'
    };
    return map[this.walletStatus] || map.disconnected;
  }

  getStatusText() {
    const map = { active: 'Active', connected: 'Connected', disconnected: 'Offline' };
    return map[this.walletStatus] || 'Offline';
  }

  getHeaderNav() {
    const isActive = (page) => this.currentPage === page ? 'active' : '';

    if (!this.token) {
      return `
        <a href="index.html" class="header-nav-item ${isActive('index')}">
          <i class="bi bi-house"></i> Wallet
        </a>
        <div class="header-nav-dropdown" data-action="toggle-dropdown">
          <span class="header-nav-item ${isActive('products') || isActive('address-check') || isActive('prices') || isActive('exchanges') || isActive('explorer') ? 'active' : ''}">
            <i class="bi bi-grid"></i> Products <i class="bi bi-chevron-down" style="font-size:10px;margin-left:2px;"></i>
          </span>
          <div class="header-dropdown-menu">
            <a href="address-check.html" class="header-dropdown-item">
              <i class="bi bi-geo-alt"></i> Address Check
            </a>
            <a href="prices.html" class="header-dropdown-item ${isActive('prices')}">
              <i class="bi bi-currency-exchange"></i> Check Prices
            </a>
            <a href="exchanges.html" class="header-dropdown-item ${isActive('exchanges')}">
              <i class="bi bi-arrow-left-right"></i> Exchange
            </a>
            <a href="explorer.html" class="header-dropdown-item ${isActive('explorer')}">
              <i class="bi bi-compass"></i> Blockchain Explorer
            </a>
          </div>
        </div>
        <a href="explorer.html" class="header-nav-item ${isActive('explorer')}">
          <i class="bi bi-compass"></i> Explorer
        </a>
        <div class="header-nav-dropdown" data-action="toggle-dropdown">
          <span class="header-nav-item ${isActive('help') || isActive('company') || isActive('support') || isActive('status') || isActive('ticket') ? 'active' : ''}">
            <i class="bi bi-question-circle"></i> FAQ <i class="bi bi-chevron-down" style="font-size:10px;margin-left:2px;"></i>
          </span>
          <div class="header-dropdown-menu">
            <a href="company.html" class="header-dropdown-item ${isActive('company')}">
              <i class="bi bi-building"></i> Company
            </a>
            <a href="support.html" class="header-dropdown-item ${isActive('support')}">
              <i class="bi bi-headset"></i> Support
            </a>
            <a href="help.html" class="header-dropdown-item ${isActive('help')}">
              <i class="bi bi-question-circle"></i> Help
            </a>
            <a href="status.html" class="header-dropdown-item ${isActive('status')}">
              <i class="bi bi-activity"></i> Status
            </a>
            <a href="ticket.html" class="header-dropdown-item ${isActive('ticket')}">
              <i class="bi bi-envelope-paper"></i> Submit a ticket
            </a>
          </div>
        </div>
      `;
    }

    return `
      <a href="dashboard.html" class="header-nav-item ${isActive('dashboard')}">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="send.html" class="header-nav-item ${isActive('send')}">
        <i class="bi bi-send"></i> Send
      </a>
      <a href="receive.html" class="header-nav-item ${isActive('receive')}">
        <i class="bi bi-qr-code"></i> Receive
      </a>
      <a href="history.html" class="header-nav-item ${isActive('history')}">
        <i class="bi bi-clock-history"></i> History
      </a>
      <a href="exchanges.html" class="header-nav-item ${isActive('exchanges')}">
        <i class="bi bi-arrow-left-right"></i> Exchanges
      </a>
      <a href="profile.html" class="header-nav-item ${isActive('profile')}">
        <i class="bi bi-person"></i> Profile
      </a>
    `;
  }

  injectNavigation() {
    if (document.getElementById('appHeader')) return;
    const header = document.createElement('header');
    header.className = 'app-header';
    header.id = 'appHeader';
    header.setAttribute('role', 'banner');
    header.innerHTML = `
      <div class="app-header-inner">
        <div class="app-header-left">
          <a href="index.html" class="app-logo">
            <div class="logo-icon">
              <img src="../img/logo.svg" alt="PruneBit" style="height:28px;width:auto;">
            </div>
            <span class="logo-text">PruneBit</span>
          </a>
        </div>
        <nav class="app-header-nav">
          ${this.getHeaderNav()}
        </nav>
        <div class="app-header-right">
          ${this.getLanguageSelector()}
          ${this.getThemeToggle()}
          ${this.token ? '<button id="headerLogout" class="btn-logout">Logout</button>' : ''}
        </div>
      </div>
    `;
    document.body.insertBefore(header, document.body.firstChild);
    this.injectPriceTicker();

    const path = window.location.pathname;
    const privatePages = this.privatePages;
    const isPrivate = privatePages.some(p => path.includes(p));
    if (document.getElementById('appFooter')) return;
    const footer = document.createElement('footer');
    footer.className = 'app-footer';
    footer.id = 'appFooter';
    footer.setAttribute('role', 'contentinfo');
    footer.innerHTML = isPrivate
      ? `<div class="app-footer-inner" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;padding:12px 0;">
            <div class="d-flex align-items-center gap-2">
              <img src="../img/logo.svg" alt="PruneBit" style="height:20px;width:auto;">
              <span style="color:var(--bs-body-color);font-weight:600;font-size:0.9rem;">PruneBit</span>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="donate-box">
                <div class="donate-trigger" data-action="toggle-donate"><i class="bi bi-heart"></i> Donate</div>
                <div class="donate-popup">
                  <div class="donate-header"><i class="bi bi-currency-bitcoin" style="color:#f7931a;font-size:1.1rem;"></i> Bitcoin</div>
                  <div class="donate-body">
                    <div class="donate-qr">
                      <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=bitcoin:1MAvgrg7WHSrUSEBUJzM8ojQG4ESE3QBTr" alt="QR" width="120" height="120" loading="lazy" data-action="img-error">
                    </div>
                    <div class="donate-addr-row" data-action="copy-donate-address">
                      <span class="donate-addr">1MAvgrg7WHSrUSEBUJzM8ojQG4ESE3QBTr</span>
                      <span class="copy-feedback">Copy</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="footer-legal-links">
                <a href="terms.html">Terms</a>
                <a href="privacy.html">Privacy</a>
                <a href="https://github.com/shop14/Prune-Bit" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i> Source Code</a>
              </div>
            </div>
          </div>`
      : `<div class="app-footer-inner">
          <div class="footer-columns">
            <div class="footer-col">
              <h6 class="footer-col-title">Products</h6>
              <a href="import.html">Classic Wallet</a>
              <a href="address-check.html">Address Check</a>
              <a href="prices.html">Check Prices</a>
              <a href="exchanges.html">Exchange</a>
              <a href="explorer.html">Blockchain Explorer</a>
              <a href="fees.html">Fees</a>
            </div>
            <div class="footer-col">
              <h6 class="footer-col-title">Support</h6>
              <a href="company.html">Company</a>
              <a href="support.html">Support</a>
              <a href="help.html">Help</a>
              <a href="https://github.com/shop14/Prune-Bit" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i> Source Code</a>
              <a href="status.html">Status <img src="../img/status-pulse.svg" alt="" width="14" height="14" style="vertical-align:middle;margin-left:4px;"></a>
              <a href="ticket.html">Submit a ticket</a>
            </div>
            <div class="footer-col">
              <h6 class="footer-col-title">Assets</h6>
              <span>Bitcoin (BTC)</span>
              <span>Ethereum (ETH)</span>
              <span>Litecoin (LTC)</span>
              <span>Dogecoin (DOGE)</span>
              <a href="receive.html" style="color:var(--bs-body-color);opacity:0.4;font-size:0.75rem;">and more ...</a>
            </div>
          </div>
          <div class="footer-bottom">
            <div class="d-flex align-items-center gap-2">
              <img src="../img/logo.svg" alt="PruneBit" style="height:20px;width:auto;">
              <span style="color:var(--bs-body-color);font-weight:600;font-size:0.9rem;">PruneBit</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <div class="donate-box">
                <div class="donate-trigger" data-action="toggle-donate"><i class="bi bi-heart"></i> Donate</div>
                <div class="donate-popup">
                  <div class="donate-header"><i class="bi bi-currency-bitcoin" style="color:#f7931a;font-size:1.1rem;"></i> Bitcoin</div>
                  <div class="donate-body">
                    <div class="donate-qr">
                      <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=bitcoin:1MAvgrg7WHSrUSEBUJzM8ojQG4ESE3QBTr" alt="QR" width="120" height="120" loading="lazy" data-action="img-error">
                    </div>
                    <div class="donate-addr-row" data-action="copy-donate-address">
                      <span class="donate-addr">1MAvgrg7WHSrUSEBUJzM8ojQG4ESE3QBTr</span>
                      <span class="copy-feedback">Copy</span>
                    </div>
                  </div>
                </div>
              </div>
              <span style="color:var(--bs-body-color);font-size:0.8rem;">&copy; 2026 PruneBit</span>
            </div>
            <div class="footer-legal-links">
              <a href="terms.html">Terms</a>
              <a href="privacy.html">Privacy</a>
              <a href="aml.html">AML Policy</a>
              <a href="https://github.com/shop14/Prune-Bit" target="_blank" rel="noopener noreferrer"><i class="bi bi-github"></i> Source Code</a>
            </div>
          </div>
        </div>`;
    document.body.appendChild(footer);
    this.injectPaymentIcons();
  }

  injectPaymentIcons() {
    if (document.getElementById('appPayments')) return;
    const payments = document.createElement('div');
    payments.id = 'appPayments';
    payments.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:16px;padding:14px 16px;border-top:1px solid var(--bs-border-color);background:var(--bs-body-bg);';
    payments.innerHTML =
      '<span style="font-size:0.8rem;color:var(--bs-secondary-color);opacity:0.85;">We do not accept banks</span>' +
      '<img src="../img/visa.svg" alt="Visa" loading="lazy" style="height:26px;width:auto;opacity:0.85;">' +
      '<img src="../img/master-card.svg" alt="MasterCard" loading="lazy" style="height:26px;width:auto;opacity:0.85;">';
    document.body.appendChild(payments);
  }

  injectPriceTicker() {
    if (document.getElementById('priceTicker')) return;
    const tickerHiddenPages = ['prices', 'setup', 'import', 'company', 'ticket'];
    if (tickerHiddenPages.indexOf(this.currentPage) !== -1) return;
    if (this.privatePages.indexOf(this.currentPage) !== -1) return;
    const ticker = document.createElement('div');
    ticker.id = 'priceTicker';
    ticker.className = 'price-ticker';
    ticker.innerHTML =
      '<div class="price-ticker-track" id="priceTickerTrack">' +
        '<div class="price-ticker-items"><span class="price-ticker-item"><span class="price-ticker-loading">Loading prices...</span></span></div>' +
      '</div>';
    const appHeader = document.getElementById('appHeader');
    if (appHeader) {
      appHeader.insertAdjacentElement('afterend', ticker);
    } else {
      document.body.insertBefore(ticker, document.body.firstChild);
    }
    this.loadTickerPrices();
  }

  async loadTickerPrices() {
    const tickerCoins = [
      { id: 'bitcoin', symbol: 'BTC' },
      { id: 'ethereum', symbol: 'ETH' },
      { id: 'litecoin', symbol: 'LTC' },
      { id: 'dogecoin', symbol: 'DOGE' },
      { id: 'ripple', symbol: 'XRP' },
      { id: 'bitcoin-cash', symbol: 'BCH' }
    ];
    try {
      const ids = tickerCoins.map(function (c) { return c.id; }).join(',');
      const res = await fetch('https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=' + ids + '&order=market_cap_desc&per_page=100&page=1&sparkline=false&price_change_percentage=24h');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const priceMap = {};
      const changeMap = {};
      data.forEach(function (c) {
        priceMap[c.id] = c.current_price;
        changeMap[c.id] = c.price_change_percentage_24h;
      });
      const items = tickerCoins.map(function (coin) {
        const price = priceMap[coin.id];
        const change = changeMap[coin.id];
        const priceStr = price == null ? '...'
          : price >= 1 ? '$' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : price >= 0.01 ? '$' + price.toFixed(4)
          : '$' + price.toFixed(8);
        const changeStr = change == null ? '--' : (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
        const changeClass = change == null ? 'change-neutral' : change > 0 ? 'change-positive' : change < 0 ? 'change-negative' : 'change-neutral';
        return '<span class="price-ticker-item"><span class="price-ticker-symbol">' + coin.symbol + '</span><span class="price-ticker-price">' + priceStr + '</span><span class="price-ticker-change ' + changeClass + '">' + changeStr + '</span></span>';
      });
      const track = document.getElementById('priceTickerTrack');
      if (track) {
        track.innerHTML =
          '<div class="price-ticker-items">' + items.join('') + '</div>';
        track.classList.add('is-loaded');
      }
    } catch (e) {
      const track = document.getElementById('priceTickerTrack');
      if (track) track.innerHTML = '<div class="price-ticker-items"><span class="price-ticker-item"><span class="price-ticker-loading">Market data unavailable</span></span></div>';
    }
  }

  injectMobileNav() {
    const mobileHeader = document.createElement('header');
    mobileHeader.className = 'mobile-header';
    mobileHeader.id = 'mobileHeader';
    mobileHeader.setAttribute('role', 'banner');
    mobileHeader.innerHTML = `
      <span class="mobile-brand">PruneBit</span>
      ${this.getLanguageSelector()}
      <span class="mobile-status">
        ${this.getStatusDot()}
      </span>
      ${this.token ? '<button id="mobileLogout" class="btn-logout btn-logout-mobile" aria-label="Logout"><i class="bi bi-box-arrow-right"></i></button>' : ''}
    `;
    document.body.appendChild(mobileHeader);

    const bottomNav = document.createElement('nav');
    bottomNav.className = 'mobile-bottom-nav';
    bottomNav.id = 'mobileBottomNav';
    bottomNav.setAttribute('role', 'navigation');
    bottomNav.setAttribute('aria-label', 'Mobile navigation');
    bottomNav.style.paddingBottom = 'max(4px, env(safe-area-inset-bottom))';
    bottomNav.innerHTML = `
      <div class="mobile-bottom-nav-inner">
        ${this.token ? `
          <a href="dashboard.html" class="${this.currentPage === 'dashboard' ? 'active' : ''}" aria-label="Dashboard">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
          <a href="send.html" class="${this.currentPage === 'send' ? 'active' : ''}" aria-label="Send">
            <i class="bi bi-send"></i> Send
          </a>
          <a href="receive.html" class="${this.currentPage === 'receive' ? 'active' : ''}" aria-label="Receive">
            <i class="bi bi-qr-code"></i> Receive
          </a>
          <a href="history.html" class="${this.currentPage === 'history' ? 'active' : ''}" aria-label="History">
            <i class="bi bi-clock-history"></i> History
          </a>
          <a href="exchanges.html" class="${this.currentPage === 'exchanges' ? 'active' : ''}" aria-label="Exchanges">
            <i class="bi bi-arrow-left-right"></i> Exchanges
          </a>
          <a href="profile.html" class="${this.currentPage === 'profile' ? 'active' : ''}" aria-label="Profile">
            <i class="bi bi-person"></i> Profile
          </a>
        ` : `
          <a href="index.html" class="${this.currentPage === 'index' ? 'active' : ''}">
            <i class="bi bi-house"></i> Wallet
          </a>
          <a href="products.html" class="${this.currentPage === 'products' ? 'active' : ''}">
            <i class="bi bi-grid"></i> Products
          </a>
          <a href="explorer.html" class="${this.currentPage === 'explorer' ? 'active' : ''}">
            <i class="bi bi-compass"></i> Explorer
          </a>
          <a href="help.html" class="${this.currentPage === 'help' ? 'active' : ''}">
            <i class="bi bi-question-circle"></i> FAQ
          </a>
        `}
      </div>
    `;
    document.body.appendChild(bottomNav);
  }

  injectLogoutModal() {
    var modal = document.createElement('div');
    modal.id = 'logoutBackupModal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;';
    modal.innerHTML = '<div style="background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:16px;padding:28px;max-width:380px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.15);text-align:center;">' +
      '<div style="width:48px;height:48px;background:rgba(255,126,0,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;"><i class="bi bi-download" style="font-size:1.3rem;color:#ff7e00;"></i></div>' +
      '<h3 style="font-size:1.1rem;font-weight:700;margin:0 0 6px;color:var(--bs-body-color);">Backup before logout?</h3>' +
      '<p style="font-size:0.85rem;color:var(--bs-secondary-color);margin:0 0 16px;">Download an encrypted backup of your wallet before signing out.</p>' +
      '<div style="margin-bottom:14px;text-align:left;">' +
        '<label style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:4px;color:var(--bs-body-color);">Enter PIN Code</label>' +
        '<div id="logoutBackupPinContainer"></div>' +
        '<div id="logoutBackupError" style="color:var(--bs-danger);font-size:0.78rem;margin-top:4px;display:none;"></div>' +
      '</div>' +
      '<div style="display:flex;gap:8px;">' +
        '<button id="logoutSkipBtn" style="flex:1;padding:10px;border:1px solid var(--bs-border-color);border-radius:8px;background:var(--bs-body-bg);color:var(--bs-body-color);font-size:0.85rem;font-weight:600;cursor:pointer;">Skip</button>' +
        '<button id="logoutBackupBtn" style="flex:1;padding:10px;border:none;border-radius:8px;background:#ff7e00;color:#fff;font-size:0.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;"><i class="bi bi-download"></i> Backup & Logout</button>' +
      '</div>' +
    '</div>';
    document.body.appendChild(modal);

    var self = this;
    document.getElementById('logoutSkipBtn').addEventListener('click', function() { self._finalLogout(); });
    document.getElementById('logoutBackupBtn').addEventListener('click', async function() {
      var pw = PinPad.getValue('logoutBackupPin');
      var errEl = document.getElementById('logoutBackupError');
      var btn = this;
      if (!pw) { errEl.textContent = 'Please enter your PIN code'; errEl.style.display = ''; return; }
      errEl.style.display = 'none';
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Backing up...';
      try {
        var res = await fetch('/api/export_backup', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: self.token, password: pw, backupPassword: pw })
        });
        var data = await res.json();
        if (data.success) {
          var blob = new Blob([JSON.stringify(data.backup, null, 2)], { type: 'application/json' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url;
          a.download = 'wallet_backup_' + new Date().toISOString().split('T')[0] + '.prunebit';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
          self._finalLogout();
        } else {
          errEl.textContent = data.error || 'Backup failed';
          errEl.style.display = '';
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-download"></i> Backup & Logout';
        }
      } catch (e) {
        errEl.textContent = 'Network error';
        errEl.style.display = '';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> Backup & Logout';
      }
    });

  }

  async _finalLogout() {
    var token = localStorage.getItem('wallet_token');
    if (token) {
      await fetch('/api/logout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
      }).catch(function () {});
    }
    localStorage.clear();
    sessionStorage.clear();
    document.cookie.split(';').forEach(function (c) {
      document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
    });
    if (window.caches) {
      caches.keys().then(function (names) { names.forEach(function (n) { caches.delete(n); }); });
    }
    if (window.indexedDB && indexedDB.databases) {
      indexedDB.databases().then(function (dbs) { dbs.forEach(function (db) { indexedDB.deleteDatabase(db.name); }); });
    }
    window.location.href = 'index.html';
  }

  startInactivityMonitor() {
    var INACTIVITY_TIMEOUT = 5 * 60 * 1000;
    var lastActivityTime = Date.now();
    var timer = null;

    function updateActivity() { lastActivityTime = Date.now(); }

    function forceLogout() {
      alert('You have been logged out due to inactivity. Please log in again.');
      localStorage.clear();
      sessionStorage.clear();
      document.cookie.split(';').forEach(function (c) {
        document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
      });
      if (window.caches) {
        caches.keys().then(function (names) { names.forEach(function (n) { caches.delete(n); }); });
      }
      if (window.indexedDB && indexedDB.databases) {
        indexedDB.databases().then(function (dbs) { dbs.forEach(function (db) { indexedDB.deleteDatabase(db.name); }); });
      }
      window.location.href = 'import.html';
    }

    function checkInactivity() {
      if (Date.now() - lastActivityTime >= INACTIVITY_TIMEOUT) {
        var token = localStorage.getItem('wallet_token');
        if (token) {
          fetch('/api/logout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token })
          }).catch(function () {});
        }
        forceLogout();
      }
    }

    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
      document.addEventListener(ev, updateActivity, ev === 'touchstart' ? { passive: true } : undefined);
    });
    timer = setInterval(checkInactivity, 30000);
  }

  attachListeners() {
    const doLogout = async () => {
      var modal = document.getElementById('logoutBackupModal');
      if (modal) {
        modal.style.display = 'flex';
    if (!document.getElementById('logoutBackupPin')) { PinPad.create({id:'logoutBackupPin',container:'logoutBackupPinContainer',maxLength:4}); }
    PinPad.reset('logoutBackupPin');
        
        
        var errEl = document.getElementById('logoutBackupError');
        if (errEl) errEl.style.display = 'none';
        var btn = document.getElementById('logoutBackupBtn');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download"></i> Backup & Logout'; }
      } else {
        this._finalLogout();
      }
    };

    const logoutBtn = document.getElementById('headerLogout');
    if (logoutBtn) logoutBtn.addEventListener('click', doLogout);
    const mobileLogoutBtn = document.getElementById('mobileLogout');
    if (mobileLogoutBtn) mobileLogoutBtn.addEventListener('click', doLogout);

    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme') || 'auto';
        const next = current === 'auto' ? 'light' : current === 'light' ? 'dark' : 'auto';
        localStorage.setItem('cv_theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
      });
    }

    const backBtn = document.getElementById('globalBack');
    if (backBtn) backBtn.addEventListener('click', () => {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = 'dashboard.html';
      }
    });

    document.addEventListener('click', function(e) {
      if (!e.target.closest('.header-nav-dropdown')) {
        document.querySelectorAll('.header-nav-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
      }
      var t = e.target.closest('[data-action]');
      if (!t) return;
      var action = t.dataset.action;
      if (action === 'toggle-dropdown') {
        e.stopPropagation();
        var wasOpen = t.classList.contains('open');
        document.querySelectorAll('.header-nav-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
        if (!wasOpen) t.classList.add('open');
        return;
      }
      if (action === 'toggle-donate') {
        t.parentElement.classList.toggle('open');
      }
      if (action === 'copy-donate-address') {
        var addr = '1MAvgrg7WHSrUSEBUJzM8ojQG4ESE3QBTr';
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(addr);
        } else {
          var ta = document.createElement('textarea');
          ta.value = addr;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        var fb = t.querySelector('.copy-feedback');
        if (fb) fb.textContent = 'Copied!';
        setTimeout(function() { if (fb) fb.textContent = 'Copy'; }, 2000);
      }
    });
    document.querySelectorAll('button.loading-btn:not([data-no-delay="true"])').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        if (btn.disabled) return;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.insertAdjacentHTML('beforeend', ' <span style="font-size:0.8rem;opacity:0.7;">...</span>');
        await CryptoVaultNav.delay(1800);
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      });
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new CryptoVaultNav();
});

/* ---------- Session consent banner (bottom bar, cookie-style) ---------- */
(function () {
  function initSessionConsent() {
    try {
      if (sessionStorage.getItem('pbConsent')) return;
      var path = window.location.pathname || '';
      if (/admin/i.test(path)) return;

      var css = document.createElement('style');
      css.textContent = [
        '.pb-consent-banner{position:fixed;left:0;right:0;bottom:0;z-index:100000;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#111);border-top:1px solid rgba(255,126,0,.35);box-shadow:0 -8px 30px rgba(0,0,0,.18);padding:12px 18px;font-family:inherit}',
        '.pb-consent-inner{max-width:1100px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:14px}',
        '.pb-consent-brand{display:flex;align-items:center;gap:8px;flex:0 0 auto}',
        '.pb-consent-brand img{height:24px;width:auto}',
        '.pb-consent-brand span{font-weight:700;font-size:.95rem}',
        '.pb-consent-text{flex:1 1 320px;min-width:260px;font-size:.8rem;line-height:1.5;color:var(--bs-secondary-color,#444)}',
        '.pb-consent-text b{color:var(--bs-body-color,#111)}',
        '.pb-consent-actions{display:flex;align-items:center;gap:10px;flex:0 0 auto;margin-left:auto}',
        '.pb-consent-links{font-size:.72rem;white-space:nowrap}',
        '.pb-consent-links a{color:var(--bs-secondary-color,#666);text-decoration:none}',
        '.pb-consent-links a:hover{text-decoration:underline}',
        '.pb-consent-btn{border:none;border-radius:999px;padding:9px 20px;font-size:.85rem;font-weight:600;color:#fff;cursor:pointer;background:linear-gradient(135deg,#FF7E00,#ff9a2e);white-space:nowrap;transition:filter .15s}',
        '.pb-consent-btn:hover{filter:brightness(1.06)}',
        '.pb-consent-close{background:none;border:none;color:var(--bs-secondary-color,#666);font-size:1.4rem;line-height:1;cursor:pointer;padding:2px 6px;border-radius:8px}',
        '.pb-consent-close:hover{color:var(--bs-body-color,#111);background:rgba(0,0,0,.05)}',
        '@media (max-width:640px){.pb-consent-inner{flex-direction:column;align-items:flex-start}.pb-consent-actions{margin-left:0;width:100%;justify-content:space-between}}'
      ].join('');
      document.head.appendChild(css);

      var b = document.createElement('div');
      b.className = 'pb-consent-banner';
      b.setAttribute('role', 'region');
      b.setAttribute('aria-label', 'Consent notice');
      b.innerHTML =
        '<div class="pb-consent-inner">' +
          '<div class="pb-consent-brand"><img src="/img/logo.svg" alt="PruneBit"><span>PruneBit</span></div>' +
          '<div class="pb-consent-text">' +
            'We currently use cookies to improve and personalize your experience on our website. ' +
            'PruneBit is non-custodial — only you control your keys. By continuing you accept our ' +
            '<a href="/html/terms.html">Terms</a> and <a href="/html/privacy.html">Privacy Policy</a>.' +
          '</div>' +
          '<div class="pb-consent-actions">' +
            '<span class="pb-consent-links"><a href="/html/terms.html">Terms</a> · <a href="/html/privacy.html">Privacy</a></span>' +
            '<button type="button" class="pb-consent-btn">Accept</button>' +
            '<button type="button" class="pb-consent-close" aria-label="Dismiss">&times;</button>' +
          '</div>' +
        '</div>';

      function accept() {
        try { sessionStorage.setItem('pbConsent', String(Date.now())); } catch (e) {}
        b.remove();
      }
      b.querySelector('.pb-consent-btn').addEventListener('click', accept);
      var cx = b.querySelector('.pb-consent-close');
      if (cx) cx.addEventListener('click', function () { b.remove(); });
      document.body.appendChild(b);
    } catch (e) { /* never block the app */ }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSessionConsent);
  } else {
    initSessionConsent();
  }
})();
