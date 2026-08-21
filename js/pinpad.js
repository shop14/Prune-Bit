var PinPad = {
  instances: {},
  _uid: 0,

  create: function(opts) {
    var id = opts.id || 'pinpad_' + (++PinPad._uid);
    var container = document.getElementById(opts.container);
    if (!container) return null;

    var wrapper = document.createElement('div');
    wrapper.className = 'pinpad-wrapper';
    wrapper.id = id + '_wrapper';

    var displayRow = document.createElement('div');
    displayRow.className = 'pinpad-display-row';

    var display = document.createElement('div');
    display.className = 'pinpad-display';
    display.id = id + '_display';
    for (var i = 0; i < 4; i++) {
      var dot = document.createElement('span');
      dot.className = 'pinpad-dot';
      dot.id = id + '_dot_' + i;
      display.appendChild(dot);
    }
    displayRow.appendChild(display);

    var hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.id = id;
    hiddenInput.name = opts.name || id;
    hiddenInput.value = '';

    var keypad = document.createElement('div');
    keypad.className = 'pinpad-keypad';
    keypad.id = id + '_keypad';
    keypad.style.display = 'none';

    var keys = [
      ['1','2','3'],
      ['4','5','6'],
      ['7','8','9'],
      ['','0','⌫']
    ];

    for (var r = 0; r < keys.length; r++) {
      var row = document.createElement('div');
      row.className = 'pinpad-row';
      for (var c = 0; c < keys[r].length; c++) {
        var val = keys[r][c];
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pinpad-key' + (val === '' ? ' pinpad-key-empty' : '') + (val === '⌫' ? ' pinpad-key-backspace' : '');
        btn.dataset.pinpad = id;
        if (val === '') {
          btn.disabled = true;
        } else {
          btn.textContent = val;
          btn.dataset.value = val;
        }
        row.appendChild(btn);
      }
      keypad.appendChild(row);
    }

    wrapper.appendChild(displayRow);
    wrapper.appendChild(hiddenInput);
    wrapper.appendChild(keypad);

    // Insert the wrapper before a target element or at the end of container
    if (opts.insertBefore) {
      container.insertBefore(wrapper, document.getElementById(opts.insertBefore));
    } else {
      container.appendChild(wrapper);
    }

    // Click on display opens keypad
    display.addEventListener('click', function() {
      PinPad.open(id);
    });

    // Keypad button handlers
    keypad.addEventListener('click', function(e) {
      var btn = e.target.closest('.pinpad-key');
      if (!btn || btn.disabled) return;
      var pid = btn.dataset.pinpad;
      var val = btn.dataset.value;
      if (val === '⌫') {
        PinPad.backspace(pid);
      } else if (val) {
        PinPad.type(pid, val);
      }
    });

    var inst = {
      id: id,
      value: '',
      input: hiddenInput,
      display: display,
      keypad: keypad,
      wrapper: wrapper,
      maxLength: opts.maxLength || 4,
      onComplete: opts.onComplete || null,
      onInput: opts.onInput || null
    };

    PinPad.instances[id] = inst;
    return inst;
  },

  open: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    // Close all other keypads first
    for (var k in PinPad.instances) {
      if (k !== id && PinPad.instances[k].keypad.style.display !== 'none') {
        PinPad.close(k);
      }
    }
    inst.keypad.style.display = 'block';
    inst.wrapper.classList.add('pinpad-active');
  },

  close: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    inst.keypad.style.display = 'none';
    inst.wrapper.classList.remove('pinpad-active');
  },

  type: function(id, digit) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    if (inst.value.length >= inst.maxLength) return;
    inst.value += digit;
    inst.input.value = inst.value;
    PinPad._updateDisplay(inst);
    if (typeof inst.onInput === 'function') {
      inst.onInput(inst.value);
    }
    if (inst.value.length >= inst.maxLength) {
      if (typeof inst.onComplete === 'function') {
        setTimeout(function() { inst.onComplete(inst.value); }, 200);
      }
      setTimeout(function() { PinPad.close(id); }, 300);
    }
    inst.input.dispatchEvent(new Event('input', { bubbles: true }));
  },

  backspace: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    if (inst.value.length <= 0) return;
    inst.value = inst.value.slice(0, -1);
    inst.input.value = inst.value;
    PinPad._updateDisplay(inst);
    if (typeof inst.onInput === 'function') {
      inst.onInput(inst.value);
    }
    inst.input.dispatchEvent(new Event('input', { bubbles: true }));
  },

  reset: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    inst.value = '';
    inst.input.value = '';
    PinPad._updateDisplay(inst);
  },

  _updateDisplay: function(inst) {
    var dots = inst.display.querySelectorAll('.pinpad-dot');
    for (var i = 0; i < dots.length; i++) {
      dots[i].className = 'pinpad-dot' + (i < inst.value.length ? ' filled' : '');
    }
  },

  getValue: function(id) {
    var inst = PinPad.instances[id];
    return inst ? inst.value : '';
  },

  clear: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    inst.value = '';
    inst.input.value = '';
    PinPad._updateDisplay(inst);
  },

  destroy: function(id) {
    var inst = PinPad.instances[id];
    if (!inst) return;
    if (inst.wrapper.parentNode) {
      inst.wrapper.parentNode.removeChild(inst.wrapper);
    }
    delete PinPad.instances[id];
  }
};

// Close keypad when clicking outside
document.addEventListener('click', function(e) {
  for (var id in PinPad.instances) {
    var inst = PinPad.instances[id];
    if (inst.keypad.style.display === 'none') continue;
    if (!inst.wrapper.contains(e.target)) {
      PinPad.close(id);
    }
  }
});
