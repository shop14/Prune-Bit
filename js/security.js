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

function showToast(msg, type) {
  type = type || 'error';
  var container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }
  var t = document.createElement('div');
  t.className = 'toast-notification toast-' + type;
  t.innerHTML = '<span>' + escapeHtml(msg) + '</span><button class="toast-close">&times;</button>';
  container.appendChild(t);
  t.querySelector('.toast-close').addEventListener('click', function() { t.remove(); });
  setTimeout(function() { if (t.parentElement) { t.classList.add('toast-fade'); setTimeout(function() { if (t.parentElement) t.remove(); }, 300); } }, 4000);
}
