/**
 * @file
 * drubricks:menu-button — opens UI.menu under the button.
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksMenuButton = {
    attach(context) {
      once('drubricks-menu-button', '[data-drubricks-menu]', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const o = Kit.options(btn);
          btn.setAttribute('aria-expanded', 'true');
          UI.menu(Kit.menuItems(o.items, btn), {
            anchor: btn,
            align: o.align === 'right' ? 'right' : undefined,
            width: o.width || undefined,
            label: o.label || undefined,
            onClose: () => btn.setAttribute('aria-expanded', 'false'),
          });
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
