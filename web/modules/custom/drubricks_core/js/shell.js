/**
 * @file
 * Drubricks shell: the UI kit inside Drupal, navigation without reloads.
 *
 * - UI.init() as soon as this script runs (options from
 *   drupalSettings.drubricks.ui), so Drupal behaviors of the components
 *   (their libraries depend on this one) always find the kit initialised;
 *   UI.attach() on every fragment Drupal attaches behaviors to, UI.detach()
 *   on exactly the element an HTMX swap replaces.
 * - Account menu items (core's "account" menu) → UI.setAccountMenu().
 * - After an HTMX swap: active sidebar item, selected row, title, focus,
 *   screen reader announcement, scroll position, narrow-mode pane.
 * - Forms in UI.modal ([data-drubricks-modal]) as a window (closeButton,
 *   draggable); "drubricks:saved" /
 *   "drubricks:deleted" close it, show a HUD message and refresh counters.
 * - Drupal messages in HTMX responses → core's Drupal.Message (the theme shows them).
 * - 4xx / 5xx in a fragment → an error state in the pane; network errors → HUD.
 */
((Drupal, drupalSettings, once, htmx) => {
  const settings = () => drupalSettings.drubricks || {};
  const hasUI = () => typeof window.UI === 'object';

  /* ---------- kit lifecycle ---------- */

  // The kit (drubricks/global, a dependency of this library) is loaded:
  // initialise it now, before any behavior runs.
  if (hasUI()) {
    // The colour palette is a site setting (theme settings, "Default UI kit
    // palette" → <html data-default-palette>), not a user choice: forget one
    // picked in this browser before.
    try {
      window.localStorage.removeItem('ui.palette');
    }
    catch (e) {
      // Storage blocked: the kit falls back to the default palette anyway.
    }
    window.UI.init({ palette: document.documentElement.dataset.defaultPalette, ...(settings().ui || {}) });
  }

  Drupal.behaviors.drubricksShell = {
    attach(context) {
      if (!hasUI()) {
        return;
      }
      if (context === document) {
        syncNavigation({ level: 'content' });
      }
      // Idempotent: the kit remembers the elements it has wired up.
      window.UI.attach(context);
      once('drubricks-account', '[data-drubricks-account-menu]', context).forEach(setAccountMenu);
      once('drubricks-inspector-open', '[data-drubricks-open]', context).forEach(() => window.UI.openInspector());
    },
    // No UI.detach() here: core triggers "unload" on the PARENT of the
    // swapped element (e.g. .app when only the sidebar is refreshed), which
    // would destroy live components (a board, a calendar) that stay on the
    // page. The exact element is detached in htmx:before:swap below.
  };

  // Tear down kit components only in the element that is being replaced.
  document.addEventListener('htmx:before:swap', (e) => {
    const ctx = e.detail && e.detail.ctx;
    const target = ctx && ctx.target;
    if (!hasUI() || !window.UI.detach || !target || target.nodeType !== 1) return;
    const style = String(ctx.swap || '').split(/\s/)[0];
    if (/^(outerHTML|innerHTML|outerMorph|innerMorph|textContent)$/.test(style) || style === '') {
      window.UI.detach(target);
    }
  });

  /**
   * The kit's scheme switch (.seg of [data-set-theme] buttons) for the menu.
   *
   * The kit's own "theme" row also offers the colour palettes, which are a
   * site setting here; its buttons and their state are the kit's.
   */
  function schemeRow() {
    const { UI } = window;
    const label = UI.esc(UI.t('theme'));
    const button = (value, inner, title) => {
      const on = UI.theme === value;
      return `<button type="button" data-set-theme="${value}" title="${UI.esc(title)}" aria-label="${UI.esc(title)}" aria-pressed="${on}"${on ? ' class="active"' : ''}>${inner}</button>`;
    };
    return `<div class="menu-row"><span>${label}</span><div class="seg" role="group" aria-label="${label}">${
      button('light', UI.icon('sun'), UI.t('light'))}${
      button('dark', UI.icon('moon'), UI.t('dark'))}${
      button('auto', UI.esc(UI.t('auto')), UI.t('matchSystem'))}</div></div>`;
  }

  function setAccountMenu(button) {
    let items = [];
    try {
      items = JSON.parse(button.dataset.drubricksAccountMenu || '[]');
    }
    catch (e) {
      return;
    }
    const menu = items.map((item) => (item === '-' ? '-' : {
      label: item.label,
      icon: item.icon,
      danger: !!item.danger,
      run: () => { if (item.href) window.location.href = item.href; },
    }));
    // Colour scheme (Light / Dark / Auto = the system setting, the default),
    // above the separator before "Log out". A getter: the row is built each
    // time the menu opens, with the current choice pressed.
    const separator = menu.lastIndexOf('-');
    menu.splice(separator === -1 ? menu.length : separator, 0, '-', { get custom() { return schemeRow(); } });
    window.UI.setAccountMenu(menu);
  }

  /* ---------- state after a swap ---------- */

  const levelOf = (el) => {
    const id = el && el.id;
    if (id === 'drubricks-detail') return 'detail';
    if (id === 'drubricks-inspector') return 'inspector';
    if (id === 'drubricks-list') return 'list';
    if (id === 'drubricks-sidebar') return 'sidebar';
    return 'content'; // #drubricks-app
  };

  function syncNavigation({ level }) {
    const path = window.location.pathname;
    // Active sidebar item: the longest href that prefixes the current path.
    let best = null;
    document.querySelectorAll('.sidebar .sb-item[href]').forEach((a) => {
      const href = new URL(a.href, window.location.href).pathname;
      if ((path === href || path.startsWith(href.replace(/\/$/, '') + '/')) && (!best || href.length > best.len)) {
        best = { a, len: href.length };
      }
    });
    document.querySelectorAll('.sidebar .sb-item').forEach((a) => {
      const on = best && a === best.a;
      a.classList.toggle('active', !!on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    // Selected row: the row whose URL is the current path (or its prefix).
    document.querySelectorAll('.column .row[href]').forEach((row) => {
      const href = new URL(row.href, window.location.href).pathname;
      const on = path === href || path.startsWith(href + '/');
      row.classList.toggle('selected', on);
      if (on) row.setAttribute('aria-current', 'true');
      else row.removeAttribute('aria-current');
    });

    if (!hasUI()) return;
    if (level === 'detail') {
      const pane = document.getElementById('drubricks-detail');
      if (pane) {
        pane.querySelector('.pane-body')?.scrollTo(0, 0);
        pane.focus({ preventScroll: true });
      }
      window.UI.showDetail?.();
    }
    else if (level === 'content') {
      window.UI.showList?.();
    }
  }

  // Main-content responses carry only the record title: add the site name
  // like a full page does ("Title | Site").
  const titleSuffix = () => {
    const site = settings().siteName;
    if (site && document.title && !document.title.endsWith(` | ${site}`)) {
      document.title = `${document.title} | ${site}`;
    }
  };
  // htmx sets the title late in the swap; watch the <title> itself.
  new MutationObserver(titleSuffix).observe(document.head, { subtree: true, childList: true, characterData: true });

  document.addEventListener('htmx:after:swap', (e) => {
    const ctx = e.detail && e.detail.ctx;
    const level = levelOf(ctx && ctx.target);
    if (level === 'sidebar' || level === 'list') {
      syncNavigation({ level: 'none' });
      return;
    }
    syncNavigation({ level });
    titleSuffix();
    if (level !== 'inspector' && hasUI()) {
      window.UI.announce(document.title);
    }
    if (level === 'inspector' && hasUI()) {
      window.UI.openInspector();
    }
  });

  /* ---------- loading state + double clicks ---------- */

  document.addEventListener('htmx:before:request', (e) => {
    const target = e.detail?.ctx?.target;
    if (target && target.nodeType === 1) {
      target.setAttribute('aria-busy', 'true');
      target.classList.add('drubricks-loading');
    }
  });
  document.addEventListener('htmx:finally:request', (e) => {
    const target = e.detail?.ctx?.target;
    if (target && target.nodeType === 1) {
      target.removeAttribute('aria-busy');
      target.classList.remove('drubricks-loading');
    }
  });

  /* ---------- messages from responses ---------- */

  // An HTMX swap keeps only the selected level, so the messages Drupal rendered
  // in the response go through core's Drupal.Message API (the theme's
  // Drupal.theme.message gives them the kit look).
  function showMessages(html) {
    if (!html || html.indexOf('messages') === -1) return;
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const messages = new Drupal.Message();
    doc.querySelectorAll('[data-drubricks-message], [data-drupal-messages] .messages').forEach((m) => {
      const text = (m.querySelector('.messages__content, .alert-main') || m).textContent.replace(/\s+/g, ' ').trim();
      const type = m.getAttribute('data-drubricks-message') || (m.className.match(/messages--(\w+)/) || [])[1] || 'status';
      if (text) messages.add(Drupal.checkPlain(text), { type });
    });
  }

  document.addEventListener('htmx:after:request', (e) => {
    const ctx = e.detail && e.detail.ctx;
    if (ctx && ctx.select && typeof ctx.text === 'string') {
      showMessages(ctx.text);
    }
  });

  /* ---------- errors ---------- */

  function errorState(status) {
    const t = Drupal.t;
    const text = status === 403 ? t('You do not have access to this page.')
      : status === 404 ? t('This page does not exist (any more).')
        : t('Something went wrong. Please try again.');
    return `<div class="empty-state drubricks-state" role="alert"><div>${Drupal.checkPlain(text)}</div></div>`;
  }

  document.addEventListener('htmx:response:error', (e) => {
    const ctx = e.detail && e.detail.ctx;
    if (!ctx) return;
    const status = ctx.response && ctx.response.status;
    ctx.swap = 'none';
    const target = ctx.target && ctx.target.nodeType === 1 ? ctx.target : null;
    const body = target && (target.matches('.pane-body') ? target : target.querySelector('.pane-body'));
    if (body && !target.closest('.modal')) {
      body.innerHTML = errorState(status);
    }
    else if (hasUI()) {
      window.UI.hud(Drupal.t('Error @status', { '@status': status }));
    }
  });

  document.addEventListener('htmx:error', () => {
    if (hasUI()) window.UI.hud(Drupal.t('Network error — check your connection.'));
  });

  /* ---------- forms in a modal ---------- */

  const withWrapper = (url) => {
    const u = new URL(url, window.location.href);
    u.searchParams.set('_wrapper_format', 'drupal_htmx');
    return u.pathname + u.search;
  };

  let modal = null;

  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-drubricks-modal]');
    if (opener && hasUI() && !e.metaKey && !e.ctrlKey && !e.shiftKey) {
      e.preventDefault();
      const url = opener.dataset.drubricksModal;
      modal = window.UI.modal({
        title: opener.dataset.drubricksModalTitle || opener.getAttribute('title') || '',
        width: 560,
        // A window: drag it by its title bar, close it with the X.
        closeButton: true,
        draggable: true,
        html: '<div class="drubricks-modal-slot"><div class="empty-line"><span class="spinner"></span></div></div>',
        // The form brings its own buttons.
        actions: [],
        onClose: () => { modal = null; },
      });
      const slot = modal.body.querySelector('.drubricks-modal-slot');
      htmx.ajax('GET', withWrapper(url), { target: slot, select: '#drubricks-detail form', swap: 'innerHTML ignoreTitle:true' })
        .then(() => {
          slot.querySelector('input:not([type=hidden]), select, textarea')?.focus();
        });
      return;
    }
    const close = e.target.closest('[data-drubricks-modal-close]');
    if (close && close.closest('.modal, [role="dialog"]') && modal) {
      e.preventDefault();
      modal.close();
    }
  });

  const refreshSidebar = () => {
    const sidebar = document.getElementById('drubricks-sidebar');
    if (!sidebar || !settings().sidebarUrl) return;
    htmx.ajax('GET', withWrapper(settings().sidebarUrl), {
      target: '#drubricks-sidebar',
      select: '#drubricks-sidebar',
      swap: 'outerHTML',
    });
  };

  // Pager links inside an app list swap only the list (URL kept in sync).
  document.addEventListener('click', (e) => {
    const link = e.target.closest('#drubricks-list .pager a[href]');
    if (!link || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    htmx.ajax('GET', withWrapper(link.href), {
      target: '#drubricks-list',
      select: '#drubricks-list',
      swap: 'outerHTML ignoreTitle:true',
    }).then(() => window.history.replaceState(window.history.state, '', link.href));
  });

  ['drubricks:saved', 'drubricks:deleted'].forEach((name) => {
    document.addEventListener(name, (e) => {
      if (modal) modal.close();
      if (e.detail && e.detail.message) new Drupal.Message().add(Drupal.checkPlain(e.detail.message), { type: 'status' });
      refreshSidebar();
    });
  });

  // Public helpers for app code (e.g. islands that save without a form).
  Drupal.drubricks = { refreshSidebar, syncNavigation };

  // View transitions for swaps, unless the user prefers less motion.
  if (htmx && htmx.config && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    htmx.config.transitions = true;
  }
})(Drupal, drupalSettings, once, htmx);
