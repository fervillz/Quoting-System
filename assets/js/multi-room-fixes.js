(function () {
  'use strict';

  function isRetailQuote() {
    var pricingToggle = document.querySelector('.qs-builder-form input[name="pricing_type"]');
    return !!(pricingToggle && pricingToggle.checked);
  }

  function displayRoomTotals(totals, grandTotal) {
    var display = {};
    Object.keys(totals || {}).forEach(function (roomId) {
      display[roomId] = Number(totals[roomId] || 0);
    });

    if (!isRetailQuote()) return display;

    Object.keys(display).forEach(function (roomId) {
      display[roomId] = Math.round(display[roomId] * 1.2222 * 100) / 100;
    });

    var roomIds = Object.keys(display);
    var expected = Number(grandTotal || 0);
    if (expected && roomIds.length) {
      var sum = roomIds.reduce(function (total, roomId) { return total + display[roomId]; }, 0);
      var difference = Math.round((expected - sum) * 100) / 100;
      var lastRoom = roomIds[roomIds.length - 1];
      display[lastRoom] = Math.round((display[lastRoom] + difference) * 100) / 100;
    }

    return display;
  }

  function updateRoomTotals(totals, grandTotal) {
    if (!totals) return;
    var display = displayRoomTotals(totals, grandTotal);
    document.querySelectorAll('[data-room-id]').forEach(function (button) {
      var roomId = button.dataset.roomId;
      if (!Object.prototype.hasOwnProperty.call(display, roomId)) return;
      var small = button.querySelector('small');
      if (!small) {
        small = document.createElement('small');
        button.appendChild(small);
      }
      small.textContent = '$' + Number(display[roomId] || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    });
  }

  function displayedGrandTotal() {
    var subtotal = document.querySelector('[data-qs-subtotal]');
    if (!subtotal) return 0;
    return Number(String(subtotal.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
  }

  if (window.fetch) {
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var calculation = init && init.body instanceof FormData && init.body.get('action') === 'qs_builder_recalculate';
      return nativeFetch(input, init).then(function (response) {
        if (calculation) {
          response.clone().json().then(function (payload) {
            if (payload && payload.success && payload.data) {
              updateRoomTotals(payload.data.room_subtotals, payload.data.subtotal);
            }
          }).catch(function () {});
        }
        return response;
      });
    };
  }

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  }

  function resetVisibleEditors(form) {
    form.querySelectorAll('.qs-doors-drawers [data-editor-field]').forEach(function (input) {
      input.value = input.dataset.editorField === 'drawer_count' ? '3' : '';
    });
    var bankCount = form.querySelector('.qs-doors-drawers [data-editor-field="drawer_count"]');
    if (bankCount) bankCount.dispatchEvent(new Event('change', { bubbles: true }));

    form.querySelectorAll('.qs-configured-component').forEach(function (section) {
      section.dataset.editingIndex = '';
      section.querySelectorAll('[data-component-field]').forEach(function (input) {
        input.value = input.dataset.componentField === 'quantity' ? '' : (input.dataset.defaultValue || '');
      });
      section.querySelectorAll('[data-edge-position], [data-filler-edge-position]').forEach(function (input) {
        input.checked = false;
      });
      section.querySelectorAll('.qs-edge-selector').forEach(function (selector) {
        selector.classList.remove('is-saved', 'has-edge-top', 'has-edge-right', 'has-edge-bottom', 'has-edge-left');
      });
    });
  }

  function syncPaintedState(form) {
    var checked = form.querySelector('[name="timber"]:checked');
    var option = checked && checked.closest('.qs-product-option');
    var painted = !!(option && /paint/i.test(option.dataset.optionLabel || option.textContent || ''));
    var paintField = form.querySelector('[data-paint-colour-field]');
    var paintInput = paintField && paintField.querySelector('[name="paint_colour"]');
    var finishField = form.querySelector('[data-product-field="finish"]');

    if (paintField) paintField.hidden = !painted;
    if (paintInput) {
      paintInput.disabled = !painted;
      paintInput.required = painted;
    }
    if (finishField) {
      finishField.hidden = painted;
      finishField.querySelectorAll('input').forEach(function (input) { input.disabled = painted; });
    }
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    if (!form) return;

    document.addEventListener('click', function (event) {
      if (!event.target.closest('.qs-room-tab, .qs-summary-room-tab, .qs-add-room, .qs-duplicate-room, .qs-delete-room')) return;
      window.setTimeout(function () {
        resetVisibleEditors(form);
        syncPaintedState(form);
      }, 0);
    });

    var pricingToggle = form.querySelector('input[name="pricing_type"]');
    if (pricingToggle) {
      pricingToggle.addEventListener('change', function () {
        updateRoomTotals((window.QSMultiRoom || {}).roomSubtotals || {}, displayedGrandTotal());
      });
    }

    syncPaintedState(form);
    updateRoomTotals((window.QSMultiRoom || {}).roomSubtotals || {}, displayedGrandTotal());
  });
}());
