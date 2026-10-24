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
