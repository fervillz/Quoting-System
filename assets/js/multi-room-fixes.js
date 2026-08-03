(function () {
  'use strict';

  function updateRoomTotals(totals) {
    if (!totals) return;
    document.querySelectorAll('[data-room-id]').forEach(function (button) {
      var roomId = button.dataset.roomId;
      if (!Object.prototype.hasOwnProperty.call(totals, roomId)) return;
      var small = button.querySelector('small');
      if (!small) {
        small = document.createElement('small');
        button.appendChild(small);
      }
      small.textContent = '$' + Number(totals[roomId] || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    });
  }

  if (window.fetch) {
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var calculation = init && init.body instanceof FormData && init.body.get('action') === 'qs_builder_recalculate';
      return nativeFetch(input, init).then(function (response) {
        if (calculation) {
          response.clone().json().then(function (payload) {
            if (payload && payload.success && payload.data) {
              updateRoomTotals(payload.data.room_subtotals);
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

    syncPaintedState(form);
  });
}());
