/**
 * @file
 * drubricks:board — UI.board from data-options with an escaped card template.
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  const esc = (s) => UI.esc(s == null ? '' : String(s));
  const color = (c) => (/^[a-z]+$/.test(c || '') ? `var(--${c})` : 'var(--accent)');
  const card = (x) => {
    const tags = (x.tags || []).map((t) => `<span class="tag" style="--c:${color(t.color || 'purple')}">${esc(t.label)}</span>`).join('');
    const prio = ['high', 'medium', 'low'].includes(x.prio) ? UI.icon('prio-' + x.prio, 'prio ' + x.prio) : '';
    const value = x.value != null ? x.value : (x.points != null ? Drupal.t('@n pts', { '@n': x.points }) : '');
    const who = x.assignee ? UI.avatar(x.assignee.name || '', { size: 'xs', color: x.assignee.color }) : '';
    return (x.key ? `<div class="bc-key">${esc(x.key)}</div>` : '')
      + `<div class="bc-title">${esc(x.title)}</div>`
      + (x.sub ? `<div class="bc-sub">${esc(x.sub)}</div>` : '')
      + (tags ? `<div class="bc-tags">${tags}</div>` : '')
      + ((prio || value || who) ? `<div class="bc-foot">${prio}${value !== '' ? `<span class="bc-value">${esc(value)}</span>` : ''}<span class="spacer"></span>${who}</div>` : '');
  };
  Drupal.behaviors.drubricksBoard = {
    attach(context) {
      once('drubricks-board', '[data-drubricks-board]', context).forEach((root) => {
        const o = Kit.options(root);
        const editable = root.dataset.drubricksBoard === 'editable';
        let items = (o.items || []).slice();
        const opts = {
          lanes: o.lanes || [],
          items,
          laneOf: (x) => x.lane,
          card,
          collapsedKey: o.collapsedKey || undefined,
          onOpen: (id) => Kit.emit(root, 'drubricks:board', { type: 'open', id, board }),
          onMove: (id, lane, index, fromLane) => {
            if (!Kit.emit(root, 'drubricks:board', { type: 'move', id, lane, index, fromLane, board }) || !editable) return;
            const it = items.find((x) => String(x.id) === String(id));
            items.splice(items.indexOf(it), 1);
            it.lane = lane;
            const inLane = items.filter((x) => x.lane === lane);
            items.splice(index < inLane.length ? items.indexOf(inLane[index]) : items.length, 0, it);
            board.update(items);
          },
        };
        if (o.laneMeta === 'points') opts.laneMeta = (lane, inLane) => esc(Drupal.t('@n pts', { '@n': inLane.reduce((s, x) => s + (Number(x.points) || 0), 0) }));
        if (o.create) {
          opts.onCreate = (lane, title) => {
            if (!Kit.emit(root, 'drubricks:board', { type: 'create', lane, title, board }) || !editable) return;
            items.push({ id: UI.uid('card'), lane, title });
            board.update(items);
          };
        }
        const board = UI.board(root.querySelector('[data-board]'), opts);
        root.drubricksBoard = board;
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
