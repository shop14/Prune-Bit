let ws = null;
let reconnectTimer = null;
let wsToken = null;
let pollTimer = null;
let failedAttempts = 0;
const WS_FALLBACK_THRESHOLD = 3;
const WS_FALLBACK_INTERVAL = (typeof window !== 'undefined' && window.PRUNEBIT_WS_INTERVAL) ? window.PRUNEBIT_WS_INTERVAL : 30000;

function connectWebSocket(token) {
  wsToken = token;
  if (ws && ws.readyState === 1) return;
  if (pollTimer) return;

  // This is a PHP deployment with no /ws server, so a WebSocket attempt would
  // only log a connection-refused error in the console. Go straight to the
  // polling fallback. A Node deployment can opt back into the real WebSocket
  // by setting window.PRUNEBIT_WS = true before loading this script.
  if (window.PRUNEBIT_WS !== true) {
    enablePollFallback();
    return;
  }

  try {
    ws = new WebSocket((window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.hostname + ':' + (window.location.port || (window.location.protocol === 'https:' ? '443' : '80')) + '/ws');
  } catch (e) {
    scheduleReconnect();
    return;
  }

  ws.onopen = function() {
    failedAttempts = 0;
    ws.send(JSON.stringify({ type: 'auth', token: wsToken }));
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
  };

  ws.onmessage = function(event) {
    try {
      var msg = JSON.parse(event.data);
      if (msg.type === 'sync_complete') {
        document.dispatchEvent(new CustomEvent('ws_sync_complete'));
      } else if (msg.type === 'balance_sync_complete') {
        document.dispatchEvent(new CustomEvent('ws_balance_sync_complete'));
      }
    } catch (e) {}
  };

  ws.onclose = function() {
    ws = null;
    scheduleReconnect();
  };

  ws.onerror = function() {
    failedAttempts++;
    if (failedAttempts >= WS_FALLBACK_THRESHOLD) {
      enablePollFallback();
      return;
    }
    if (ws) { ws.onclose = null; ws.close(); ws = null; }
  };
}

function scheduleReconnect() {
  if (reconnectTimer || pollTimer) return;
  reconnectTimer = setTimeout(function() {
    reconnectTimer = null;
    if (wsToken) connectWebSocket(wsToken);
  }, 10000);
}

// No WebSocket server available (typical PHP shared hosting): emulate the
// server push events with a periodic timer so pages auto-refresh as they
// did under Node.js, and stop the pointless reconnect loop. The interval
// defaults to 30s and can be overridden with window.PRUNEBIT_WS_INTERVAL.
// Fires once after 2s on page load so the first sync is near-instant.
function enablePollFallback() {
  if (ws) { ws.onclose = null; ws.onerror = null; try { ws.close(); } catch (e) {} ws = null; }
  if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
  if (pollTimer) return;
  setTimeout(function() {
    document.dispatchEvent(new CustomEvent('ws_sync_complete'));
    document.dispatchEvent(new CustomEvent('ws_balance_sync_complete'));
  }, 2000);
  pollTimer = setInterval(function() {
    document.dispatchEvent(new CustomEvent('ws_sync_complete'));
    document.dispatchEvent(new CustomEvent('ws_balance_sync_complete'));
  }, WS_FALLBACK_INTERVAL);
}

function disconnectWebSocket() {
  if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  if (ws) { ws.onclose = null; ws.close(); ws = null; }
}