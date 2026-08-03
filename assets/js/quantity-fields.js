(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function editorPanel(section, type) {
    return section.querySelector('[data-editor-type="' + type + '"]');
  }

  function editorField(section, type, key) {
    var panel = editorPanel(section, type);
    return panel ? panel.querySelector('[data-editor-field="' + key + '"]') : null;
  }

  function storedValue(row, key) {
    var input = row.querySelector('[name$="[' + key + ']"]');
    return input ? input.value : '';
  }

  function addFrontQuantityField(section, type) {
    var panel = editorPanel(section, type);
    var fields = panel ? panel.querySelector('.qs-front-editor-fields') : null;
    if (!fields || fields.querySelector('[data-editor-field="quantity"]')) {
      return;
    }

    var quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.min = '1';
    quantity.step = '1';
    quantity.placeholder = 'Quantity';
    quantity.setAttribute('aria-label', type + ' quantity');
    quantity.setAttribute('data-editor-field', 'quantity');
    fields.appendChild(quantity);
  }

  function clearDefaultQuantityValues(root) {
    root.querySelectorAll('[data-editor-field="quantity"], [data-component-field="quantity"]').forEach(function (input) {
      input.removeAttribute('value');
      if (input.value === '1') {
        input.value = '';
      }
    });
  }

  function reportMissingQuantity(input) {
    if (!input || Number(input.value) <= 0) {
      if (input) {
        input.setCustomValidity('Please enter a quantity.');
        input.reportValidity();
        window.setTimeout(function () {
          input.setCustomValidity('');
        }, 0);
      }
      return true;
    }
    return false;
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    if (!form) {
      return;
    }

    document.querySelectorAll('.qs-doors-drawers').forEach(function (section) {
      addFrontQuantityField(section, 'Door');
      addFrontQuantityField(section, 'Drawer');
      clearDefaultQuantityValues(section);
    });

    document.querySelectorAll('.qs-configured-component').forEach(clearDefaultQuantityValues);

    document.addEventListener('click', function (event) {
      var doorButton = event.target.closest('.qs-commit-item');
      if (doorButton) {
        var section = doorButton.closest('.qs-doors-drawers');
        var type = section ? (section.dataset.activeType || 'Door') : '';
        var quantityInput = section ? editorField(section, type, 'quantity') : null;

        if (reportMissingQuantity(quantityInput)) {
          event.preventDefault();
          event.stopImmediatePropagation();
          return;
        }

        var desiredQuantity = Number(quantityInput.value);
        var editingIndex = section.dataset.editingIndex;
        var width = editorField(section, type, 'width');
        var height = editorField(section, type, 'height');
        var existingRows = Array.prototype.slice.call(section.querySelectorAll('.qs-repeater-row'));
        var matchingRow = null;
        var previousQuantity = 0;

        if (editingIndex !== '') {
          matchingRow = existingRows[Number(editingIndex)] || null;
        } else if (type !== 'Drawer Bank') {
          matchingRow = existingRows.find(function (row) {
            return storedValue(row, 'type') === type &&
              String(storedValue(row, 'width')) === String(width ? width.value : '') &&
              String(storedValue(row, 'height')) === String(height ? height.value : '');
          }) || null;
          previousQuantity = matchingRow ? Number(storedValue(matchingRow, 'quantity')) || 0 : 0;
        }

        window.setTimeout(function () {
          var rows = Array.prototype.slice.call(section.querySelectorAll('.qs-repeater-row'));
          var row = editingIndex !== '' ? rows[Number(editingIndex)] : null;

          if (!row && type !== 'Drawer Bank') {
            row = rows.find(function (candidate) {
              return storedValue(candidate, 'type') === type &&
                String(storedValue(candidate, 'width')) === String(width ? width.value : '') &&
                String(storedValue(candidate, 'height')) === String(height ? height.value : '');
            }) || null;
          }

          if (!row && type === 'Drawer Bank') {
            row = rows[rows.length - 1] || null;
          }

          if (row) {
            var hiddenQuantity = row.querySelector('[name$="[quantity]"]');
            if (hiddenQuantity) {
              hiddenQuantity.value = editingIndex === '' && type !== 'Drawer Bank'
                ? String(previousQuantity + desiredQuantity)
                : String(desiredQuantity);
              hiddenQuantity.dispatchEvent(new Event('input', { bubbles: true }));
            }
          }

          section.querySelectorAll('[data-editor-field="quantity"]').forEach(function (input) {
            input.value = '';
          });
        }, 0);

        return;
      }

      var componentButton = event.target.closest('.qs-commit-component');
      if (!componentButton) {
        return;
      }

      var componentSection = componentButton.closest('.qs-configured-component');
      var componentQuantity = componentSection ? componentSection.querySelector('[data-component-field="quantity"]') : null;
      if (componentQuantity && reportMissingQuantity(componentQuantity)) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      window.setTimeout(function () {
        if (componentQuantity) {
          componentQuantity.value = '';
        }
      }, 0);
    }, true);
  });
}());
