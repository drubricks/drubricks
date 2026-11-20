/**
 * @file
 * drubricks:calendar — UI.calendar from data-options. Callbacks become a
 * cancellable "drubricks:calendar" event; editable calendars then update
 * their own copy of the events (save them on the server in your listener).
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksCalendar = {
    attach(context) {
      once('drubricks-calendar', '[data-drubricks-calendar]', context).forEach((root) => {
        const o = Kit.options(root);
        const editable = root.dataset.drubricksCalendar === 'editable';
        const C = UI.calendar;
        let events = (o.events || []).slice();
        let date = o.date || C.ymd(new Date());
        let view = o.view || 'week';
        const fire = (type, detail) => Kit.emit(root, 'drubricks:calendar', Object.assign({ type, calendar: cal }, detail));
        const cal = UI.calendar(root.querySelector('[data-cal]'), Object.assign({}, o, {
          events,
          date,
          view,
          onOpen: (id) => fire('open', { id }),
          onCreate: (range) => {
            if (!fire('create', { range }) || !editable) return;
            events = events.concat(Object.assign({ id: UI.uid('e'), title: Drupal.t('New event') }, range));
            cal.update(events);
          },
          onMove: (id, range) => {
            if (!fire('move', { id, range }) || !editable) return;
            events = events.map((e) => (String(e.id) === String(id) ? Object.assign({}, e, range) : e));
            cal.update(events);
          },
          onResize: (id, range) => {
            if (!fire('resize', { id, range }) || !editable) return;
            events = events.map((e) => (String(e.id) === String(id) ? Object.assign({}, e, range) : e));
            cal.update(events);
          },
          onDate: (d) => { date = d; fire('date', { date: d }); },
          onResource: (id) => fire('resource', { id }),
        }));
        root.drubricksCalendar = cal;
        root.addEventListener('click', (e) => {
          const v = e.target.closest('[data-cal-view]');
          if (v) {
            view = v.dataset.calView;
            root.querySelectorAll('[data-cal-view]').forEach((b) => { b.classList.toggle('active', b === v); b.setAttribute('aria-pressed', String(b === v)); });
            cal.set({ view });
            return;
          }
          const nav = e.target.closest('[data-cal-nav]');
          if (nav) {
            const n = Number(nav.dataset.calNav);
            const span = view === 'week' || view === 'agenda' ? 7 : 1;
            date = n ? C.ymd(C.addDays(date, n * span)) : C.ymd(new Date());
            cal.set({ date });
          }
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
