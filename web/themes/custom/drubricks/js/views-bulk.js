/**
 * @file
 * Bulk form of a View (core's views_form + tableselect): the action bar says
 * how many rows are selected and stays inactive until one is.
 *
 * Added to core/drupal.tableselect by libraries-extend (drubricks.info.yml).
 */
((Drupal, once) => {
  Drupal.behaviors.drubricksViewsBulk = {
    attach(context) {
      once('drubricks-views-bulk', 'form:has(input[name*="_bulk_form["])', context).forEach((form) => {
        const bar = form.querySelector('[data-drupal-selector="edit-header"] > .form-wrapper');
        if (!bar) {
          return;
        }
        const count = document.createElement('span');
        count.className = 'drubricks-bulk-count';
        count.setAttribute('aria-live', 'polite');
        bar.prepend(count);
        const update = () => {
          const n = form.querySelectorAll('input[name*="_bulk_form["]:checked').length;
          bar.dataset.drubricksBulk = String(n);
          count.textContent = n ? Drupal.formatPlural(n, '1 selected', '@count selected') : Drupal.t('Select rows for bulk actions');
        };
        // tableselect's "select all" sets .checked without a change event on
        // each row: listen on the form and after clicks.
        form.addEventListener('change', update);
        form.addEventListener('click', () => setTimeout(update));
        update();
      });
    },
  };
})(Drupal, once);
