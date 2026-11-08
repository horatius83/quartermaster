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

  // Item-name suggestions (views/partials/item_suggest.php). Typing searches the catalog through htmx; the ▾ button
  // lists the category chosen in the form's Category field. This opens, closes and picks.
  // The list's state lives on it: data-mode is "search" or "browse", data-picked the name just picked, and data-gen
  // counts the user's moves so a browse reply that arrives after one of them is dropped.
  function suggestParts(el) {
    var box = el && el.closest && el.closest('.suggest');
    if (!box) return null;
    var input = box.querySelector('[role="combobox"]');
    var list = box.querySelector('.suggest-list');
    return input && list ? { box: box, input: input, list: list, toggle: box.querySelector('.suggest-toggle') } : null;
  }
  function suggestOptions(p) {
    return p.list.querySelectorAll('[role="option"]');
  }
  function suggestCategory(p) {
    var form = p.box.closest('form');
    return form && form.querySelector('select[name="category_id"]');
  }
  function setSuggestActive(p, index) {
    var opts = suggestOptions(p);
    Array.prototype.forEach.call(opts, function (o, i) { o.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
    p.list.dataset.active = String(index);
    var opt = opts[index];
    if (opt) {
      p.input.setAttribute('aria-activedescendant', opt.id);
      // Scroll the list only. scrollIntoView would also scroll the page and could push the input off screen.
      if (opt.offsetTop < p.list.scrollTop) p.list.scrollTop = opt.offsetTop;
      else if (opt.offsetTop + opt.offsetHeight > p.list.scrollTop + p.list.clientHeight) {
        p.list.scrollTop = opt.offsetTop + opt.offsetHeight - p.list.clientHeight;
      }
    } else {
      p.input.removeAttribute('aria-activedescendant');
    }
  }
  function setSuggestOpen(p, open) {
    p.list.hidden = !open;
    p.input.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (p.toggle) p.toggle.setAttribute('aria-expanded', open && p.list.dataset.mode === 'browse' ? 'true' : 'false');
    if (!open) setSuggestActive(p, -1);
  }
  function numberSuggestions(p) {
    p.list.scrollTop = 0;
    Array.prototype.forEach.call(suggestOptions(p), function (o, i) { o.id = p.list.id + '-' + i; });
  }
  function suggestMoved(p) {
    p.list.dataset.gen = String((parseInt(p.list.dataset.gen || '0', 10) || 0) + 1);
  }
  function closeSuggestions(p) {
    suggestMoved(p);
    setSuggestOpen(p, false);
  }
  function pickSuggestion(p, opt) {
    p.input.value = opt.dataset.value;
    // Replies still on their way (or a delayed request yet to fire) mustn't reopen the list over the pick.
    p.list.dataset.picked = p.input.value;
    // A catalog item keeps its own category, so show that one rather than leave a different one chosen.
    var category = suggestCategory(p);
    var id = opt.dataset.categoryId;
    if (category && id && category.querySelector('option[value="' + id + '"]')) category.value = id;
    p.list.dataset.mode = '';
    closeSuggestions(p);
  }
  function browseSuggestions(p) {
    var category = suggestCategory(p);
    suggestMoved(p);
    var gen = p.list.dataset.gen;
    p.list.dataset.mode = 'browse';
    var url = p.toggle.dataset.url + '?browse=1&category_id=' + encodeURIComponent(category ? category.value : '');
    fetch(url, { credentials: 'same-origin', headers: { 'HX-Request': 'true' } })
      .then(function (res) {
        if (!res.ok) throw new Error(String(res.status));
        return res.text();
      })
      .then(function (html) {
        if (p.list.dataset.gen !== gen || !document.body.contains(p.list)) return;
        p.list.innerHTML = html;
        numberSuggestions(p);
        setSuggestOpen(p, true);
      })
      .catch(function () {
        showToast('Could not load the catalog. Try again.', 'error');
      });
  }

  // Note what each search asked for, so a reply that no longer matches the input can be dropped.
  document.addEventListener('htmx:beforeRequest', function (evt) {
    var p = suggestParts(evt.detail.elt);
    if (p && evt.detail.elt === p.input) evt.detail.xhr.qmSearched = p.input.value;
  });
  document.addEventListener('htmx:beforeSwap', function (evt) {
    var p = suggestParts(evt.detail.target);
    if (!p || evt.detail.target !== p.list) return;
    // hx-sync only aborts a request once the next one is sent, 250 ms after typing stops, so a reply can land for
    // older text. Nor should a search reply reopen the list over a pick, or replace a list being browsed.
    if (evt.detail.xhr.qmSearched !== p.input.value || p.input.value === p.list.dataset.picked
        || p.list.dataset.mode === 'browse') {
      evt.detail.shouldSwap = false;
    }
  });
  document.addEventListener('htmx:afterSwap', function (evt) {
    var p = suggestParts(evt.detail.target);
    if (!p || evt.detail.target !== p.list) return;
    numberSuggestions(p);
    // Don't pop open if the user has already moved on (e.g. tapped another field before the reply came).
    setSuggestOpen(p, suggestOptions(p).length > 0 && p.input.value !== '' && document.activeElement === p.input);
  });

  document.addEventListener('input', function (evt) {
    var p = suggestParts(evt.target);
    if (!p || evt.target !== p.input) return;
    // Typing always searches the whole catalog, even with a category being browsed.
    suggestMoved(p);
    p.list.dataset.mode = 'search';
    delete p.list.dataset.picked;
    if (p.input.value === '') {
      p.list.innerHTML = '';
      setSuggestOpen(p, false);
    }
  });

  document.addEventListener('keydown', function (evt) {
    var p = suggestParts(evt.target);
    if (!p || evt.target !== p.input) return;
    var count = suggestOptions(p).length;
    var active = parseInt(p.list.dataset.active || '-1', 10);
    if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
      if (!count) {
        // Nothing to move through yet: down on an empty field browses, like the ▾ button.
        if (evt.key === 'ArrowDown' && p.input.value === '' && p.toggle) {
          evt.preventDefault();
          browseSuggestions(p);
        }
        return;
      }
      evt.preventDefault();
      if (p.list.hidden) setSuggestOpen(p, true);
      // Cycle through the options and back to the typed text (-1).
      var next = active + (evt.key === 'ArrowDown' ? 1 : -1);
      if (next >= count) next = -1;
      if (next < -1) next = count - 1;
      setSuggestActive(p, next);
    } else if (evt.key === 'Enter' && !p.list.hidden && active >= 0) {
      evt.preventDefault();
      pickSuggestion(p, suggestOptions(p)[active]);
    } else if (evt.key === 'Escape' && !p.list.hidden) {
      evt.preventDefault();
      closeSuggestions(p);
    }
  });

  // Keep focus where it is while tapping or clicking the list or the ▾ button. If the input has focus it keeps it
  // (and a phone keeps its keyboard); if it hasn't, browsing doesn't pop the keyboard up.
  document.addEventListener('mousedown', function (evt) {
    if (evt.target.closest && evt.target.closest('.suggest-list, .suggest-toggle')) evt.preventDefault();
  });
  document.addEventListener('click', function (evt) {
    if (!evt.target.closest) return;
    var toggle = evt.target.closest('.suggest-toggle');
    var p = suggestParts(toggle);
    if (p) {
      if (!p.list.hidden && p.list.dataset.mode === 'browse') closeSuggestions(p);
      else browseSuggestions(p);
      return;
    }
    var opt = evt.target.closest('.suggest-list [role="option"]');
    p = suggestParts(opt);
    if (p) pickSuggestion(p, opt);
  });
  // Close when the user moves on: a tap or click elsewhere, or tabbing out of the field and its button.
  document.addEventListener('pointerdown', function (evt) {
    Array.prototype.forEach.call(document.querySelectorAll('.suggest'), function (box) {
      var p = suggestParts(box);
      if (p && !p.list.hidden && !box.contains(evt.target)) closeSuggestions(p);
    });
  });
  document.addEventListener('focusout', function (evt) {
    var p = suggestParts(evt.target);
    if (p && !p.list.hidden && evt.relatedTarget && !p.box.contains(evt.relatedTarget)) closeSuggestions(p);
  });
  // Changing Category while browsing (the list closes when the select is tapped) shows the new category.
  document.addEventListener('change', function (evt) {
    if (!evt.target.matches || !evt.target.matches('select[name="category_id"]') || !evt.target.form) return;
    var p = suggestParts(evt.target.form.querySelector('.suggest'));
    if (p && p.toggle && p.list.dataset.mode === 'browse') browseSuggestions(p);
  });
  document.addEventListener('reset', function (evt) {
    Array.prototype.forEach.call(evt.target.querySelectorAll('.suggest'), function (box) {
      var p = suggestParts(box);
      if (!p) return;
      p.list.innerHTML = '';
      p.list.dataset.mode = '';
      closeSuggestions(p);
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
