/**
 * @file
 * drubricks:data-table — sort in the browser (table[data-drubricks-sort]) and
 * open rows with a URL (tr[data-href]) on click or Enter.
 */
((Drupal, once, UI) => {
  if (!UI) return;
  Drupal.behaviors.drubricksDataTable = {
    attach(context) {
      once('drubricks-data-table', '.data-table', context).forEach((table) => {
        const body = table.tBodies[0];
        if (table.hasAttribute('data-drubricks-sort') && table.tHead) {
          table.tHead.addEventListener('click', (e) => {
            const th = e.target.closest('th[aria-sort]');
            if (!th || !e.target.closest('button')) return;
            const dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
            table.querySelectorAll('th[aria-sort]').forEach((x) => x.setAttribute('aria-sort', x === th ? dir : 'none'));
            const text = (tr) => (tr.cells[th.cellIndex] ? tr.cells[th.cellIndex].textContent.trim() : '');
            const value = (tr) => (th.classList.contains('num') ? Number(text(tr).replace(/[^0-9.-]/g, '')) : text(tr).toLowerCase());
            Array.from(body.rows)
              .sort((a, b) => (value(a) > value(b) ? 1 : value(a) < value(b) ? -1 : 0) * (dir === 'ascending' ? 1 : -1))
              .forEach((tr) => body.appendChild(tr));
          });
        }
        const open = (tr, e) => {
          if (!tr || !tr.dataset.href || (e && e.target.closest('a, button, input, select, textarea'))) return;
          window.location.href = tr.dataset.href;
        };
        body.addEventListener('click', (e) => open(e.target.closest('tr[data-href]'), e));
        body.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('tr[data-href]')) open(e.target); });
      });
    },
  };
})(Drupal, once, window.UI);
