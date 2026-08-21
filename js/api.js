const API_BASE = '/api';

function handleCaptchaRequired(data) {
  if (data && data.captchaRequired) {
    window.location.href = '/captcha.html';
    return true;
  }
  return false;
}

async function apiPost(path, payload) {
  try {
    var resp = await fetch(API_BASE + path, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    var data = await resp.json();
    handleCaptchaRequired(data);
    return data;
  } catch (e) {
    return { error: 'Network error. Please check your connection.' };
  }
}

async function apiGet(path, headers) {
  try {
    headers = headers || {};
    var resp = await fetch(API_BASE + path, {
      method: 'GET',
      headers: headers
    });
    var data = await resp.json();
    handleCaptchaRequired(data);
    return data;
  } catch (e) {
    return { error: 'Network error. Please check your connection.' };
  }
}
