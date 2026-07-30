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

  function quoteStatusFromRow(row) {
    var badge = row.querySelector('.qs-status');
    if (!badge) {
      return '';
    }

    var statuses = [
      'draft',
      'pending_review',
      'awaiting_deposit',
      'deposit_paid',
      'final_balance',
      'paid_in_full'
    ];

    for (var index = 0; index < statuses.length; index += 1) {
      if (badge.classList.contains('qs-status-' + statuses[index])) {
        return statuses[index];
      }
    }

    return '';
  }

  function matchesFilter(pair, filter) {
    if (!filter) {
      return true;
    }

    if (filter === 'payment_verify') {
      return pair.verify;
    }

    if (filter === 'approved') {
      return pair.status === 'deposit_paid' || pair.status === 'final_balance';
    }

    if (filter === 'completed') {
      return pair.status === 'paid_in_full';
    }

    return pair.status === filter;
  }

  function filterUrl(filter, activeFilter) {
    var url = new URL(window.location.href);
    url.searchParams.delete('qs_payment_notice');

    if (filter && filter !== activeFilter) {
      url.searchParams.set('status', filter);
    } else {
      url.searchParams.delete('status');
    }

    return url.toString();
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

  function updateActionCountClass(container) {
    Array.prototype.slice.call(container.classList).forEach(function (className) {
      if (/^qs-action-count-\d+$/.test(className)) {
        container.classList.remove(className);
      }
    });
    container.classList.add('qs-action-count-' + container.children.length);
  }

  function makeFilterCard(card, filter, activeFilter) {
    var link = card;

    if (card.tagName.toLowerCase() !== 'a') {
      link = document.createElement('a');
      link.className = card.className;
      link.innerHTML = card.innerHTML;
      card.parentNode.replaceChild(link, card);
    }

    link.classList.add('qs-stat-card-filter');
    link.setAttribute('data-status-filter', filter);
    link.href = filterUrl(filter, activeFilter);

    if (filter === activeFilter) {
      link.classList.add('is-active');
      link.setAttribute('aria-current', 'page');
    } else {
      link.classList.remove('is-active');
      link.removeAttribute('aria-current');
    }

    return link;
  }

  ready(function () {
    var data = window.QSPaymentPriority || {};
    var dashboard = document.querySelector('.qs-admin-dashboard');
    if (!dashboard) {
      return;
    }

    var states = data.states || {};
    var activeFilter = data.activeFilter || '';
    var stats = dashboard.querySelector('.qs-admin-stats');
    if (stats) {
      var priorityCard = document.createElement('a');
      priorityCard.className = 'qs-stat-card qs-stat-card-payment';
      priorityCard.innerHTML = '<span>Payments to Verify</span><strong>' + String(data.verifyCount || 0) + '</strong>';
      stats.insertBefore(priorityCard, stats.firstElementChild);
      makeFilterCard(priorityCard, 'payment_verify', activeFilter);

      var cardFilters = {
        'DRAFT QUOTES': 'draft',
        'PENDING REVIEW': 'pending_review',
        'DEPOSIT REQUESTED': 'awaiting_deposit',
        'APPROVED QUOTES': 'approved',
        'COMPLETED': 'completed'
      };

      Array.prototype.slice.call(stats.querySelectorAll('.qs-stat-card:not(.qs-stat-card-payment)')).forEach(function (card) {
        var label = card.querySelector('span');
        var filter = label ? cardFilters[(label.textContent || '').trim().toUpperCase()] : '';
        if (filter) {
          makeFilterCard(card, filter, activeFilter);
        }
      });
    }

    var statusSelect = dashboard.querySelector('.qs-admin-search select[name="status"]');
    if (statusSelect) {
      var virtualOptions = [
        { value: 'payment_verify', label: 'Payment to Verify' },
        { value: 'approved', label: 'Approved Quotes' },
        { value: 'completed', label: 'Completed' }
      ];

      virtualOptions.reverse().forEach(function (item) {
        if (!statusSelect.querySelector('option[value="' + item.value + '"]')) {
          var option = document.createElement('option');
          option.value = item.value;
          option.textContent = item.label;
          option.selected = activeFilter === item.value;
          statusSelect.insertBefore(option, statusSelect.options[1] || null);
        }
      });
    }

    var hasVisibleQuotes = false;

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
              updateActionCountClass(actionContainer);
            }
          }
        }

        return {
          row: row,
          expansion: expansion,
          priority: typeof state.priority === 'number' ? state.priority : 60,
          verify: !!state.needs_verification,
          status: quoteStatusFromRow(row),
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

      if (activeFilter) {
        pairs.forEach(function (pair) {
          var visible = matchesFilter(pair, activeFilter);
          pair.row.hidden = !visible;
          if (pair.expansion) {
            pair.expansion.hidden = true;
          }
          if (visible) {
            hasVisibleQuotes = true;
          }
        });

        var group = tbody.closest('.qs-company-group');
        if (group) {
          group.hidden = !pairs.some(function (pair) {
            return matchesFilter(pair, activeFilter);
          });
        }
      } else if (pairs.length) {
        hasVisibleQuotes = true;
      }
    });

    if (activeFilter && !hasVisibleQuotes) {
      var panel = dashboard.querySelector('.qs-admin-panel');
      if (panel && !panel.querySelector('.qs-payment-empty')) {
        var empty = document.createElement('p');
        empty.className = 'qs-admin-empty qs-payment-empty';
        empty.textContent = activeFilter === 'payment_verify'
          ? 'There are no bank-transfer payments waiting for verification.'
          : 'There are no quotes in this status.';
        panel.appendChild(empty);
      }
    }
  });
}());
