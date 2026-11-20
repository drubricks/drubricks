/**
 * @file
 * drubricks:modal-button — UI.modal / UI.confirm from data-options; the body
 * is the markup of the next <template>. The popup is owned by the button, so
 * UI.detach of its fragment closes it.
 */
((Drupal, drupalSettings, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksModalButton = {
    attach(context) {
      once('drubricks-modal-button', '[data-drubricks-modal-button]', context).forEach((btn) => {
        btn.addEventListener('click', async () => {
          const o = Kit.options(btn);
          if (o.confirm) {
            const ok = await UI.confirm({ title: o.title, message: o.message, confirmLabel: o.confirmLabel || undefined, destructive: !!o.destructive });
            Kit.emit(btn, 'drubricks:modal', { value: ok });
            return;
          }
          const tpl = btn.nextElementSibling;
          let chosen = null;
          const m = UI.modal({
            title: o.title || undefined,
            message: o.message || undefined,
            icon: o.icon || undefined,
            width: o.width || undefined,
            closeButton: !!o.closeButton,
            draggable: !!o.draggable,
            label: o.title ? undefined : btn.textContent.trim(),
            owner: btn,
            html: tpl && tpl.matches('template') ? tpl.innerHTML : '',
            actions: (o.actions && o.actions.length ? o.actions : [{ label: Drupal.t('OK'), primary: true }]).map((a) => ({
              label: a.label,
              primary: !!a.primary,
              destructive: !!a.destructive,
              value: a.value ?? a.label,
              run: () => {
                chosen = a.value ?? a.label;
                if (a.hud) UI.hud(a.hud);
              },
            })),
            onClose: (result) => Kit.emit(btn, 'drubricks:modal', { value: result == null ? chosen : result }),
          });
          // Kit widgets and component behaviors in the popup content.
          Drupal.attachBehaviors(m.body, drupalSettings);
        });
      });
    },
  };
})(Drupal, drupalSettings, once, window.UI, window.DrubricksKit);
