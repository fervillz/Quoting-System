(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
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
