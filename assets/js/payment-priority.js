(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }

  function quoteIdFromRow(row) {
    var button = row.querySelector('.qs-expand-btn');
    var controls = button ? button.getAttribute('aria-controls') : '';
    var match = controls ? controls.match(/(\d+)$/) : null;
    return match ? match[1] : '';
  }

  function buildConfirmationForm(quoteId, state) {
    var form = document.createElement('form');
    form.method = 'post';
    form.className = 'qs-admin-action-form qs-payment-confirm-form';
    form.setAttribute('data-confirm', state.confirm_message || 'Confirm that this payment has been received?');

    var fields = {
      qs_dashboard_action_nonce: state.nonce || '',
      quote_id: quoteId,
      qs_dashboard_action: state.confirm_action || ''
    };

    Object.keys(fields).forEach(function (name) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });

    var button = document.createElement('button');
    button.type = 'submit';
    button.textContent = state.confirm_label || 'Confirm Payment Received';
    form.appendChild(button);

    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });

    return form;
  }

  ready(function () {
    var data = window.QSPaymentPriority || {};
    var dashboard = document.querySelector('.qs-admin-dashboard');
    if (!dashboard) {
      return;
    }

    var states = data.states || {};
    var stats = dashboard.querySelector('.qs-admin-stats');
    if (stats) {
      var priorityCard = document.createElement('a');
      priorityCard.className = 'qs-stat-card qs-stat-card-payment';
      if (data.activeFilter === 'payment_verify') {
        priorityCard.className += ' is-active';
      }
      priorityCard.href = data.activeFilter === 'payment_verify' ? data.clearUrl : data.filterUrl;
      priorityCard.innerHTML = '<span>Payments to Verify</span><strong>' + String(data.verifyCount || 0) + '</strong>';
      stats.insertBefore(priorityCard, stats.firstElementChild);
    }

    var statusSelect = dashboard.querySelector('.qs-admin-search select[name="status"]');
    if (statusSelect && !statusSelect.querySelector('option[value="payment_verify"]')) {
      var option = document.createElement('option');
      option.value = 'payment_verify';
      option.textContent = 'Payment to Verify';
      option.selected = data.activeFilter === 'payment_verify';
      statusSelect.insertBefore(option, statusSelect.options[1] || null);
    }

    dashboard.querySelectorAll('.qs-admin-table tbody').forEach(function (tbody) {
      var rows = Array.prototype.filter.call(tbody.children, function (child) {
        return child.classList.contains('qs-admin-quote-row');
      });

      var pairs = rows.map(function (row, index) {
        var quoteId = quoteIdFromRow(row);
        var state = states[quoteId] || {};
        var expansion = row.nextElementSibling && row.nextElementSibling.classList.contains('qs-admin-expand-row')
          ? row.nextElementSibling
          : null;
        var statusCell = row.querySelector('td:nth-child(5)');

        if (statusCell && state.label) {
          var existingStatus = statusCell.querySelector('.qs-status');
          var stack = document.createElement('span');
          stack.className = 'qs-status-stack';
          if (existingStatus) {
            statusCell.insertBefore(stack, existingStatus);
            stack.appendChild(existingStatus);
          } else {
            statusCell.appendChild(stack);
          }

          var paymentBadge = document.createElement('span');
          paymentBadge.className = 'qs-payment-status qs-payment-status-' + (state.class || 'awaiting');
          paymentBadge.textContent = state.label;
          stack.appendChild(paymentBadge);
        }

        if (state.needs_verification) {
          row.classList.add('has-payment-priority');

          if (expansion) {
            var actionContainer = expansion.querySelector('.qs-admin-row-actions');
            var existingButton = actionContainer
              ? Array.prototype.find.call(actionContainer.querySelectorAll('button'), function (button) {
                  return /mark deposit as paid/i.test(button.textContent || '');
                })
              : null;

            if (existingButton && state.payment_type === 'deposit') {
              existingButton.textContent = state.confirm_label || 'Confirm Deposit Received';
              var existingForm = existingButton.closest('form');
              if (existingForm && state.confirm_message) {
                existingForm.setAttribute('data-confirm', state.confirm_message);
              }
            } else if (actionContainer && state.confirm_action && state.nonce) {
              actionContainer.appendChild(buildConfirmationForm(quoteId, state));
            }
          }
        }

        return {
          row: row,
          expansion: expansion,
          priority: typeof state.priority === 'number' ? state.priority : 60,
          verify: !!state.needs_verification,
          index: index
        };
      });

      pairs.sort(function (a, b) {
        return a.priority === b.priority ? a.index - b.index : a.priority - b.priority;
      });

      pairs.forEach(function (pair) {
        tbody.appendChild(pair.row);
        if (pair.expansion) {
          tbody.appendChild(pair.expansion);
        }
      });

      if (data.activeFilter === 'payment_verify') {
        pairs.forEach(function (pair) {
          pair.row.hidden = !pair.verify;
          if (pair.expansion) {
            pair.expansion.hidden = true;
          }
        });

        var group = tbody.closest('.qs-company-group');
        if (group) {
          group.hidden = !pairs.some(function (pair) { return pair.verify; });
        }
      }
    });

    if (data.activeFilter === 'payment_verify') {
      var visibleGroups = dashboard.querySelectorAll('.qs-company-group:not([hidden])');
      if (!visibleGroups.length) {
        var panel = dashboard.querySelector('.qs-admin-panel');
        if (panel && !panel.querySelector('.qs-payment-empty')) {
          var empty = document.createElement('p');
          empty.className = 'qs-admin-empty qs-payment-empty';
          empty.textContent = 'There are no bank-transfer payments waiting for verification.';
          panel.appendChild(empty);
        }
      }
    }
  });
}());
