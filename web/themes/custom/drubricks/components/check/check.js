/**
 * @file
 * drubricks:check — toggle the round checkbox and fire "drubricks:check" ({ checked, value }).
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksCheck = {
    attach(context) {
      once('drubricks-check', 'button.check[data-drubricks-check]', context).forEach((b) => {
        b.addEventListener('click', () => {
          const checked = !b.classList.contains('on');
          if (!Kit.emit(b, 'drubricks:check', { checked, value: b.dataset.drubricksCheck })) return;
          b.classList.toggle('on', checked);
          b.setAttribute('aria-pressed', String(checked));
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
