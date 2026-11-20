/* =========================================================
   UI kit — behaviour for the app shell (vanilla JS, no deps).
   Exposes window.UI. Works from file:// (no server needed).

   Automatic behaviour (just use the classes from ui.css):
   - responsive modes on .app[data-mode]: wide / compact / narrow
   - [data-action="toggle-sidebar"]  show / hide the sidebar (aria-expanded kept in sync)
   - [data-action="toggle-inspector"] show / hide the right sidebar (.inspector), ⌥⌘I (aria-pressed)
   - [data-action="back"]            narrow mode: back to the list
   - .resizer inside .sidebar/.column drag left/right (double-click = reset)
   - .sb-heading                      collapse a sidebar section (aria-expanded)
   - .sb-item                         becomes .active, fires "ui:navigate"
   - .column .row                     becomes .selected, fires "ui:select"
                                      (↑ ↓ keys move the selection; rows are focusable,
                                      Enter / Space select the focused row)
   - .p-row.click, .feed-item.click   focusable, Enter / Space = click
   - .data-table.click tbody tr, .data-table tr.click   focusable, Enter = click
   - [data-set-theme="auto|light|dark"] theme buttons
   - .search                          clear button + Esc to clear
   - .pane-body scroll                hairline under its .pane-header
   - .tabs > .tab[data-tab]           tabs: .active + aria-selected, ← → Home End,
                                      shows / hides [data-tab-panel="…"], fires "ui:tab"
   - [data-tip="text"]                tooltip (.tooltip) after ~400 ms hover or on keyboard focus
                                      (optional data-tip-kbd="⌘K" key cap, data-side="top|bottom")
   - icon-only buttons with a title   the title is mirrored into aria-label
   - menus                            ↑ ↓ Home End Enter Esc, real focus + focus restore
   - popups                           Tab is trapped inside, focus returns to the opener
   - board cards                      Alt+← → move between lanes, Alt+↑ ↓ reorder
   - calendar events                  Alt+↑ ↓ move by a step, Alt+← → by a day / resource, Alt+Shift+↑ ↓ resize

   API:
   UI.init({ key, wide, compact, locale, currency, strings, router, iconSprite, palette })
                                     global part (listeners on document, theme, strings) once + UI.attach(document);
                                     key = localStorage prefix; calling it again only re-applies the options
   UI.attach(context = document)     wire up kit behaviour in a fragment that was just inserted (e.g. swapped in by HTMX);
                                     idempotent — elements are remembered, a second call binds nothing twice. Fires "ui:attach".
   UI.detach(context = document)     before a fragment is removed: closes its menus / popovers / tooltips (and popups opened
                                     with { owner } inside it), runs the teardowns registered on its elements (board, calendar,
                                     date picker, resizers, context menus, UI.onDetach). Fires "ui:detach". Returns how many ran.
   UI.behavior(name, { attach(ctx), detach(ctx) })   your own code that runs on every UI.attach / UI.detach (Drupal-style)
   UI.once(id, selector | elements, context) → elements not yet seen under that id (UI.detach forgets them again)
   UI.onDetach(element, fn) → unregister   run fn when UI.detach() covers the element
   UI.setStrings({ key: 'text' }), UI.t(key, vars?), UI.strings   texts of the kit itself (i18n)
   UI.defaultStrings                 every key with its English text (frozen; also dist/kit/strings.json)
   UI.setLocale({ locale, currency }), UI.locale, UI.currency       used by UI.fmt
   UI.setTheme('auto'|'light'|'dark'), UI.theme, UI.toggleDark()   ([data-dark-switch] toggles light ⇄ dark)
   UI.setPalette(id), UI.palette, UI.defaultPalette, UI.palettes, UI.paletteButtons()
                                     default palette: <html data-default-palette="cobalt"> or UI.init({ palette }) —
                                     used until the user picks one
   UI.toggleSidebar(force?), UI.showDetail(), UI.showList(), UI.mode, UI.view
   UI.toggleInspector(force?), UI.openInspector(), UI.closeInspector(), UI.inspectorOpen
   UI.selectRow(rowEl, { scroll, open })
   UI.modal({ title, message, icon, html, width, label, dismissible, closeButton, draggable, onClose, owner,
              actions: [{ label, primary, destructive, value, run }] }) → { el, body, close(result) }, UI.modalOpen,
   UI.closeModals()                  (popups and menus also close on Back / Forward to another route)
   UI.confirm({ title, message, icon, confirmLabel, cancelLabel, destructive }) → Promise<boolean>
   UI.popover(anchorElement, html, { width, align, className, onClose, focus, label })   (board, calendar, date picker:
                                     .destroy() — UI.detach calls it for you)
   UI.board(container, options)      Jira-style board, see the Board section below
   UI.calendar(container, options)   week / day / resources / agenda calendar with drag & keyboard, see the Calendar section
   UI.datePicker(container, options) month grid date picker (keyboard: arrows, PageUp/PageDown, Home/End)
   UI.menu(items, { anchor | x,y, align, width, className, onClose, onEnter, focus, label, owner }), UI.menuOpen
                                     (an item with checked: true|false is a switch row: run(checked), the menu stays open)
   UI.closeMenu(), UI.contextMenu(element, fnReturningItems) → off,
   UI.setAccountMenu(items)          items + the strings 'dark-switch', 'palettes', 'theme' (built-in rows)
   UI.hud(text, { undo, duration }), UI.announce(text)   (announce: screen readers only)
   UI.route(handler) → unsubscribe, UI.navigate(path, { replace, params }), UI.currentRoute   router: hash (#/path?x=y, default)
   UI.setRouter({ mode: 'hash' | 'path', base: '/app/' }), UI.router, UI.href(path, params)   or path mode (History API)
   UI.selectTab(tabElement, focus?)
   UI.uid(prefix?), UI.initials(name), UI.avatar(name, { size, color, className, title })
   UI.fmt.ago(date), UI.fmt.date(date, opts), UI.fmt.number(n, opts), UI.fmt.money(n, currency, opts), UI.fmt.dayDiff(date),
   UI.fmt.time(date, { hour12 })     (12 / 24 h from the locale)
   UI.store(key, defaults) → { key, get(), set(v), reset() }, UI.load(key, default), UI.save(key, value)
   UI.debounce(fn, ms)
   UI.icon(name, extraClass)         <svg><use href="#i-name"> (or "<iconSprite>#i-name" with UI.init({ iconSprite }))
   UI.esc(text)                      escape user data before it goes into any html string

   Events on document: ui:mode, ui:theme, ui:palette, ui:inspector, ui:navigate, ui:select,
   ui:tab { tab, el }, ui:route { path, parts, params }, ui:attach { context }, ui:detach { context }
   ========================================================= */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  /** like $$, but the root itself counts too when it matches (a fragment handed to UI.attach can be the component) */
  var scoped = function (s, r) {
    r = r || document;
    var self = r.nodeType === 1 && r.matches(s) ? [r] : [];
    return self.concat($$(s, r));
  };
  var clamp = function (v, a, b) { return Math.min(b, Math.max(a, v)); };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var icon = function (name, cls) {
    return '<svg class="ic ' + (cls || '') + '" aria-hidden="true"><use href="' + esc(cfg.iconSprite || '') + '#i-' + name + '"/></svg>';
  };
  var emit = function (name, detail) { document.dispatchEvent(new CustomEvent(name, { detail: detail })); };

  var cfg = { key: 'app', wide: 1100, compact: 760, inspectorInline: 1280, locale: 'en-US', currency: 'USD', iconSprite: '' };
  var app = null;
  var state = { mode: null, collapsed: false, view: 'list', inspector: false };
  var seq = 0;     // counter for generated ids
  var kbd = false; // was the last interaction a key press? (where menu focus goes, focus tooltips)

  function setAttr(el, name, value) { if (el.getAttribute(name) !== String(value)) el.setAttribute(name, value); }
  function isTextField(el) {
    if (!el || !el.matches) return false;
    if (el.matches('textarea, select, [contenteditable=""], [contenteditable="true"]')) return true;
    return el.matches('input') && !/^(button|submit|reset|checkbox|radio|range|color|file|image)$/.test(el.type);
  }
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), ' +
    'textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), [contenteditable=""], [contenteditable="true"]';
  function focusables(root, not) {
    return $$(FOCUSABLE, root).filter(function (x) { return x.getClientRects().length && !(not && x.matches(not)); });
  }
  function focusEl(el, scroll) { try { el.focus({ preventScroll: !scroll }); } catch (e) {} }

  /* ---------- strings (i18n) ----------
     Every text the kit shows by itself. Override with UI.init({ strings: { cancel: 'Anuluj' } })
     or UI.setStrings({...}); read with UI.t('cancel'). {name} placeholders are filled from vars.
     UI.defaultStrings keeps this English table (frozen); tools/vendor.mjs writes it to dist/kit/strings.json —
     keep the object between the markers a plain literal (strings and + only). */
  /* @strings-begin */
  var STR = {
    cancel: 'Cancel', ok: 'OK', undo: 'Undo', create: 'Create', dialog: 'Dialog', close: 'Close',
    hideSidebar: 'Hide Sidebar', showSidebar: 'Show Sidebar',
    hideInspector: 'Hide Inspector', showInspector: 'Show Inspector',
    hide: 'Hide', show: 'Show', expand: 'Expand', collapse: 'Collapse',
    boardPlaceholder: 'What needs to be done?',
    boardMoved: 'Moved {title} to {lane}, position {pos} of {total}',
    theme: 'Theme', colors: 'Colors', light: 'Light', dark: 'Dark', auto: 'Auto', matchSystem: 'Match system',
    darkMode: 'Dark mode',
    paletteLabel: '{name} palette',
    paletteLagoon: 'Lagoon', paletteSage: 'Sage', paletteBerry: 'Berry', paletteIndigo: 'Indigo', paletteCobalt: 'Cobalt',
    account: 'Account', accountProfile: 'Profile', accountSettings: 'Settings', accountNotifications: 'Notifications',
    accountShortcuts: 'Keyboard shortcuts', accountBilling: 'Billing', accountSwitch: 'Switch account…',
    accountHelp: 'Help & feedback', accountLogout: 'Log out',
    justNow: 'just now', minAgo: '{n} min ago', hoursAgo: '{n} h ago',
    today: 'Today', yesterday: 'Yesterday', tomorrow: 'Tomorrow',
    daysAgo: '{n} days ago', weeksAgo: '{n} wk ago', monthsAgo: '{n} mo ago', yearsAgo: '{n} yr ago',
    calLabel: 'Calendar', calAllDay: 'All day', calNoEvents: 'Nothing scheduled', calPickDay: 'Choose a day',
    calHelp: 'Arrow keys choose a time slot, Shift+Up and Shift+Down change its length, Enter creates a booking there. ' +
      'On an event: Enter opens it, Alt+arrow keys move it, Alt+Shift+Up and Alt+Shift+Down change its length.',
    calMoved: 'Moved {title} to {when}', calResized: '{title} now {when}', calUnavailable: 'Not available',
    dpPrev: 'Previous month', dpNext: 'Next month'
  };
  /* @strings-end */
  var DEFAULT_STR = Object.freeze(Object.assign({}, STR));
  function t(key, vars) {
    var s = STR[key] == null ? key : String(STR[key]);
    return vars ? s.replace(/\{(\w+)\}/g, function (m, k) { return vars[k] == null ? '' : vars[k]; }) : s;
  }
  function setStrings(o) {
    for (var k in o || {}) STR[k] = o[k];
    update();
  }

  /* ---------- storage ---------- */
  function load(k, d) {
    try { var v = localStorage.getItem('ui.' + cfg.key + '.' + k); return v == null ? d : JSON.parse(v); }
    catch (e) { return d; }
  }
  function save(k, v) { try { localStorage.setItem('ui.' + cfg.key + '.' + k, JSON.stringify(v)); } catch (e) {} }

  /** UI.store('db', defaults) → { get(), set(v), reset() } — JSON in localStorage under "ui.<key>.<name>".
      defaults may be a value (returned as a fresh copy) or a function returning one. */
  function store(k, defaults) {
    function fresh() { return typeof defaults === 'function' ? defaults() : defaults === undefined ? undefined : JSON.parse(JSON.stringify(defaults)); }
    return {
      key: k,
      get: function () { var v = load(k); return v === undefined ? fresh() : v; },
      set: function (v) { save(k, v); return v; },
      reset: function () { try { localStorage.removeItem('ui.' + cfg.key + '.' + k); } catch (e) {} return fresh(); }
    };
  }

  /* ---------- theme ---------- */
  var mq = window.matchMedia('(prefers-color-scheme: dark)');
  var theme = 'auto';
  try { theme = JSON.parse(localStorage.getItem('ui.theme') || '"auto"'); } catch (e) {}

  function applyTheme() {
    var dark = theme === 'dark' || (theme === 'auto' && mq.matches);
    document.documentElement.dataset.scheme = dark ? 'dark' : 'light';
    markButtons(document);
  }
  function isDark() { return document.documentElement.dataset.scheme === 'dark'; }
  /** dark-mode switches: light ⇄ dark from the scheme on screen (with 'auto' this sets an explicit theme) */
  function toggleDark(force) { setTheme((force == null ? !isDark() : !!force) ? 'dark' : 'light'); }
  /** .active + aria-pressed on the theme / palette buttons inside root, checked state of the dark-mode switches */
  function markButtons(root) {
    var dark = isDark();
    scoped('[data-dark-switch]', root).forEach(function (b) {
      if (b.type === 'checkbox') b.checked = dark; else setAttr(b, 'aria-checked', dark);
    });
    scoped('[data-set-theme]', root).forEach(function (b) {
      b.classList.toggle('active', b.dataset.setTheme === theme);
      setAttr(b, 'aria-pressed', b.dataset.setTheme === theme);
    });
    scoped('[data-set-palette]', root).forEach(function (b) {
      b.classList.toggle('active', b.dataset.setPalette === palette);
      setAttr(b, 'aria-pressed', b.dataset.setPalette === palette);
    });
  }
  /* ---------- colour palettes (colorhunt.co) ---------- */
  var PALETTES = [
    { id: 'lagoon', label: 'Lagoon', colors: ['#eeeeee', '#b9e4e8', '#167d8d', '#071b2b'] },
    { id: 'sage', label: 'Sage', colors: ['#f5f5f0', '#e6d8c3', '#c2a68c', '#5d866c'] },
    { id: 'berry', label: 'Berry', colors: ['#f2dace', '#d49a9a', '#aa5e7a', '#7c0e44'] },
    { id: 'indigo', label: 'Indigo', colors: ['#fcf9ec', '#c9b5e0', '#6061b6', '#00105b'] },
    { id: 'cobalt', label: 'Cobalt', colors: ['#abd2fa', '#7692ff', '#1b2cc1', '#091540'] }
  ];
  function isPalette(id) { return PALETTES.some(function (p) { return p.id === id; }); }
  /** the user's own choice (localStorage "ui.palette"), or null */
  function storedPalette() {
    try { var v = JSON.parse(localStorage.getItem('ui.palette')); return isPalette(v) ? v : null; } catch (e) { return null; }
  }
  // the page's default (used until the user picks one): <html data-default-palette="cobalt">, or UI.init({ palette })
  var defaultPalette = document.documentElement.getAttribute('data-default-palette');
  if (!isPalette(defaultPalette)) defaultPalette = 'lagoon';
  var palette = storedPalette() || defaultPalette;

  function applyPalette() {
    if (palette === 'lagoon') delete document.documentElement.dataset.palette;
    else document.documentElement.dataset.palette = palette;
    markButtons(document);
  }
  function setPalette(id) {
    if (!isPalette(id)) return;
    palette = id;
    try { localStorage.setItem('ui.palette', JSON.stringify(id)); } catch (e) {}
    applyPalette();
    emit('ui:palette', { palette: id });
  }
  /** swatch buttons for every palette (used in the account menu; reusable anywhere) */
  function paletteButtons() {
    return PALETTES.map(function (p) {
      var c = p.colors, on = p.id === palette;
      var name = STR['palette' + p.id.charAt(0).toUpperCase() + p.id.slice(1)] || p.label;
      return '<button class="palette-swatch' + (on ? ' active' : '') + '" data-set-palette="' + p.id + '" title="' + esc(name) +
        '" aria-label="' + esc(t('paletteLabel', { name: name })) + '" aria-pressed="' + on + '"' +
        ' style="background:conic-gradient(' + c[3] + ' 0 25%,' + c[2] + ' 0 50%,' + c[1] + ' 0 75%,' + c[0] + ' 0)"></button>';
    }).join('');
  }

  function setTheme(v) {
    theme = v;
    try { localStorage.setItem('ui.theme', JSON.stringify(v)); } catch (e) {}
    applyTheme();
    emit('ui:theme', { theme: v, scheme: document.documentElement.dataset.scheme });
  }
  if (mq.addEventListener) mq.addEventListener('change', applyTheme); else mq.addListener(applyTheme);

  /* ---------- layout ---------- */
  function hasColumn() { return !!(app && app.querySelector(':scope > .column')); }

  function update() {
    if (!app) return;
    var w = window.innerWidth;
    var mode = w >= cfg.wide ? 'wide' : w >= cfg.compact ? 'compact' : 'narrow';
    if (mode !== state.mode) {
      state.mode = mode;
      app.classList.remove('sidebar-open');
      if (mode !== 'narrow') state.view = 'list';
      emit('ui:mode', { mode: mode });
    }
    app.dataset.mode = mode;
    app.dataset.view = state.view;
    app.classList.toggle('sidebar-collapsed', mode === 'wide' && state.collapsed);
    var visible = mode === 'wide' ? !state.collapsed : app.classList.contains('sidebar-open');
    $$('[data-action="toggle-sidebar"]').forEach(function (b) {
      b.title = t(visible ? 'hideSidebar' : 'showSidebar');
      setAttr(b, 'aria-expanded', visible);
      labelIconButton(b);
    });

    // right sidebar: inline when there is room, otherwise it slides over the content
    app.classList.toggle('inspector-inline', mode === 'wide' && w >= cfg.inspectorInline);
    app.classList.toggle('inspector-open', state.inspector);
    $$('[data-action="toggle-inspector"]').forEach(function (b) {
      b.classList.toggle('on', state.inspector);
      b.title = t(state.inspector ? 'hideInspector' : 'showInspector');
      setAttr(b, 'aria-pressed', state.inspector);
      labelIconButton(b);
    });
  }

  function toggleInspector(force) {
    var next = typeof force === 'boolean' ? force : !state.inspector;
    if (next === state.inspector) return;
    state.inspector = next;
    save('inspector', next);
    update();
    emit('ui:inspector', { open: next });
  }

  function toggleSidebar(force) {
    if (!app) return;
    if (state.mode === 'wide') {
      state.collapsed = typeof force === 'boolean' ? !force : !state.collapsed;
      save('collapsed', state.collapsed);
    } else {
      app.classList.toggle('sidebar-open', typeof force === 'boolean' ? force : undefined);
    }
    update();
  }
  function showDetail() { state.view = 'detail'; update(); }
  function showList() { state.view = 'list'; update(); }

  /* ---------- resizers ---------- */
  var LIMITS = { '--sidebar-w': [170, 360, 220], '--column-w': [240, 620, 340], '--inspector-w': [260, 620, 340] };

  function restoreWidths() {
    ['--sidebar-w', '--column-w', '--inspector-w'].forEach(function (v) {
      var stored = load(v, null);
      if (stored) app.style.setProperty(v, stored + 'px');
    });
  }
  /** one .resizer of the shell (bound by UI.attach); returns its teardown */
  function bindResizer(r) {
    var v = r.closest('.sidebar') ? '--sidebar-w' : r.closest('.inspector') ? '--inspector-w' : '--column-w';
    var dir = v === '--inspector-w' ? -1 : 1; // the inspector grows when dragged to the left
    var lim = LIMITS[v];
    var shell = function () { return r.closest('.app') || app; };

    function down(e) {
      e.preventDefault();
      r.setPointerCapture(e.pointerId);
      var pane = r.parentElement, a = shell();
      var x0 = e.clientX, w0 = pane.getBoundingClientRect().width;
      r.classList.add('dragging');
      a.classList.add('resizing');
      document.body.classList.add('col-resizing');
      function move(ev) {
        var maxW = Math.min(lim[1], window.innerWidth - 360);
        a.style.setProperty(v, clamp(w0 + dir * (ev.clientX - x0), lim[0], maxW) + 'px');
      }
      function up() {
        r.removeEventListener('pointermove', move);
        r.removeEventListener('pointerup', up);
        r.classList.remove('dragging');
        a.classList.remove('resizing');
        document.body.classList.remove('col-resizing');
        save(v, Math.round(pane.getBoundingClientRect().width));
      }
      r.addEventListener('pointermove', move);
      r.addEventListener('pointerup', up);
    }
    function reset() {
      shell().style.setProperty(v, lim[2] + 'px');
      save(v, lim[2]);
    }
    r.addEventListener('pointerdown', down);
    r.addEventListener('dblclick', reset);
    return function () { r.removeEventListener('pointerdown', down); r.removeEventListener('dblclick', reset); };
  }

  /* ---------- menus ---------- */
  var menu = null; // { el, anchor, onEnter, onClose, tookFocus, returnTo, aOwner, scope, expanded }

  function closeMenu(restore) {
    if (!menu) return;
    var m = menu; menu = null;
    var ae = document.activeElement;
    var inside = m.el.contains(ae);
    m.el.remove();
    if (m.aOwner) m.aOwner.removeAttribute('aria-activedescendant');
    if (m.expanded) m.anchor.setAttribute('aria-expanded', 'false');
    // focus was moved into the menu → give it back (unless something else took it meanwhile)
    if (m.tookFocus && restore !== false && (inside || !ae || ae === document.body) &&
        m.returnTo && document.contains(m.returnTo)) focusEl(m.returnTo);
    if (m.onClose) m.onClose();
  }

  /**
   * items: [{ label | html, icon, shortcut, disabled, danger, run }, '-', { header: 'Title' }, { custom: html }]
   *        { label, icon, checked: true | false, run(checked) } is a switch row (role="menuitemcheckbox" + .switch):
   *        click / Enter / Space flip it and call run(checked); the menu stays open.
   * opts:  { anchor: Element, align: 'left'|'right', x, y, width, className, onClose, onEnter, focus, owner }
   *        owner: the element the menu belongs to (default: the anchor, else the element that had focus) —
   *        UI.detach() of a fragment containing it closes the menu.
   *
   * SECURITY: `label`, `header` and `shortcut` are escaped for you. `html` (item), `custom` (item) and
   * `opts.html` are inserted as RAW HTML — escape any user data in them with UI.esc().
   *
   * focus: where keyboard focus goes. Default (undefined): focus moves into the menu (first item when it
   * was opened with the keyboard) and returns to the previously focused element when the menu closes —
   * EXCEPT when a text field has focus (autocomplete / suggestions while typing): then focus stays in the
   * field, ↑ ↓ Enter still work and the highlighted item is exposed via aria-activedescendant.
   * Pass focus: true / false to force either behaviour.
   */
  function showMenu(items, opts) {
    opts = opts || {};
    closeMenu();
    var prev = document.activeElement;
    var el = document.createElement('div');
    el.className = 'menu' + (opts.className ? ' ' + opts.className : '');
    el.setAttribute('role', opts.html != null ? 'dialog' : 'menu');
    el.tabIndex = -1;
    if (opts.html != null) el.innerHTML = opts.html;
    (items || []).forEach(function (it) {
      if (it === '-') { el.insertAdjacentHTML('beforeend', '<div class="menu-sep" role="separator"></div>'); return; }
      if (it.header) { el.insertAdjacentHTML('beforeend', '<div class="menu-header" role="presentation">' + esc(it.header) + '</div>'); return; }
      if (it.custom != null) { el.insertAdjacentHTML('beforeend', '<div class="menu-custom" role="presentation">' + it.custom + '</div>'); return; }
      var b = document.createElement('button');
      var sw = it.checked != null;   // switch row
      b.className = 'menu-item' + (it.danger ? ' danger' : '') + (sw ? ' menu-switch' : '');
      b.setAttribute('role', sw ? 'menuitemcheckbox' : 'menuitem');
      if (sw) b.setAttribute('aria-checked', String(!!it.checked));
      if (it.darkSwitch) b.setAttribute('data-dark-switch', '');
      b.id = 'ui-mi-' + (++seq);
      b.tabIndex = -1; // ↑ ↓ move between items; Tab is for the other controls in the menu
      b.disabled = !!it.disabled;
      b.innerHTML = (it.icon ? icon(it.icon) : '') +
        '<span class="ml">' + (it.html != null ? it.html : esc(it.label)) + '</span>' +
        (sw ? '<span class="switch" aria-hidden="true"></span>' : it.shortcut ? '<span class="sc">' + esc(it.shortcut) + '</span>' : '');
      b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep focus where it is
      b.addEventListener('mousemove', function () { if (!b.classList.contains('focused')) focusItem(b); });
      b.addEventListener('click', function () {
        if (!sw) { closeMenu(); if (it.run) it.run(); return; }
        var on = b.getAttribute('aria-checked') !== 'true';
        b.setAttribute('aria-checked', String(on));
        if (it.run) it.run(on);
      });
      el.appendChild(b);
    });
    el.addEventListener('mouseleave', function () { focusItem(null); });
    // real focus → .focused (Tab / screen-reader navigation inside the menu)
    el.addEventListener('focusin', function (e) {
      var it = e.target.closest && e.target.closest('.menu-item');
      if (it ? !it.classList.contains('focused') : e.target !== el) markItem(it);
    });
    if (opts.label) el.setAttribute('aria-label', opts.label);
    document.body.appendChild(el);
    if (opts.width) el.style.width = opts.width + 'px';

    var r = el.getBoundingClientRect(), x, y;
    if (opts.anchor) {
      var a = opts.anchor.getBoundingClientRect();
      x = opts.align === 'right' ? a.right - r.width : a.left;
      y = a.bottom + 6;
      if (y + r.height > window.innerHeight - 8) y = Math.max(8, a.top - r.height - 6);
    } else {
      x = opts.x; y = opts.y;
      if (y + r.height > window.innerHeight - 8) y = Math.max(8, window.innerHeight - r.height - 8);
    }
    el.style.left = clamp(x, 8, window.innerWidth - r.width - 8) + 'px';
    el.style.top = y + 'px';

    var take = opts.focus != null ? !!opts.focus : !isTextField(prev);
    var anchor = opts.anchor || null;
    var hasPrev = prev && prev !== document.body && prev.focus;
    menu = {
      el: el, anchor: anchor, onClose: opts.onClose, onEnter: opts.onEnter, tookFocus: take,
      returnTo: hasPrev ? prev : anchor && anchor.tabIndex >= 0 ? anchor : null,
      aOwner: !take && hasPrev ? prev : null,      // the text field that keeps focus (aria-activedescendant)
      scope: opts.owner || anchor || (hasPrev ? prev : null),   // UI.detach() of this element closes the menu
      expanded: !!(anchor && anchor.matches('button, [role="button"], a') && !anchor.hasAttribute('data-account'))
    };
    if (menu.expanded) { anchor.setAttribute('aria-haspopup', opts.html != null ? 'dialog' : 'menu'); anchor.setAttribute('aria-expanded', 'true'); }
    if (take) {
      var first = kbd && opts.html == null ? $('.menu-item:not(:disabled)', el) : null;
      if (first) markItem(first);
      focusEl(first || el);
    }
    return el;
  }

  function markItem(b) {
    if (!menu) return;
    $$('.menu-item.focused', menu.el).forEach(function (x) { x.classList.remove('focused'); });
    if (b) b.classList.add('focused');
    if (menu.aOwner) {
      if (b) menu.aOwner.setAttribute('aria-activedescendant', b.id); else menu.aOwner.removeAttribute('aria-activedescendant');
    }
  }
  /** highlight an item (.focused) and, when the menu owns the focus, really focus it */
  function focusItem(b) {
    if (!menu) return;
    markItem(b);
    if (menu.tookFocus) {
      var ae = document.activeElement, target = b || menu.el;
      var free = !ae || ae === document.body || (menu.el.contains(ae) && (b ? !isTextField(ae) : ae.matches('.menu-item')));
      if (ae !== target && free) focusEl(target, !!b);
    } else if (b && b.scrollIntoView) b.scrollIntoView({ block: 'nearest' });
  }
  function moveFocus(d, edge) {
    var items = $$('.menu-item:not(:disabled)', menu.el);
    if (!items.length) return;
    var i = items.findIndex(function (b) { return b.classList.contains('focused'); });
    var n = edge ? (d > 0 ? items.length - 1 : 0) : i < 0 ? (d > 0 ? 0 : items.length - 1) : (i + d + items.length) % items.length;
    focusItem(items[n]);
  }

  /** right-click menu for an element; returns off(). UI.detach() of the element calls off() for you. */
  function contextMenu(target, fn) {
    function open(e) {
      var items = fn(e);
      if (!items || !items.length) return;
      e.preventDefault();
      showMenu(items, { x: e.clientX, y: e.clientY, owner: target });
    }
    target.addEventListener('contextmenu', open);
    var unregister = null;
    function off() { target.removeEventListener('contextmenu', open); if (unregister) unregister(); }
    unregister = onDetach(target, off);
    return off;
  }

  /**
   * UI.popover(anchor, html, { width, align, className, onClose, focus, label })
   * SECURITY: `html` is inserted as RAW HTML — escape any user data in it with UI.esc().
   * Focus moves into the popover (see `focus` in UI.menu) and returns to the anchor when it closes.
   */
  function popover(anchor, html, opts) {
    opts = opts || {};
    if (menu && menu.anchor === anchor) { closeMenu(); return null; } // second click toggles it off
    return showMenu(null, { anchor: anchor, html: html, width: opts.width, align: opts.align, className: 'popover ' + (opts.className || ''), onClose: opts.onClose, focus: opts.focus, label: opts.label, owner: opts.owner });
  }

  /* ---------- account menu (sidebar footer) ----------
     <button class="sb-account" data-account data-name="Alex Morgan" data-email="alex@example.com">…</button>
     Opens a demo menu (profile, settings, dark mode, colours, log out…). Items do nothing unless you pass
     your own with UI.setAccountMenu([{ label, icon, shortcut, danger, run }, '-', 'dark-switch', 'palettes', …]).
     Built-in rows by name: 'dark-switch' (Dark mode switch), 'palettes' (colour swatches),
     'theme' (Light / Dark / Auto segmented control + the colour swatches). */
  var accountItems = null;

  function paletteRow() {
    return '<div class="menu-row"><span>' + esc(t('colors')) + '</span><div class="palettes" role="group" aria-label="' + esc(t('colors')) + '">' + paletteButtons() + '</div></div>';
  }
  /** "Dark mode" switch item — toggled through the delegated [data-dark-switch] handler, synced by markButtons() */
  function darkSwitchItem() {
    return { label: t('darkMode'), icon: 'moon', checked: isDark(), darkSwitch: true };
  }
  var BUILTIN_ITEMS = {
    'dark-switch': darkSwitchItem,
    palettes: function () { return { custom: paletteRow() }; },
    theme: function () { return { custom: themeRow() }; }
  };

  function themeRow() {
    var b = function (v, inner, title) {
      return '<button data-set-theme="' + v + '" title="' + esc(title) + '" aria-pressed="' + (theme === v) +
        '" class="' + (theme === v ? 'active' : '') + '">' + inner + '</button>';
    };
    return '<div class="menu-row"><span>' + esc(t('theme')) + '</span><div class="seg" role="group" aria-label="' + esc(t('theme')) + '">' +
      b('light', icon('sun'), t('light')) + b('dark', icon('moon'), t('dark')) + b('auto', esc(t('auto')), t('matchSystem')) + '</div></div>' +
      paletteRow();
  }

  function defaultAccountItems() {
    return [
      { label: t('accountProfile'), icon: 'user', shortcut: '⇧⌘P' },
      { label: t('accountSettings'), icon: 'gear', shortcut: '⌘,' },
      { label: t('accountNotifications'), icon: 'bell' },
      { label: t('accountShortcuts'), icon: 'keyboard', shortcut: '⌘/' },
      { label: t('accountBilling'), icon: 'credit-card' },
      '-',
      darkSwitchItem(),
      { custom: paletteRow() },
      '-',
      { label: t('accountSwitch'), icon: 'users' },
      { label: t('accountHelp'), icon: 'help' },
      '-',
      { label: t('accountLogout'), icon: 'log-out', danger: true }
    ];
  }

  function openAccountMenu(btn) {
    if (menu && menu.anchor === btn) { closeMenu(); return; }
    var av = btn.querySelector('.avatar');
    var head = '<div class="menu-account">' + (av ? av.outerHTML.replace('avatar sm', 'avatar') : '') +
      '<div class="min-w-0"><div class="ma-name">' + esc(btn.dataset.name || t('account')) + '</div>' +
      (btn.dataset.email ? '<div class="ma-email">' + esc(btn.dataset.email) + '</div>' : '') + '</div></div>';
    var items = [{ custom: head }, '-'].concat(accountItems ? accountItems.map(function (it) {
      return typeof it === 'string' && BUILTIN_ITEMS.hasOwnProperty(it) ? BUILTIN_ITEMS[it]() : it;
    }) : defaultAccountItems());
    btn.classList.add('open');
    btn.setAttribute('aria-haspopup', 'menu');
    btn.setAttribute('aria-expanded', 'true');
    showMenu(items, {
      anchor: btn, width: Math.max(250, btn.offsetWidth), className: 'menu-account-pop', label: btn.dataset.name || t('account'),
      onClose: function () { btn.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }

  /* ---------- popups (modal dialogs) ---------- */
  var modals = [];
  /** close every open popup (top first), as if dismissed: onClose gets null, UI.confirm resolves false */
  function closeModals() { modals.slice().reverse().forEach(function (m) { m.close(null); }); }

  /** the element focus should return to: skips openers that live in a popup which is itself gone */
  function liveOpener(el) {
    for (var i = 0; el && i < 10; i++) {
      var b = el.closest && el.closest('.modal-backdrop');
      if (!b || (document.contains(b) && !b.classList.contains('closing'))) break;
      el = b._opener;
    }
    return el && el !== document.body && document.contains(el) ? el : null;
  }
  function trapTab(e, root) {
    var f = focusables(root);
    if (!f.length) { e.preventDefault(); focusEl(root); return; }
    var first = f[0], last = f[f.length - 1], ae = document.activeElement;
    if (!root.contains(ae) || ae === root) { e.preventDefault(); focusEl(e.shiftKey ? last : first); }
    else if (e.shiftKey && ae === first) { e.preventDefault(); focusEl(last); }
    else if (!e.shiftKey && ae === last) { e.preventDefault(); focusEl(first); }
  }

  /**
   * UI.modal({ title, message, icon, html, width, label, dismissible, closeButton, draggable, onClose, owner,
   *            actions: [{ label, primary, destructive, value, run(api) → return false to keep it open }] })
   * Returns { el, body, close(result) }.
   * closeButton: an X at the end of the header (closes like Escape).
   * draggable: the header becomes a title bar to drag the popup by (it stays on screen; not in narrow mode).
   * owner (optional): the element the popup belongs to — UI.detach() of a fragment containing it closes the popup
   * (as if dismissed). Without it a popup outlives content swaps behind it.
   *
   * SECURITY: `title`, `message` and action labels are escaped for you. `html` is inserted as RAW HTML —
   * escape any user data in it with UI.esc().
   *
   * Accessibility (automatic): role="dialog" + aria-modal, labelled by the title (or `label` / `message`),
   * described by the message; Tab / Shift+Tab stay inside the top popup; focus returns to the element
   * that had it when the popup opened.
   */
  function modal(o) {
    o = o || {};
    closeMenu();
    var opener = document.activeElement;
    var id = 'ui-modal-' + (++seq);
    var back = document.createElement('div');
    back.className = 'modal-backdrop';
    back.innerHTML = '<div class="modal" role="dialog" aria-modal="true" tabindex="-1" style="--w:' + (o.width || 460) + 'px">' +
      (o.title || o.message || o.icon ? '<div class="modal-header">' +
        (o.icon ? '<div class="modal-icon">' + icon(o.icon) + '</div>' : '') +
        (o.title ? '<h2 id="' + id + '-title">' + esc(o.title) + '</h2>' : '') +
        (o.message ? '<p id="' + id + '-desc">' + esc(o.message) + '</p>' : '') + '</div>' : '') +
      (o.html ? '<div class="modal-body">' + o.html + '</div>' : '') +
      '<div class="modal-footer"></div></div>';
    back._opener = opener;
    document.body.appendChild(back);

    var el = back.firstChild, footer = el.querySelector('.modal-footer'), closed = false, primary = null;
    var heading = o.title ? null : el.querySelector('.modal-body h1, .modal-body h2, .modal-body h3');
    if (heading && !heading.id) heading.id = id + '-title';
    if (o.label) el.setAttribute('aria-label', o.label);
    else if (o.title || heading) el.setAttribute('aria-labelledby', o.title ? id + '-title' : heading.id);
    else el.setAttribute('aria-label', o.message || t('dialog'));
    if (o.message && (o.title || o.label || heading)) el.setAttribute('aria-describedby', id + '-desc');

    var api = {
      el: el,
      body: el.querySelector('.modal-body'),
      owner: o.owner || null,
      close: function (result) {
        if (closed) return;
        closed = true;
        modals.splice(modals.indexOf(api), 1);
        back.classList.add('closing');
        setTimeout(function () { back.parentNode && back.parentNode.removeChild(back); }, 150);
        // give focus back to whatever opened the popup (onClose may still move it elsewhere)
        var ae = document.activeElement, to = liveOpener(opener);
        if (to && (!ae || ae === document.body || back.contains(ae))) focusEl(to);
        else if (back.contains(ae)) ae.blur();
        if (o.onClose) o.onClose(result);
      }
    };
    (o.actions || [{ label: t('ok'), primary: true }]).forEach(function (a) {
      var b = document.createElement('button');
      b.className = 'btn' + (a.primary ? ' primary' : '') + (a.destructive ? ' destructive' : '');
      b.textContent = a.label;
      b.addEventListener('click', function () {
        var r = a.run ? a.run(api) : undefined;
        if (r !== false) api.close(a.value !== undefined ? a.value : a.label);
      });
      footer.appendChild(b);
      if (a.primary || a.destructive) primary = b;
    });
    back.addEventListener('mousedown', function (e) { if (e.target === back && o.dismissible !== false) api.close(null); });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Tab' && !menu && modals[modals.length - 1] === api) { trapTab(e, el); return; }
      if (e.key === 'Enter' && primary && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') { e.preventDefault(); primary.click(); }
    });
    if (o.closeButton || o.draggable) modalWindow(api, el, o);
    modals.push(api);
    var first = el.querySelector('.modal-body input, .modal-body select, .modal-body textarea') || primary || footer.lastChild || el;
    setTimeout(function () { if (closed) return; first.focus(); if (first.select) first.select(); }, 30);
    return api;
  }

  // A popup as a window: an X in its header (closeButton) and the header as
  // a title bar to drag it by (draggable), kept on screen with an 8px margin.
  function modalWindow(api, el, o) {
    var bar = el.querySelector('.modal-header');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'modal-header';
      el.insertBefore(bar, el.firstChild);
    }
    bar.classList.add('modal-bar');
    if (o.closeButton) {
      var x = document.createElement('button');
      x.type = 'button';
      x.className = 'icon-btn modal-close';
      x.title = t('close');
      x.setAttribute('aria-label', t('close'));
      x.innerHTML = icon('xmark');
      x.addEventListener('click', function () { api.close(null); });
      bar.appendChild(x);
    }
    if (!o.draggable) return;
    bar.classList.add('draggable');
    var offset = { x: 0, y: 0 }, drag = null;
    function clamp(v, min, max) { return Math.min(Math.max(v, min), Math.max(min, max)); }
    bar.addEventListener('pointerdown', function (e) {
      if (e.button !== 0 || state.mode === 'narrow' || e.target.closest('button, a, input, select, textarea')) return;
      drag = { px: e.clientX, py: e.clientY, x: offset.x, y: offset.y, rect: el.getBoundingClientRect() };
      bar.setPointerCapture(e.pointerId);
      bar.classList.add('dragging');
      e.preventDefault();
    });
    bar.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var r = drag.rect;
      offset = {
        x: drag.x + clamp(e.clientX - drag.px, 8 - r.left, window.innerWidth - 8 - r.right),
        y: drag.y + clamp(e.clientY - drag.py, 8 - r.top, window.innerHeight - 8 - r.bottom)
      };
      el.style.translate = offset.x + 'px ' + offset.y + 'px';
    });
    function stop() { drag = null; bar.classList.remove('dragging'); }
    bar.addEventListener('pointerup', stop);
    bar.addEventListener('pointercancel', stop);
  }

  function confirmDialog(o) {
    o = o || {};
    return new Promise(function (resolve) {
      modal({
        title: o.title, message: o.message, icon: o.icon, width: o.width || 380,
        actions: [
          { label: o.cancelLabel || t('cancel'), value: false },
          { label: o.confirmLabel || t('ok'), value: true, primary: !o.destructive, destructive: !!o.destructive }
        ],
        onClose: function (r) { resolve(r === true); }
      });
    });
  }

  /* ---------- Board (Jira-style kanban) ----------
     var b = UI.board(containerEl, {
       lanes:   [{ id, title, color: 'blue' | '#hex', limit }],   // limit = WIP limit (optional)
       items:   [...records],
       laneOf:  item => laneId,
       orderOf: item => number,                                   // optional sort inside a lane
       card:    item => innerHTML of the card (use .bc-key/.bc-title/.bc-sub/.bc-tags/.bc-foot)
                — RAW HTML: escape user data with UI.esc()
       laneMeta:(lane, items) => html,                            // optional, e.g. a sum
       selected: id,
       onOpen:  (id, event) => {},                                // click / Return on a card
       onMove:  (id, toLane, index, fromLane) => {},              // drag & drop (between or within lanes)
                                                                  // and the keyboard: Alt+← → / Alt+↑ ↓
       onCreate:(laneId, title) => {},                            // inline “+ Create” at the bottom of a lane
       collapsedKey: 'board-x'                                    // remember collapsed lanes
     });
     b.update(items, { selected })                                // re-render after data changes

     Keyboard: cards are focusable. Enter opens; Alt+← / Alt+→ moves the focused card to the previous /
     next lane that is not collapsed, Alt+↑ / Alt+↓ reorders it inside its lane. The same onMove is
     called, the card is re-focused after the re-render and the move is announced to screen readers. */
  function colorVar(c) { return !c ? 'var(--gray)' : /^(#|rgb|hsl|var)/.test(c) ? c : 'var(--' + c + ')'; }

  function board(container, opts) {
    if (container._board) { container._board.set(opts); return container._board; }
    var o = Object.assign({ idOf: function (x) { return x.id; } }, opts);
    var collapsed = o.collapsedKey ? load(o.collapsedKey, {}) : {};
    var drag = null; // { id, from, index }
    var indicator = document.createElement('div');
    indicator.className = 'drop-indicator';
    var offs = [];  // listeners on the container, removed by destroy()
    function on(type, fn) { container.addEventListener(type, fn); offs.push(function () { container.removeEventListener(type, fn); }); }
    liveRegion(); // must exist before the first announcement

    function laneItems(laneId) {
      var its = o.items.filter(function (x) { return o.laneOf(x) === laneId; });
      if (o.orderOf) its.sort(function (a, b) { return o.orderOf(a) - o.orderOf(b); });
      return its;
    }

    function render() {
      var old = container.querySelector('.board');
      var sx = old ? old.scrollLeft : 0, sy = container.scrollTop;
      var keys = o.onMove ? ' aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight Alt+ArrowUp Alt+ArrowDown"' : '';
      container.innerHTML = '<div class="board">' + o.lanes.map(function (l) {
        var its = laneItems(l.id), col = !!collapsed[l.id];
        return '<section class="lane' + (col ? ' collapsed' : '') + (l.limit && its.length > l.limit ? ' over-limit' : '') +
            '" data-lane="' + esc(l.id) + '" aria-label="' + esc(l.title) + '" style="--c:' + colorVar(l.color) + '">' +
          '<header class="lane-head">' +
            '<button class="lane-toggle" data-lane-toggle aria-expanded="' + !col + '" title="' + esc(t(col ? 'expand' : 'collapse')) + '">' + icon(col ? 'chevron-right' : 'chevron-down') + '</button>' +
            '<span class="lane-dot"></span><span class="lane-title">' + esc(l.title) + '</span>' +
            '<span class="lane-count">' + its.length + (l.limit ? ' / ' + l.limit : '') + '</span>' +
            (o.laneMeta ? '<span class="lane-meta">' + o.laneMeta(l, its) + '</span>' : '') +
          '</header>' +
          '<div class="lane-cards">' + its.map(function (x) {
            var id = o.idOf(x);
            return '<article class="board-card' + (id === o.selected ? ' selected' : '') + '" draggable="true" tabindex="0"' + keys + ' data-inspect data-id="' + esc(id) + '">' + o.card(x) + '</article>';
          }).join('') + '</div>' +
          (o.onCreate ? '<button class="lane-create" data-lane-create>' + icon('plus') + '<span>' + esc(t('create')) + '</span></button>' : '') +
        '</section>';
      }).join('') + '</div>';
      container.querySelector('.board').scrollLeft = sx;
      container.scrollTop = sy;
    }

    function onBoard(e) { return e.target.closest && e.target.closest('.board') && container.contains(e.target); }
    function cardById(id) { return $$('.board-card', container).find(function (c) { return c.dataset.id === String(id); }); }

    function quickCreate(lane) {
      var btn = lane.querySelector('[data-lane-create]');
      var box = document.createElement('div');
      box.className = 'lane-quick';
      box.innerHTML = '<textarea class="field" rows="2" placeholder="' + esc(t('boardPlaceholder')) + '" aria-label="' + esc(t('boardPlaceholder')) + '"></textarea>' +
        '<div class="lane-quick-actions"><button class="btn small primary" data-q="ok">' + esc(t('create')) + '</button><button class="btn small" data-q="cancel">' + esc(t('cancel')) + '</button></div>';
      btn.replaceWith(box);
      var ta = box.querySelector('textarea');
      ta.focus();
      function done(save) {
        var text = ta.value.trim();
        if (save && text) o.onCreate(lane.dataset.lane, text); else render();
      }
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); done(true); }
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(false); }
      });
      box.addEventListener('click', function (e) {
        var q = e.target.closest('[data-q]'); if (q) { e.stopPropagation(); done(q.dataset.q === 'ok'); }
      });
    }

    /** keyboard alternative to drag & drop */
    function keyMove(card, key) {
      var lane = card.closest('.lane'), from = lane.dataset.lane, id = card.dataset.id;
      var cards = $$('.board-card', lane), i = cards.indexOf(card), to = from, index;
      if (key === 'ArrowUp' || key === 'ArrowDown') {
        index = i + (key === 'ArrowDown' ? 1 : -1);
        if (index < 0 || index > cards.length - 1) return;
      } else {
        var lanes = $$('.lane:not(.collapsed)', container);
        var target = lanes[lanes.indexOf(lane) + (key === 'ArrowRight' ? 1 : -1)];
        if (!target) return;
        to = target.dataset.lane;
        index = Math.min(i, $$('.board-card', target).length);
      }
      focusMem = { kind: '.board-card', id: id, el: card };
      o.onMove(id, to, index, from);
      setTimeout(function () {
        var c = cardById(id); if (!c) return;
        if (document.activeElement !== c && (!document.activeElement || document.activeElement === document.body)) focusEl(c, true);
        var l = c.closest('.lane'), all = $$('.board-card', l), title = c.querySelector('.bc-title');
        var def = o.lanes.find(function (x) { return String(x.id) === l.dataset.lane; });
        announce(t('boardMoved', { title: (title ? title.textContent : c.textContent).trim().slice(0, 80), lane: def ? def.title : l.dataset.lane, pos: all.indexOf(c) + 1, total: all.length }));
      }, 60);
    }

    on('click', function (e) {
      if (!onBoard(e)) return;
      var tg = e.target.closest('[data-lane-toggle]');
      if (tg) {
        var id = tg.closest('.lane').dataset.lane;
        collapsed[id] = !collapsed[id];
        if (o.collapsedKey) save(o.collapsedKey, collapsed);
        render();
        var again = $$('.lane', container).find(function (l) { return l.dataset.lane === id; });
        if (again && kbd) focusEl(again.querySelector('[data-lane-toggle]'));
        return;
      }
      var c = e.target.closest('[data-lane-create]');
      if (c) { quickCreate(c.closest('.lane')); return; }
      var card = e.target.closest('.board-card');
      if (card && !e.target.closest('button, a, input')) {
        $$('.board-card.selected', container).forEach(function (x) { x.classList.remove('selected'); });
        card.classList.add('selected');
        o.selected = card.dataset.id;
        if (o.onOpen) o.onOpen(card.dataset.id, e);
      }
    });
    on('keydown', function (e) {
      var card = e.target.closest && e.target.closest('.board .board-card');
      if (card && e.target === card && e.altKey && !e.metaKey && !e.ctrlKey && !e.shiftKey && /^Arrow/.test(e.key) && o.onMove) {
        e.preventDefault(); e.stopPropagation();
        keyMove(card, e.key);
        return;
      }
      if (card && e.key === 'Enter' && o.onOpen) { e.preventDefault(); card.click(); }
    });

    on('dragstart', function (e) {
      var card = e.target.closest && e.target.closest('.board .board-card'); if (!card) return;
      var lane = card.closest('.lane');
      drag = { id: card.dataset.id, from: lane.dataset.lane, index: $$('.board-card', lane).indexOf(card) };
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', drag.id);
      setTimeout(function () { card.classList.add('dragging'); }, 0);
    });
    on('dragover', function (e) {
      if (!drag) return;
      var lane = e.target.closest && e.target.closest('.board .lane'); if (!lane) return;
      e.preventDefault();
      $$('.lane.drop-over', container).forEach(function (l) { if (l !== lane) l.classList.remove('drop-over'); });
      lane.classList.add('drop-over');
      if (lane.classList.contains('collapsed')) { if (indicator.parentNode) indicator.parentNode.removeChild(indicator); return; }
      var list = lane.querySelector('.lane-cards');
      var before = $$('.board-card:not(.dragging)', list).find(function (c) {
        var r = c.getBoundingClientRect(); return e.clientY < r.top + r.height / 2;
      });
      if (before) list.insertBefore(indicator, before); else list.appendChild(indicator);
    });
    on('drop', function (e) {
      if (!drag) return;
      var lane = e.target.closest && e.target.closest('.board .lane'); if (!lane) return;
      e.preventDefault();
      var to = lane.dataset.lane, index;
      if (indicator.parentNode && lane.contains(indicator)) {
        index = 0;
        for (var n = indicator.previousElementSibling; n; n = n.previousElementSibling) if (n.classList.contains('board-card') && !n.classList.contains('dragging')) index++;
      } else index = laneItems(to).filter(function (x) { return o.idOf(x) !== drag.id; }).length;
      var d = drag; cleanup();
      if (!(to === d.from && index === d.index) && o.onMove) o.onMove(d.id, to, index, d.from);
    });
    function cleanup() {
      drag = null;
      if (indicator.parentNode) indicator.parentNode.removeChild(indicator);
      $$('.lane.drop-over, .board-card.dragging', container).forEach(function (x) { x.classList.remove('drop-over', 'dragging'); });
    }
    on('dragend', cleanup);

    var unregister = null;
    var api = {
      el: container,
      update: function (items, extra) { if (items) o.items = items; if (extra) Object.assign(o, extra); render(); },
      set: function (next) { Object.assign(o, next); render(); },
      /** remove the listeners (the markup stays); UI.board(container, …) afterwards starts a new board */
      destroy: function () {
        if (container._board !== api) return;
        cleanup();
        offs.forEach(function (f) { f(); }); offs = [];
        delete container._board;
        if (unregister) unregister();
      }
    };
    container._board = api;
    unregister = onDetach(container, api.destroy);
    render();
    return api;
  }

  /* ---------- dates (local time; shared by UI.calendar, UI.datePicker, UI.fmt.time) ---------- */
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function ymdOf(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
  function isoOf(d) { return ymdOf(d) + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()); }
  var LOCAL_DT = /^(\d{4})-(\d\d)-(\d\d)(?:[T ](\d\d):(\d\d)(?::\d\d(?:\.\d+)?)?)?$/;
  /** 'YYYY-MM-DD' or 'YYYY-MM-DDTHH:mm' → Date in local time (never UTC) */
  function parseLocal(s) {
    if (s instanceof Date) return new Date(s.getTime());
    var m = LOCAL_DT.exec(String(s == null ? '' : s));
    return m ? new Date(+m[1], m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)) : new Date(s);
  }
  function dayOf(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
  function plusDays(d, n) { return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n, d.getHours(), d.getMinutes()); }
  function atMinute(day, min) { return new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, min); }
  function weekOf(d, ws) { var s = dayOf(d); return plusDays(s, -((s.getDay() - (ws || 0) + 7) % 7)); }
  function minuteOf(d) { return d.getHours() * 60 + d.getMinutes(); }
  function sameDate(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
  var dtfCache = {};
  function dtf(opts) {
    var k = cfg.locale + JSON.stringify(opts);
    if (!dtfCache[k]) {
      try { dtfCache[k] = new Intl.DateTimeFormat(cfg.locale, opts); } catch (e) { dtfCache[k] = new Intl.DateTimeFormat('en-US', opts); }
    }
    return dtfCache[k];
  }
  /** does the locale use a 12-hour clock? (force = true / false overrides) */
  function is12h(force) {
    if (force != null) return !!force;
    var r = dtf({ hour: 'numeric' }).resolvedOptions();
    return r.hour12 != null ? !!r.hour12 : /h1[12]/.test(r.hourCycle || '');
  }
  function timeText(d, h12) {
    return dtf(is12h(h12) ? { hour: 'numeric', minute: '2-digit', hour12: true } : { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(d);
  }
  /** "Tue, Nov 17, 9:00 – 10:30 AM"-style text for announcements and labels */
  function whenText(s, e, allDay, h12) {
    var df = dtf({ weekday: 'short', month: 'short', day: 'numeric' });
    if (allDay) {
      var last = plusDays(dayOf(e), -1);
      return df.format(s) + (last > s && !sameDate(last, s) ? ' – ' + df.format(last) : '');
    }
    var endsNextMidnight = minuteOf(e) === 0 && sameDate(plusDays(e, -1), s);
    return df.format(s) + ', ' + timeText(s, h12) + ' – ' + (sameDate(s, e) || endsNextMidnight ? '' : df.format(e) + ', ') + timeText(e, h12);
  }

  /* ---------- Calendar (week / day / resources / agenda) ----------
     var cal = UI.calendar(containerEl, {
       view:  'week' | 'day' | 'resources' | 'agenda',      // resources = one day, one column per resource
       date:  '2026-11-16',                                  // any day of the week to show (default: today)
       events: [{ id, title, start, end, allDay, resource, color, tentative, readOnly, className }],
                       // start / end: local ISO 'YYYY-MM-DDTHH:mm'; all-day: 'YYYY-MM-DD' with an exclusive end
       resources: [{ id, title, sub, color, hours }],        // columns of the 'resources' view; colour of their events
       blocked: [{ start, end, resource, label }],           // unavailable time, drawn hatched
       hours: { start: 8, end: 18, days: [1, 2, 3, 4, 5] },  // business hours: the rest is shaded (resource.hours wins)
       startHour: 7, endHour: 20, step: 15, hourHeight: 48, duration: 60,  // grid · snap (min) · px per hour · new event length
       weekStart: 1, weekends: true, allDay: true,           // first weekday (0 = Sunday) · show Sat/Sun · all-day row
       narrow: 'day' | 'agenda' | false, narrowWidth: 600,   // what a container narrower than narrowWidth shows instead of 7 columns
       selected: id, readOnly: false, hour12: null, label: 'Calendar', clickCreate: true,
       event: (e, { time, compact, view }) => innerHTML,     // card content — RAW HTML: escape user data with UI.esc()
       eventLabel: e => text,                                // accessible name of an event (default: title, time, resource)
       validate: (id | null, { start, end, resource, allDay }) => true | false | 'message',  // live feedback while dragging
       onOpen(id, event), onCreate({ start, end, resource, allDay }), onMove(id, { start, end, resource, allDay }),
       onResize(id, { start, end }), onDate(ymd), onResource(id)
     });
     cal.update(events, { date, view, … }) · cal.set(options) · cal.scrollTo('08:00') · cal.focusEvent(id) · cal.range · cal.el

     Pointer: drag on empty space creates (snapped to step), drag an event moves it (across days, and across resource
     columns in 'resources' view), drag its bottom edge resizes; the grid auto-scrolls near its edges; Esc cancels.
     Touch: long-press (~0.45 s) before dragging, so a plain swipe still scrolls.
     Keyboard: the grid is one tab stop — arrow keys move a slot cursor, Shift+↑ ↓ change its length, Enter = onCreate.
     Events are focusable: Enter opens, Alt+↑ ↓ move by `step`, Alt+← → by a day (resource column), Alt+Shift+↑ ↓ resize.
     The kit never changes your data: it calls you back and you call cal.update(). If you don't, the event snaps back. */
  var CAL_DEFAULTS = {
    view: 'week', date: null, events: [], resources: [], blocked: [], hours: null,
    startHour: 7, endHour: 20, step: 15, hourHeight: 48, duration: 60,
    weekStart: 1, weekends: true, allDay: true, narrow: 'day', narrowWidth: 600,
    selected: null, readOnly: false, hour12: null, label: null, clickCreate: true
  };

  function calendar(container, opts) {
    if (container._calendar) { container._calendar.set(opts); return container._calendar; }
    var o = Object.assign({}, CAL_DEFAULTS, opts);
    var cols = [], days = [], eff = 'week', narrow = false;
    var cursor = null;          // keyboard slot cursor { col, min, len }
    var drag = null;            // pointer drag in progress
    var renders = 0, firstRender = true, suppressClick = 0;
    var helpId = 'ui-cal-help-' + (++seq);
    var offs = [];  // listeners, timer and observer, removed by destroy()
    function on(type, fn, opt) { container.addEventListener(type, fn, opt); offs.push(function () { container.removeEventListener(type, fn, opt); }); }
    liveRegion();

    var lo = function () { return o.startHour * 60; };
    var hi = function () { return o.endHour * 60; };
    var px = function () { return o.hourHeight / 60; };      // px per minute
    function evById(id) { for (var i = 0; i < o.events.length; i++) if (String(o.events[i].id) === String(id)) return o.events[i]; return null; }
    function resById(id) { for (var i = 0; i < (o.resources || []).length; i++) if (String(o.resources[i].id) === String(id)) return o.resources[i]; return null; }
    function colorOf(e) { var r = e.resource != null ? resById(e.resource) : null; return colorVar(e.color || (r && r.color) || 'accent'); }
    function startOf(e) { return parseLocal(e.start); }
    function endOf(e) {
      var s = startOf(e);
      if (e.allDay) return e.end ? dayOf(parseLocal(e.end)) : plusDays(dayOf(s), 1);
      return e.end ? parseLocal(e.end) : new Date(s.getTime() + o.duration * 6e4);
    }
    function editable(e) { return !o.readOnly && !e.readOnly; }
    function labelOf(e) {
      if (o.eventLabel) return o.eventLabel(e);
      var r = e.resource != null ? resById(e.resource) : null;
      return (e.title || '') + ', ' + whenText(startOf(e), endOf(e), e.allDay, o.hour12) + (r ? ', ' + r.title : '');
    }
    function timeRange(e) { return e.allDay ? t('calAllDay') : timeText(startOf(e), o.hour12) + ' – ' + timeText(endOf(e), o.hour12); }
    function content(e, compact) {
      var info = { time: timeRange(e), compact: !!compact, view: eff };
      return o.event ? o.event(e, info) : '<span class="cal-ev-title">' + esc(e.title) + '</span>' + (e.allDay || eff === 'agenda' ? '' : ' <span class="cal-ev-time">' + esc(info.time) + '</span>');
    }

    /* ----- range & columns ----- */
    function computeRange() {
      var base = parseLocal(o.date || new Date());
      if (isNaN(base)) base = new Date();
      var w = container.clientWidth;
      narrow = !!o.narrow && w > 0 && w < o.narrowWidth;
      eff = o.view === 'day' || o.view === 'resources' || o.view === 'agenda' ? o.view : 'week';
      if (narrow && eff === 'week') eff = o.narrow === 'agenda' ? 'agenda' : 'day';
      days = [];
      if (eff === 'week' || eff === 'agenda') {
        var s = weekOf(base, o.weekStart);
        for (var i = 0; i < 7; i++) { var d = plusDays(s, i); if (o.weekends || (d.getDay() !== 0 && d.getDay() !== 6)) days.push(d); }
      } else days.push(dayOf(base));
      cols = eff === 'resources'
        ? (o.resources || []).map(function (r) { return { date: days[0], key: ymdOf(days[0]), resource: r }; })
        : days.map(function (d) { return { date: d, key: ymdOf(d), resource: null }; });
    }
    function inCol(x, c) { return !c.resource || (x.resource != null && String(x.resource) === String(c.resource.id)); }
    function hoursOf(c) { return (c.resource && c.resource.hours) || o.hours; }

    /* ----- timed segments per column, packed side by side ----- */
    var MIN_PX = 20;
    function segmentsFor(c) {
      var a0 = atMinute(c.date, lo()), b0 = atMinute(c.date, hi()), out = [];
      o.events.forEach(function (e) {
        if (e.allDay || !inCol(e, c)) return;
        var s = startOf(e), en = endOf(e);
        if (en <= a0 || s >= b0) return;
        var a = s < a0 ? a0 : s, b = en > b0 ? b0 : en;
        var top = minuteOf(a) - lo(), bot = b.getTime() === b0.getTime() ? hi() - lo() : minuteOf(b) - lo();
        if (bot <= top) bot = top + o.step;
        out.push({ e: e, top: top, bot: bot, vis: Math.max(bot, top + MIN_PX / px()), first: s >= a0, last: en <= b0 });
      });
      out.sort(function (x, y) { return x.top - y.top || (y.bot - y.top) - (x.bot - x.top); });
      var cluster = [], ends = [], cEnd = -1;
      function flush() {
        cluster.forEach(function (sg) {
          sg.n = ends.length; sg.span = 1;
          // grow to the right over sub-columns that are free for the whole of this event
          for (var k = sg.i + 1; k < sg.n; k++) {
            if (cluster.some(function (o) { return o.i === k && o.top < sg.vis && sg.top < o.vis; })) break;
            sg.span++;
          }
        });
        cluster = []; ends = []; cEnd = -1;
      }
      out.forEach(function (sg) {
        if (cluster.length && sg.top >= cEnd) flush();
        var i = 0;
        while (i < ends.length && ends[i] > sg.top) i++;
        ends[i] = sg.vis; sg.i = i; cluster.push(sg);
        cEnd = Math.max(cEnd, sg.vis);
      });
      flush();
      return out;
    }

    /* ----- html ----- */
    var KEYS = ' aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown Alt+ArrowLeft Alt+ArrowRight Alt+Shift+ArrowUp Alt+Shift+ArrowDown"';
    function eventHtml(sg, stops) {
      var e = sg.e, id = String(e.id), h = Math.max((sg.bot - sg.top) * px(), MIN_PX), compact = h < 34;
      var w = 100 / sg.n, ed = editable(e) && (o.onMove || o.onResize);
      var stop = !stops[id]; stops[id] = true;
      var cls = 'cal-event' + (compact ? ' compact' : '') + (e.tentative ? ' tentative' : '') + (id === String(o.selected) ? ' selected' : '') +
        (!sg.first ? ' cont-top' : '') + (!sg.last ? ' cont-bottom' : '') + (ed ? '' : ' locked') + (e.className ? ' ' + e.className : '');
      var tip = compact || sg.n > sg.span ? ' data-tip="' + esc((e.title || '') + ' · ' + timeRange(e)) + '"' : '';
      return '<div class="' + esc(cls) + '" data-id="' + esc(id) + '" data-inspect role="button" tabindex="' + (stop ? 0 : -1) + '" aria-label="' + esc(labelOf(e)) + '"' + tip +
        (ed ? KEYS : '') + ' style="--c:' + esc(colorOf(e)) + ';top:' + (sg.top * px()) + 'px;height:' + h + 'px;left:calc(' + (sg.i * w) + '% + 1px);width:calc(' + (w * sg.span) + '% - 3px)">' +
        '<div class="cal-ev-body">' + content(e, compact) + '</div>' +
        (ed && sg.last && o.onResize ? '<span class="cal-resize" aria-hidden="true"></span>' : '') + '</div>';
    }
    function offHtml(c) {
      var h = hoursOf(c);
      if (!h) return '';
      var total = hi() - lo(), wd = h.days || [0, 1, 2, 3, 4, 5, 6];
      var band = function (a, b) { return b > a ? '<div class="cal-off" style="top:' + a * px() + 'px;height:' + (b - a) * px() + 'px"></div>' : ''; };
      if (wd.indexOf(c.date.getDay()) < 0) return band(0, total);
      return band(0, Math.min(total, (h.start - o.startHour) * 60)) + band(Math.max(0, (h.end - o.startHour) * 60), total);
    }
    function blockedHtml(c) {
      var a0 = atMinute(c.date, lo()), b0 = atMinute(c.date, hi());
      return (o.blocked || []).map(function (b) {
        if (c.resource && b.resource != null && String(b.resource) !== String(c.resource.id)) return '';
        var s = parseLocal(b.start), en = parseLocal(b.end);
        if (en <= a0 || s >= b0) return '';
        var top = s <= a0 ? 0 : minuteOf(s) - lo(), bot = en >= b0 ? hi() - lo() : minuteOf(en) - lo();
        return '<div class="cal-blocked" style="top:' + top * px() + 'px;height:' + (bot - top) * px() + 'px"' + (b.label ? ' title="' + esc(b.label) + '"' : '') + '>' +
          (b.label ? '<span>' + esc(b.label) + '</span>' : '') + '</div>';
      }).join('');
    }
    function nowHtml(c) {
      var now = new Date(), m = minuteOf(now);
      return sameDate(c.date, now) && m >= lo() && m < hi() ? '<div class="cal-now" style="top:' + (m - lo()) * px() + 'px" aria-hidden="true"></div>' : '';
    }
    function headHtml() {
      var today = new Date(), click = eff === 'resources' ? !!o.onResource : !!o.onDate;
      var wd = dtf({ weekday: 'short' }), full = dtf({ weekday: 'long', month: 'long', day: 'numeric' });
      return cols.map(function (c, i) {
        var tag = click ? 'button' : 'div', isToday = sameDate(c.date, today), h = hoursOf(c);
        var closed = h && h.days && h.days.indexOf(c.date.getDay()) < 0;
        var inner = c.resource
          ? '<span class="cal-hd-dot" style="--c:' + esc(colorVar(c.resource.color || 'accent')) + '"></span><span class="cal-hd-main"><span class="cal-hd-title">' + esc(c.resource.title) + '</span>' +
            (c.resource.sub ? '<span class="cal-hd-sub">' + esc(c.resource.sub) + '</span>' : '') + '</span>'
          : '<span class="cal-hd-wd">' + esc(wd.format(c.date)) + '</span><span class="cal-hd-n">' + c.date.getDate() + '</span>';
        return '<' + tag + (click ? ' type="button"' : '') + ' class="cal-hd' + (isToday && !c.resource ? ' today' : '') + (closed ? ' closed' : '') + '" data-col="' + i + '"' +
          (c.resource ? '' : ' aria-label="' + esc(full.format(c.date)) + '"' + (isToday ? ' aria-current="date"' : '')) + '>' + inner + '</' + tag + '>';
      }).join('');
    }
    function alldayHtml() {
      var items = [], lanes = [];
      o.events.forEach(function (e) {
        if (!e.allDay) return;
        var s = dayOf(startOf(e)), en = endOf(e), a = -1, b = -1;
        cols.forEach(function (c, i) {
          if (c.resource && (e.resource == null || String(e.resource) !== String(c.resource.id))) return;
          if (c.date >= s && c.date < en) { if (a < 0) a = i; b = i; }
        });
        if (a >= 0) items.push({ e: e, a: a, b: b, first: sameDate(cols[a].date, s), last: plusDays(cols[b].date, 1) >= en });
      });
      items.sort(function (x, y) { return x.a - y.a || (y.b - y.a) - (x.b - x.a); });
      items.forEach(function (it) {
        var l = 0;
        while (lanes[l] != null && lanes[l] >= it.a) l++;
        lanes[l] = it.b; it.lane = l;
      });
      var n = Math.max(1, lanes.length), stops = {};
      return '<div class="cal-allday" style="grid-template-rows:repeat(' + n + ',22px)">' +
        cols.map(function (c, i) { return '<div class="cal-ad-cell" data-col="' + i + '" style="grid-column:' + (i + 1) + '"></div>'; }).join('') +
        items.map(function (it) {
          var e = it.e, id = String(e.id), stop = !stops[id], ed = editable(e) && o.onMove;
          stops[id] = true;
          return '<div class="cal-event allday compact' + (e.tentative ? ' tentative' : '') + (id === String(o.selected) ? ' selected' : '') + (!it.first ? ' cont-left' : '') + (!it.last ? ' cont-right' : '') +
            (ed ? '' : ' locked') + (e.className ? ' ' + esc(e.className) : '') + '" data-id="' + esc(id) + '" data-inspect role="button" tabindex="' + (stop ? 0 : -1) + '" aria-label="' + esc(labelOf(e)) + '"' +
            (ed ? ' aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight"' : '') + ' style="--c:' + esc(colorOf(e)) + ';grid-column:' + (it.a + 1) + ' / ' + (it.b + 2) + ';grid-row:' + (it.lane + 1) + '">' +
            '<div class="cal-ev-body">' + content(e, true) + '</div></div>';
        }).join('') + '</div>';
    }
    function gridHtml() {
      var stops = {}, total = hi() - lo(), hourFmt = is12h(o.hour12) ? dtf({ hour: 'numeric', hour12: true }) : null, hours = '';
      for (var hr = o.startHour; hr < o.endHour; hr++) {
        var d = atMinute(days[0], hr * 60);
        hours += '<span class="cal-hour" style="top:' + (hr - o.startHour) * o.hourHeight + 'px">' + esc(hourFmt ? hourFmt.format(d) : timeText(d, false)) + '</span>';
      }
      var today = new Date();
      return '<div class="cal-sheet" style="--cols:' + cols.length + ';--hour-h:' + o.hourHeight + 'px">' +
        '<div class="cal-corner cal-corner-top"></div>' + headHtml() +
        (o.allDay ? '<div class="cal-corner cal-ad-label">' + esc(t('calAllDay')) + '</div>' + alldayHtml() : '') +
        '<div class="cal-gutter" style="height:' + total * px() + 'px" aria-hidden="true">' + hours + '</div>' +
        cols.map(function (c, i) {
          var h = hoursOf(c);
          return '<div class="cal-col' + (sameDate(c.date, today) && !c.resource ? ' today' : '') + (h && h.days && h.days.indexOf(c.date.getDay()) < 0 ? ' closed' : '') +
            '" data-col="' + i + '" style="height:' + total * px() + 'px">' + offHtml(c) + blockedHtml(c) +
            segmentsFor(c).map(function (sg) { return eventHtml(sg, stops); }).join('') + nowHtml(c) + '</div>';
        }).join('') + '</div>';
    }
    function stripHtml() {
      var today = new Date(), wd = dtf({ weekday: 'short' }), full = dtf({ weekday: 'long', month: 'long', day: 'numeric' }), sel = dayOf(parseLocal(o.date || today));
      var week = [], s = weekOf(sel, o.weekStart);
      for (var i = 0; i < 7; i++) { var d = plusDays(s, i); if (o.weekends || (d.getDay() !== 0 && d.getDay() !== 6)) week.push(d); }
      return '<div class="cal-strip" role="group" aria-label="' + esc(t('calPickDay')) + '">' + week.map(function (d) {
        var on = sameDate(d, sel), has = o.events.some(function (e) { var a = dayOf(startOf(e)), b = endOf(e); return d >= a && d < b; });
        return '<button type="button" class="cal-strip-day' + (on ? ' sel' : '') + (sameDate(d, today) ? ' today' : '') + (has ? ' has-events' : '') + '" data-cal-date="' + ymdOf(d) +
          '" aria-pressed="' + on + '" aria-label="' + esc(full.format(d)) + '"><span>' + esc(wd.format(d)) + '</span><b>' + d.getDate() + '</b></button>';
      }).join('') + '</div>';
    }
    function agendaHtml() {
      var today = new Date(), df = dtf({ weekday: 'long', month: 'short', day: 'numeric' });
      return '<div class="cal-agenda" role="list">' + days.map(function (d) {
        var next = plusDays(d, 1);
        var evs = o.events.filter(function (e) { return startOf(e) < next && endOf(e) > d; })
          .sort(function (a, b) { return (b.allDay ? 1 : 0) - (a.allDay ? 1 : 0) || startOf(a) - startOf(b); });
        return '<section class="cal-ag-day' + (sameDate(d, today) ? ' today' : '') + '" role="listitem"><h3 class="cal-ag-date">' + esc(df.format(d)) +
          (sameDate(d, today) ? ' <span>' + esc(t('today')) + '</span>' : '') + '</h3>' +
          (evs.length ? evs.map(function (e) {
            return '<button type="button" class="cal-ag-item' + (String(e.id) === String(o.selected) ? ' selected' : '') + (e.tentative ? ' tentative' : '') + (e.className ? ' ' + esc(e.className) : '') +
              '" data-id="' + esc(e.id) + '" data-inspect style="--c:' + esc(colorOf(e)) + '" aria-label="' + esc(labelOf(e)) + '"><span class="cal-ag-time">' +
              (e.allDay ? '<span>' + esc(t('calAllDay')) + '</span>' : '<span>' + esc(timeText(startOf(e), o.hour12)) + '</span><span>' + esc(timeText(endOf(e), o.hour12)) + '</span>') + '</span>' +
              '<span class="cal-ag-main">' + content(e, false) + '</span></button>';
          }).join('') : '<div class="cal-ag-empty">' + esc(t('calNoEvents')) + '</div>') + '</section>';
      }).join('') + '</div>';
    }

    function scroller() { return container.querySelector('.cal-scroll'); }
    function colEls() { return $$('.cal-col', container); }
    function headH() { var h = container.querySelector('.cal-hd, .cal-corner-top'), a = container.querySelector('.cal-allday'); return (h ? h.offsetHeight : 0) + (a ? a.offsetHeight : 0); }

    function render() {
      if (drag) cancelDrag();
      renders++;
      var sc = scroller(), st = sc ? sc.scrollTop : null, sl = sc ? sc.scrollLeft : 0, prevView = eff;
      var ae = document.activeElement, focus = null;
      if (ae && container.contains(ae)) {
        if (ae.matches('.cal-event, .cal-ag-item')) focus = { id: ae.dataset.id };
        else if (ae.matches('.cal-scroll')) focus = { grid: true };
        else if (ae.matches('[data-cal-date]')) focus = { date: ae.dataset.calDate };
        else if (ae.matches('.cal-hd')) focus = { col: ae.dataset.col };
      }
      computeRange();
      var label = o.label || t('calLabel');
      var body = eff === 'agenda' ? agendaHtml()
        : '<div class="cal-scroll" tabindex="0" role="region" aria-label="' + esc(label) + '" aria-describedby="' + helpId + '">' + gridHtml() + '</div>';
      container.innerHTML = '<div class="calendar" data-view="' + eff + '"' + (narrow ? ' data-narrow' : '') + '>' +
        (narrow && eff !== 'agenda' ? stripHtml() : '') + body + '<span class="vh" id="' + helpId + '">' + esc(t('calHelp')) + '</span></div>';
      sc = scroller();
      if (sc) {
        if (st == null || firstRender) initialScroll(sc); else { sc.scrollTop = st; sc.scrollLeft = prevView === eff ? sl : 0; }
        if (cursor) cursor.col = clamp(cursor.col, 0, Math.max(0, cols.length - 1));
      }
      firstRender = false;
      if (focus) {
        var el = focus.id != null ? $$('.cal-event, .cal-ag-item', container).filter(function (x) { return x.dataset.id === focus.id; }).sort(function (a, b) { return b.tabIndex - a.tabIndex; })[0]
          : focus.grid ? sc : focus.date ? container.querySelector('[data-cal-date="' + focus.date + '"]') : container.querySelector('.cal-hd[data-col="' + focus.col + '"]');
        if (el) { focusEl(el); if (focus.grid && cursor) placeCursor(false); }
      }
    }

    function initialScroll(sc) {
      var now = new Date(), m = minuteOf(now), target;
      var h = cols.length ? hoursOf(cols[0]) : o.hours;
      if (cols.some(function (c) { return sameDate(c.date, now); }) && m >= lo() && m < hi()) target = m - 90;
      else target = (h ? h.start : o.startHour) * 60 - 30;
      sc.scrollTop = Math.max(0, (target - lo()) * px());
    }
    function scrollTo(time) {
      var sc = scroller(); if (!sc) return;
      var m = typeof time === 'number' ? time : time instanceof Date ? minuteOf(time) : (function () { var p = String(time).split(':'); return +p[0] * 60 + (+p[1] || 0); })();
      sc.scrollTop = Math.max(0, (m - lo()) * px());
    }

    /* ----- keyboard slot cursor ----- */
    function ensureCursor() {
      if (cursor) return cursor;
      var now = new Date(), ti = -1;
      cols.forEach(function (c, i) { if (ti < 0 && sameDate(c.date, now)) ti = i; });
      var h = cols.length ? hoursOf(cols[Math.max(0, ti)]) : o.hours;
      var m = ti >= 0 && minuteOf(now) >= lo() && minuteOf(now) < hi() ? Math.ceil(minuteOf(now) / o.step) * o.step : (h ? h.start * 60 : lo());
      var len = Math.min(o.duration, hi() - lo());
      cursor = { col: Math.max(0, ti), min: clamp(m, lo(), hi() - len), len: len };
      return cursor;
    }
    function cursorResult() {
      var c = cols[cursor.col];
      return { start: isoOf(atMinute(c.date, cursor.min)), end: isoOf(atMinute(c.date, cursor.min + cursor.len)), resource: c.resource ? c.resource.id : null, allDay: false };
    }
    function placeCursor(scroll) {
      var col = colEls()[cursor.col], sc = scroller();
      if (!col || !sc) return;
      var el = container.querySelector('.cal-cursor');
      if (!el) { el = document.createElement('div'); el.className = 'cal-cursor'; el.setAttribute('aria-hidden', 'true'); }
      if (el.parentNode !== col) col.appendChild(el);
      var c = cols[cursor.col];
      el.style.top = (cursor.min - lo()) * px() + 'px';
      el.style.height = cursor.len * px() + 'px';
      el.textContent = timeText(atMinute(c.date, cursor.min), o.hour12) + ' – ' + timeText(atMinute(c.date, cursor.min + cursor.len), o.hour12);
      if (!scroll) return;
      var top = col.offsetTop + el.offsetTop, hh = headH();
      if (top - hh < sc.scrollTop) sc.scrollTop = top - hh - 8;
      else if (top + el.offsetHeight > sc.scrollTop + sc.clientHeight) sc.scrollTop = top + el.offsetHeight - sc.clientHeight + 8;
      var gut = container.querySelector('.cal-gutter'), gw = gut ? gut.offsetWidth : 0;
      if (col.offsetLeft - gw < sc.scrollLeft) sc.scrollLeft = col.offsetLeft - gw;
      else if (col.offsetLeft + col.offsetWidth > sc.scrollLeft + sc.clientWidth) sc.scrollLeft = col.offsetLeft + col.offsetWidth - sc.clientWidth;
    }
    function hideCursor() { var el = container.querySelector('.cal-cursor'); if (el) el.remove(); }
    function announceSlot() {
      var r = cursorResult(), c = cols[cursor.col];
      announce(whenText(parseLocal(r.start), parseLocal(r.end), false, o.hour12) + (c.resource ? ', ' + c.resource.title : ''));
    }
    function gridKey(e) {
      if (e.altKey || e.metaKey || e.ctrlKey || !cols.length) return;
      var k = e.key, c = ensureCursor(), st = o.step, down = k === 'ArrowDown' || k === 'PageDown';
      if (k === 'ArrowUp' || k === 'ArrowDown') {
        if (e.shiftKey) c.len = clamp(c.len + (down ? st : -st), st, hi() - c.min);
        else c.min = clamp(c.min + (down ? st : -st), lo(), hi() - c.len);
      } else if (k === 'ArrowLeft' || k === 'ArrowRight') c.col = clamp(c.col + (k === 'ArrowRight' ? 1 : -1), 0, cols.length - 1);
      else if (k === 'PageUp' || k === 'PageDown') c.min = clamp(c.min + (down ? 60 : -60), lo(), hi() - c.len);
      else if (k === 'Home') c.min = lo();
      else if (k === 'End') c.min = hi() - c.len;
      else if ((k === 'Enter' || k === ' ') && o.onCreate && !o.readOnly) {
        e.preventDefault(); e.stopPropagation();
        o.onCreate(cursorResult());
        return;
      } else return;
      e.preventDefault(); e.stopPropagation();
      placeCursor(true);
      announceSlot();
    }

    /* ----- keyboard on events ----- */
    function afterChange(id, key) {
      setTimeout(function () {
        var e = evById(id);
        if (!e) return;
        var el = $$('.cal-event, .cal-ag-item', container).filter(function (x) { return x.dataset.id === String(id) && x.tabIndex >= 0; })[0];
        var ae = document.activeElement;
        if (el && (!ae || ae === document.body || !document.contains(ae)) && !modals.length) focusEl(el);
        if (el && el.scrollIntoView && el.matches('.cal-event:not(.allday)')) {
          var sc = scroller(), top = el.offsetTop + el.parentNode.offsetTop, hh = headH();
          if (sc && top - hh < sc.scrollTop) sc.scrollTop = top - hh - 8;
          else if (sc && top + el.offsetHeight > sc.scrollTop + sc.clientHeight) sc.scrollTop = top + el.offsetHeight - sc.clientHeight + 8;
        }
        var r = e.resource != null ? resById(e.resource) : null;
        announce(t(key, { title: e.title || '', when: whenText(startOf(e), endOf(e), e.allDay, o.hour12) + (r ? ', ' + r.title : '') }));
      }, 40);
    }
    function shiftDays(d, n, allDay) {
      var x = plusDays(d, n), guard = 0;
      while (!o.weekends && (x.getDay() === 0 || x.getDay() === 6) && guard++ < 3) x = plusDays(x, n < 0 ? -1 : 1);
      return allDay ? ymdOf(x) : isoOf(x);
    }
    function eventKey(e, el) {
      var ev = evById(el.dataset.id);
      if (!ev) return;
      if ((e.key === 'Enter' || e.key === ' ') && !e.altKey && !e.metaKey && !e.ctrlKey) {
        e.preventDefault(); e.stopPropagation();
        open(ev, e);
        return;
      }
      if (!e.altKey || e.metaKey || e.ctrlKey || !/^Arrow/.test(e.key) || !editable(ev)) return;
      var id = String(ev.id), s = startOf(ev), en = endOf(ev), before = renders, horiz = e.key === 'ArrowLeft' || e.key === 'ArrowRight';
      var d = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : -1;
      if (e.shiftKey) {
        if (!o.onResize || ev.allDay || horiz) return;
        var ne = new Date(en.getTime() + d * o.step * 6e4);
        if (ne - s < o.step * 6e4) return;
        e.preventDefault(); e.stopPropagation();
        o.onResize(id, { start: ev.start, end: isoOf(ne) });
        if (renders === before) render();
        afterChange(id, 'calResized');
        return;
      }
      if (!o.onMove) return;
      var r;
      if (!horiz) {
        if (ev.allDay) return;
        var ns = new Date(s.getTime() + d * o.step * 6e4);
        r = { start: isoOf(ns), end: isoOf(new Date(en.getTime() + d * o.step * 6e4)), resource: ev.resource == null ? null : ev.resource, allDay: false };
      } else if (eff === 'resources') {
        var ci = -1;
        cols.forEach(function (c, i) { if (String(c.resource.id) === String(ev.resource)) ci = i; });
        var target = cols[ci + d];
        if (ci < 0 || !target) return;
        r = { start: ev.start, end: ev.allDay ? ymdOf(en) : ev.end, resource: target.resource.id, allDay: !!ev.allDay };
      } else {
        r = { start: shiftDays(s, d, ev.allDay), end: ev.allDay ? ymdOf(plusDays(en, d)) : null, resource: ev.resource == null ? null : ev.resource, allDay: !!ev.allDay };
        if (!ev.allDay) { var ms = parseLocal(r.start) - s; r.end = isoOf(new Date(en.getTime() + ms)); }
        else r.end = ymdOf(plusDays(parseLocal(r.start), Math.round((en - dayOf(s)) / DAY)));
      }
      e.preventDefault(); e.stopPropagation();
      o.onMove(id, r);
      if (renders === before) render();
      afterChange(id, 'calMoved');
    }
    function open(ev, e) {
      o.selected = String(ev.id);
      $$('.cal-event.selected, .cal-ag-item.selected', container).forEach(function (x) { x.classList.remove('selected'); });
      $$('.cal-event, .cal-ag-item', container).forEach(function (x) { if (x.dataset.id === String(ev.id)) x.classList.add('selected'); });
      if (o.onOpen) o.onOpen(String(ev.id), e);
    }

    /* ----- pointer: create / move / resize ----- */
    function colIndexAt(x) {
      var els = colEls();
      for (var i = 0; i < els.length; i++) { var r = els[i].getBoundingClientRect(); if (x >= r.left && x < r.right) return i; }
      if (!els.length) return -1;
      return x < els[0].getBoundingClientRect().left ? 0 : els.length - 1;
    }
    function adIndexAt(x) {
      var els = $$('.cal-ad-cell', container);
      for (var i = 0; i < els.length; i++) { var r = els[i].getBoundingClientRect(); if (x >= r.left && x < r.right) return i; }
      return x < (els[0] ? els[0].getBoundingClientRect().left : 0) ? 0 : els.length - 1;
    }
    function minuteAt(colEl, y) { return lo() + (y - colEl.getBoundingClientRect().top) / px(); }
    function snap(m) { return Math.round(m / o.step) * o.step; }
    function snapDown(m) { return Math.floor(m / o.step) * o.step; }

    function startDrag(e) {
      if (drag || (e.pointerType === 'mouse' && e.button !== 0) || o.readOnly || !e.target.closest) return;
      var sc = e.target.closest('.cal-scroll');
      if (!sc || !container.contains(sc) || e.target.closest('.cal-hd, .cal-corner')) return;
      var evEl = e.target.closest('.cal-event'), colEl = e.target.closest('.cal-col'), kind = null, ev = null;
      if (evEl) {
        ev = evById(evEl.dataset.id);
        if (!ev || !editable(ev)) return;
        kind = e.target.closest('.cal-resize') ? 'resize' : ev.allDay ? 'moveAllDay' : 'move';
        if ((kind === 'resize' && !o.onResize) || (kind !== 'resize' && !o.onMove)) return;
      } else if (colEl && o.onCreate && !e.target.closest('.cal-blocked')) kind = 'create';
      else return;
      drag = { kind: kind, ev: ev, el: evEl, id: ev ? String(ev.id) : null, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY,
        pid: e.pointerId, touch: e.pointerType === 'touch', active: false, sc: sc, res: null, key: '' };
      if (kind === 'create') { drag.col = +colEl.dataset.col; drag.anchor = snapDown(minuteAt(colEl, e.clientY)); }
      if (kind === 'move' || kind === 'resize') {
        var c0 = +evEl.closest('.cal-col').dataset.col, s = startOf(ev);
        drag.col0 = c0; drag.s = s; drag.dur = Math.round((endOf(ev) - s) / 6e4);
        drag.sMin = Math.round((s - cols[c0].date) / 6e4);            // start in minutes from the column's midnight (may be < 0)
        drag.grab = minuteAt(evEl.closest('.cal-col'), e.clientY) - drag.sMin;
      }
      if (kind === 'moveAllDay') { drag.col0 = adIndexAt(e.clientX); }
      if (drag.touch) drag.timer = setTimeout(function () { if (drag && !drag.active) { activate(); try { if (navigator.vibrate) navigator.vibrate(10); } catch (x) {} } }, 450);
      document.addEventListener('pointermove', onPointerMove);
      document.addEventListener('pointerup', onPointerUp);
      document.addEventListener('pointercancel', onPointerCancel);
    }
    function activate() {
      drag.active = true;
      var cal = container.querySelector('.calendar');
      cal.classList.add(drag.kind === 'resize' ? 'is-resizing' : 'is-dragging');
      if (drag.el) $$('.cal-event', container).forEach(function (x) { if (x.dataset.id === drag.id) x.classList.add('dragging'); });
      try { drag.sc.setPointerCapture(drag.pid); } catch (x) {}
      hideTip(); hideCursor();
      try { window.getSelection().removeAllRanges(); } catch (x) {}
      document.addEventListener('keydown', escDrag, true);
      track();
      drag.raf = requestAnimationFrame(autoScroll);
    }
    function onPointerMove(e) {
      if (!drag || e.pointerId !== drag.pid) return;
      drag.x = e.clientX; drag.y = e.clientY;
      if (!drag.active) {
        var dist = Math.abs(drag.x - drag.x0) + Math.abs(drag.y - drag.y0);
        if (drag.touch) { if (dist > 10) cancelDrag(); return; }   // the finger is scrolling, not dragging
        if (dist < 5) return;
        activate();
      }
      if (Math.abs(drag.x - drag.x0) + Math.abs(drag.y - drag.y0) >= 5) drag.moved = true;
      e.preventDefault();
      track();
    }
    function onPointerUp(e) {
      if (!drag || e.pointerId !== drag.pid) return;
      var d = drag;
      if (!d.active) { cancelDrag(); return; }               // a plain click: the click handler deals with it
      suppressClick = Date.now() + 500;
      track();
      cancelDrag();
      commit(d);
    }
    function onPointerCancel(e) { if (drag && e.pointerId === drag.pid) cancelDrag(); }
    function escDrag(e) { if (e.key === 'Escape' && drag) { e.preventDefault(); e.stopPropagation(); suppressClick = Date.now() + 500; cancelDrag(); } }
    function cancelDrag() {
      if (!drag) return;
      clearTimeout(drag.timer);
      cancelAnimationFrame(drag.raf);
      document.removeEventListener('pointermove', onPointerMove);
      document.removeEventListener('pointerup', onPointerUp);
      document.removeEventListener('pointercancel', onPointerCancel);
      document.removeEventListener('keydown', escDrag, true);
      try { if (drag.sc.hasPointerCapture && drag.sc.hasPointerCapture(drag.pid)) drag.sc.releasePointerCapture(drag.pid); } catch (x) {}
      $$('.cal-ghost', container).forEach(function (g) { g.remove(); });
      $$('.cal-event.dragging', container).forEach(function (x) { x.classList.remove('dragging'); });
      var cal = container.querySelector('.calendar');
      if (cal) cal.classList.remove('is-dragging', 'is-resizing');
      drag = null;
    }
    function track() {
      var d = drag, els = colEls(), r = null, colEl, m;
      if (d.kind === 'create') {
        colEl = els[d.col]; m = snapDown(minuteAt(colEl, d.y));
        var a = Math.min(d.anchor, m), b = Math.max(d.anchor, m) + o.step;
        a = clamp(a, lo(), hi() - o.step); b = clamp(b, a + o.step, hi());
        r = { col: d.col, a: a, b: b };
      } else if (d.kind === 'move') {
        var ci = colIndexAt(d.x); colEl = els[ci];
        var dur = Math.min(d.dur, hi() - lo()), na = snap(minuteAt(colEl, d.y) - d.grab);
        na = clamp(na, Math.min(lo(), d.sMin), Math.max(lo(), hi() - dur));
        r = { col: ci, a: na, b: na + d.dur };
      } else if (d.kind === 'resize') {
        colEl = els[d.col0]; m = snap(minuteAt(colEl, d.y));
        r = { col: d.col0, a: d.sMin, b: clamp(m, Math.max(d.sMin, lo()) + o.step, hi()) };
      } else {
        r = { col: adIndexAt(d.x), delta: adIndexAt(d.x) - d.col0 };
      }
      d.res = r;
      paintGhost();
    }
    function result(d) {
      var r = d.res;
      if (!r) return null;
      if (d.kind === 'moveAllDay') {
        var ev = d.ev, s = dayOf(startOf(ev)), en = endOf(ev), c = cols[r.col];
        if (eff === 'resources') return { start: ev.start, end: ymdOf(en), resource: c.resource.id, allDay: true };
        var diff = Math.round((cols[r.col].date - cols[d.col0].date) / DAY);
        return { start: ymdOf(plusDays(s, diff)), end: ymdOf(plusDays(en, diff)), resource: ev.resource == null ? null : ev.resource, allDay: true };
      }
      var col = cols[r.col], day = col.date;
      var start = d.kind === 'resize' ? d.s : atMinute(day, r.a), end = atMinute(day, r.b);
      var res = col.resource ? col.resource.id : d.ev && d.ev.resource != null ? d.ev.resource : null;
      return { start: isoOf(start), end: isoOf(end), resource: res, allDay: false };
    }
    function paintGhost() {
      var d = drag, r = d.res, g = container.querySelector('.cal-ghost'), res = result(d);
      var verdict = o.validate ? o.validate(d.id, res) : true, bad = verdict === false || typeof verdict === 'string';
      var color = d.ev ? colorOf(d.ev) : res.resource != null && resById(res.resource) ? colorVar(resById(res.resource).color) : 'var(--accent)';
      if (!g) { g = document.createElement('div'); g.setAttribute('aria-hidden', 'true'); }
      g.className = 'cal-event cal-ghost' + (d.kind === 'moveAllDay' ? ' allday' : '') + (bad ? ' invalid' : '');
      g.style.setProperty('--c', color);
      var label = d.kind === 'moveAllDay' ? (d.ev.title || '') : timeText(parseLocal(res.start), o.hour12) + ' – ' + timeText(parseLocal(res.end), o.hour12);
      var msg = bad ? (typeof verdict === 'string' ? verdict : t('calUnavailable')) : '';
      g.innerHTML = '<div class="cal-ev-body"><span class="cal-ev-time">' + esc(label) + '</span>' + (msg ? '<span class="cal-ghost-msg">' + esc(msg) + '</span>' : '') + '</div>';
      if (d.kind === 'moveAllDay') {
        var ad = container.querySelector('.cal-allday'), span = Math.max(1, Math.round((endOf(d.ev) - dayOf(startOf(d.ev))) / DAY));
        if (g.parentNode !== ad) ad.appendChild(g);
        g.style.gridColumn = (r.col + 1) + ' / span ' + Math.min(span, cols.length - r.col);
        g.style.gridRow = '1';
        return;
      }
      var colEl = colEls()[r.col], top = Math.max(r.a, lo()) - lo(), bot = Math.min(r.b, hi()) - lo();
      if (g.parentNode !== colEl) colEl.appendChild(g);
      g.style.top = top * px() + 'px';
      g.style.height = Math.max((bot - top) * px(), MIN_PX) + 'px';
      g.style.left = '1px'; g.style.width = 'calc(100% - 3px)';
    }
    function commit(d) {
      var r = result(d), before = renders;
      if (!r) return;
      if (d.kind === 'create') {
        if (!d.moved && d.res.b - d.res.a <= o.step) {   // long-press without moving = a default-length booking
          var len = Math.min(o.duration, hi() - lo()), a = clamp(d.res.a, lo(), hi() - len);
          r.start = isoOf(atMinute(cols[d.res.col].date, a)); r.end = isoOf(atMinute(cols[d.res.col].date, a + len));
        }
        o.onCreate(r);
      } else if (d.kind === 'resize') {
        if (r.end !== isoOf(endOf(d.ev))) { o.onResize(d.id, { start: d.ev.start, end: r.end }); if (renders === before) render(); afterChange(d.id, 'calResized'); return; }
      } else {
        var same = r.start === (d.ev.allDay ? ymdOf(dayOf(startOf(d.ev))) : isoOf(startOf(d.ev))) && String(r.resource) === String(d.ev.resource == null ? null : d.ev.resource);
        if (!same) { o.onMove(d.id, r); if (renders === before) render(); afterChange(d.id, 'calMoved'); return; }
      }
      if (renders === before) render();
    }
    function autoScroll() {
      if (!drag || !drag.active) return;
      var sc = drag.sc, rc = sc.getBoundingClientRect(), edge = 40, dy = 0, dx = 0, top = rc.top + headH();
      var gut = container.querySelector('.cal-gutter'), left = rc.left + (gut ? gut.offsetWidth : 0);
      var sp = function (dist) { return Math.ceil((edge - Math.max(0, dist)) / 3) + 2; };
      if (drag.kind !== 'moveAllDay') {
        if (drag.y < top + edge) dy = -sp(drag.y - top); else if (drag.y > rc.bottom - edge) dy = sp(rc.bottom - drag.y);
      }
      if (drag.kind !== 'resize' && drag.kind !== 'create') {
        if (drag.x < left + edge && sc.scrollLeft > 0) dx = -sp(drag.x - left); else if (drag.x > rc.right - edge) dx = sp(rc.right - drag.x);
      }
      if (dy || dx) {
        var bt = sc.scrollTop, bl = sc.scrollLeft;
        sc.scrollTop += dy; sc.scrollLeft += dx;
        if (sc.scrollTop !== bt || sc.scrollLeft !== bl) track();
      }
      drag.raf = requestAnimationFrame(autoScroll);
    }

    /* ----- listeners (bound once; the content is re-rendered) ----- */
    on('pointerdown', startDrag);
    on('touchmove', function (e) { if (drag && drag.active) e.preventDefault(); }, { passive: false });
    on('contextmenu', function (e) { if (drag && drag.touch) e.preventDefault(); });
    on('click', function (e) {
      if (!e.target.closest) return;
      var day = e.target.closest('[data-cal-date]');
      if (day) { o.date = day.dataset.calDate; render(); if (o.onDate) o.onDate(o.date); return; }
      var hd = e.target.closest('button.cal-hd');
      if (hd) { var c = cols[+hd.dataset.col]; if (c && c.resource && o.onResource) o.onResource(c.resource.id); else if (c && o.onDate) o.onDate(c.key); return; }
      if (Date.now() < suppressClick) return;
      var evEl = e.target.closest('.cal-event:not(.cal-ghost), .cal-ag-item');
      if (evEl) { var ev = evById(evEl.dataset.id); if (ev) open(ev, e); return; }
      if (!o.onCreate || o.readOnly || !o.clickCreate || e.target.closest('.cal-blocked')) return;
      var colEl = e.target.closest('.cal-col');
      if (colEl) {
        var c2 = cols[+colEl.dataset.col], len = Math.min(o.duration, hi() - lo());
        var a = clamp(snapDown(minuteAt(colEl, e.clientY)), lo(), hi() - len);
        o.onCreate({ start: isoOf(atMinute(c2.date, a)), end: isoOf(atMinute(c2.date, a + len)), resource: c2.resource ? c2.resource.id : null, allDay: false });
        return;
      }
      var cell = e.target.closest('.cal-ad-cell');
      if (cell) { var c3 = cols[+cell.dataset.col]; o.onCreate({ start: c3.key, end: ymdOf(plusDays(c3.date, 1)), resource: c3.resource ? c3.resource.id : null, allDay: true }); }
    });
    on('keydown', function (e) {
      if (!e.target.closest) return;
      var evEl = e.target.closest('.cal-event:not(.cal-ghost), .cal-ag-item');
      if (evEl && container.contains(evEl)) { eventKey(e, evEl); return; }
      if (e.target.matches('.cal-scroll')) gridKey(e);
    });
    on('focusin', function (e) {
      if (e.target.matches && e.target.matches('.cal-scroll') && kbd) { ensureCursor(); placeCursor(true); }
    });
    on('focusout', function (e) { if (e.target.matches && e.target.matches('.cal-scroll')) hideCursor(); });

    // "now" line + the narrow / wide switch
    var timer = setInterval(function () {
      if (!document.contains(container)) { clearInterval(timer); return; }
      if (drag) return;
      var now = new Date();
      $$('.cal-col', container).forEach(function (el) {
        var c = cols[+el.dataset.col], line = el.querySelector('.cal-now'), m = minuteOf(now);
        var show = c && sameDate(c.date, now) && m >= lo() && m < hi();
        if (show && !line) el.insertAdjacentHTML('beforeend', nowHtml(c));
        else if (show) line.style.top = (m - lo()) * px() + 'px';
        else if (line) line.remove();
      });
    }, 30000);
    offs.push(function () { clearInterval(timer); });
    if (window.ResizeObserver) {
      var ro = new ResizeObserver(function () {
        var w = container.clientWidth, n = !!o.narrow && w > 0 && w < o.narrowWidth;
        if (n !== narrow && !drag) render();
      });
      ro.observe(container);
      offs.push(function () { ro.disconnect(); });
    }
    var unregister = null;

    var api = {
      el: container,
      get options() { return o; },
      get view() { return eff; },
      get range() { var last = days[days.length - 1]; return { start: ymdOf(days[0]), end: ymdOf(plusDays(last, 1)), days: days.map(ymdOf) }; },
      update: function (events, extra) { if (events) o.events = events; if (extra) Object.assign(o, extra); render(); },
      set: function (next) { Object.assign(o, next); render(); },
      scrollTo: scrollTo,
      focusEvent: function (id) {
        var el = $$('.cal-event, .cal-ag-item', container).filter(function (x) { return x.dataset.id === String(id) && x.tabIndex >= 0; })[0];
        if (el) { focusEl(el); if (el.scrollIntoView) el.scrollIntoView({ block: 'nearest' }); }
        return !!el;
      },
      /** stop a drag in progress, the "now" timer and the resize observer, remove the listeners (the markup stays) */
      destroy: function () {
        if (container._calendar !== api) return;
        if (drag) cancelDrag();
        offs.forEach(function (f) { f(); }); offs = [];
        delete container._calendar;
        if (unregister) unregister();
      }
    };
    container._calendar = api;
    unregister = onDetach(container, api.destroy);
    render();
    return api;
  }
  /** date helpers that match the calendar's format (local time, no time zones) */
  calendar.iso = function (d) { return isoOf(parseLocal(d)); };
  calendar.ymd = function (d) { return ymdOf(parseLocal(d)); };
  calendar.parse = parseLocal;
  calendar.addDays = function (d, n) { return plusDays(parseLocal(d), n); };
  calendar.weekStart = function (d, ws) { return ymdOf(weekOf(parseLocal(d || new Date()), ws == null ? 1 : ws)); };
  calendar.when = function (start, end, allDay) { return whenText(parseLocal(start), parseLocal(end || start), !!allDay); };

  /* ---------- Date picker (month grid with keyboard) ----------
     var dp = UI.datePicker(containerEl, {
       date: '2026-11-17',              // selected day (or null)
       month: '2026-11-01',             // month to show (default: the selected one / today)
       weekStart: 1, min, max,          // 'YYYY-MM-DD' limits (optional)
       range: ['2026-11-16', '2026-11-22'],   // days to tint, e.g. the week a calendar shows (optional)
       marks: { '2026-11-20': 'green' } | (ymd => colour | null),   // a dot under the day
       label: 'Choose date',
       onSelect: ymd => {}
     });
     dp.set({ date, month, … }), dp.el
     Renders .date-picker › .dp-head (title + prev / next) and a .cal-grid of buttons. One tab stop:
     ← → ↑ ↓ move by a day / week, PageUp / PageDown by a month, Home / End to the week's start / end, Enter selects. */
  function datePicker(container, opts) {
    if (container._datePicker) { container._datePicker.set(opts); return container._datePicker; }
    var o = Object.assign({ date: null, month: null, weekStart: 1, min: null, max: null, range: null, marks: null, label: null, onSelect: null }, opts);
    var shown = null;   // first day of the shown month
    var offs = [];
    function on(type, fn) { container.addEventListener(type, fn); offs.push(function () { container.removeEventListener(type, fn); }); }

    function monthOf(v) { var d = parseLocal(v); return new Date(d.getFullYear(), d.getMonth(), 1); }
    function markOf(k) { var m = o.marks; return !m ? null : typeof m === 'function' ? m(k) : m[k] || null; }
    function off(k) { return (o.min && k < o.min) || (o.max && k > o.max); }
    function render(focusKey) {
      shown = monthOf(o.month || o.date || new Date());
      var first = weekOf(shown, o.weekStart), today = ymdOf(new Date()), sel = o.date ? ymdOf(parseLocal(o.date)) : null;
      var lastDay = new Date(shown.getFullYear(), shown.getMonth() + 1, 0), rows = Math.ceil((Math.round((lastDay - first) / DAY) + 1) / 7);
      var title = dtf({ month: 'long', year: 'numeric' }).format(shown), full = dtf({ weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
      var inMonth = function (k) { return k.slice(0, 7) === ymdOf(shown).slice(0, 7); };
      var stop = focusKey && inMonth(focusKey) ? focusKey : sel && inMonth(sel) ? sel : inMonth(today) ? today : ymdOf(shown);
      var wd = '', cells = '';
      for (var i = 0; i < 7; i++) wd += '<span class="cal-wd" aria-hidden="true">' + esc(dtf({ weekday: 'short' }).format(plusDays(first, i)).slice(0, 2)) + '</span>';
      for (var j = 0; j < rows * 7; j++) {
        var d = plusDays(first, j), k = ymdOf(d), mark = markOf(k);
        var inR = o.range && k >= o.range[0] && k <= o.range[1];
        cells += '<button type="button" class="cal-day' + (inMonth(k) ? '' : ' out') + (k === today ? ' today' : '') + (k === sel ? ' sel' : '') + (inR ? ' in-range' : '') + (mark ? ' marked' : '') +
          '" data-date="' + k + '" tabindex="' + (k === stop ? 0 : -1) + '" aria-label="' + esc(full.format(d)) + '"' + (k === sel ? ' aria-pressed="true"' : '') +
          (k === today ? ' aria-current="date"' : '') + (off(k) ? ' disabled' : '') + (mark ? ' style="--c:' + esc(colorVar(mark)) + '"' : '') + '>' + d.getDate() + '</button>';
      }
      container.innerHTML = '<div class="date-picker" role="group" aria-label="' + esc(o.label || title) + '"><div class="dp-head"><span class="dp-title" aria-live="polite">' + esc(title) + '</span>' +
        '<button type="button" class="icon-btn small" data-dp="-1" title="' + esc(t('dpPrev')) + '">' + icon('chevron-left') + '</button>' +
        '<button type="button" class="icon-btn small" data-dp="1" title="' + esc(t('dpNext')) + '">' + icon('chevron-right') + '</button></div>' +
        '<div class="cal-grid">' + wd + cells + '</div></div>';
      if (focusKey) { var b = container.querySelector('[data-date="' + focusKey + '"]'); if (b) focusEl(b); }
    }
    function go(k) {
      if (!k.slice(0, 7) || k.slice(0, 7) !== ymdOf(shown).slice(0, 7)) o.month = k;
      render(k);
    }
    on('click', function (e) {
      var nav = e.target.closest && e.target.closest('[data-dp]');
      if (nav) {
        o.month = ymdOf(new Date(shown.getFullYear(), shown.getMonth() + +nav.dataset.dp, 1));
        render();
        var again = container.querySelector('[data-dp="' + nav.dataset.dp + '"]'); if (again) focusEl(again);
        return;
      }
      var day = e.target.closest && e.target.closest('.cal-day[data-date]');
      if (!day || day.disabled) return;
      o.date = day.dataset.date; o.month = o.date;
      render(day === document.activeElement || kbd ? o.date : null);
      if (o.onSelect) o.onSelect(o.date);
    });
    on('keydown', function (e) {
      var day = e.target.closest && e.target.closest('.cal-day[data-date]');
      if (!day || e.altKey || e.metaKey || e.ctrlKey) return;
      var d = parseLocal(day.dataset.date), n = null;
      if (e.key === 'ArrowLeft') n = plusDays(d, -1); else if (e.key === 'ArrowRight') n = plusDays(d, 1);
      else if (e.key === 'ArrowUp') n = plusDays(d, -7); else if (e.key === 'ArrowDown') n = plusDays(d, 7);
      else if (e.key === 'Home') n = weekOf(d, o.weekStart); else if (e.key === 'End') n = plusDays(weekOf(d, o.weekStart), 6);
      else if (e.key === 'PageUp' || e.key === 'PageDown') {
        var dm = e.key === 'PageDown' ? 1 : -1, last = new Date(d.getFullYear(), d.getMonth() + dm + 1, 0).getDate();
        n = new Date(d.getFullYear(), d.getMonth() + dm, Math.min(d.getDate(), last));
      }
      if (!n) return;
      e.preventDefault(); e.stopPropagation();
      go(ymdOf(n));
    });
    var api = {
      el: container,
      get date() { return o.date; },
      set: function (next) { Object.assign(o, next || {}); render(); },
      update: function (next) { Object.assign(o, next || {}); render(); },
      destroy: function () {
        if (container._datePicker !== api) return;
        offs.forEach(function (f) { f(); }); offs = [];
        delete container._datePicker;
        if (unregister) unregister();
      }
    };
    var unregister = null;
    container._datePicker = api;
    unregister = onDetach(container, api.destroy);
    render();
    return api;
  }

  /* ---------- HUD + screen-reader announcements ---------- */
  var hudEl = null, hudTimer = null;
  function hud(text, opts) {
    opts = opts || {};
    if (!hudEl) {
      hudEl = document.createElement('div');
      hudEl.className = 'hud';
      hudEl.setAttribute('role', 'status');
      hudEl.setAttribute('aria-live', 'polite');
      hudEl.setAttribute('aria-atomic', 'true');
    }
    if (!hudEl.isConnected) document.body.appendChild(hudEl);   // also after the page content was swapped
    hudEl.innerHTML = '<span>' + esc(text) + '</span>';
    if (opts.undo) {
      var b = document.createElement('button');
      b.textContent = t('undo');
      b.addEventListener('click', function () { opts.undo(); hideHud(); });
      hudEl.appendChild(b);
    }
    hudEl.classList.add('show');
    clearTimeout(hudTimer);
    hudTimer = setTimeout(hideHud, opts.duration || 3000);
  }
  function hideHud() { if (hudEl) hudEl.classList.remove('show'); }

  var liveEl = null;
  function liveRegion() {
    if ((liveEl && liveEl.isConnected) || !document.body) return liveEl;
    if (!liveEl) {
      liveEl = document.createElement('div');
      liveEl.setAttribute('aria-live', 'polite');
      liveEl.setAttribute('aria-atomic', 'true');
      liveEl.setAttribute('data-ui-live', '');
      liveEl.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0';
    }
    document.body.appendChild(liveEl);   // (again, if the page content was swapped and took it along)
    return liveEl;
  }
  /** say something to screen readers without showing it (polite live region) */
  function announce(text) {
    var el = liveRegion(); if (!el) return;
    el.textContent = '';
    setTimeout(function () { el.textContent = text; }, 40);
  }

  /* ---------- list selection helpers ---------- */
  function selectRow(row, opts) {
    if (!row) return;
    var col = row.closest('.column');
    $$('.row.selected', col).forEach(function (r) { r.classList.remove('selected'); if (r.getAttribute('role') === 'option') r.setAttribute('aria-selected', 'false'); });
    row.classList.add('selected');
    if (row.getAttribute('role') === 'option') row.setAttribute('aria-selected', 'true');
    if (opts && opts.scroll) row.scrollIntoView({ block: 'nearest' });
    if (!opts || opts.open !== false) { if (state.mode === 'narrow') showDetail(); }
    emit('ui:select', { id: row.dataset.id, el: row });
  }

  /* ---------- accessibility pass ----------
     Rows, tabs and icon buttons are rendered by app code (innerHTML), so the kit fixes them up centrally:
     a MutationObserver runs enhance() after every DOM change. It only adds what is missing. */
  var ROWS = '.column .row, .p-row.click, .feed-item.click';
  var TABLE_ROWS = '.data-table.click tbody tr, .data-table tr.click'; // focusable + Enter, keep their table semantics
  var focusMem = null; // { kind, id, el } — the row / card that has focus, so it survives a re-render

  function labelIconButton(b) {
    if (b.hasAttribute('aria-labelledby') || (b.hasAttribute('aria-label') && !b._uiLabel)) return;
    var label = b.getAttribute('title') || b.getAttribute('data-tip');
    if (!label || b.textContent.trim()) return;
    b._uiLabel = true;
    setAttr(b, 'aria-label', label);
  }

  function enhance() {
    $$(ROWS).forEach(function (r) {
      if (r.matches('a[href], button')) return;
      if (!r.hasAttribute('tabindex')) r.tabIndex = 0;
      var isRow = r.classList.contains('row');
      if (!r.hasAttribute('role')) {
        r.setAttribute('role', isRow ? 'option' : 'button');
        var p = isRow && r.parentElement;
        if (p && !p.hasAttribute('role')) p.setAttribute('role', 'listbox');
      }
      if (isRow && r.getAttribute('role') === 'option') setAttr(r, 'aria-selected', r.classList.contains('selected'));
    });
    $$(TABLE_ROWS).forEach(function (r) {
      if (!r.hasAttribute('tabindex')) r.tabIndex = 0;
      if (r.closest('table').hasAttribute('aria-multiselectable') || r.classList.contains('selected')) setAttr(r, 'aria-selected', r.classList.contains('selected'));
    });
    $$('.sb-heading').forEach(function (h) {
      var sec = h.closest('.sb-section');
      if (sec) setAttr(h, 'aria-expanded', !sec.classList.contains('collapsed'));
    });
    $$('.sidebar .sb-item').forEach(function (it) {
      if (it.classList.contains('active')) setAttr(it, 'aria-current', 'page');
      else if (it.hasAttribute('aria-current')) it.removeAttribute('aria-current');
    });
    $$('button[title], a[title], [role="button"][title], button[data-tip], a[data-tip]').forEach(labelIconButton);
    $$('.tabs').forEach(setupTabs);

    // the focused row / card was replaced by a re-render → focus its successor
    if (focusMem && !document.contains(focusMem.el)) {
      var m = focusMem, ae = document.activeElement;
      focusMem = null;
      if ((!ae || ae === document.body) && !modals.length && !menu) {
        var next = $$(m.kind).find(function (x) { return x.dataset.id === m.id; });
        if (next) { focusEl(next); focusMem = { kind: m.kind, id: m.id, el: next }; }
      }
    }
    if (tip.target && !document.contains(tip.target)) hideTip();
  }

  /* ---------- tabs ----------
     <div class="tabs" role="tablist">
       <button class="tab active" data-tab="a">One</button> <button class="tab" data-tab="b">Two</button>
     </div>
     <div data-tab-panel="a">…</div> <div data-tab-panel="b" hidden>…</div>      (panels are optional)
     Panels are looked up inside the nearest [data-tabs] ancestor, or the tablist's parent.
     Click / ← → Home End switch tabs; "ui:tab" { tab, el } is fired on document. */
  function tabsOf(list) { return $$('.tab', list).filter(function (x) { return x.closest('.tabs') === list; }); }
  function tabOff(x) { return x.disabled || x.getAttribute('aria-disabled') === 'true'; }

  function setupTabs(list) {
    if (!list.hasAttribute('role')) list.setAttribute('role', 'tablist');
    var tabs = tabsOf(list);
    var active = tabs.find(function (x) { return x.classList.contains('active'); });
    var stop = active || tabs.find(function (x) { return !tabOff(x); });
    tabs.forEach(function (x) {
      if (!x.hasAttribute('role')) x.setAttribute('role', 'tab');
      setAttr(x, 'aria-selected', x === active);
      setAttr(x, 'tabindex', x === stop ? 0 : -1);
    });
    if (!active) return;
    var scope = list.closest('[data-tabs]') || list.parentElement;
    $$('[data-tab-panel]', scope).forEach(function (p) {
      var name = p.getAttribute('data-tab-panel');
      var tab = tabs.find(function (x) { return x.dataset.tab === name; });
      if (!tab) return; // belongs to another (nested) tablist
      if (p.hidden !== (tab !== active)) p.hidden = tab !== active;
      if (!p.hasAttribute('role')) p.setAttribute('role', 'tabpanel');
      if (!p.id) p.id = 'ui-tabpanel-' + (++seq);
      if (!tab.id) tab.id = 'ui-tab-' + (++seq);
      setAttr(tab, 'aria-controls', p.id);
      if (!p.hasAttribute('aria-label')) setAttr(p, 'aria-labelledby', tab.id);
    });
  }

  /** activate a tab element (same as clicking it); returns false if it is disabled */
  function selectTab(tab, focus) {
    var list = tab && tab.closest && tab.closest('.tabs');
    if (!list || tabOff(tab)) return false;
    var was = tab.classList.contains('active');
    tabsOf(list).forEach(function (x) { x.classList.toggle('active', x === tab); });
    setupTabs(list);
    if (focus) focusEl(tab, true);
    if (!was) emit('ui:tab', { tab: tab.dataset.tab, el: tab });
    return true;
  }

  /* ---------- tooltips ----------
     <button data-tip="Archive" data-tip-kbd="⌫" data-side="bottom">…</button>
     shows <div class="tooltip" role="tooltip"> after ~400 ms of hover, or at once on keyboard focus.
     data-tip-kbd (optional) adds a shortcut key cap; data-side="top|bottom" (optional, default top) is the preferred
     side — it flips when there is no room. The tooltip gets data-side (the side used) and class "show". */
  var tip = { el: null, target: null, timer: null, pending: null, touch: 0 };

  function hideTip() {
    clearTimeout(tip.timer);
    tip.pending = null;
    if (!tip.target) return;
    var tg = tip.target; tip.target = null;
    if (tip.el.parentNode) tip.el.parentNode.removeChild(tip.el);
    var d = (tg.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== 'ui-tooltip'; });
    if (d.length) tg.setAttribute('aria-describedby', d.join(' ')); else tg.removeAttribute('aria-describedby');
  }
  function showTip(target) {
    hideTip();
    var text = target.getAttribute('data-tip');
    if (!text || !document.contains(target)) return;
    if (!tip.el) {
      tip.el = document.createElement('div');
      tip.el.className = 'tooltip';
      tip.el.id = 'ui-tooltip';
      tip.el.setAttribute('role', 'tooltip');
    }
    var el = tip.el;
    el.classList.remove('show');
    el.textContent = text;
    var keyCap = target.getAttribute('data-tip-kbd');
    if (keyCap) {
      var k = document.createElement('kbd');
      k.className = 'kbd';
      k.textContent = keyCap;
      el.appendChild(k);
    }
    el.style.left = '0px'; el.style.top = '0px';
    document.body.appendChild(el);
    var r = target.getBoundingClientRect(), w = el.offsetWidth, h = el.offsetHeight, gap = 6;
    var above = r.top - h - gap, below = r.bottom + gap;
    var side = target.getAttribute('data-side') === 'bottom' ? 'bottom' : 'top';
    if (side === 'top' && above < 4) side = 'bottom';
    else if (side === 'bottom' && below + h > window.innerHeight - 4 && above >= 4) side = 'top';
    var top = side === 'top' ? above : below;
    el.dataset.side = side;
    el.style.left = Math.round(clamp(r.left + r.width / 2 - w / 2, 4, Math.max(4, window.innerWidth - w - 4))) + 'px';
    el.style.top = Math.round(clamp(top, 4, Math.max(4, window.innerHeight - h - 4))) + 'px';
    el.offsetWidth; // start a CSS transition, if the stylesheet has one
    el.classList.add('show');
    tip.target = target;
    // describe the element — unless the tip only repeats its accessible name
    if (target.getAttribute('aria-label') !== text) {
      var d = target.getAttribute('aria-describedby');
      target.setAttribute('aria-describedby', (d ? d + ' ' : '') + 'ui-tooltip');
    }
  }
  function tipTarget(e) { return e.target && e.target.closest ? e.target.closest('[data-tip]') : null; }

  /* ---------- router: hash (default, works on file://) or path (History API) ----------
     UI.route(function (r) { r.path → 'deals/42', r.parts → ['deals', '42'], r.params → { tab: 'notes' } })
     UI.navigate('deals/42', { params: { tab: 'notes' }, replace: false })
       hash mode → location.hash = '#/deals/42?tab=notes'
       path mode → history.pushState → <base>deals/42?tab=notes   (UI.setRouter({ mode: 'path', base: '/crm/' }))
     Hash mode: only hashes that start with "#/" (or an empty hash) are routes, so plain #anchors keep working.
     Path mode: only URLs under `base` are routes; the hash is left alone; Back / Forward come in as popstate.
       The server must answer every URL under `base` with the same page. Links: <a href="/crm/deals/42" data-ui-route>
       (or UI.href('deals/42')) navigate without a reload; other links behave normally.
     Boundary with a server-side navigation library (HTMX, Drupal): the page-level URL belongs to it. Use the kit router
     only inside a JS "island" — hash mode, or path mode with a `base` that the library never pushes URLs under. */
  var routeHandlers = [], currentRoute = null, routedKey = null;
  var router = { mode: 'hash', base: '/' };

  function dec(s) { try { return decodeURIComponent(s.replace(/\+/g, ' ')); } catch (e) { return s; } }
  function parseHash(hash) {
    var h = String(hash || '').replace(/^#\/?/, ''), q = h.indexOf('?');
    var params = {};
    if (q >= 0) h.slice(q + 1).split('&').forEach(function (kv) {
      if (!kv) return;
      var i = kv.indexOf('=');
      params[dec(i < 0 ? kv : kv.slice(0, i))] = i < 0 ? '' : dec(kv.slice(i + 1));
    });
    var parts = (q >= 0 ? h.slice(0, q) : h).split('/').filter(Boolean).map(dec);
    return { path: parts.join('/'), parts: parts, params: params };
  }
  function isRouteHash(h) { return !h || h === '#' || h.slice(0, 2) === '#/'; }
  function normBase(b) { var x = String(b == null ? '/' : b).replace(/^\/+|\/+$/g, ''); return x ? '/' + x + '/' : '/'; }
  /** is the current URL one of ours? */
  function inScope() {
    if (router.mode === 'hash') return isRouteHash(location.hash);
    var p = location.pathname;
    return p.indexOf(router.base) === 0 || p + '/' === router.base;
  }
  function locKey() { return router.mode === 'path' ? location.pathname + location.search : location.hash; }
  function readRoute() {
    if (router.mode === 'hash') return parseHash(isRouteHash(location.hash) ? location.hash : '');
    if (!inScope()) return parseHash('');
    return parseHash('#' + location.pathname.slice(router.base.length - 1) + location.search);
  }
  /** the URL of a route in the current mode: '#/deals/42?tab=notes' or '/crm/deals/42?tab=notes' */
  function href(path, params) {
    var parts = String(path == null ? '' : path).replace(/^#?\/?/, '').split('/').filter(Boolean).map(encodeURIComponent);
    var qs = [], p = params || {};
    for (var k in p) if (p[k] != null && p[k] !== '') qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(p[k]));
    var rest = parts.join('/') + (qs.length ? '?' + qs.join('&') : '');
    return router.mode === 'path' ? router.base + rest : '#/' + rest;
  }
  function dispatchRoute() {
    if (!inScope()) return;
    var k = locKey();
    if (k === routedKey) return;
    routedKey = k;
    currentRoute = readRoute();
    emit('ui:route', currentRoute);
    routeHandlers.slice().forEach(function (fn) { fn(currentRoute); });
  }
  /** register a handler; it is called right away with the current route and on every change. Returns an "off" function. */
  function route(fn) {
    routeHandlers.push(fn);
    fn(currentRoute);
    return function () { var i = routeHandlers.indexOf(fn); if (i >= 0) routeHandlers.splice(i, 1); };
  }
  function navigate(path, opts) {
    opts = opts || {};
    var url = href(path, opts.params);
    if (router.mode === 'path') {
      if (url !== location.pathname + location.search) {
        if (opts.replace) history.replaceState(Object.assign({}, history.state, { uiRoute: true }), '', url);
        else history.pushState({ uiRoute: true }, '', url);
      }
    } else if (url !== location.hash && !(url === '#/' && !location.hash)) {
      if (opts.replace) location.replace(url); else location.hash = url;
    }
    dispatchRoute(); // synchronous; the hashchange that follows is ignored
    return currentRoute;
  }
  /** UI.setRouter({ mode: 'hash' | 'path', base: '/' }) — switch modes; handlers get the route of the current URL */
  function setRouter(o) {
    o = o || {};
    if (o.mode) router.mode = o.mode === 'path' ? 'path' : 'hash';
    if (o.base != null) router.base = normBase(o.base);
    routedKey = null;
    if (inScope()) dispatchRoute(); else { currentRoute = parseHash(''); }
    return { mode: router.mode, base: router.base };
  }
  currentRoute = readRoute();
  routedKey = inScope() ? locKey() : null;
  // Back / Forward / an edited URL leave the screen the popups belonged to → close them first
  window.addEventListener('hashchange', function () {
    if (router.mode !== 'hash') return;
    if (isRouteHash(location.hash) && location.hash !== routedKey) { closeModals(); closeMenu(); }
    dispatchRoute();
  });
  window.addEventListener('popstate', function () {
    if (router.mode !== 'path' || !inScope()) return;
    if (locKey() !== routedKey) { closeModals(); closeMenu(); }
    dispatchRoute();
  });
  // path mode: <a href="/base/x" data-ui-route> navigates without a reload (plain clicks only)
  document.addEventListener('click', function (e) {
    if (router.mode !== 'path' || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href][data-ui-route]');
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) return;
    var u = new URL(a.href, location.href);
    if (u.origin !== location.origin || !(u.pathname.indexOf(router.base) === 0 || u.pathname + '/' === router.base)) return;
    e.preventDefault();
    var rest = u.pathname.slice(router.base.length), params = parseHash('#?' + u.search.slice(1)).params;
    navigate(rest, { params: params });
  });

  /* ---------- shared helpers (so apps don't copy-paste them) ---------- */
  function uid(prefix) { return (prefix || '') + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }

  function initials(name) {
    return String(name || '?').split(/\s+/).filter(function (w) { return /[\p{L}\p{N}]/u.test(w[0] || ''); })
      .slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase() || '?';
  }
  var AVATAR_COLORS = ['blue', 'teal', 'green', 'orange', 'purple', 'pink', 'indigo', 'red'];
  function hashOf(s) { var h = 0; s = String(s); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h; }
  /**
   * UI.avatar('Ada Lovelace', { size: 'xs'|'sm'|'xl', color: 'blue' | '#hex' | 'var(--x)', className, title })
   * → '<span class="avatar sm" style="background:…" title="Ada Lovelace">AL</span>' (the name is escaped).
   * Without `color` the name picks one of the kit's status colours, always the same one.
   * color: false = the neutral grey default avatar. title: false = no tooltip.
   */
  function avatar(name, o) {
    o = o || {};
    var cls = 'avatar' + (o.size ? ' ' + o.size : '') + (o.className ? ' ' + o.className : '');
    var color = o.color === false ? '' : o.color ? colorVar(o.color) : 'var(--' + AVATAR_COLORS[hashOf(name || '') % AVATAR_COLORS.length] + ')';
    var title = o.title === false ? '' : o.title || name;
    return '<span class="' + esc(cls) + '"' + (color ? ' style="background:' + esc(color) + '"' : '') + (title ? ' title="' + esc(title) + '"' : '') + '>' + esc(initials(name)) + '</span>';
  }

  var DAY = 864e5;
  var YMD = /^\d{4}-\d\d-\d\d$/;
  function toDate(d) {
    if (d instanceof Date) return d;
    if (typeof d === 'string' && YMD.test(d)) { var p = d.split('-'); return new Date(+p[0], p[1] - 1, +p[2]); } // local, not UTC
    return new Date(d);
  }
  /** whole calendar days from today: 0 today, 1 tomorrow, -1 yesterday */
  function dayDiff(d) {
    var a = new Date(), b = new Date(toDate(d).getTime());
    a.setHours(0, 0, 0, 0); b.setHours(0, 0, 0, 0);
    return Math.round((b - a) / DAY);
  }
  var nfCache = {};
  function nf(opts) {
    var k = cfg.locale + JSON.stringify(opts || {});
    if (!nfCache[k]) {
      try { nfCache[k] = new Intl.NumberFormat(cfg.locale, opts); }
      catch (e) { nfCache[k] = { format: function (n) { return String(Math.round(n)); } }; }
    }
    return nfCache[k];
  }
  var fmt = {
    /** "just now", "5 min ago", "3 h ago", "Yesterday", "4 days ago", "2 wk ago", "3 mo ago", "1 yr ago".
        A date-only value ('2026-03-01') has no time, so it starts at "Today". */
    ago: function (d) {
      if (d == null || d === '') return '';
      var dt = toDate(d); if (isNaN(dt)) return '';
      var dateOnly = typeof d === 'string' && YMD.test(d), ms = Date.now() - dt.getTime(), n = -dayDiff(dt);
      if (!dateOnly) {
        if (ms < 60e3) return t('justNow');
        if (ms < 36e5) return t('minAgo', { n: Math.round(ms / 6e4) });
        if (ms < DAY) return t('hoursAgo', { n: Math.round(ms / 36e5) });
      }
      if (n <= 0) return t('today');
      if (n === 1) return t('yesterday');
      if (n < 7) return t('daysAgo', { n: n });
      if (n < 30) return t('weeksAgo', { n: Math.round(n / 7) });
      if (n < 365) return t('monthsAgo', { n: Math.round(n / 30) });
      return t('yearsAgo', { n: Math.round(n / 365) });
    },
    /** "Mar 4" (+ year when it is not this year). opts: { relative: true → Today / Tomorrow / Yesterday / weekday
        within a week, long: true → adds the weekday, format: Intl.DateTimeFormat options, locale, empty: '' } */
    date: function (d, opts) {
      opts = opts || {};
      var dt = d == null || d === '' ? null : toDate(d);
      if (!dt || isNaN(dt)) return opts.empty == null ? '' : opts.empty;
      var loc = opts.locale || cfg.locale;
      if (opts.relative) {
        var n = dayDiff(dt);
        if (n === 0) return t('today');
        if (n === 1) return t('tomorrow');
        if (n === -1) return t('yesterday');
        if (n > 1 && n < 7) return dt.toLocaleDateString(loc, { weekday: opts.long ? 'long' : 'short' });
      }
      var f = opts.format;
      if (!f) {
        f = { month: 'short', day: 'numeric' };
        if (dt.getFullYear() !== new Date().getFullYear()) f.year = 'numeric';
        if (opts.long) f.weekday = 'short';
      }
      return dt.toLocaleDateString(loc, f);
    },
    /** UI.fmt.number(1234.5) → "1,235"; opts = Intl.NumberFormat options (default: no decimals) */
    number: function (n, opts) { return nf(opts || { maximumFractionDigits: 0 }).format(+n || 0); },
    /** UI.fmt.money(1250000, 'EUR', { compact: true }) → "€1.3M"; currency defaults to UI.init({ currency }) / 'USD' */
    money: function (n, currency, opts) {
      opts = opts || {};
      return nf({ style: 'currency', currency: currency || cfg.currency, notation: opts.compact ? 'compact' : 'standard',
        maximumFractionDigits: opts.decimals != null ? opts.decimals : opts.compact ? 1 : 0 }).format(+n || 0);
    },
    dayDiff: dayDiff,
    /** UI.fmt.time('2026-11-16T14:30') → "2:30 PM" (en-US) / "14:30" (en-GB, pl-PL …) — 12 or 24 h from the locale;
        opts: { hour12: true | false } to force one. Accepts a Date or a local ISO string. */
    time: function (d, opts) {
      if (d == null || d === '') return '';
      var dt = parseLocal(d);
      return isNaN(dt) ? '' : timeText(dt, opts && opts.hour12);
    }
  };

  /** UI.debounce(fn, 250) → function; .cancel() drops a pending call, .flush() runs it now */
  function debounce(fn, ms) {
    var timer = null, args, self;
    function run() { timer = null; fn.apply(self, args); }
    function d() { args = arguments; self = this; clearTimeout(timer); timer = setTimeout(run, ms == null ? 200 : ms); }
    d.cancel = function () { clearTimeout(timer); timer = null; };
    d.flush = function () { if (timer != null) { clearTimeout(timer); run(); } };
    return d;
  }

  /* ---------- behaviour that works on every page (also without .app / UI.init) ---------- */
  function bindGlobal() {
    document.addEventListener('keydown', function (e) {
      kbd = true;
      if (e.key === 'Escape' && (tip.target || tip.pending)) hideTip();
    }, true);
    document.addEventListener('pointerdown', function (e) { kbd = false; hideTip(); if (e.pointerType === 'touch') tip.touch = Date.now(); }, true);
    document.addEventListener('scroll', hideTip, true);

    // tooltips
    document.addEventListener('mouseover', function (e) {
      var tg = tipTarget(e);
      if (!tg || tg === tip.target || tg === tip.pending || Date.now() - tip.touch < 800) return;
      clearTimeout(tip.timer);
      tip.pending = tg;
      tip.timer = setTimeout(function () { tip.pending = null; showTip(tg); }, 400);
    });
    document.addEventListener('mouseout', function (e) {
      var tg = tip.target || tip.pending;
      if (!tg || tipTarget(e) !== tg || (e.relatedTarget && tg.contains(e.relatedTarget))) return;
      if (!(kbd && document.activeElement === tg)) hideTip(); // a tip shown for keyboard focus stays until blur
    });

    document.addEventListener('focusin', function (e) {
      var tg = e.target;
      if (!tg || !tg.matches) return;
      // remember a focused row / card so the focus survives a re-render (see enhance)
      var kind = tg.matches('.board-card') ? '.board-card' : tg.matches('.p-row.click') ? '.p-row.click' : tg.matches('.column .row') ? '.column .row' :
        tg.matches('.feed-item.click') ? '.feed-item.click' : tg.matches(TABLE_ROWS) ? '.data-table tr' : null;
      focusMem = kind && tg.dataset.id != null ? { kind: kind, id: tg.dataset.id, el: tg } : null;
      if (kind === '.column .row') $$('.column').forEach(function (c) { c.classList.toggle('focused', c.contains(tg)); });
      var tt = tipTarget(e);
      if (tt && kbd) showTip(tt);
    });
    document.addEventListener('focusout', function (e) {
      if (tip.target && tipTarget(e) === tip.target) hideTip();
      // a real blur (the element is still there) forgets the row; a re-render does not
      if (focusMem && e.target === focusMem.el && !e.relatedTarget && document.contains(e.target)) {
        var m = focusMem;
        setTimeout(function () { if (focusMem === m && document.contains(m.el) && document.activeElement !== m.el) focusMem = null; }, 0);
      }
    });

    document.addEventListener('click', function (e) {
      var tab = e.target.closest && e.target.closest('.tabs .tab');
      if (tab) selectTab(tab);
    });

    document.addEventListener('keydown', function (e) {
      var tg = e.target;
      if (!tg || !tg.matches || e.defaultPrevented) return;
      // tabs: ← → Home End
      if (tg.matches('.tabs .tab') && !e.altKey && !e.metaKey && !e.ctrlKey) {
        var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (d || e.key === 'Home' || e.key === 'End') {
          var tabs = tabsOf(tg.closest('.tabs')).filter(function (x) { return !tabOff(x); });
          if (!tabs.length) return;
          var i = tabs.indexOf(tg);
          var next = e.key === 'Home' ? tabs[0] : e.key === 'End' ? tabs[tabs.length - 1] : tabs[(i + d + tabs.length) % tabs.length];
          e.preventDefault();
          selectTab(next, true);
        }
        return;
      }
      // rows: Enter / Space on the focused row = click (Space is left to the app once the row is selected)
      if ((e.key === 'Enter' || e.key === ' ') && !e.altKey && !e.metaKey && !e.ctrlKey && tg.matches(ROWS) && !tg.matches('a[href], button')) {
        if (modals.length || menu) return;
        e.preventDefault();
        if (e.key === 'Enter' || !tg.classList.contains('selected')) tg.click();
      }
      if (e.key === 'Enter' && !e.altKey && !e.metaKey && !e.ctrlKey && tg.matches(TABLE_ROWS)) {
        if (modals.length || menu) return;
        e.preventDefault();
        tg.click();
      }
    });

    new MutationObserver(enhance).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['title', 'data-tip'] });
    enhance();
  }

  /* ---------- global events ---------- */
  function bind() {
    document.addEventListener('click', function (e) {
      var t = e.target;

      var act = t.closest('[data-action]');
      if (act && !act.disabled) {
        if (act.dataset.action === 'toggle-sidebar') { toggleSidebar(); return; }
        if (act.dataset.action === 'back') { showList(); return; }
        if (act.dataset.action === 'toggle-inspector') { toggleInspector(); return; }
      }

      var th = t.closest('[data-set-theme]');
      if (th) { setTheme(th.dataset.setTheme); return; }

      var ds = t.closest('[data-dark-switch]');
      if (ds && !ds.disabled) { toggleDark(); return; }

      var pl = t.closest('[data-set-palette]');
      if (pl) { setPalette(pl.dataset.setPalette); return; }

      var acc = t.closest('[data-account]');
      if (acc) { openAccountMenu(acc); return; }

      var head = t.closest('.sb-heading');
      if (head) {
        var sec = head.closest('.sb-section');
        var off = sec.classList.toggle('collapsed');
        head.setAttribute('aria-expanded', String(!off));
        var hint = head.querySelector('.hint');
        if (hint) hint.textContent = STR[off ? 'show' : 'hide'];
        return;
      }

      var item = t.closest('.sidebar .sb-item');
      if (item && !item.hasAttribute('data-no-select')) {
        $$('.sidebar .sb-item.active').forEach(function (x) { x.classList.remove('active'); x.removeAttribute('aria-current'); });
        item.classList.add('active');
        item.setAttribute('aria-current', 'page');
        if (app) app.classList.remove('sidebar-open');
        if (state.mode === 'narrow') showList();
        update();
        emit('ui:navigate', { key: item.dataset.key, el: item });
        return;
      }

      var row = t.closest('.column .row');
      if (row) { selectRow(row); return; }

      var clear = t.closest('.search-clear');
      if (clear) {
        var input = clear.closest('.search').querySelector('input');
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
      }
    });

    // focus state of the column (accent-coloured selection) + closing overlays
    document.addEventListener('pointerdown', function (e) {
      var t = e.target;
      if (menu && !menu.el.contains(t) && !(menu.anchor && menu.anchor.contains(t))) closeMenu();
      if (modals.length || t.closest('.menu, .hud, .modal-backdrop') || !app) return;
      // a slide-over inspector closes when you click elsewhere (but not on things that open it)
      if (state.inspector && !app.classList.contains('inspector-inline') &&
          !t.closest('.inspector, [data-action="toggle-inspector"], [data-inspect]')) toggleInspector(false);
      if (t.closest('.pane-header')) return;
      $$('.column').forEach(function (c) { c.classList.toggle('focused', c.contains(t)); });
      if (app.classList.contains('sidebar-open') && !t.closest('.sidebar') && !t.closest('[data-action="toggle-sidebar"]')) {
        app.classList.remove('sidebar-open');
        update();
      }
    }, true);

    // search fields: has-value + Esc to clear
    document.addEventListener('input', function (e) {
      var s = e.target.closest && e.target.closest('.search');
      if (s) s.classList.toggle('has-value', !!e.target.value);
    });

    // hairline under a pane header once its body is scrolled
    document.addEventListener('scroll', function (e) {
      var body = e.target;
      if (!body.classList || !body.classList.contains('pane-body')) return;
      var head = body.parentElement.querySelector(':scope > .pane-header');
      if (head) head.classList.toggle('scrolled', body.scrollTop > 2);
    }, true);

    document.addEventListener('keydown', function (e) {
      // popups: Esc closes the top one; nothing else reacts while a popup is open
      if (modals.length && !menu) {
        var top = modals[modals.length - 1];
        if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); top.close(null); }
        else if (e.key === 'Tab' && !top.el.contains(e.target)) trapTab(e, top.el); // focus got outside: pull it back in
        return;
      }
      // menus: keyboard navigation
      if (menu) {
        var inMenu = menu.el.contains(e.target);
        var onItem = inMenu && e.target.matches('.menu-item');
        var typing = isTextField(e.target);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); moveFocus(e.key === 'ArrowDown' ? 1 : -1); return; }
        if ((e.key === 'Home' || e.key === 'End') && !typing) { e.preventDefault(); moveFocus(e.key === 'End' ? 1 : -1, true); return; }
        if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); closeMenu(); return; }
        if (e.key === 'Tab' && menu.tookFocus && (inMenu || e.target === document.body)) {
          // Tab walks the other controls of the menu (theme buttons, popover fields…); with none it closes the menu
          var stops = focusables(menu.el, '.menu-item');
          if (!stops.length) { closeMenu(); return; }
          e.preventDefault();
          var si = stops.indexOf(e.target);
          focusEl(stops[si < 0 ? (e.shiftKey ? stops.length - 1 : 0) : (si + (e.shiftKey ? -1 : 1) + stops.length) % stops.length], true);
          return;
        }
        if (e.key === 'Enter' || (e.key === ' ' && onItem)) {
          if (inMenu && !onItem && e.target !== menu.el) return; // a button / field inside the menu handles its own keys
          var f = onItem ? e.target : $('.menu-item.focused', menu.el);
          if (f) { e.preventDefault(); e.stopImmediatePropagation(); f.click(); return; }
          if (menu.onEnter && e.key === 'Enter') { e.preventDefault(); var cb = menu.onEnter; closeMenu(); cb(); return; }
        }
      }

      var t = e.target;
      var inField = t.matches && t.matches('input, textarea, select, [contenteditable="true"]');

      if (e.key === 'Escape' && inField && t.closest('.search')) {
        if (t.value) { t.value = ''; t.dispatchEvent(new Event('input', { bubbles: true })); }
        else t.blur();
        return;
      }
      if (inField) return;

      if (app) {   // shell shortcuts
        // ⌃⌘S / Ctrl+Alt+S — toggle sidebar
        if ((e.key === 's' || e.key === 'S') && ((e.metaKey && e.ctrlKey) || (e.ctrlKey && e.altKey))) {
          e.preventDefault(); toggleSidebar(); return;
        }
        if (e.key === 'Escape' && app.classList.contains('sidebar-open')) { app.classList.remove('sidebar-open'); update(); return; }
        if (e.key === 'Escape' && state.inspector && !app.classList.contains('inspector-inline')) { toggleInspector(false); return; }
        // ⌥⌘I / Ctrl+Alt+I — toggle the inspector
        if (e.code === 'KeyI' && e.altKey && (e.metaKey || e.ctrlKey) && app.querySelector('.inspector')) {
          e.preventDefault(); toggleInspector(); return;
        }
      }

      // ↑ ↓ inside the focused column
      var col = $('.column.focused');
      if (col && (e.key === 'ArrowDown' || e.key === 'ArrowUp') && !e.altKey) {
        var rows = $$('.row', col);
        if (!rows.length) return;
        e.preventDefault();
        var i = rows.findIndex(function (r) { return r.classList.contains('selected'); });
        var n = clamp(i + (e.key === 'ArrowDown' ? 1 : -1), 0, rows.length - 1);
        if (i < 0) n = 0;
        // keyboard focus follows the selection when it is on a row
        if (t.matches && t.matches('.row') && col.contains(t) && rows[n].tabIndex >= 0) focusEl(rows[n]);
        selectRow(rows[n], { scroll: true, open: false });
      }
      if (col && e.key === 'Enter' && state.mode === 'narrow') showDetail();
    });

    window.addEventListener('resize', update);
  }

  /* ---------- lifecycle: global part once, UI.attach(fragment) many times ----------
     Delegated from document (work for any content, also content inserted later — nothing to attach):
       bindGlobal (on load): tooltips, tabs (click + arrow keys), Enter / Space on rows and table rows, focus memory,
                             the accessibility pass (MutationObserver → enhance(): roles, tab stops, aria labels, tab panels)
       bind (UI.init, once): data-action buttons, theme / palette buttons, account menu, sidebar headings and items,
                             list row selection + ↑ ↓, search clear / Esc / has-value, pane-header hairline,
                             closing menus / slide-overs on outside clicks, popup + menu keyboard handling, window resize
     Bound per element (UI.attach): the .app shell (state, stored pane widths, responsive mode), .resizer handles,
       initial state of theme / palette buttons and search fields, plus every UI.behavior.
     Created by your code (they bind to their own container): UI.board, UI.calendar, UI.datePicker, UI.contextMenu. */
  var claims = new WeakMap();   // element → Set of once-ids (a WeakMap, not an attribute: a re-inserted HTML snapshot is new)
  var behaviors = [];           // [{ name, attach, detach }]
  var started = false, inited = false, attachedDoc = false;

  /** true the first time an element is claimed under an id */
  function claim(el, id) {
    var s = claims.get(el);
    if (s && s.has(id)) return false;
    if (!s) claims.set(el, s = new Set());
    s.add(id);
    return true;
  }
  /** UI.once('my-thing', '.selector', context) → the matching elements (context included) not seen before under that id */
  function once(id, sel, ctx) {
    var els = typeof sel === 'string' ? scoped(sel, ctx) : sel && sel.nodeType ? [sel] : Array.prototype.slice.call(sel || []);
    return els.filter(function (el) { return claim(el, id); });
  }
  function runBehaviors(phase, ctx) {
    (phase === 'detach' ? behaviors.slice().reverse() : behaviors.slice()).forEach(function (b) {
      if (typeof b[phase] !== 'function') return;
      try { b[phase](ctx); } catch (e) { console.error('[UI] behavior "' + b.name + '" failed in ' + phase + ':', e); }
    });
  }
  /** UI.behavior('name', { attach(context), detach(context) }) — run on every UI.attach / UI.detach
      (attach also runs at once on the whole document, if the page is already attached).
      Registering the same name again replaces it. Use UI.once() inside so a second attach binds nothing twice. */
  function behavior(name, b) {
    behaviors = behaviors.filter(function (x) { return x.name !== name; });
    var entry = { name: name, attach: b && b.attach, detach: b && b.detach };
    behaviors.push(entry);
    if (attachedDoc && typeof entry.attach === 'function') {
      try { entry.attach(document); } catch (e) { console.error('[UI] behavior "' + name + '" failed in attach:', e); }
    }
  }

  /** the global part: listeners on document / window — exactly once per page */
  function start() {
    if (started) return;
    started = true;
    bind();
  }

  /** take over an .app element: stored state, pane widths, responsive mode */
  function adoptApp(a) {
    app = a;
    state.collapsed = load('collapsed', false);
    state.inspector = load('inspector', false);
    app.classList.add('preload'); // no animations while the initial layout settles
    restoreWidths();
    update();
    requestAnimationFrame(function () { requestAnimationFrame(function () { a.classList.remove('preload'); }); });
    var col = $('.column', app);
    if (col) col.classList.add('focused');
  }

  /** wire up a fragment that was just put into the page (idempotent) */
  function attach(context) {
    var ctx = context || document;
    if (!document.body || !ctx.querySelectorAll) return ctx;
    start();
    if (window.UI_iconsEnsure) window.UI_iconsEnsure();   // the icon sprite, if a swap took it along
    if (inited) {
      // the shell: adopt an .app in the fragment when there is none (yet), or the old one was swapped out
      var a = ctx.nodeType === 1 && ctx.matches('.app') ? ctx : ctx.querySelector('.app');
      if (a && a !== app && a.isConnected && (!app || !app.isConnected)) adoptApp(a);
    }
    if (app) scoped('.resizer', ctx).forEach(function (r) {
      if (r.closest('.app') === app && claim(r, 'ui:resizer')) onDetach(r, bindResizer(r));
    });
    markButtons(ctx);
    scoped('.search input', ctx).forEach(function (i) { i.closest('.search').classList.toggle('has-value', !!i.value); });
    update();
    enhance();
    runBehaviors('attach', ctx);
    if (ctx === document) attachedDoc = true;
    emit('ui:attach', { context: ctx });
    return ctx;
  }

  /* registered teardowns (UI.detach runs them) */
  var teardowns = new WeakMap();   // element → [fn]
  /** UI.onDetach(el, fn) → unregister: fn runs when UI.detach() covers el */
  function onDetach(el, fn) {
    var list = teardowns.get(el);
    if (!list) teardowns.set(el, list = []);
    list.push(fn);
    return function () { var i = list.indexOf(fn); if (i >= 0) list.splice(i, 1); };
  }

  /** before a fragment is removed: close what belongs to it, run its teardowns, forget its UI.once marks */
  function detach(context) {
    var ctx = context || document;
    if (!ctx.querySelectorAll) return 0;
    var all = ctx === document;
    var inside = function (el) { return !!el && el.nodeType === 1 && (all || ctx === el || ctx.contains(el)); };
    emit('ui:detach', { context: ctx });   // first, while the content is still complete
    runBehaviors('detach', ctx);
    // popups that belong to this content (or sit inside it)
    if (menu && (all || inside(menu.el) || inside(menu.anchor) || inside(menu.scope))) closeMenu(false);
    modals.slice().reverse().forEach(function (m) { if (all || inside(m.el) || inside(m.owner)) m.close(null); });
    if (inside(tip.target) || inside(tip.pending)) hideTip();
    // teardowns registered on the elements: board, calendar, date picker, resizers, context menus, UI.onDetach
    var ran = 0;
    scoped('*', ctx).forEach(function (el) {
      claims.delete(el);
      var fns = teardowns.get(el);
      if (!fns) return;
      teardowns.delete(el);
      fns.slice().forEach(function (fn) {
        try { fn(el); ran++; } catch (e) { console.error('[UI] teardown failed:', e); }
      });
    });
    if (app && inside(app)) app = null;   // the shell goes with it; UI.attach adopts the next one
    return ran;
  }

  /* ---------- init ---------- */
  function init(options) {
    for (var k in options || {}) if (k !== 'strings' && k !== 'router' && k !== 'palette') cfg[k] = options[k];
    if (options && isPalette(options.palette)) {
      // the app's default palette — the user's own choice (stored by UI.setPalette / the swatches) still wins
      defaultPalette = options.palette;
      var p = storedPalette() || defaultPalette;
      if (p !== palette) { palette = p; applyPalette(); emit('ui:palette', { palette: p }); }
    }
    if (options && options.strings) for (var s in options.strings) STR[s] = options.strings[s];
    if (options && options.router) setRouter(options.router);
    inited = true;
    applyTheme();
    start();
    attach(document);
  }

  window.UI = {
    init: init,
    /** fragments: UI.attach(el) after inserting, see the lifecycle section above */
    attach: attach,
    detach: detach,
    behavior: behavior,
    once: once,
    onDetach: onDetach,
    /** texts of the kit itself: UI.setStrings({ cancel: 'Anuluj' }), UI.t('cancel'), UI.t('minAgo', { n: 5 }) */
    setStrings: setStrings,
    t: t,
    strings: STR,
    /** the built-in English table, frozen — every key the kit uses (a Drupal module can build its translations from it) */
    defaultStrings: DEFAULT_STR,
    /** formatting locale / default currency for UI.fmt, e.g. UI.setLocale({ locale: 'pl-PL', currency: 'PLN' }) */
    setLocale: function (o) {
      o = o || {};
      if (o.locale) cfg.locale = o.locale;
      if (o.currency) cfg.currency = o.currency;
    },
    get locale() { return cfg.locale; },
    get currency() { return cfg.currency; },
    setTheme: setTheme,
    get theme() { return theme; },
    /** light ⇄ dark from what is on screen (force: true = dark, false = light); sets an explicit theme */
    toggleDark: toggleDark,
    /** colour palettes: 'lagoon' | 'sage' | 'berry' | 'indigo' | 'cobalt' (buttons with data-set-palette do the same) */
    setPalette: setPalette,
    get palette() { return palette; },
    /** the page's default palette (<html data-default-palette> or UI.init({ palette }), else 'lagoon') */
    get defaultPalette() { return defaultPalette; },
    palettes: PALETTES,
    paletteButtons: paletteButtons,
    get mode() { return state.mode; },
    get view() { return state.view; },
    toggleSidebar: toggleSidebar,
    toggleInspector: toggleInspector,
    openInspector: function () { toggleInspector(true); },
    closeInspector: function () { toggleInspector(false); },
    get inspectorOpen() { return state.inspector; },
    modal: modal,
    confirm: confirmDialog,
    get modalOpen() { return modals.length > 0; },
    closeModals: closeModals,
    popover: popover,
    board: board,
    /** week / day / resources / agenda calendar — see the Calendar section above */
    calendar: calendar,
    /** month grid date picker with keyboard navigation */
    datePicker: datePicker,
    /** replace the account menu items; strings insert built-in rows: 'dark-switch', 'palettes', 'theme' */
    setAccountMenu: function (items) { accountItems = items; },
    showDetail: showDetail,
    showList: showList,
    selectRow: selectRow,
    selectTab: selectTab,
    menu: showMenu,
    closeMenu: function () { closeMenu(); },
    get menuOpen() { return !!menu; },
    contextMenu: contextMenu,
    hud: hud,
    announce: announce,
    /** router: UI.route(fn), UI.navigate('path/x', { params, replace }), UI.currentRoute → { path, parts, params };
        UI.setRouter({ mode: 'hash' | 'path', base }), UI.router, UI.href(path, params) */
    route: route,
    navigate: navigate,
    get currentRoute() { return currentRoute; },
    setRouter: setRouter,
    get router() { return { mode: router.mode, base: router.base }; },
    href: href,
    icon: icon,
    /** register extra Lucide icons by kebab-case name: UI.addIcons(['rocket']) → UI.icon('rocket')
        (needs Lucide + ui/icons.js; with a static sprite, the icons must already be in it) */
    addIcons: function (names) { if (window.UI_addIcons) window.UI_addIcons(names); },
    esc: esc,
    load: load,
    save: save,
    store: store,
    uid: uid,
    initials: initials,
    avatar: avatar,
    fmt: fmt,
    debounce: debounce
  };

  applyTheme();
  applyPalette();
  if (document.body) bindGlobal(); else document.addEventListener('DOMContentLoaded', bindGlobal);
})();
