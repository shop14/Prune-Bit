export function formatBalance(balance, coin) {
  if (!balance) return '0';
  return `${parseFloat(balance).toFixed(8)} ${coin.toUpperCase()}`;
}

export function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString();
}

export function truncateAddress(address, startLength = 6, endLength = 4) {
  if (!address) return '';
  return `${address.substring(0, startLength)}...${address.substring(address.length - endLength)}`;
}

export function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      alert('Copied to clipboard!');
    }).catch(() => {
      fallbackCopy(text);
    });
  } else {
    fallbackCopy(text);
  }
}

function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    alert('Copied to clipboard!');
  } catch (e) {
    console.error('Failed to copy');
  }
  document.body.removeChild(ta);
}

export function validatePassword(password) {
  if (!password || password.length < 8) {
    return { valid: false, message: 'Password must be at least 8 characters' };
  }
  if (!/[A-Z]/.test(password)) {
    return { valid: false, message: 'Password must contain at least one uppercase letter' };
  }
  if (!/[a-z]/.test(password)) {
    return { valid: false, message: 'Password must contain at least one lowercase letter' };
  }
  if (!/[0-9]/.test(password)) {
    return { valid: false, message: 'Password must contain at least one number' };
  }
  return { valid: true };
}

export function validatePIN(pin) {
  if (!pin || pin.length !== 4) {
    return { valid: false, message: 'PIN must be 4 digits' };
  }
  if (!/^\d{4}$/.test(pin)) {
    return { valid: false, message: 'PIN must contain only digits' };
  }
  return { valid: true };
}

export function validateMnemonic(mnemonic) {
  const words = mnemonic.trim().split(/\s+/);
  if (words.length !== 12 && words.length !== 24) {
    return { valid: false, message: 'Mnemonic must be 12 or 24 words' };
  }
  return { valid: true };
}
