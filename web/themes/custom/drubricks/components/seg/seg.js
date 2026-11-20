/**
 * @file
 * drubricks:seg — switch .active / aria-pressed and fire "drubricks:seg"
 * ({ value }) on the .seg. Theme buttons (data-set-theme) are the kit's.
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksSeg = {
    attach(context) {
      once('drubricks-seg', '[data-drubricks-seg]', context).forEach((seg) => {
        seg.addEventListener('click', (e) => {
          const b = e.target.closest('button');
          if (!b || b.hasAttribute('data-set-theme') || !seg.contains(b)) return;
          if (!Kit.emit(seg, 'drubricks:seg', { value: b.dataset.value ?? b.textContent.trim() })) return;
          seg.querySelectorAll('button').forEach((x) => { x.classList.toggle('active', x === b); x.setAttribute('aria-pressed', String(x === b)); });
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
