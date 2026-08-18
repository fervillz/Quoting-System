(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  }

  function storedValue(row, key) {
    var input = row && row.querySelector('[name$="[' + key + ']"]');
    return input ? String(input.value || '') : '';
  }

  function syncPaintedEditor(editor, timberValue) {
    if (!editor || !/paint/i.test(timberValue || '')) return;
    var timber = editor.querySelector('[data-item-config-field="timber"]');
    var paint = editor.querySelector('[data-item-config-field="paint_colour"]');
    var finish = editor.querySelector('[data-item-config-field="finish"]');
    if (timber && !timber.value) timber.value = timberValue;
    if (paint) {
      paint.hidden = false;
      paint.disabled = false;
    }
    if (finish) {
      finish.hidden = true;
      finish.disabled = true;
      finish.value = '';
    }
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    if (!form) return;

    document.querySelectorAll('.qs-legacy-door-specifications input, .qs-legacy-door-specifications select, .qs-legacy-door-specifications textarea').forEach(function (input) {
      input.removeAttribute('required');
    });

    // Keep the same Painted Oak fallback the original Door Specifications
    // picker provided when Painted Oak is not a Timber taxonomy product.
    document.querySelectorAll('[data-item-config-field="timber"]').forEach(function (select) {
      var hasPainted = Array.prototype.some.call(select.options, function (option) {
        return /paint/i.test(option.textContent || '');
      });
      if (!hasPainted) {
        var option = document.createElement('option');
        option.value = 'Painted Oak';
        option.textContent = 'Painted Oak';
        select.appendChild(option);
      }
    });

    // Restore the carry-forward value for existing quotes whose Painted Oak
    // value could not be selected until the fallback option above existed.
    var doorSection = document.querySelector('.qs-doors-drawers');
    if (doorSection) {
      var doorRows = doorSection.querySelectorAll('.qs-repeater-row');
      var legacyTimber = form.querySelector('[name="timber"]:checked');
      var lastDoorTimber = doorRows.length ? storedValue(doorRows[doorRows.length - 1], 'timber') : '';
      var doorTimber = lastDoorTimber || (legacyTimber ? legacyTimber.value : '');
      doorSection.querySelectorAll('.qs-item-editor').forEach(function (editor) {
        syncPaintedEditor(editor, doorTimber);
      });
    }

    document.querySelectorAll('.qs-configured-component').forEach(function (section) {
      var rows = section.querySelectorAll('.qs-repeater-row');
      if (!rows.length) return;
      syncPaintedEditor(section.querySelector('.qs-component-editor'), storedValue(rows[rows.length - 1], 'timber'));
    });

    // Notes are item-specific. Specifications may carry forward, but notes do not.
    document.querySelectorAll('[data-item-config-field="notes"]').forEach(function (notes) {
      notes.value = '';
    });

    // Match the client flow: Kick Material first, then Timber / Finish, then dimensions.
    var kickboardEditor = document.querySelector('.qs-kickboards .qs-component-editor');
    if (kickboardEditor) {
      var config = kickboardEditor.querySelector('.qs-item-config-block');
      var fields = kickboardEditor.querySelector('.qs-kickboard-fields');
      if (config && fields) kickboardEditor.insertBefore(config, fields);
    }
  });
}());
