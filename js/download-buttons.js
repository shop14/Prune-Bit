(function () {
  if (document.getElementById('dl-android')) return;
  var privatePages = ['dashboard', 'send', 'receive', 'addresses', 'history', 'profile'];
  var path = window.location.pathname;
  var isPrivate = privatePages.some(function (p) { return path.includes(p); });
  if (isPrivate) return;
  function addAndroidButton() {
    var container = document.querySelector('.footer-bottom');
    if (!container) {
      setTimeout(addAndroidButton, 500);
      return;
    }
    var row = document.createElement('div');
    row.id = 'dl-android';
    row.className = 'd-flex flex-wrap justify-content-center align-items-center gap-3 mb-3';
    row.innerHTML =
      '<a href="/Prunebit_app.apk" style="display:inline-flex;align-items:center;text-decoration:none;" download>' +
        '<img src="/img/apk-android-mini.svg" alt="Download APK" style="height:22px;width:auto;">' +
      '</a>' +
      '<img src="/img/apple-ios.svg" alt="App Store" style="height:22px;width:auto;">';
    container.insertBefore(row, container.firstChild);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addAndroidButton);
  } else {
    addAndroidButton();
  }
})();
