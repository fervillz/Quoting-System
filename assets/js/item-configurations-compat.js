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
