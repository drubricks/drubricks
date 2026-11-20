/**
 * @file
 * drubricks:context-menu — UI.contextMenu on the region (off() on UI.detach).
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksContextMenu = {
    attach(context) {
      once('drubricks-context-menu', '[data-drubricks-context-menu]', context).forEach((el) => {
        UI.contextMenu(el, () => Kit.menuItems(Kit.options(el).items, el));
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
