/**
 * @file
 * drubricks:month-grid — interactive day buttons: select one, fire "drubricks:date".
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksMonthGrid = {
    attach(context) {
      once('drubricks-month-grid', '[data-drubricks-month="interactive"]', context).forEach((grid) => {
        grid.addEventListener('click', (e) => {
          const day = e.target.closest('button.cal-day');
          if (!day || !Kit.emit(grid, 'drubricks:date', { date: day.dataset.date })) return;
          grid.querySelectorAll('.cal-day').forEach((d) => {
            d.classList.toggle('sel', d === day);
            if (d === day) d.setAttribute('aria-pressed', 'true');
            else d.removeAttribute('aria-pressed');
          });
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
