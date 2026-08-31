(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  }

  function addQuantity(section, label) {
    var fields = section.querySelector('.qs-component-editor .qs-editor-fields');
    if (!fields || section.querySelector('[data-component-field="quantity"]')) return;
    var input = document.createElement('input');
    input.type = 'number';
    input.min = '1';
    input.step = '1';
    input.placeholder = 'Quantity';
    input.setAttribute('aria-label', label + ' quantity');
    input.setAttribute('data-component-field', 'quantity');
    fields.appendChild(input);
  }

  function syncEdgeVisualState(selector) {
    if (!selector) return;
    ['top', 'right', 'bottom', 'left'].forEach(function (edge) {
      var input = selector.querySelector(
        '[data-edge-position="' + edge.charAt(0).toUpperCase() + edge.slice(1) + '"], ' +
        '[data-filler-edge-position="' + edge.charAt(0).toUpperCase() + edge.slice(1) + '"]'
      );
      selector.classList.toggle('qs-edge-selected-' + edge, !!(input && input.checked));
    });
  }

  function edgeSelectorMarkup() {
    var wrap = document.createElement('div');
    wrap.className = 'qs-edge-selector qs-filler-edge-selector';
    wrap.setAttribute('data-filler-edge-selector', '');
    ['Top', 'Right', 'Bottom', 'Left'].forEach(function (edge) {
      var label = document.createElement('label');
      label.className = 'qs-edge-choice qs-edge-' + edge.toLowerCase();
      label.innerHTML = '<input type="checkbox" value="' + edge + '" data-filler-edge-position="' + edge + '"><span></span><em>' + edge + ' edge</em>';
      wrap.appendChild(label);
    });
    var face = document.createElement('div');
    face.className = 'qs-edge-face';
    face.textContent = 'Face';
    wrap.appendChild(face);
    var save = document.createElement('button');
    save.type = 'button';
    save.className = 'qs-save-edges';
    save.textContent = 'Save';
    wrap.appendChild(save);
    return wrap;
  }

  function syncFillerChecks(section) {
    var hidden = section.querySelector('[data-component-field="edges_seen"]');
    var selector = section.querySelector('[data-filler-edge-selector]');
    if (!hidden || !selector) return;
    var selected = String(hidden.value || '').toLowerCase();
    selector.querySelectorAll('[data-filler-edge-position]').forEach(function (input) {
      input.checked = selected.indexOf(String(input.value).toLowerCase()) !== -1;
    });
    selector.classList.toggle('is-saved', !!hidden.value);
    syncEdgeVisualState(selector);
  }

  function setupFillerEdges(section) {
    var select = section.querySelector('[data-component-field="edges_seen"]');
    if (!select || select.type === 'hidden') return;
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.setAttribute('data-component-field', 'edges_seen');
    select.parentNode.insertBefore(hidden, select);
    var label = select.previousElementSibling;
    if (label && label.tagName === 'LABEL') label.textContent = 'Edge/s Seen*';
    var selector = edgeSelectorMarkup();
    select.parentNode.insertBefore(selector, select.nextSibling);
    select.remove();

    function sync() {
      hidden.value = Array.prototype.map.call(selector.querySelectorAll('[data-filler-edge-position]:checked'), function (input) { return input.value; }).join(' + ');
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
      selector.classList.toggle('is-saved', !!hidden.value);
      syncEdgeVisualState(selector);
    }
    selector.addEventListener('change', sync);
    selector.querySelector('.qs-save-edges').addEventListener('click', function () {
      sync();
      var button = selector.querySelector('.qs-save-edges');
      if (!hidden.value || !button) return;
      button.textContent = 'Saved';
      window.setTimeout(function () { button.textContent = 'Save'; }, 900);
    });
    syncEdgeVisualState(selector);
  }

  function addEndPanelFaceOption(section) {
    var select = section.querySelector('[data-component-field="faces_seen"]');
    if (!select || select.querySelector('option[value="1 Face / 3 Returns (100mm)"]')) return;
    var option = document.createElement('option');
    option.value = '1 Face / 3 Returns (100mm)';
    option.textContent = '1 Face / 3 Returns (100mm)';
    var twoFaces = select.querySelector('option[value="2 Faces"]');
    select.insertBefore(option, twoFaces || null);
  }

  function stripKickboardNote() {
    document.querySelectorAll('.qs-kickboard-notes li').forEach(function (item) {
      if (/Cost at LM Rate/i.test(item.textContent || '')) item.remove();
    });
  }

  function storedValue(row, key) {
    var input = row && row.querySelector('[name$="[' + key + ']"]');
    return input ? input.value : '';
  }

  function mm(value, axis) {
    return value ? String(value) + 'mm (' + axis + ')' : '';
  }

  function updateSummary() {
    var paint = document.querySelector('[data-summary="paint_colour"]');
    if (paint) {
      var value = (paint.textContent || '').trim();
      var hide = !value || value === '—';
      paint.hidden = hide;
      var term = paint.previousElementSibling;
      if (term && term.tagName === 'DT') term.hidden = hide;
    }

    document.querySelectorAll('.qs-summary-item').forEach(function (item) {
      var action = item.querySelector('[data-summary-action]');
      if (!action) return;
      var component = action.dataset.component;
      var index = Number(action.dataset.rowIndex);
      var section = document.querySelector('.qs-component[data-component="' + component + '"]');
      var row = section && section.querySelectorAll('.qs-repeater-row')[index];
      if (!row) return;

      var width = storedValue(row, 'width');
      var height = storedValue(row, 'height');
      var primary = item.querySelector('.qs-summary-item-primary');
      var type = storedValue(row, 'type');

      if (component === 'doors_drawers' && type === 'Drawer Bank') {
        height = ['top_height','top_middle_height','middle_height','bottom_middle_height','bottom_height'].reduce(function (sum, key) {
          return sum + (Number(storedValue(row, key)) || 0);
        }, 0);
      } else if (component === 'kickboards') {
        width = storedValue(row, 'length');
      }

      if (primary && width && height) {
        var text = mm(width, 'w') + ' × ' + mm(height, 'h');
        if (primary.textContent !== text) primary.textContent = text;
      }
    });
  }

  function highlightEditor(component) {
    var section = document.querySelector('.qs-component[data-component="' + component + '"]');
    if (!section) return;
    document.querySelectorAll('.qs-edit-highlight').forEach(function (node) { node.classList.remove('qs-edit-highlight'); });
    var editor = section.querySelector('.qs-door-entry-editor, .qs-component-editor') || section;
    editor.classList.add('qs-edit-highlight');
    window.setTimeout(function () { editor.classList.remove('qs-edit-highlight'); }, 4000);
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    if (!form) return;

    var endPanels = document.querySelector('.qs-end-panels');
    var fillers = document.querySelector('.qs-fillers');
    if (endPanels) {
      addQuantity(endPanels, 'End panel');
      addEndPanelFaceOption(endPanels);
      var endPanelSelector = endPanels.querySelector('.qs-edge-selector');
      syncEdgeVisualState(endPanelSelector);
      if (endPanelSelector) {
        endPanelSelector.addEventListener('change', function () {
          syncEdgeVisualState(endPanelSelector);
        });
      }
    }
    if (fillers) {
      addQuantity(fillers, 'Filler');
      setupFillerEdges(fillers);
    }
    stripKickboardNote();

    document.addEventListener('click', function (event) {
      var button = event.target.closest('.qs-commit-component');
      if (button) {
        var section = button.closest('.qs-configured-component');
        if (section && (section.classList.contains('qs-end-panels') || section.classList.contains('qs-fillers'))) {
          var quantity = section.querySelector('[data-component-field="quantity"]');
          if (!quantity || Number(quantity.value) <= 0) {
            if (quantity) {
              quantity.setCustomValidity('Please enter a quantity.');
              quantity.reportValidity();
              setTimeout(function () { quantity.setCustomValidity(''); }, 0);
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
          }
          var desired = quantity.value;
          var editing = section.dataset.editingIndex;
          setTimeout(function () {
            var rows = section.querySelectorAll('.qs-repeater-row');
            var row = editing !== '' ? rows[Number(editing)] : rows[rows.length - 1];
            var hidden = row && row.querySelector('[name$="[quantity]"]');
            if (hidden) {
              hidden.value = desired;
              hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
            quantity.value = '';
            updateSummary();
          }, 0);
        }
      }

      var edit = event.target.closest('[data-summary-action="edit"]');
      if (edit) {
        setTimeout(function () {
          highlightEditor(edit.dataset.component);
          if (edit.dataset.component === 'fillers' && fillers) syncFillerChecks(fillers);
          if (edit.dataset.component === 'end_panels' && endPanels) syncEdgeVisualState(endPanels.querySelector('.qs-edge-selector'));
        }, 0);
      }
    }, true);

    var observer = new MutationObserver(updateSummary);
    var summary = document.querySelector('.qs-summary-items');
    if (summary) observer.observe(summary, { childList: true, subtree: true, characterData: true });
    form.addEventListener('input', function () { setTimeout(updateSummary, 0); });
    form.addEventListener('change', function () { setTimeout(updateSummary, 0); });
    updateSummary();
  });
}());
