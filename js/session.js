(function () {
  window.sessionManager = {
    getToken: function () {
      return localStorage.getItem('wallet_token');
    },
    setToken: function (token) {
      localStorage.setItem('wallet_token', token);
    },
    removeToken: function () {
      this.clearAllStorage();
    },
    clearAllStorage: function () {
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
    },
    requireAuth: function () {
      var t = this.getToken();
      if (!t) throw new Error('Not authenticated');
      return t;
    },
    isAuthenticated: function () {
      return !!this.getToken();
    }
  };
})();
