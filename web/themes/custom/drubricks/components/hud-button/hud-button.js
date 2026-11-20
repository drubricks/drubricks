/**
 * @file
 * drubricks:hud-button — UI.hud(message [, { undo }]).
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksHudButton = {
    attach(context) {
      once('drubricks-hud-button', '[data-drubricks-hud]', context).forEach((btn) => {
        btn.addEventListener('click', () => {
          const undo = btn.dataset.undo;
          UI.hud(btn.dataset.drubricksHud, undo ? { undo: () => { Kit.emit(btn, 'drubricks:undo', {}); UI.hud(undo); } } : undefined);
        });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
