// Small helpers that sit alongside htmx.
(function () {
  'use strict';

  var TOAST_MS = 4500;

  function container() {
    return document.getElementById('toasts');
  }

  function dismissLater(el) {
    setTimeout(function () {
      el.classList.add('toast-out');
      setTimeout(function () { el.remove(); }, 400);
    }, TOAST_MS);
  }

  function showToast(message, kind) {
    var box = container();
    if (!box) return;
    var p = document.createElement('p');
    p.className = 'toast toast-' + (kind || 'ok');
    p.textContent = message;
    box.appendChild(p);
    dismissLater(p);
  }

  // Server sends HX-Trigger: {"qm-toast": {"items": [{message, kind}]}}
  document.addEventListener('qm-toast', function (evt) {
    var items = (evt.detail && evt.detail.items) || [];
    items.forEach(function (t) { showToast(t.message, t.kind); });
  });

  // Server sends HX-Trigger-After-Swap: {"qm-reset": {"form": "#give-form"}}
  document.addEventListener('qm-reset', function (evt) {
    var sel = evt.detail && evt.detail.form;
    var form = sel && document.querySelector(sel);
    if (form) {
      form.reset();
      var first = form.querySelector('input:not([type=hidden])');
      if (first) first.focus();
    }
  });

  // A catalog redraw waits for row saves in flight. If it overtook one, the table would show the row's old
  // values, and the next edit to that row would save them over the new ones. (See views/dm/catalog.php.)
  var rowSaves = 0;
  var waitingRedraws = [];
  document.addEventListener('htmx:beforeRequest', function (evt) {
    if (!evt.detail.elt.closest('#catalog-body')) return;
    rowSaves++;
    evt.detail.xhr.addEventListener('loadend', function () {
      rowSaves--;
      if (rowSaves === 0) {
        waitingRedraws.splice(0).forEach(function (waiting) {
          if (document.body.contains(waiting.elt)) {
            waiting.issueRequest(true);
            return;
          }
          // The save replaced the row this request came from, and htmx drops requests from removed elements.
          // Its replacement isn't wired up until the swap settles, so do that now. htmx won't do it a second time.
          var again = waiting.elt.id && document.getElementById(waiting.elt.id);
          if (again) {
            htmx.process(again);
            again.click();
          }
        });
      }
    });
  });
  document.addEventListener('htmx:confirm', function (evt) {
    if (rowSaves > 0 && evt.detail.target && evt.detail.target.id === 'catalog-body') {
      evt.preventDefault();
      waitingRedraws.push(evt.detail);
    }
  });

  // Item-name suggestions (views/partials/item_suggest.php). htmx fills the list; this opens, closes and picks.
  function suggestInput(list) {
    return document.querySelector('[aria-controls="' + list.id + '"]');
  }
  function suggestOptions(list) {
    return list.querySelectorAll('[role="option"]');
  }
  function setSuggestActive(input, list, index) {
    var opts = suggestOptions(list);
    Array.prototype.forEach.call(opts, function (o, i) { o.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
    list.dataset.active = String(index);
    if (opts[index]) {
      input.setAttribute('aria-activedescendant', opts[index].id);
      // Scroll the list only. scrollIntoView would also scroll the page and could push the input off screen.
      var opt = opts[index];
      if (opt.offsetTop < list.scrollTop) list.scrollTop = opt.offsetTop;
      else if (opt.offsetTop + opt.offsetHeight > list.scrollTop + list.clientHeight) {
        list.scrollTop = opt.offsetTop + opt.offsetHeight - list.clientHeight;
      }
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  }
  function setSuggestOpen(input, list, open) {
    list.hidden = !open;
    input.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (!open) setSuggestActive(input, list, -1);
  }
  function pickSuggestion(input, list, opt) {
    input.value = opt.dataset.value;
    // Replies still on their way (or a delayed request yet to fire) mustn't reopen the list over the pick.
    list.dataset.picked = input.value;
    setSuggestOpen(input, list, false);
  }
  function suggestListFor(input) {
    var id = input.getAttribute('role') === 'combobox' && input.getAttribute('aria-controls');
    var list = id && document.getElementById(id);
    return list && list.classList.contains('suggest-list') ? list : null;
  }

  // Note what each suggestion request searched for, so a reply that no longer matches the input can be dropped.
  document.addEventListener('htmx:beforeRequest', function (evt) {
    if (suggestListFor(evt.detail.elt)) evt.detail.xhr.qmSearched = evt.detail.elt.value;
  });
  document.addEventListener('htmx:beforeSwap', function (evt) {
    var list = evt.detail.target;
    if (!list.classList || !list.classList.contains('suggest-list')) return;
    var input = suggestInput(list);
    if (!input) return;
    // hx-sync only aborts a request once the next one is sent, 250 ms after typing stops. A reply landing in
    // that gap is for older text.
    if (evt.detail.xhr.qmSearched !== input.value || input.value === list.dataset.picked) {
      evt.detail.shouldSwap = false;
    }
  });

  document.addEventListener('htmx:afterSwap', function (evt) {
    var list = evt.detail.target;
    if (!list.classList || !list.classList.contains('suggest-list')) return;
    var input = suggestInput(list);
    if (!input) return;
    var opts = suggestOptions(list);
    Array.prototype.forEach.call(opts, function (o, i) { o.id = list.id + '-' + i; });
    // Don't pop open if the user has already moved on (e.g. tapped another field before the reply came).
    setSuggestOpen(input, list, opts.length > 0 && input.value !== '' && document.activeElement === input);
  });

  document.addEventListener('input', function (evt) {
    var list = suggestListFor(evt.target);
    if (!list) return;
    delete list.dataset.picked;
    if (evt.target.value === '') {
      list.innerHTML = '';
      setSuggestOpen(evt.target, list, false);
    }
  });

  document.addEventListener('keydown', function (evt) {
    var input = evt.target;
    var list = suggestListFor(input);
    if (!list) return;
    var count = suggestOptions(list).length;
    var active = parseInt(list.dataset.active || '-1', 10);
    if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
      if (!count) return;
      evt.preventDefault();
      if (list.hidden) { setSuggestOpen(input, list, true); }
      // Cycle through the options and back to the typed text (-1).
      var next = active + (evt.key === 'ArrowDown' ? 1 : -1);
      if (next >= count) next = -1;
      if (next < -1) next = count - 1;
      setSuggestActive(input, list, next);
    } else if (evt.key === 'Enter' && !list.hidden && active >= 0) {
      evt.preventDefault();
      pickSuggestion(input, list, suggestOptions(list)[active]);
    } else if (evt.key === 'Escape' && !list.hidden) {
      evt.preventDefault();
      setSuggestOpen(input, list, false);
    }
  });

  // Keep focus in the input while tapping or clicking an option. Otherwise the input's blur closes the list
  // before the click lands, and the tap does nothing (this happens on touch screens especially).
  document.addEventListener('mousedown', function (evt) {
    if (evt.target.closest && evt.target.closest('.suggest-list')) evt.preventDefault();
  });
  document.addEventListener('click', function (evt) {
    var opt = evt.target.closest && evt.target.closest('.suggest-list [role="option"]');
    if (!opt) return;
    var list = opt.closest('.suggest-list');
    var input = suggestInput(list);
    if (input) { pickSuggestion(input, list, opt); input.focus(); }
  });
  document.addEventListener('focusout', function (evt) {
    var list = suggestListFor(evt.target);
    if (list && !(evt.relatedTarget && list.contains(evt.relatedTarget))) setSuggestOpen(evt.target, list, false);
  });
  document.addEventListener('reset', function (evt) {
    Array.prototype.forEach.call(evt.target.querySelectorAll('.suggest-list'), function (list) {
      var input = suggestInput(list);
      list.innerHTML = '';
      if (input) setSuggestOpen(input, list, false);
    });
  });

  // Network trouble: let the user know instead of failing silently.
  document.addEventListener('htmx:sendError', function () {
    showToast('Could not reach the server. Check your connection.', 'error');
  });
  document.addEventListener('htmx:responseError', function (evt) {
    showToast('The server had trouble (' + evt.detail.xhr.status + '). Try again.', 'error');
  });

  // Flash messages rendered with the page fade out like toasts.
  function initPageToasts() {
    var box = container();
    if (!box) return;
    Array.prototype.forEach.call(box.querySelectorAll('.toast'), dismissLater);
  }
  document.addEventListener('DOMContentLoaded', initPageToasts);
  document.addEventListener('htmx:afterSettle', function (evt) {
    if (evt.target === document.body) initPageToasts();
  });
})();
