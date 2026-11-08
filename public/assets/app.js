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
