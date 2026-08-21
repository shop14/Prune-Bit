(function() {
  'use strict';

  window.Auth = {
    getToken: function() {
      const match = document.cookie.match(/(^|;\s*)wallet_token=([^;]+)/);
      return match ? decodeURIComponent(match[2]) : null;
    },

    getWalletId: function() {
      const match = document.cookie.match(/(^|;\s*)walletId=([^;]+)/);
      return match ? decodeURIComponent(match[2]) : null;
    },

    setSession: function(token, walletId) {
      const isSecure = window.location.protocol === 'https:';
      const cookieOptions = 'path=/;SameSite=Lax' + (isSecure ? ';Secure' : '');
      document.cookie = 'wallet_token=' + encodeURIComponent(token) + ';' + cookieOptions + ';max-age=' + (24 * 60 * 60);
      document.cookie = 'walletId=' + encodeURIComponent(walletId) + ';' + cookieOptions + ';max-age=' + (24 * 60 * 60);
    },

    clearSession: function() {
      document.cookie = 'wallet_token=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT;SameSite=Lax';
      document.cookie = 'walletId=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT;SameSite=Lax';
      localStorage.clear();
      sessionStorage.clear();
    },

    isAuthenticated: function() {
      return !!this.getToken();
    },

    getAuthHeaders: function() {
      return { 'Content-Type': 'application/json' };
    },

    makeAuthenticatedRequest: function(url, options = {}) {
      const token = this.getToken();
      if (!token) {
        return Promise.reject(new Error('Not authenticated'));
      }
      
      const headers = new Headers(options.headers || {});
      headers.set('Content-Type', 'application/json');
      
      return fetch(url, {
        ...options,
        headers,
        credentials: 'include',
        body: options.body ? JSON.stringify({ ...JSON.parse(options.body), token }) : JSON.stringify({ token })
      });
    },

    logout: async function(skipBackup = false) {
      const token = this.getToken();
      if (token) {
        try {
          await fetch('/api/logout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ token })
          });
        } catch (e) {
          console.error('Logout API error:', e);
        }
      }
      this.clearSession();
      if (!skipBackup) {
        window.location.href = 'index.html';
      }
    }
  };
})();
