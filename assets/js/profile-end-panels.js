(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function storedValue(row, key) {
    var input = row.querySelector('[name$="[' + key + ']"]');
    return input ? input.value : '';
  }

  function addEditor(section) {
    var actions = section.querySelector('.qs-type-actions');
    var editor = section.querySelector('.qs-door-entry-editor');
    var commit = editor ? editor.querySelector('.qs-commit-item') : null;
    if (!actions || !editor || !commit || actions.querySelector('[data-select-type="Profile End Panel"]')) {
      return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-select-type', 'Profile End Panel');
    button.setAttribute('aria-pressed', 'false');
    button.textContent = '+ Add Profile End Panel';
    actions.appendChild(button);

    var panel = document.createElement('div');
    panel.className = 'qs-item-editor';
    panel.setAttribute('data-editor-type', 'Profile End Panel');
    panel.hidden = true;
    panel.innerHTML = '' +
      '<div class="qs-editor-fields qs-front-editor-fields">' +
        '<input type="number" min="1" step="1" data-editor-field="width" placeholder="Width mm" aria-label="Profile end panel width in millimetres">' +
        '<input type="number" min="1" step="1" data-editor-field="height" placeholder="Height mm" aria-label="Profile end panel height in millimetres">' +
        '<input type="number" min="1" step="1" data-editor-field="quantity" placeholder="Quantity" aria-label="Profile end panel quantity">' +
      '</div>' +
      '<p class="qs-item-instruction">Uses the selected door profile pricing.</p>';
    editor.insertBefore(panel, commit);
  }

  function addSummaryAction(actions, label, icon, action, index) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'qs-summary-action';
    button.setAttribute('data-summary-action', action);
    button.setAttribute('data-component', 'doors_drawers');
    button.setAttribute('data-row-index', String(index));
    button.setAttribute('aria-label', label);

    var image = document.createElement('img');
    image.src = icon || '';
    image.alt = '';
    button.appendChild(image);
    actions.appendChild(button);
  }

  function renderSummary(section, summary) {
    if (!summary || summary.querySelector('[data-profile-end-panel-group]')) {
      return;
    }

    var items = Array.prototype.map.call(section.querySelectorAll('.qs-repeater-row'), function (row, index) {
      return {
        row: row,
        index: index,
        type: storedValue(row, 'type'),
        width: storedValue(row, 'width'),
        height: storedValue(row, 'height'),
        quantity: storedValue(row, 'quantity')
      };
    }).filter(function (item) {
      return item.type === 'Profile End Panel' && Number(item.quantity) > 0;
    });

    if (!items.length) {
      return;
    }

    var config = window.QSProfileEndPanels || {};
    var group = document.createElement('div');
    group.className = 'qs-summary-group';
    group.setAttribute('data-profile-end-panel-group', '1');

    var heading = document.createElement('strong');
    heading.className = 'qs-summary-group-title';
    heading.textContent = 'Profile End Panels (' + items.length + ')';
    group.appendChild(heading);

    items.forEach(function (item) {
      var entry = document.createElement('div');
      entry.className = 'qs-summary-item';

      var content = document.createElement('div');
      content.className = 'qs-summary-item-content';
      var primary = document.createElement('span');
      primary.className = 'qs-summary-item-primary';
      primary.textContent = item.width + 'mm (w) × ' + item.height + 'mm (h)';
      content.appendChild(primary);

      var quantity = document.createElement('span');
      quantity.className = 'qs-summary-item-quantity';
      quantity.textContent = 'Qty. ' + item.quantity;

      var actions = document.createElement('div');
      actions.className = 'qs-summary-item-actions';
      addSummaryAction(actions, 'Edit item', config.editIcon, 'edit', item.index);
      addSummaryAction(actions, 'Remove item', config.removeIcon, 'remove', item.index);

      entry.appendChild(content);
      entry.appendChild(quantity);
      entry.appendChild(actions);
      group.appendChild(entry);
    });

    summary.appendChild(group);
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    var section = document.querySelector('.qs-doors-drawers');
    var summary = document.querySelector('.qs-summary-items');
    if (!form || !section) {
      return;
    }

    addEditor(section);
    renderSummary(section, summary);

    if (summary && window.MutationObserver) {
      var rendering = false;
      var observer = new MutationObserver(function () {
        if (rendering) {
          return;
        }
        rendering = true;
        renderSummary(section, summary);
        rendering = false;
      });
      observer.observe(summary, { childList: true, subtree: true });
    }

    form.addEventListener('input', function () {
      window.setTimeout(function () {
        renderSummary(section, summary);
      }, 0);
    });
    form.addEventListener('change', function () {
      window.setTimeout(function () {
        renderSummary(section, summary);
      }, 0);
    });
  });
}());
