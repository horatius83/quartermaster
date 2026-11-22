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
  // lists the category chosen in the form's Category field. This opens, closes and picks. The stock form's Notes field
  // (views/dm/stores.php) suggests spells the same way, with no ▾ button.
  // The list's state lives on it: data-mode is "search" or "browse"; data-settled is text the user is done with (an
  // item picked, or the list dismissed), which no search reply may reopen the list over; and data-gen counts the user's
  // moves, so any reply that arrives after one of them is dropped. Browse requests go through htmx too (so a sign-in
  // redirect or a server error is handled like any other), marked with an X-QM-Suggest header.
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
    Array.prototype.forEach.call(suggestOptions(p), function (o, i) { o.id = p.list.id + '-' + i; });
  }
  function suggestMoved(p) {
    p.list.dataset.gen = String((parseInt(p.list.dataset.gen || '0', 10) || 0) + 1);
  }
  function closeSuggestions(p) {
    suggestMoved(p);
    setSuggestOpen(p, false);
  }
  // Closed on purpose (Escape, ▾ again, or a pick), unlike a tap elsewhere: changing Category doesn't bring it back,
  // and nor does a search for this text that was still waiting out its typing delay.
  function dismissSuggestions(p) {
    p.list.dataset.mode = '';
    p.list.dataset.settled = p.input.value;
    closeSuggestions(p);
  }
  // A suggestion field whose search takes in another field (hx-include="#that-field": the stock form's Notes takes in
  // the item, whose spell level picks the spells). When that field changes, the list held is for its old value, so
  // drop it (and any reply still on its way) and, if there's text to search for, search again.
  function refreshDependents(field) {
    if (!field.id) return;
    Array.prototype.forEach.call(document.querySelectorAll('[role="combobox"][hx-include="#' + field.id + '"]'), function (input) {
      var p = suggestParts(input);
      if (!p) return;
      p.list.innerHTML = '';
      p.list.dataset.mode = '';
      delete p.list.dataset.settled;
      closeSuggestions(p);
      if (input.value !== '') htmx.trigger(input, 'qm-refresh');
    });
  }
  function pickSuggestion(p, opt) {
    if (opt.dataset.more) {
      // "Show more" at the end of a long category: load the next batch under this one.
      requestBrowse(p, 'more', opt.dataset.more);
      return;
    }
    p.input.value = opt.dataset.value;
    refreshDependents(p.input);
    // A catalog item keeps its own category, so show that one rather than leave a different one chosen.
    var category = suggestCategory(p);
    var id = opt.dataset.categoryId;
    if (category && id && category.querySelector('option[value="' + id + '"]')) category.value = id;
    // A pick fills Price, Weight and Category from the catalog, over whatever they hold (see the description box,
    // below). Category is set first so the box's reply finds it as picked and marks it filled in. The pick also
    // cancels a request the typing had waiting (htmx keeps one delay per element), so nothing replaces this one.
    if (itemDetailsFor(p.input)) htmx.trigger(p.input, 'qm-item-changed', { pick: true });
    dismissSuggestions(p);
  }
  function requestBrowse(p, kind, offset) {
    var category = suggestCategory(p);
    var url = p.input.getAttribute('hx-get') + '?browse=1&category_id=' + encodeURIComponent(category ? category.value : '')
      + (offset ? '&offset=' + encodeURIComponent(offset) : '');
    // Sent from ▾, not the field: requests from the field share its hx-sync, so a search still in its typing delay
    // would fire and abort this one.
    htmx.ajax('GET', url, {
      source: p.toggle, target: p.list, swap: kind === 'more' ? 'beforeend' : 'innerHTML', headers: { 'X-QM-Suggest': kind },
    });
  }
  function browseSuggestions(p) {
    suggestMoved(p);
    p.list.dataset.mode = 'browse';
    requestBrowse(p, 'browse');
  }

  // Which request a reply answers: a typed search, a browse (▾), or "more" of a browse.
  function suggestKind(evt) {
    var headers = evt.detail.requestConfig && evt.detail.requestConfig.headers;
    return (headers && headers['X-QM-Suggest']) || 'search';
  }
  // Note how far along the user was when each request (search or browse) was sent, so a late reply can be dropped.
  document.addEventListener('htmx:beforeRequest', function (evt) {
    var p = suggestParts(evt.detail.elt);
    if (!p || (evt.detail.elt !== p.input && evt.detail.elt !== p.toggle)) return;
    evt.detail.xhr.qmGen = p.list.dataset.gen;
  });
  document.addEventListener('htmx:beforeSwap', function (evt) {
    var p = suggestParts(evt.detail.target);
    if (!p || evt.detail.target !== p.list || !evt.detail.shouldSwap) return;
    var kind = suggestKind(evt);
    // Any reply is stale once the user has typed, browsed, picked or closed the list since asking: each of those
    // moves data-gen on. (hx-sync only aborts a request once the next is sent, so replies do land late.) A search can
    // also be sent after one of those, when its typing delay runs out: it mustn't replace a list being browsed (▾
    // tapped straight after typing), or reopen the list over a pick or Escape.
    var stale = evt.detail.xhr.qmGen !== p.list.dataset.gen
      || (kind === 'search' && (p.list.dataset.mode === 'browse' || p.input.value === p.list.dataset.settled));
    if (stale) {
      evt.detail.shouldSwap = false;
    } else if (kind === 'more') {
      var more = p.list.querySelector('[data-more]');
      evt.detail.xhr.qmFirstNew = suggestOptions(p).length - (more ? 1 : 0);
      if (more) more.remove();
    }
  });
  document.addEventListener('htmx:afterSwap', function (evt) {
    var p = suggestParts(evt.detail.target);
    if (!p || evt.detail.target !== p.list) return;
    var kind = suggestKind(evt);
    numberSuggestions(p);
    if (kind === 'search') {
      p.list.scrollTop = 0;
      // Don't pop open if the user has already moved on (e.g. tapped another field before the reply came).
      setSuggestOpen(p, suggestOptions(p).length > 0 && p.input.value !== '' && document.activeElement === p.input);
    } else if (kind === 'browse') {
      p.list.scrollTop = 0;
      setSuggestOpen(p, true);
    } else if (parseInt(p.list.dataset.active || '-1', 10) >= 0) {
      // Chosen from the keyboard: carry on from the first of the new items.
      setSuggestActive(p, evt.detail.xhr.qmFirstNew);
    }
  });

  document.addEventListener('input', function (evt) {
    refreshDependents(evt.target);
    // What was filled in was for the old name. Take it out now rather than when the description box catches up, so
    // Enter or Add to shelf straight after typing can't stock the new name at the old one's price.
    if (itemDetailsFor(evt.target)) forgetFilledIn(evt.target.form);
    var p = suggestParts(evt.target);
    if (!p || evt.target !== p.input) return;
    // Typing always searches the whole catalog, even with a category being browsed.
    suggestMoved(p);
    p.list.dataset.mode = 'search';
    delete p.list.dataset.settled;
    if (p.input.value === '') {
      p.list.innerHTML = '';
      setSuggestOpen(p, false);
    }
  });

  document.addEventListener('keydown', function (evt) {
    var p = suggestParts(evt.target);
    if (!p) return;
    if (evt.target === p.toggle) {
      // Enter or Space activates ▾ (see the click handler), which then moves focus into the field. The mark lasts
      // only until the key comes up, so it can't make a later tap or a screen reader's double-tap look like a key.
      if (evt.key === 'Enter' || evt.key === ' ') p.toggle.dataset.keyboard = '1';
      if (evt.key === 'Escape' && !p.list.hidden) {
        evt.preventDefault();
        dismissSuggestions(p);
        return;
      }
      // Down with the list closed opens it, like Enter; with it open, the arrow keys carry on in the field.
      if (evt.key === 'ArrowDown' && p.list.hidden) {
        evt.preventDefault();
        browseSuggestions(p);
        p.input.focus();
        return;
      }
      if ((evt.key === 'ArrowDown' || evt.key === 'ArrowUp') && !p.list.hidden) p.input.focus();
      else return;
    } else if (evt.target !== p.input) {
      return;
    }
    var count = suggestOptions(p).length;
    var active = parseInt(p.list.dataset.active || '-1', 10);
    if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
      // Down on an empty field with the list closed browses, like the ▾ button. (Whatever the closed list still
      // holds may be another category's.)
      if (evt.key === 'ArrowDown' && p.list.hidden && p.input.value === '' && p.toggle) {
        evt.preventDefault();
        browseSuggestions(p);
        return;
      }
      if (!count) return;
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
      dismissSuggestions(p);
    }
  });
  // Space clicks on keyup, after this; Enter clicked on keydown, before it. Either way the click has had the mark.
  function forgetKeyboard(evt) {
    var toggle = evt.target.closest && evt.target.closest('.suggest-toggle');
    if (toggle) setTimeout(function () { delete toggle.dataset.keyboard; }, 0);
  }
  document.addEventListener('keyup', forgetKeyboard);
  document.addEventListener('focusout', forgetKeyboard);

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
      var fromKeyboard = toggle.dataset.keyboard;
      delete toggle.dataset.keyboard;
      if (!p.list.hidden && p.list.dataset.mode === 'browse') {
        dismissSuggestions(p);
      } else {
        browseSuggestions(p);
        // From the keyboard (Enter or Space on ▾), carry on in the field, where the arrow keys move through the list.
        // Not for taps: a phone would put its keyboard up over the list. (A click's detail is 0 for a screen reader's
        // double-tap as well as for keys, so the keydown handler marks keyboard use instead.)
        if (fromKeyboard) p.input.focus();
      }
      return;
    }
    var opt = evt.target.closest('.suggest-list [role="option"]');
    p = suggestParts(opt);
    if (p) pickSuggestion(p, opt);
  });
  // Close when the user moves on: a tap or click elsewhere, or tabbing out of the field and its button.
  document.addEventListener('pointerdown', function (evt) {
    // A tap or click on ▾ isn't keyboard use, whatever keys were pressed on it before.
    var toggle = evt.target.closest && evt.target.closest('.suggest-toggle');
    if (toggle) delete toggle.dataset.keyboard;
    Array.prototype.forEach.call(document.querySelectorAll('.suggest'), function (box) {
      var p = suggestParts(box);
      if (p && !p.list.hidden && !box.contains(evt.target)) closeSuggestions(p);
    });
  });
  document.addEventListener('focusout', function (evt) {
    var p = suggestParts(evt.target);
    if (p && !p.list.hidden && evt.relatedTarget && !p.box.contains(evt.relatedTarget)) closeSuggestions(p);
  });
  // Changing Category while browsing shows the new category. The list closes when the select is tapped (and on a
  // phone it covers the select), so this counts a list closed by a tap elsewhere, but not one dismissed with ▾ or Escape.
  document.addEventListener('change', function (evt) {
    if (!evt.target.matches || !evt.target.matches('select[name="category_id"]') || !evt.target.form) return;
    var p = suggestParts(evt.target.form.querySelector('.suggest'));
    if (p && p.toggle && p.list.dataset.mode === 'browse') browseSuggestions(p);
  });
  // The give and stock forms' description box (views/partials/item_details.php): what the catalog says about the item
  // in the field it takes in (hx-include), drawn again as the name changes. For a catalog item, its price, weight and
  // category go into the form's Price (Value on the give form), Weight and Category. Picking it fills them whatever
  // they hold; typing its name only fills a field the DM hasn't changed (one still at its default or holding what was
  // filled in before).
  // Either way a field the DM changes while the reply is on its way is theirs, and a reply for a name the field no
  // longer holds is dropped. When the name stops being a catalog item, a field still holding what was filled in goes
  // back to its default, so a new item isn't added to the catalog with another item's price or category.
  var DETAIL_FIELDS = { price: 'price', weight: 'weight', category_id: 'categoryId' };
  function itemDetailsFor(input) {
    var form = input.id && input.form;
    return form ? form.querySelector('.item-details[hx-include="#' + input.id + '"]') : null;
  }
  function isItemDetails(el) {
    return !!(el && el.classList && el.classList.contains('item-details'));
  }
  function detailFields(form) {
    return Object.keys(DETAIL_FIELDS).map(function (name) {
      return form.querySelector('input[name="' + name + '"], select[name="' + name + '"]');
    }).filter(Boolean);
  }
  // What the field holds when the form is reset: for a select, the option marked selected in the page, or the first.
  function fieldDefault(field) {
    if (field.tagName !== 'SELECT') return field.defaultValue;
    var chosen = Array.prototype.filter.call(field.options, function (o) { return o.defaultSelected; })[0] || field.options[0];
    return chosen ? chosen.value : '';
  }
  function forgetFilledIn(form) {
    detailFields(form).forEach(function (field) {
      if (field.dataset.autofilled === undefined) return;
      if (field.value === field.dataset.autofilled) field.value = fieldDefault(field);
      delete field.dataset.autofilled;
    });
  }
  function detailsName(box) {
    var input = document.querySelector(box.getAttribute('hx-include'));
    return input ? input.value.trim().toLowerCase() : '';
  }
  document.addEventListener('htmx:beforeRequest', function (evt) {
    var box = evt.detail.elt;
    if (!isItemDetails(box)) return;
    var trigger = evt.detail.requestConfig && evt.detail.requestConfig.triggeringEvent;
    var xhr = evt.detail.xhr;
    xhr.qmItem = detailsName(box);
    xhr.qmPick = !!(trigger && trigger.detail && trigger.detail.pick);
    xhr.qmFields = detailFields(box.closest('form')).map(function (field) { return field.value; });
  });
  document.addEventListener('htmx:beforeSwap', function (evt) {
    if (isItemDetails(evt.detail.target) && evt.detail.xhr.qmItem !== detailsName(evt.detail.target)) evt.detail.shouldSwap = false;
  });
  document.addEventListener('htmx:afterSwap', function (evt) {
    var box = evt.detail.target;
    if (!isItemDetails(box)) return;
    var info = box.querySelector('[data-found]');
    var form = box.closest('form');
    var xhr = evt.detail.xhr;
    if (!info || !form) return;
    if (info.dataset.found !== '1') {
      forgetFilledIn(form);
      return;
    }
    detailFields(form).forEach(function (field, i) {
      if (field.value !== xhr.qmFields[i]) return;
      var mine = field.value !== fieldDefault(field) && field.value !== field.dataset.autofilled;
      if (mine && !xhr.qmPick) return;
      var value = info.dataset[DETAIL_FIELDS[field.name]];
      if (value === undefined || (field.tagName === 'SELECT' && !field.querySelector('option[value="' + value + '"]'))) return;
      field.value = value;
      field.dataset.autofilled = value;
    });
  });

  document.addEventListener('reset', function (evt) {
    // The form's fields go back to their defaults, so nothing in them is filled in any more. Once they have, draw the
    // description box again for the emptied item field.
    Array.prototype.forEach.call(evt.target.querySelectorAll('[data-autofilled]'), function (field) { delete field.dataset.autofilled; });
    Array.prototype.forEach.call(evt.target.querySelectorAll('.item-details'), function (box) {
      var input = document.querySelector(box.getAttribute('hx-include'));
      if (input) setTimeout(function () { htmx.trigger(input, 'qm-item-changed'); }, 0);
    });
    Array.prototype.forEach.call(evt.target.querySelectorAll('.suggest'), function (box) {
      var p = suggestParts(box);
      if (!p) return;
      p.list.innerHTML = '';
      p.list.dataset.mode = '';
      closeSuggestions(p);
    });
  });

  // A boosted link only swaps the page body, so after a deploy a tab left open would keep running the old app.js and
  // app.css against new markup. When the page fetched says its assets have changed, load it in full instead.
  // Only pages fetched by GET: a form's reply was built from messages and kept input taken from the session, so
  // fetching it again would lose them. Forms here answer with redirect(), which htmx follows as a full page load
  // (HX-Redirect), so they pick up new assets anyway.
  document.addEventListener('htmx:beforeSwap', function (evt) {
    if (!evt.detail.boosted || !evt.detail.shouldSwap || evt.detail.requestConfig.verb !== 'get') return;
    if (assetsChanged(evt.detail.serverResponse)) {
      evt.detail.shouldSwap = false;
      window.location.href = evt.detail.xhr.responseURL || evt.detail.pathInfo.requestPath;
    }
  });
  // Back or Forward to a page htmx no longer has cached: it fetches the page itself and swaps it in without the event
  // above. It can't be stopped here, but loading the address in full replaces it straight away.
  document.addEventListener('htmx:historyCacheMissLoad', function (evt) {
    if (assetsChanged(evt.detail.xhr.response)) window.location.replace(evt.detail.path);
  });
  function assetsChanged(html) {
    var mine = document.querySelector('meta[name="qm-assets"]');
    var theirs = /<meta name="qm-assets" content="([^"]*)"/.exec(html || '');
    return !!(mine && theirs && theirs[1] !== mine.getAttribute('content'));
  }

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
