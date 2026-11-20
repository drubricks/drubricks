/**
 * @file
 * drubricks:popover-button — UI.popover with the markup of the next <template>.
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksPopoverButton = {
    attach(context) {
      once('drubricks-popover-button', '[data-drubricks-popover]', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const tpl = btn.nextElementSibling;
          const o = Kit.options(btn);
          UI.popover(btn, tpl && tpl.matches('template') ? tpl.innerHTML : '', { width: o.width || 260, label: o.label || undefined });
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
