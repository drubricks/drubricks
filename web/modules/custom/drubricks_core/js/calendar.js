/**
 * @file
 * Calendar view island (any entity type): UI.calendar + saving changes.
 *
 * The server renders data-drubricks-calendar-island='{events, resources, view, date,
 * hours, step, endpoint, addUrl, addTitle, key}' (CalendarController).
 * - Drag / resize an event: shown at once, saved with PATCH; if the server
 *   refuses (e.g. a double booking), the event snaps back with the message.
 * - Drag on empty time (or Enter on the slot cursor): the add form opens in
 *   a modal, prefilled with ?start=&end=&resource=.
 * - Click / Enter on an event: the record opens in the right-side panel
 *   (?open=ID) — the calendar stays (AGENTS.md rule).
 * - Toolbar: previous / today / next, Day / Week / Resources / Agenda;
 *   ?date= and ?view= keep the place in the URL.
 * UI.detach (when the pane is swapped) destroys the calendar.
 */
((Drupal, drupalSettings, once) => {
  let token = null;
  const csrf = async () => {
    token ??= await fetch(drupalSettings.drubricks.csrfTokenUrl, { credentials: 'same-origin' }).then((r) => r.text());
    return token;
  };

  const openModal = (url, title) => {
    const a = document.createElement('a');
    a.href = url;
    a.dataset.drubricksModal = url;
    a.dataset.drubricksModalTitle = title || '';
    a.hidden = true;
    document.body.appendChild(a);
    a.click();
    a.remove();
  };

  Drupal.behaviors.drubricksCalendarIsland = {
    attach(context) {
      if (typeof window.UI !== 'object') return;
      once('drubricks-calendar-island', '[data-drubricks-calendar-island]', context).forEach((el) => {
        const cfg = JSON.parse(el.dataset.drubricksCalendarIsland || '{}');
        const C = window.UI.calendar;
        let events = cfg.events || [];
        let view = cfg.view || 'week';
        let date = cfg.date || C.ymd(new Date());
        const pane = el.closest('#drubricks-detail') || document;
        const rangeEl = pane.querySelector('[data-cal-range]');

        const keepUrl = () => {
          const url = new URL(window.location.href);
          url.searchParams.set('date', date);
          url.searchParams.set('view', view);
          window.history.replaceState(window.history.state, '', url);
        };
        const label = () => {
          if (!rangeEl || !cal.range) return;
          const fmt = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric' });
          const start = C.parse(cal.range.start);
          const end = C.addDays(C.parse(cal.range.end), -1);
          rangeEl.textContent = view === 'day' || view === 'resources' ? fmt.format(start) : `${fmt.format(start)} – ${fmt.format(end)}`;
        };
        const markView = () => pane.querySelectorAll('[data-cal-view]').forEach((b) => {
          b.classList.toggle('active', b.dataset.calView === view);
          b.setAttribute('aria-pressed', String(b.dataset.calView === view));
        });

        const save = async (id, change) => {
          const before = events.map((e) => ({ ...e }));
          const ev = events.find((e) => String(e.id) === String(id));
          if (!ev) return;
          Object.assign(ev, change);
          cal.update(events);
          try {
            const response = await fetch(cfg.endpoint.replace(/\/0$/, `/${id}`), {
              method: 'PATCH',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': await csrf() },
              body: JSON.stringify(change),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || Drupal.t('The change could not be saved.'));
            Object.assign(ev, data.data || {});
            cal.update(events);
            window.UI.announce(Drupal.t('Saved.'));
            Drupal.drubricks?.refreshSidebar();
          }
          catch (e) {
            events = before;
            cal.update(events);
            window.UI.hud(e.message);
          }
        };

        const cal = window.UI.calendar(el, {
          view,
          date,
          events,
          resources: cfg.resources || [],
          hours: cfg.hours,
          step: cfg.step || 15,
          startHour: Math.max(0, (cfg.hours?.start ?? 8) - 1),
          endHour: Math.min(24, (cfg.hours?.end ?? 18) + 2),
          label: document.title,
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
          onMove: (id, { start, end, resource }) => save(id, resource != null ? { start, end, resource: String(resource) } : { start, end }),
          onResize: (id, { start, end }) => save(id, { start, end }),
          onCreate: ({ start, end, resource }) => {
            if (!cfg.addUrl) return;
            const url = new URL(cfg.addUrl, window.location.href);
            url.searchParams.set('start', start);
            url.searchParams.set('end', end);
            if (resource != null) url.searchParams.set('resource', String(resource));
            openModal(url.pathname + url.search, cfg.addTitle);
          },
          onDate: (ymd) => { date = ymd; keepUrl(); label(); },
        });

        pane.querySelectorAll('[data-cal-nav]').forEach((button) => button.addEventListener('click', () => {
          const step = Number(button.dataset.calNav);
          const days = view === 'day' || view === 'resources' ? 1 : 7;
          date = step === 0 ? C.ymd(new Date()) : C.ymd(C.addDays(C.parse(date), step * days));
          cal.update(events, { date });
          keepUrl();
          label();
        }));
        pane.querySelectorAll('[data-cal-view]').forEach((button) => button.addEventListener('click', () => {
          view = button.dataset.calView;
          cal.update(events, { view });
          markView();
          keepUrl();
          label();
        }));
        markView();
        label();
      });
    },
  };
})(Drupal, drupalSettings, once);
