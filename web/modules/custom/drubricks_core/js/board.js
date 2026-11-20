/**
 * @file
 * Board view island (any entity type): UI.board + saving moves with PATCH.
 *
 * The server renders data-drubricks-board-island='{lanes, items, endpoint, field,
 * order, sum, filter, key}' (BoardController). A move is shown at once and
 * saved; if the server refuses it (validation, access, network) the board
 * goes back and shows the server's message. A card opens in the right-side
 * panel (?open=ID), never on another page. The filter select above the
 * board narrows the cards without a request and keeps ?<field>= in the URL.
 * UI.detach (when the pane is swapped) destroys the board.
 */
((Drupal, drupalSettings, once) => {
  let token = null;
  const csrf = async () => {
    token ??= await fetch(drupalSettings.drubricks.csrfTokenUrl, { credentials: 'same-origin' }).then((r) => r.text());
    return token;
  };

  const card = (t) => {
    const { esc, icon, avatar } = window.UI;
    return `${t.key ? `<div class="bc-key">${esc(t.key)}</div>` : ''}
      <div class="bc-title">${esc(t.title)}</div>
      ${t.subtitle ? `<div class="bc-sub">${esc(t.subtitle)}</div>` : ''}
      <div class="bc-foot">${t.icon ? icon(t.icon) : ''}${t.meta ? `<span class="bc-meta">${esc(t.meta)}</span>` : ''}<span class="grow"></span>${t.avatar ? avatar(t.avatar, { size: 'sm' }) : ''}</div>`;
  };

  Drupal.behaviors.drubricksBoardIsland = {
    attach(context) {
      if (typeof window.UI !== 'object') return;
      once('drubricks-board-island', '[data-drubricks-board-island]', context).forEach((el) => {
        const cfg = JSON.parse(el.dataset.drubricksBoardIsland || '{}');
        let all = cfg.items || [];
        const pane = el.closest('#drubricks-detail') || document;
        const filterEl = pane.querySelector('[data-board-filter]');
        let filter = filterEl ? Number(filterEl.value) : 0;
        const visible = () => (filter ? all.filter((t) => t.filter === filter) : all);
        const endpoint = (id) => cfg.endpoint.replace(/\/0$/, `/${id}`);
        const money = (n) => (window.UI.fmt && window.UI.fmt.money ? window.UI.fmt.money(n) : String(n));

        const board = window.UI.board(el, {
          lanes: cfg.lanes,
          items: visible(),
          laneOf: (t) => t.lane,
          orderOf: cfg.order ? (t) => t.order : undefined,
          card,
          laneMeta: cfg.sum ? (lane, items) => money(items.reduce((s, t) => s + (t.sum || 0), 0)) : undefined,
          collapsedKey: cfg.key,
          // A card opens in the right-side panel; the board stays (?open=ID).
          onOpen: (id) => {
            const url = new URL(window.location.href);
            url.searchParams.set('open', String(id));
            const request = new URL(url);
            request.searchParams.set('_wrapper_format', 'drupal_htmx');
            window.htmx.ajax('GET', request.pathname + request.search, {
              target: '#drubricks-inspector',
              select: '#drubricks-inspector',
              swap: 'outerHTML ignoreTitle:true',
            }).then(() => {
              window.history.replaceState(window.history.state, '', url);
              window.UI.openInspector?.();
            });
          },
          onMove: async (id, toLane, index) => {
            const before = all.map((t) => ({ ...t }));
            const item = all.find((t) => t.id === Number(id));
            if (!item) return;
            if (!item.editable) {
              board.update(visible());
              window.UI.hud(Drupal.t('You cannot change this record.'));
              return;
            }
            const body = { [cfg.field]: toLane };
            item.lane = toLane;
            if (cfg.order) {
              const lane = visible().filter((t) => t.lane === toLane && t.id !== item.id).sort((a, b) => a.order - b.order);
              lane.splice(index, 0, item);
              lane.forEach((t, i) => { t.order = i; });
              body[cfg.order] = index;
            }
            board.update(visible());
            try {
              const response = await fetch(endpoint(item.id), {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': await csrf() },
                body: JSON.stringify(body),
              });
              const data = await response.json().catch(() => ({}));
              if (!response.ok) throw new Error(data.message || Drupal.t('The change could not be saved.'));
              Object.assign(item, data.data || {});
              board.update(visible());
              Drupal.drubricks?.refreshSidebar();
            }
            catch (e) {
              all = before;
              board.update(visible());
              window.UI.hud(e.message);
            }
          },
        });

        if (filterEl) {
          filterEl.addEventListener('change', () => {
            filter = Number(filterEl.value);
            board.update(visible());
            const url = new URL(window.location.href);
            if (filter) url.searchParams.set(cfg.filter, String(filter));
            else url.searchParams.delete(cfg.filter);
            window.history.replaceState(window.history.state, '', url);
            window.UI.announce(Drupal.formatPlural(visible().length, '1 card', '@count cards'));
          });
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
