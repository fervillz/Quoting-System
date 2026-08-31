(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  }

  function optionsMap(items) {
    var map = {};
    (items || []).forEach(function (item) { map[String(item.id)] = item.label; });
    return map;
  }

  ready(function () {
    var form = document.querySelector('.qs-builder-form');
    if (!form) return;

    var data = window.QSItemConfigurations || {};
    var labels = {
      door_profile: optionsMap(data.profiles),
      timber: optionsMap(data.timbers),
      handle_profile: optionsMap(data.handles),
      finish: optionsMap(data.finishes)
    };
    var products = {
      door_profile: data.profiles || [],
      timber: data.timbers || [],
      handle_profile: data.handles || [],
      finish: data.finishes || []
    };
    var profileBounds = {};
    (data.profiles || []).forEach(function (item) {
      if (item && item.id && item.bounds) profileBounds[String(item.id)] = item.bounds;
    });
    var panelBounds = data.panelBounds || null;

    function storedValue(row, key) {
      var input = row && row.querySelector('[name$="[' + key + ']"]');
      return input ? input.value : '';
    }

    function globalValue(name) {
      var checked = form.querySelector('[name="' + name + '"]:checked');
      if (checked) return checked.value;
      var input = form.querySelector('[name="' + name + '"]');
      return input ? input.value : '';
    }

    function globalDefaults(includeProfile) {
      var values = {
        timber: globalValue('timber'),
        finish: globalValue('finish'),
        paint_colour: globalValue('paint_colour')
      };
      if (includeProfile) {
        values.door_profile = globalValue('door_profile');
        values.handle_profile = globalValue('handle_profile');
      }
      return values;
    }

    function makeSelect(field, placeholder) {
      var select = document.createElement('select');
      select.setAttribute('data-item-config-field', field);
      select.setAttribute('aria-label', placeholder);
      var blank = document.createElement('option');
      blank.value = '';
      blank.textContent = placeholder;
      select.appendChild(blank);
      (products[field] || []).forEach(function (item) {
        var option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label;
        select.appendChild(option);
      });
      return select;
    }

    function makeConfigBlock(includeProfile) {
      var wrap = document.createElement('div');
      wrap.className = 'qs-item-config-block';
      var grid = document.createElement('div');
      grid.className = 'qs-item-config-grid';

      if (includeProfile) grid.appendChild(makeSelect('door_profile', 'Profile'));
      grid.appendChild(makeSelect('timber', 'Timber'));
      if (includeProfile) grid.appendChild(makeSelect('handle_profile', 'Handle Profile'));
      grid.appendChild(makeSelect('finish', 'Finish'));

      var paint = document.createElement('input');
      paint.type = 'text';
      paint.placeholder = 'Paint Colour';
      paint.setAttribute('data-item-config-field', 'paint_colour');
      paint.setAttribute('aria-label', 'Paint Colour');
      paint.hidden = true;
      grid.appendChild(paint);

      wrap.appendChild(grid);
      return wrap;
    }

    function makeNotes() {
      var notes = document.createElement('textarea');
      notes.rows = 2;
      notes.placeholder = 'Notes';
      notes.setAttribute('data-item-config-field', 'notes');
      notes.setAttribute('aria-label', 'Item notes');
      notes.className = 'qs-item-notes';
      return notes;
    }

    function configField(root, key) {
      return root && root.querySelector('[data-item-config-field="' + key + '"]');
    }

    function isPainted(root) {
      var timber = configField(root, 'timber');
      if (!timber || !timber.value) return false;
      var label = labels.timber[String(timber.value)] || (timber.selectedOptions[0] ? timber.selectedOptions[0].textContent : '');
      return /paint/i.test(label || '');
    }

    function syncPaintFields(root) {
      if (!root) return;
      var painted = isPainted(root);
      var paint = configField(root, 'paint_colour');
      var finish = configField(root, 'finish');
      if (paint) {
        paint.hidden = !painted;
        paint.disabled = !painted;
        if (!painted) paint.value = '';
      }
      if (finish) {
        finish.hidden = painted;
        finish.disabled = painted;
        if (painted) finish.value = '';
      }
    }

    function getConfig(root, includeProfile) {
      var values = {
        timber: configField(root, 'timber') ? configField(root, 'timber').value : '',
        finish: configField(root, 'finish') ? configField(root, 'finish').value : '',
        paint_colour: configField(root, 'paint_colour') ? configField(root, 'paint_colour').value.trim() : '',
        notes: configField(root, 'notes') ? configField(root, 'notes').value.trim() : ''
      };
      if (includeProfile) {
        values.door_profile = configField(root, 'door_profile') ? configField(root, 'door_profile').value : '';
        values.handle_profile = configField(root, 'handle_profile') ? configField(root, 'handle_profile').value : '';
      }
      return values;
    }

    function setConfig(root, values, includeProfile) {
      if (!root) return;
      ['timber', 'finish', 'paint_colour', 'notes'].concat(includeProfile ? ['door_profile', 'handle_profile'] : []).forEach(function (key) {
        var input = configField(root, key);
        if (input && values && values[key] !== undefined) input.value = values[key] || '';
      });
      syncPaintFields(root);
    }

    function rowConfig(row, includeProfile, fallback) {
      var values = {
        timber: storedValue(row, 'timber') || (fallback && fallback.timber) || '',
        finish: storedValue(row, 'finish') || (fallback && fallback.finish) || '',
        paint_colour: storedValue(row, 'paint_colour') || (fallback && fallback.paint_colour) || '',
        notes: storedValue(row, 'notes') || ''
      };
      if (includeProfile) {
        values.door_profile = storedValue(row, 'door_profile') || (fallback && fallback.door_profile) || '';
        values.handle_profile = storedValue(row, 'handle_profile') || (fallback && fallback.handle_profile) || '';
      }
      return values;
    }

    function productLabel(key, value) {
      if (!value) return '';
      return labels[key] && labels[key][String(value)] ? labels[key][String(value)] : String(value);
    }

    function hideLegacySpecifications() {
      document.querySelectorAll('.qs-form-section').forEach(function (section) {
        var heading = section.querySelector('h3');
        if (heading && heading.textContent.trim() === 'Door Specifications') {
          section.classList.add('qs-legacy-door-specifications');
          section.hidden = true;
        }
      });

      var summary = document.querySelector('.qs-builder-summary');
      if (!summary) return;
      var selectedHeading = Array.prototype.find.call(summary.querySelectorAll('h4'), function (heading) {
        return heading.textContent.trim() === 'Selected Specifications';
      });
      if (selectedHeading) {
        selectedHeading.hidden = true;
        var dl = selectedHeading.nextElementSibling;
        if (dl && dl.tagName === 'DL') dl.hidden = true;
      }
    }

    var doorSection = document.querySelector('.qs-doors-drawers');
    var doorLast = globalDefaults(true);
    if (doorSection) {
      var existingDoorRows = doorSection.querySelectorAll('.qs-repeater-row');
      if (existingDoorRows.length) doorLast = rowConfig(existingDoorRows[existingDoorRows.length - 1], true, doorLast);

      doorSection.querySelectorAll('.qs-item-editor').forEach(function (panel) {
        if (!panel.querySelector('.qs-item-config-block')) panel.insertBefore(makeConfigBlock(true), panel.firstChild);
        if (!panel.querySelector('.qs-item-notes')) panel.appendChild(makeNotes());
        setConfig(panel, doorLast, true);
      });
    }

    var componentLast = {};
    var sharedComponentLast = globalDefaults(false);

    function sharedComponentConfig(values) {
      return {
        timber: values && values.timber ? values.timber : '',
        finish: values && values.finish ? values.finish : '',
        paint_colour: values && values.paint_colour ? values.paint_colour : '',
        notes: ''
      };
    }

    function propagateSharedComponentConfig(sourceSection) {
      document.querySelectorAll('.qs-configured-component').forEach(function (section) {
        if (section === sourceSection || section.dataset.editingIndex !== '') return;
        if (section.querySelectorAll('.qs-repeater-row').length) return;
        var editor = section.querySelector('.qs-component-editor');
        if (editor) setConfig(editor, sharedComponentLast, false);
      });
    }

    document.querySelectorAll('.qs-configured-component').forEach(function (section) {
      var component = section.dataset.component;
      var editor = section.querySelector('.qs-component-editor');
      if (!editor) return;
      var block = makeConfigBlock(false);
      var materialLabel = editor.querySelector('.qs-editor-label');
      if (component === 'kickboards' && materialLabel) {
        var materialSelect = editor.querySelector('[data-component-field="material"]');
        if (materialSelect) editor.insertBefore(block, materialLabel);
        else editor.insertBefore(block, editor.firstChild);
      } else {
        editor.insertBefore(block, editor.firstChild);
      }
      editor.appendChild(makeNotes());

      var rows = section.querySelectorAll('.qs-repeater-row');
      componentLast[component] = rows.length ? rowConfig(rows[rows.length - 1], false, sharedComponentLast) : sharedComponentLast;
      if (rows.length) sharedComponentLast = sharedComponentConfig(componentLast[component]);
      setConfig(editor, componentLast[component], false);
    });

    form.addEventListener('change', function (event) {
      var root = event.target.closest('.qs-item-editor, .qs-component-editor');
      if (root && event.target.matches('[data-item-config-field="timber"]')) syncPaintFields(root);

      var componentEditor = event.target.closest('.qs-configured-component .qs-component-editor');
      if (componentEditor && event.target.matches('[data-item-config-field="timber"], [data-item-config-field="finish"], [data-item-config-field="paint_colour"]')) {
        sharedComponentLast = sharedComponentConfig(getConfig(componentEditor, false));
        propagateSharedComponentConfig(componentEditor.closest('.qs-configured-component'));
      }
    });

    function field(root, selector) {
      return root ? root.querySelector(selector) : null;
    }

    function value(root, selector) {
      var input = field(root, selector);
      return input ? String(input.value || '').trim() : '';
    }

    function report(input, message) {
      if (!input) return false;
      input.setCustomValidity(message);
      input.reportValidity();
      window.setTimeout(function () { input.setCustomValidity(''); }, 0);
      return false;
    }

    function clearPricingError(root) {
      if (!root) return;
      root.querySelectorAll('.qs-pricing-invalid').forEach(function (input) {
        input.classList.remove('qs-pricing-invalid');
        input.removeAttribute('aria-invalid');
      });
      var error = root.querySelector('.qs-pricing-error');
      if (error) error.remove();
    }

    function rangeText(bounds) {
      return 'width ' + bounds.min_width + '–' + bounds.max_width + 'mm and height ' +
        bounds.min_height + '–' + bounds.max_height + 'mm';
    }

    function showPricingError(root, inputs, message) {
      clearPricingError(root);
      (inputs || []).forEach(function (input) {
        if (!input) return;
        input.classList.add('qs-pricing-invalid');
        input.setAttribute('aria-invalid', 'true');
      });
      var error = document.createElement('div');
      error.className = 'qs-pricing-error';
      error.setAttribute('role', 'alert');
      error.textContent = message;
      var button = root.querySelector('.qs-commit-item, .qs-commit-component');
      if (button && button.parentNode === root) root.insertBefore(error, button);
      else root.appendChild(error);
      if (inputs && inputs[0]) inputs[0].focus();
      return false;
    }

    function validatePricingRange(root, bounds, widthInput, heightInputs, label) {
      if (!bounds || !widthInput) return true;
      var invalid = [];
      var width = Number(widthInput.value);
      if (width < Number(bounds.min_width) || width > Number(bounds.max_width)) invalid.push(widthInput);
      (heightInputs || []).forEach(function (input) {
        if (!input) return;
        var height = Number(input.value);
        if (height < Number(bounds.min_height) || height > Number(bounds.max_height)) invalid.push(input);
      });
      if (!invalid.length) {
        clearPricingError(root);
        return true;
      }
      return showPricingError(
        root,
        invalid,
        (label || 'This item') + ' pricing supports ' + rangeText(bounds) +
          '. Please adjust the highlighted measurement.'
      );
    }

    function validateConfig(root, includeProfile) {
      var required = includeProfile ? ['door_profile', 'timber', 'handle_profile'] : ['timber'];
      if (!isPainted(root)) required.push('finish');
      required.forEach(function (key) {});
      for (var i = 0; i < required.length; i++) {
        var input = configField(root, required[i]);
        if (!input || !input.value) return report(input, 'Please select this option.');
      }
      if (isPainted(root)) {
        var paint = configField(root, 'paint_colour');
        if (!paint || !paint.value.trim()) return report(paint, 'Please enter the paint colour.');
      }
      return true;
    }

    function hiddenInput(row, component, index, key) {
      var input = row.querySelector('[name$="[' + key + ']"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'components[' + component + '][' + index + '][' + key + ']';
        row.appendChild(input);
      }
      return input;
    }

    function rowValues(row) {
      var values = {};
      row.querySelectorAll('[name]').forEach(function (input) {
        var match = input.name.match(/\[([^\]]+)\]$/);
        if (match) values[match[1]] = input.value;
      });
      return values;
    }

    function setRowValues(row, component, index, values) {
      Object.keys(values).forEach(function (key) {
        hiddenInput(row, component, index, key).value = values[key] === undefined || values[key] === null ? '' : String(values[key]);
      });
      if (component === 'doors_drawers') row.dataset.itemType = values.type || 'Door';
    }

    function createRow(section, values) {
      var component = section.dataset.component;
      var row = document.createElement('div');
      row.className = 'qs-repeater-row qs-stored-item';
      section.querySelector('.qs-repeater-list').appendChild(row);
      setRowValues(row, component, section.querySelectorAll('.qs-repeater-row').length - 1, values);
      return row;
    }

    function reindex(section) {
      var component = section.dataset.component;
      section.querySelectorAll('.qs-repeater-row').forEach(function (row, index) {
        row.querySelectorAll('[name]').forEach(function (input) {
          var key = input.name.match(/\[([^\]]+)\]$/);
          if (key) input.name = 'components[' + component + '][' + index + '][' + key[1] + ']';
        });
      });
    }

    function signature(values) {
      return Object.keys(values).filter(function (key) { return key !== 'quantity'; }).sort().map(function (key) {
        return key + ':' + String(values[key] || '');
      }).join('|');
    }

    function triggerRefresh(row) {
      var input = row && row.querySelector('[name]');
      if (input) input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function summaryTarget(component, index) {
      return document.querySelector(
        '.qs-summary-item[data-summary-component="' + component + '"][data-summary-row-index="' + index + '"]'
      );
    }

    function pulseSummaryTarget(target, keepSelected) {
      if (!target) return;
      if (keepSelected) target.classList.add('is-summary-editing');
      target.classList.remove('is-summary-pulse');
      window.requestAnimationFrame(function () {
        target.classList.add('is-summary-pulse');
        window.setTimeout(function () {
          target.classList.remove('is-summary-pulse');
        }, 360);
      });
    }

    function flyToSummary(source, target) {
      if (!source || !target) return;
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        target.classList.add('is-summary-arrival');
        window.setTimeout(function () { target.classList.remove('is-summary-arrival'); }, 650);
        return;
      }

      var container = target.closest('.qs-summary-items');
      if (container) {
        var targetBefore = target.getBoundingClientRect();
        var containerRect = container.getBoundingClientRect();
        if (targetBefore.bottom > containerRect.bottom || targetBefore.top < containerRect.top) {
          target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
      }

      var sourceRect = source.getBoundingClientRect();
      var targetRect = target.getBoundingClientRect();
      if (!sourceRect.width || !sourceRect.height || !targetRect.width || !targetRect.height) return;

      var flyer = target.cloneNode(true);
      flyer.classList.remove('is-summary-editing', 'is-summary-pulse', 'is-summary-arrival');
      flyer.classList.add('qs-fly-to-summary');
      flyer.querySelectorAll('button').forEach(function (button) { button.remove(); });
      var startLeft = sourceRect.left + Math.max(0, (sourceRect.width - targetRect.width) / 2);
      var startTop = sourceRect.top + Math.max(0, Math.min(sourceRect.height - targetRect.height, 24));
      flyer.style.left = startLeft + 'px';
      flyer.style.top = startTop + 'px';
      flyer.style.width = targetRect.width + 'px';
      flyer.style.height = targetRect.height + 'px';
      document.body.appendChild(flyer);

      var dx = targetRect.left - startLeft;
      var dy = targetRect.top - startTop;
      var done = function () {
        flyer.remove();
        target.classList.add('is-summary-arrival');
        window.setTimeout(function () { target.classList.remove('is-summary-arrival'); }, 650);
      };

      if (flyer.animate) {
        var animation = flyer.animate(
          [
            { transform: 'translate3d(0,0,0) scale(.82)', opacity: .92 },
            { transform: 'translate3d(' + dx + 'px,' + dy + 'px,0) scale(1)', opacity: .18 }
          ],
          { duration: 680, easing: 'cubic-bezier(.22,.8,.22,1)', fill: 'forwards' }
        );
        animation.addEventListener('finish', done, { once: true });
      } else {
        flyer.style.transition = 'transform 680ms cubic-bezier(.22,.8,.22,1), opacity 680ms ease';
        window.requestAnimationFrame(function () {
          flyer.style.transform = 'translate3d(' + dx + 'px,' + dy + 'px,0)';
          flyer.style.opacity = '.18';
        });
        window.setTimeout(done, 700);
      }
    }

    function animateSummaryCommit(section, row, source, wasUpdate) {
      if (!section || !row) return;
      var rows = Array.prototype.slice.call(section.querySelectorAll('.qs-repeater-row'));
      var index = rows.indexOf(row);
      if (index < 0) return;
      var target = summaryTarget(section.dataset.component, index);
      if (!target) return;
      if (wasUpdate) pulseSummaryTarget(target, true);
      else flyToSummary(source, target);
    }

    function selectedEdges(section) {
      var checks = section.querySelectorAll('[data-edge-position]:checked, [data-filler-edge-position]:checked');
      if (checks.length) return Array.prototype.map.call(checks, function (input) { return input.value; }).join(' + ');
      return value(section, '[data-component-field="edges_seen"]');
    }

    function doorPanel(section) {
      var type = section.dataset.activeType || 'Door';
      return section.querySelector('.qs-item-editor[data-editor-type="' + type + '"]');
    }

    function commitDoor(section) {
      var type = section.dataset.activeType || 'Door';
      var panel = doorPanel(section);
      if (!panel || !validateConfig(panel, true)) return;

      var width = value(panel, '[data-editor-field="width"]');
      var height = value(panel, '[data-editor-field="height"]');
      var quantity = value(panel, '[data-editor-field="quantity"]');
      if (!width || Number(width) <= 0) return report(field(panel, '[data-editor-field="width"]'), 'Please enter the width.');
      if (!quantity || Number(quantity) <= 0) return report(field(panel, '[data-editor-field="quantity"]'), 'Please enter a quantity.');

      if (type !== 'Drawer Bank' && (!height || Number(height) <= 0)) {
        return report(field(panel, '[data-editor-field="height"]'), 'Please enter the height.');
      }

      if (type === 'Drawer Bank') {
        var visibleHeights = panel.querySelectorAll('[data-bank-counts]:not([hidden]):not([disabled])');
        for (var h = 0; h < visibleHeights.length; h++) {
          if (Number(visibleHeights[h].value) <= 0) return report(visibleHeights[h], 'Please enter this drawer height.');
        }
      }

      var config = getConfig(panel, true);
      var bounds = profileBounds[String(config.door_profile)] || null;
      var widthInput = field(panel, '[data-editor-field="width"]');
      var heightInputs = type === 'Drawer Bank'
        ? Array.prototype.slice.call(panel.querySelectorAll('[data-bank-counts]:not([hidden]):not([disabled])'))
        : [field(panel, '[data-editor-field="height"]')];
      var profileLabel = labels.door_profile[String(config.door_profile)] || 'Selected profile';
      if (!validatePricingRange(panel, bounds, widthInput, heightInputs, profileLabel)) return;

      var values = {
        type: type,
        door_profile: config.door_profile,
        timber: config.timber,
        handle_profile: config.handle_profile,
        finish: config.finish,
        paint_colour: config.paint_colour,
        width: width,
        height: type === 'Drawer Bank' ? '' : height,
        quantity: quantity,
        edge_profile: '',
        drawer_count: type === 'Drawer Bank' ? (value(panel, '[data-editor-field="drawer_count"]') || '3') : '',
        top_height: type === 'Drawer Bank' ? value(panel, '[data-editor-field="top_height"]') : '',
        top_middle_height: type === 'Drawer Bank' ? value(panel, '[data-editor-field="top_middle_height"]') : '',
        middle_height: type === 'Drawer Bank' ? value(panel, '[data-editor-field="middle_height"]') : '',
        bottom_middle_height: type === 'Drawer Bank' ? value(panel, '[data-editor-field="bottom_middle_height"]') : '',
        bottom_height: type === 'Drawer Bank' ? value(panel, '[data-editor-field="bottom_height"]') : '',
        notes: config.notes
      };

      var rows = Array.prototype.slice.call(section.querySelectorAll('.qs-repeater-row'));
      var editing = section.dataset.editingIndex;
      var wasUpdate = editing !== '';
      var animationSource = panel;
      var row = wasUpdate ? rows[Number(editing)] : null;
      if (!row) {
        var desiredSignature = signature(values);
        row = rows.find(function (candidate) {
          var existing = rowValues(candidate);
          existing.quantity = quantity;
          return signature(existing) === desiredSignature;
        }) || null;
        if (row) values.quantity = String((Number(storedValue(row, 'quantity')) || 0) + Number(quantity));
      }
      if (!row) row = createRow(section, values);
      else setRowValues(row, 'doors_drawers', rows.indexOf(row), values);

      reindex(section);
      doorLast = {
        door_profile: config.door_profile,
        timber: config.timber,
        handle_profile: config.handle_profile,
        finish: config.finish,
        paint_colour: config.paint_colour,
        notes: ''
      };
      section.dataset.editingIndex = '';
      panel.querySelectorAll('[data-editor-field]').forEach(function (input) {
        if (input.dataset.editorField === 'drawer_count') input.value = '3';
        else input.value = '';
      });
      configField(panel, 'notes').value = '';
      var button = section.querySelector('.qs-commit-item');
      if (button) button.textContent = 'Add Item';
      triggerRefresh(row);
      animateSummaryCommit(section, row, animationSource, wasUpdate);
    }

    function coreComponentValues(section) {
      var component = section.dataset.component;
      var editor = section.querySelector('.qs-component-editor');
      var config = getConfig(editor, false);
      var values = {
        timber: config.timber,
        finish: config.finish,
        paint_colour: config.paint_colour,
        notes: config.notes,
        quantity: value(editor, '[data-component-field="quantity"]')
      };

      if (component === 'end_panels' || component === 'fillers') {
        values.width = value(editor, '[data-component-field="width"]');
        values.height = value(editor, '[data-component-field="height"]');
        values.faces_seen = value(editor, '[data-component-field="faces_seen"]');
        values.edges_seen = selectedEdges(section);
      } else if (component === 'kickboards') {
        values.material = value(editor, '[data-component-field="material"]');
        values.height = value(editor, '[data-component-field="height"]');
        values.length = value(editor, '[data-component-field="length"]');
      }
      return values;
    }

    function commitComponent(section) {
      var component = section.dataset.component;
      var editor = section.querySelector('.qs-component-editor');
      if (!editor || !validateConfig(editor, false)) return;
      var values = coreComponentValues(section);

      ['quantity', 'height'].forEach(function () {});
      if (!values.quantity || Number(values.quantity) <= 0) return report(field(editor, '[data-component-field="quantity"]'), 'Please enter a quantity.');
      if (!values.height || Number(values.height) <= 0) return report(field(editor, '[data-component-field="height"]'), 'Please enter the height.');
      if (component === 'kickboards' && Number(values.height) > 200) {
        return report(field(editor, '[data-component-field="height"]'), 'Kickboard height cannot exceed 200mm.');
      }

      if (component === 'end_panels' || component === 'fillers') {
        if (!values.width || Number(values.width) <= 0) return report(field(editor, '[data-component-field="width"]'), 'Please enter the width.');
        if (!validatePricingRange(
          editor,
          panelBounds,
          field(editor, '[data-component-field="width"]'),
          [field(editor, '[data-component-field="height"]')],
          component === 'end_panels' ? 'End Panel' : 'Filler'
        )) return;
        if (!values.faces_seen) return report(field(editor, '[data-component-field="faces_seen"]'), 'Please select the face seen.');
        if (!values.edges_seen) {
          var edge = section.querySelector('[data-edge-position], [data-filler-edge-position]');
          return report(edge, 'Please select at least one seen edge.');
        }
      }
      if (component === 'kickboards') {
        if (!values.material) return report(field(editor, '[data-component-field="material"]'), 'Please select the kick material.');
        if (!values.length || Number(values.length) <= 0) return report(field(editor, '[data-component-field="length"]'), 'Please enter the length.');
      }

      var rows = Array.prototype.slice.call(section.querySelectorAll('.qs-repeater-row'));
      var editing = section.dataset.editingIndex;
      var wasUpdate = editing !== '';
      var animationSource = editor;
      var row = wasUpdate ? rows[Number(editing)] : null;
      if (!row) {
        var desiredSignature = signature(values);
        row = rows.find(function (candidate) {
          var existing = rowValues(candidate);
          existing.quantity = values.quantity;
          return signature(existing) === desiredSignature;
        }) || null;
        if (row) values.quantity = String((Number(storedValue(row, 'quantity')) || 0) + Number(values.quantity));
      }
      if (!row) row = createRow(section, values);
      else setRowValues(row, component, rows.indexOf(row), values);
      reindex(section);

      componentLast[component] = {
        timber: values.timber,
        finish: values.finish,
        paint_colour: values.paint_colour,
        notes: ''
      };
      sharedComponentLast = sharedComponentConfig(componentLast[component]);
      propagateSharedComponentConfig(section);
      section.dataset.editingIndex = '';
      editor.querySelectorAll('[data-component-field]').forEach(function (input) {
        if (input.type === 'hidden' && input.dataset.componentField === 'edges_seen') input.value = '';
        else if (component === 'kickboards' && input.dataset.componentField === 'material') {
          input.value = values.material || input.dataset.defaultValue || '';
        } else if (input.tagName === 'SELECT') {
          var defaultValue = input.dataset.defaultValue || '';
          input.value = defaultValue;
        } else input.value = '';
      });
      section.querySelectorAll('[data-edge-position], [data-filler-edge-position]').forEach(function (input) { input.checked = false; });
      configField(editor, 'notes').value = '';
      var button = section.querySelector('.qs-commit-component');
      if (button) button.textContent = 'Add Item';
      triggerRefresh(row);
      animateSummaryCommit(section, row, animationSource, wasUpdate);
    }

    form.addEventListener('input', function (event) {
      if (!event.target.matches('[data-editor-field], [data-component-field]')) return;
      var root = event.target.closest('.qs-item-editor, .qs-component-editor');
      if (root && event.target.classList.contains('qs-pricing-invalid')) clearPricingError(root);
    });

    // Intercept before the legacy document-capture quantity handlers so rows
    // with identical dimensions but different finishes are never merged.
    window.addEventListener('click', function (event) {
      var doorButton = event.target.closest && event.target.closest('.qs-commit-item');
      if (doorButton) {
        event.preventDefault();
        event.stopPropagation();
        commitDoor(doorButton.closest('.qs-doors-drawers'));
        return;
      }
      var componentButton = event.target.closest && event.target.closest('.qs-commit-component');
      if (componentButton) {
        event.preventDefault();
        event.stopPropagation();
        commitComponent(componentButton.closest('.qs-configured-component'));
      }
    }, true);

    document.addEventListener('click', function (event) {
      var typeButton = event.target.closest('[data-select-type]');
      if (typeButton && doorSection) {
        window.setTimeout(function () {
          if (doorSection.dataset.editingIndex === '') {
            var panel = doorPanel(doorSection);
            if (panel) setConfig(panel, doorLast, true);
          }
        }, 0);
      }

      var edit = event.target.closest('[data-summary-action="edit"]');
      if (!edit) return;
      window.setTimeout(function () {
        var section = document.querySelector('.qs-component[data-component="' + edit.dataset.component + '"]');
        if (!section) return;
        var row = section.querySelectorAll('.qs-repeater-row')[Number(edit.dataset.rowIndex)];
        if (!row) return;
        if (edit.dataset.component === 'doors_drawers') {
          var panel = doorPanel(section);
          if (panel) setConfig(panel, rowConfig(row, true, globalDefaults(true)), true);
        } else {
          var editor = section.querySelector('.qs-component-editor');
          if (editor) setConfig(editor, rowConfig(row, false, globalDefaults(false)), false);
        }
      }, 0);
    });

    function appendSummaryDetails() {
      document.querySelectorAll('.qs-summary-item').forEach(function (item) {
        var action = item.querySelector('[data-summary-action]');
        if (!action) return;
        var component = action.dataset.component;
        var section = document.querySelector('.qs-component[data-component="' + component + '"]');
        var row = section && section.querySelectorAll('.qs-repeater-row')[Number(action.dataset.rowIndex)];
        var content = item.querySelector('.qs-summary-item-content');
        if (!row || !content) return;

        var old = content.querySelector('.qs-item-config-summary');
        var parts = [];
        if (component === 'doors_drawers') {
          parts.push('Profile: ' + productLabel('door_profile', storedValue(row, 'door_profile') || globalValue('door_profile')));
          parts.push('Timber: ' + productLabel('timber', storedValue(row, 'timber') || globalValue('timber')));
          parts.push('Handle: ' + productLabel('handle_profile', storedValue(row, 'handle_profile') || globalValue('handle_profile')));
          var finishValue = storedValue(row, 'finish') || globalValue('finish');
          if (finishValue) parts.push('Finish: ' + productLabel('finish', finishValue));
        } else {
          parts.push('Timber: ' + productLabel('timber', storedValue(row, 'timber') || globalValue('timber')));
          var rowFinish = storedValue(row, 'finish') || globalValue('finish');
          if (rowFinish) parts.push('Finish: ' + productLabel('finish', rowFinish));
        }
        if (storedValue(row, 'paint_colour')) parts.push('Paint: ' + storedValue(row, 'paint_colour'));
        if (storedValue(row, 'notes')) parts.push('Notes: ' + storedValue(row, 'notes'));
        var detailText = parts.filter(function (part) { return !/: $/.test(part); }).join(' · ');
        if (!detailText) {
          if (old) old.remove();
          return;
        }
        if (old) {
          if (old.textContent !== detailText) old.textContent = detailText;
          return;
        }
        var detail = document.createElement('small');
        detail.className = 'qs-item-config-summary';
        detail.textContent = detailText;
        content.appendChild(detail);
      });
    }

    var summary = document.querySelector('.qs-summary-items');
    if (summary && window.MutationObserver) {
      var summaryObserver = new MutationObserver(function () { window.setTimeout(appendSummaryDetails, 0); });
      summaryObserver.observe(summary, { childList: true, subtree: true });
    }
    form.addEventListener('input', function (event) {
      if (event.target.closest('.qs-repeater-row')) window.setTimeout(appendSummaryDetails, 0);
    });
    form.addEventListener('change', function (event) {
      if (event.target.closest('.qs-repeater-row')) window.setTimeout(appendSummaryDetails, 0);
    });

    hideLegacySpecifications();
    appendSummaryDetails();
  });
}());
