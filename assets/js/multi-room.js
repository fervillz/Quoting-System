(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback);
    else callback();
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function uid() {
    return 'room-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  var componentFields = {
    doors_drawers: ['type', 'width', 'height', 'quantity', 'edge_profile', 'drawer_count', 'top_height', 'top_middle_height', 'middle_height', 'bottom_middle_height', 'bottom_height'],
    end_panels: ['height', 'width', 'quantity', 'faces_seen', 'edges_seen'],
    fillers: ['height', 'width', 'quantity', 'faces_seen', 'edges_seen'],
    kickboards: ['material', 'height', 'length', 'quantity']
  };

  var specificationFields = ['door_profile', 'timber', 'finish', 'handle_profile', 'paint_colour'];

  function blankRoom(number) {
    return {
      id: uid(),
      name: 'Room ' + number,
      door_profile: '',
      timber: '',
      finish: '',
      handle_profile: '',
      paint_colour: '',
      components: {
        doors_drawers: [],
        end_panels: [],
        fillers: [],
        kickboards: []
      }
    };
  }

  function normaliseRoom(room, number) {
    var normal = Object.assign(blankRoom(number), room || {});
    normal.id = normal.id || uid();
    normal.name = normal.name || ('Room ' + number);
    normal.components = normal.components || {};
    Object.keys(componentFields).forEach(function (component) {
      normal.components[component] = Array.isArray(normal.components[component]) ? normal.components[component] : [];
    });
    return normal;
  }

  ready(function () {
    var config = window.QSMultiRoom || {};
    var form = document.querySelector('.qs-builder-form');
    if (!form) return;

    var rooms = (Array.isArray(config.rooms) && config.rooms.length ? config.rooms : [blankRoom(1)]).map(normaliseRoom);
    var activeId = config.activeRoomId && rooms.some(function (room) { return room.id === config.activeRoomId; })
      ? config.activeRoomId
      : rooms[0].id;
    var isApplying = false;
    var summaryItems = form.querySelector('.qs-summary-items');
    var summary = form.querySelector('.qs-builder-summary');
    var roomSubtotals = config.roomSubtotals || {};

    var roomsInput = document.createElement('input');
    roomsInput.type = 'hidden';
    roomsInput.name = 'qs_rooms_json';
    form.appendChild(roomsInput);

    var activeInput = document.createElement('input');
    activeInput.type = 'hidden';
    activeInput.name = 'qs_active_room_id';
    form.appendChild(activeInput);

    function activeRoom() {
      return rooms.find(function (room) { return room.id === activeId; }) || rooms[0];
    }

    function selectedValue(name) {
      var checked = form.querySelector('[name="' + name + '"]:checked');
      if (checked) return checked.value;
      var field = form.querySelector('[name="' + name + '"]');
      return field ? field.value : '';
    }

    function captureRows(component) {
      var section = form.querySelector('.qs-component[data-component="' + component + '"]');
      if (!section) return [];
      return Array.prototype.map.call(section.querySelectorAll('.qs-repeater-row'), function (row) {
        var values = {};
        componentFields[component].forEach(function (key) {
          var input = row.querySelector('[name$="[' + key + ']"]');
          values[key] = input ? input.value : '';
        });
        return values;
      }).filter(function (row) {
        return Number(row.quantity) > 0 && (Number(row.width) > 0 || Number(row.length) > 0);
      });
    }

    function captureActiveRoom() {
      if (isApplying) return;
      var room = activeRoom();
      if (!room) return;
      specificationFields.forEach(function (key) {
        room[key] = selectedValue(key);
      });
      Object.keys(componentFields).forEach(function (component) {
        room.components[component] = captureRows(component);
      });
      syncHidden();
      renderRoomNavigation();
    }

    function createStoredRow(component, values, index) {
      var row = document.createElement('div');
      row.className = 'qs-repeater-row qs-stored-item';
      if (component === 'doors_drawers') row.dataset.itemType = values.type || 'Door';
      componentFields[component].forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'components[' + component + '][' + index + '][' + key + ']';
        input.value = values[key] == null ? '' : values[key];
        row.appendChild(input);
      });
      return row;
    }

    function applyRows(component, rows) {
      var section = form.querySelector('.qs-component[data-component="' + component + '"]');
      var list = section && section.querySelector('.qs-repeater-list');
      if (!list) return;
      list.innerHTML = '';
      (rows || []).forEach(function (values, index) {
        list.appendChild(createStoredRow(component, values, index));
      });
      section.dataset.editingIndex = '';
    }

    function updatePicker(name, value) {
      var radios = form.querySelectorAll('[name="' + name + '"]');
      var matched = false;
      radios.forEach(function (radio) {
        radio.checked = String(radio.value) === String(value || '');
        if (radio.checked) {
          matched = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
      if (!matched) {
        radios.forEach(function (radio) { radio.checked = false; });
        var picker = form.querySelector('[data-picker="' + name + '"]');
        if (picker) {
          var label = picker.querySelector('[data-picker-selected-label]');
          var swatch = picker.querySelector('[data-picker-selected-swatch]');
          if (label) label.textContent = 'Select an option';
          if (swatch) swatch.style.backgroundImage = '';
          picker.querySelectorAll('.qs-product-option').forEach(function (option) { option.classList.remove('is-selected'); });
        }
      }
    }

    function applyRoom(room) {
      isApplying = true;
      specificationFields.forEach(function (key) {
        if (key === 'paint_colour') {
          var paint = form.querySelector('[name="paint_colour"]');
          if (paint) paint.value = room.paint_colour || '';
        } else {
          updatePicker(key, room[key] || '');
        }
      });
      Object.keys(componentFields).forEach(function (component) {
        applyRows(component, room.components[component]);
      });
      isApplying = false;
      activeInput.value = room.id;
      syncHidden();
      form.dispatchEvent(new Event('input', { bubbles: true }));
      window.setTimeout(function () {
        renderProfileEndPanelSummary();
        syncRoomName();
      }, 0);
    }

    function syncHidden() {
      roomsInput.value = JSON.stringify(rooms);
      activeInput.value = activeId;
    }

    var specificationSection = Array.prototype.find.call(form.querySelectorAll('.qs-form-section'), function (section) {
      var heading = section.querySelector('h3');
      return heading && /Door Specifications/i.test(heading.textContent || '');
    });
    var componentSections = Array.prototype.slice.call(form.querySelectorAll('.qs-component[data-component]'));
    var roomConfiguration = document.createElement('div');
    roomConfiguration.className = 'qs-room-configuration';
    if (specificationSection) {
      specificationSection.parentNode.insertBefore(roomConfiguration, specificationSection);
      roomConfiguration.appendChild(specificationSection);
      componentSections.forEach(function (section) { roomConfiguration.appendChild(section); });
    }

    var manager = document.createElement('section');
    manager.className = 'qs-room-manager';
    manager.innerHTML = '' +
      '<div class="qs-room-manager-heading"><div><h3>Rooms</h3><p>Each room has its own profile, timber, finish and component list.</p></div>' +
      '<button type="button" class="qs-add-room">+ Add Room</button></div>' +
      '<div class="qs-room-tabs" role="tablist" aria-label="Quote rooms"></div>' +
      '<div class="qs-room-toolbar">' +
        '<label>Room Name<input type="text" class="qs-room-name" maxlength="80"></label>' +
        '<button type="button" class="qs-duplicate-room">Duplicate Room</button>' +
        '<button type="button" class="qs-delete-room">Delete Room</button>' +
      '</div>';
    if (roomConfiguration.parentNode) roomConfiguration.parentNode.insertBefore(manager, roomConfiguration);

    var tabs = manager.querySelector('.qs-room-tabs');
    var roomName = manager.querySelector('.qs-room-name');

    var summaryTabs = document.createElement('div');
    summaryTabs.className = 'qs-summary-room-tabs';
    if (summary) {
      var summaryHeading = summary.querySelector('h2');
      if (summaryHeading) summaryHeading.insertAdjacentElement('afterend', summaryTabs);
    }

    function money(value) {
      return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderRoomNavigation() {
      tabs.innerHTML = '';
      summaryTabs.innerHTML = '';
      rooms.forEach(function (room, index) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'qs-room-tab' + (room.id === activeId ? ' is-active' : '');
        button.dataset.roomId = room.id;
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-selected', room.id === activeId ? 'true' : 'false');
        button.innerHTML = '<span>' + escapeHtml(room.name || ('Room ' + (index + 1))) + '</span>' +
          (roomSubtotals[room.id] != null ? '<small>$' + money(roomSubtotals[room.id]) + '</small>' : '');
        tabs.appendChild(button);

        var summaryButton = button.cloneNode(true);
        summaryButton.className = 'qs-summary-room-tab' + (room.id === activeId ? ' is-active' : '');
        summaryTabs.appendChild(summaryButton);
      });
      syncRoomName();
    }

    function escapeHtml(value) {
      var span = document.createElement('span');
      span.textContent = value == null ? '' : String(value);
      return span.innerHTML;
    }

    function syncRoomName() {
      var room = activeRoom();
      if (roomName && room && roomName.value !== room.name) roomName.value = room.name;
    }

    function switchRoom(roomId) {
      if (roomId === activeId) return;
      captureActiveRoom();
      activeId = roomId;
      applyRoom(activeRoom());
      renderRoomNavigation();
      if (roomConfiguration) roomConfiguration.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function addRoom() {
      captureActiveRoom();
      var room = blankRoom(rooms.length + 1);
      rooms.push(room);
      activeId = room.id;
      applyRoom(room);
      renderRoomNavigation();
      roomName.focus();
      roomName.select();
    }

    function duplicateRoom() {
      captureActiveRoom();
      var source = activeRoom();
      var duplicate = clone(source);
      duplicate.id = uid();
      duplicate.name = source.name + ' Copy';
      rooms.push(duplicate);
      activeId = duplicate.id;
      applyRoom(duplicate);
      renderRoomNavigation();
    }

    function deleteRoom() {
      if (rooms.length === 1) {
        window.alert('A quote must contain at least one room.');
        return;
      }
      var room = activeRoom();
      if (!window.confirm('Delete ' + room.name + ' and all items assigned to it?')) return;
      var index = rooms.findIndex(function (candidate) { return candidate.id === room.id; });
      rooms.splice(index, 1);
      activeId = rooms[Math.max(0, index - 1)].id;
      applyRoom(activeRoom());
      renderRoomNavigation();
    }

    manager.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-room-id]');
      if (tab) switchRoom(tab.dataset.roomId);
      if (event.target.closest('.qs-add-room')) addRoom();
      if (event.target.closest('.qs-duplicate-room')) duplicateRoom();
      if (event.target.closest('.qs-delete-room')) deleteRoom();
    });
    summaryTabs.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-room-id]');
      if (tab) switchRoom(tab.dataset.roomId);
    });
    roomName.addEventListener('input', function () {
      var room = activeRoom();
      room.name = roomName.value.trim() || 'Room';
      syncHidden();
      renderRoomNavigation();
      roomName.focus();
    });

    function addProfileEndPanelEditor() {
      var section = form.querySelector('.qs-doors-drawers');
      if (!section) return;
      var actions = section.querySelector('.qs-type-actions');
      var editor = section.querySelector('.qs-door-entry-editor');
      if (!actions || !editor || actions.querySelector('[data-select-type="Profile End Panel"]')) return;

      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.selectType = config.profileEndPanelType || 'Profile End Panel';
      button.setAttribute('aria-pressed', 'false');
      button.textContent = '+ Add Profile End Panel';
      actions.appendChild(button);

      var panel = document.createElement('div');
      panel.className = 'qs-item-editor';
      panel.dataset.editorType = config.profileEndPanelType || 'Profile End Panel';
      panel.hidden = true;
      panel.innerHTML = '<div class="qs-editor-fields qs-front-editor-fields">' +
        '<input type="number" min="1" step="1" data-editor-field="width" placeholder="Width mm" aria-label="Profile end panel width in millimetres">' +
        '<input type="number" min="1" step="1" data-editor-field="height" placeholder="Height mm" aria-label="Profile end panel height in millimetres">' +
        '<input type="number" min="1" step="1" data-editor-field="quantity" placeholder="Quantity" aria-label="Profile end panel quantity">' +
        '</div><p class="qs-item-instruction">Uses the selected room profile pricing. Handle charges are not applied.</p>';
      editor.insertBefore(panel, editor.querySelector('.qs-commit-item'));
    }

    function renderProfileEndPanelSummary() {
      if (!summaryItems || summaryItems.querySelector('[data-profile-end-panel-group]')) return;
      var section = form.querySelector('.qs-doors-drawers');
      if (!section) return;
      var items = Array.prototype.map.call(section.querySelectorAll('.qs-repeater-row'), function (row, index) {
        function value(key) {
          var input = row.querySelector('[name$="[' + key + ']"]');
          return input ? input.value : '';
        }
        return { row: row, index: index, type: value('type'), width: value('width'), height: value('height'), quantity: value('quantity') };
      }).filter(function (item) { return item.type === (config.profileEndPanelType || 'Profile End Panel') && Number(item.quantity) > 0; });
      if (!items.length) return;

      var group = document.createElement('div');
      group.className = 'qs-summary-group';
      group.dataset.profileEndPanelGroup = '1';
      group.innerHTML = '<strong class="qs-summary-group-title">Profile End Panels (' + items.length + ')</strong>';
      items.forEach(function (item) {
        var entry = document.createElement('div');
        entry.className = 'qs-summary-item';
        entry.innerHTML = '<div class="qs-summary-item-content"><span class="qs-summary-item-primary">' +
          escapeHtml(item.width + 'mm (w) × ' + item.height + 'mm (h)') + '</span></div>' +
          '<span class="qs-summary-item-quantity">Qty. ' + escapeHtml(item.quantity) + '</span>' +
          '<div class="qs-summary-item-actions">' +
            '<button type="button" class="qs-summary-action" data-summary-action="edit" data-component="doors_drawers" data-row-index="' + item.index + '" aria-label="Edit item"><img src="' + escapeHtml(config.editIcon || '') + '" alt=""></button>' +
            '<button type="button" class="qs-summary-action" data-summary-action="remove" data-component="doors_drawers" data-row-index="' + item.index + '" aria-label="Remove item"><img src="' + escapeHtml(config.removeIcon || '') + '" alt=""></button>' +
          '</div>';
        group.appendChild(entry);
      });
      summaryItems.appendChild(group);
    }

    function setupLeadTime() {
      var lead = summary && summary.querySelector('.qs-lead-time');
      if (!lead) return;
      var span = lead.querySelector('span');
      if (!span) return;
      span.textContent = config.leadTime || span.textContent;
      if (!config.canEditLeadTime) return;
      var input = document.createElement('input');
      input.type = 'text';
      input.name = 'estimated_lead_time';
      input.value = config.leadTime || '';
      input.placeholder = '4–6 Weeks';
      input.setAttribute('aria-label', 'Estimated lead time');
      span.replaceWith(input);
    }

    addProfileEndPanelEditor();
    setupLeadTime();

    tabs.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      var current = rooms.findIndex(function (room) { return room.id === activeId; });
      var next = event.key === 'ArrowRight' ? (current + 1) % rooms.length : (current - 1 + rooms.length) % rooms.length;
      switchRoom(rooms[next].id);
      tabs.querySelector('[data-room-id="' + rooms[next].id + '"]').focus();
    });

    form.addEventListener('input', function (event) {
      if (isApplying || event.target === roomName || event.target === roomsInput || event.target === activeInput) return;
      window.setTimeout(captureActiveRoom, 0);
    });
    form.addEventListener('change', function () {
      if (!isApplying) window.setTimeout(captureActiveRoom, 0);
    });
    form.addEventListener('submit', function () {
      captureActiveRoom();
      syncHidden();
    }, true);

    var observer = new MutationObserver(function () {
      if (!isApplying) {
        captureActiveRoom();
        renderProfileEndPanelSummary();
      }
    });
    componentSections.forEach(function (section) {
      var list = section.querySelector('.qs-repeater-list');
      if (list) observer.observe(list, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
    });
    if (summaryItems) {
      new MutationObserver(renderProfileEndPanelSummary).observe(summaryItems, { childList: true, subtree: true });
    }

    applyRoom(activeRoom());
    renderRoomNavigation();
  });
}());
