if (!document.querySelector('link[href*="bootstrap-icons"]')) {
  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
  document.head.appendChild(link);
}

if (!document.querySelector('link[href*="bitcoin-style"]')) {
  var cssLink = document.createElement('link');
  cssLink.rel = 'stylesheet';
  cssLink.href = '../css/bitcoin-style.css';
  document.head.appendChild(cssLink);
}

const adminToken = localStorage.getItem('admin_token');
if (!adminToken) window.location.href = 'admin_login.html';

var adminLastActivity = Date.now();
const ADMIN_INACTIVITY_TIMEOUT = 600000;
function checkAdminInactivity() {
  if (localStorage.getItem('admin_token') && Date.now() - adminLastActivity >= ADMIN_INACTIVITY_TIMEOUT) {
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = 'admin_login.html';
  }
}
['mousedown','mousemove','keypress','scroll','touchstart','click'].forEach(function(ev) {
  document.addEventListener(ev, function() { adminLastActivity = Date.now(); });
});
setInterval(checkAdminInactivity, 30000);

function getActivePage() {
  var page = window.location.pathname.split('/').pop() || 'admin_dashboard.html';
  return page.replace('.html', '');
}

function renderAdminHeader() {
  var active = getActivePage();
  var pages = [
    { id: 'admin_dashboard', icon: 'bi-speedometer2', label: 'Dashboard' },
    { id: 'admin_wallets', icon: 'bi-wallet2', label: 'Wallets' },
    { id: 'admin_sessions', icon: 'bi-person-check', label: 'Sessions' },
    { id: 'admin_transactions', icon: 'bi-receipt', label: 'Transactions' },
    { id: 'admin_exchanges', icon: 'bi-arrow-left-right', label: 'Exchanges' },
    { id: 'admin_tickets', icon: 'bi-ticket-detailed', label: 'Tickets' },
    { id: 'admin_status', icon: 'bi-activity', label: 'Status' },
    { id: 'admin_settings', icon: 'bi-gear', label: 'Settings' }
  ];
  var navLinks = pages.map(function(p) {
    var cls = active === p.id ? 'admin-nav-link active' : 'admin-nav-link';
    return '<a href="' + p.id + '.html" class="' + cls + '"><i class="bi ' + p.icon + '"></i><span>' + p.label + '</span></a>';
  }).join('');

  var header = document.createElement('div');
  header.className = 'admin-header';
  header.innerHTML =
    '<div class="admin-header-inner">' +
      '<div class="admin-header-left">' +
        '<a href="admin_dashboard.html" class="admin-header-brand">' +
          '<img src="../img/logo.svg" alt="PruneBit" style="height:22px;width:auto;">' +
          '<span class="admin-header-brand-text">PruneBit</span>' +
        '</a>' +
      '</div>' +
      '<nav class="admin-header-nav">' + navLinks + '</nav>' +
      '<div class="admin-header-right">' +
        '<button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle theme"><i class="bi bi-circle-half"></i></button>' +
        '<button id="adminLogoutBtn" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-md-inline">Logout</span></button>' +
      '</div>' +
    '</div>';
  document.body.insertBefore(header, document.body.firstChild);
}

function setupAdminTheme() {
  var theme = localStorage.getItem('cv_theme') || 'light';
  document.documentElement.setAttribute('data-bs-theme', theme);
  var btn = document.getElementById('themeToggle');
  if (btn) btn.addEventListener('click', function() {
    var current = document.documentElement.getAttribute('data-bs-theme') || 'auto';
    var next = current === 'auto' ? 'light' : current === 'light' ? 'dark' : 'auto';
    localStorage.setItem('cv_theme', next);
    document.documentElement.setAttribute('data-bs-theme', next);
  });
}

function setupAdminLogout() {
  var btn = document.getElementById('adminLogoutBtn');
  if (btn) btn.addEventListener('click', function() {
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = 'admin_login.html';
  });
}

function adminPost(url, body) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ token: adminToken }, body || {}))
  }).then(function(r) {
    if (r.status === 401) {
      localStorage.clear();
      sessionStorage.clear();
      window.location.href = 'admin_login.html';
      return { success: false, error: 'Session expired' };
    }
    return r.json();
  });
}

function adminGet(url) {
  return fetch(url, {
    method: 'GET',
    headers: { 'Content-Type': 'application/json' }
  }).then(function(r) {
    if (r.status === 401) {
      localStorage.clear();
      sessionStorage.clear();
      window.location.href = 'admin_login.html';
      return { success: false, error: 'Session expired' };
    }
    return r.json();
  });
}

function escapeHtml(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
    switch (ch) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#39;';
      default: return ch;
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  renderAdminHeader();
  setupAdminTheme();
  setupAdminLogout();
});
