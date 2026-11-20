/**
 * @file
 * drubricks:date-picker — UI.datePicker on [data-picker]; the kit registers
 * its destroy() with UI.onDetach, so HTMX swaps clean it up.
 */
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksDatePicker = {
    attach(context) {
      once('drubricks-date-picker', '[data-drubricks-date-picker]', context).forEach((root) => {
        const input = root.querySelector('input[type=hidden]');
        const o = Kit.options(root);
        const picker = UI.datePicker(root.querySelector('[data-picker]'), Object.assign({}, o, {
          onSelect: (d) => {
            if (!Kit.emit(root, 'drubricks:date', { date: d, picker })) return;
            if (input) {
              input.value = d;
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            picker.set({ date: d });
          },
        }));
        root.drubricksPicker = picker;
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
