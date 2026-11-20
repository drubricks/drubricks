/**
 * @file
 * Helpers for the JS-driven SDC components (components/*.js).
 *
 * window.DrubricksKit:
 * - options(el): the JSON in data-options (server-rendered, already escaped).
 * - emit(el, name, detail): fires a bubbling, cancellable CustomEvent and
 *   returns false when a listener called preventDefault() — module code uses
 *   it to save a move on the server or to reject it.
 * - menuItems(items, el): JSON items → UI.menu items. { url } navigates,
 *   { hud } shows a message, anything else fires "drubricks:menu" on el with
 *   { value, label }.
 */
((UI) => {
  const options = (el, attr = 'data-options') => {
    try {
      return JSON.parse(el.getAttribute(attr) || '{}') || {};
    }
    catch (e) {
      return {};
    }
  };
  const emit = (el, name, detail) => el.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable: true, detail }));
  const menuItems = (items, el) => (items || []).map((item) => {
    if (item === '-' || item.separator) return '-';
    if (item.header) return { header: item.header };
    return {
      label: item.label,
      icon: item.icon,
      shortcut: item.shortcut,
      danger: !!item.danger,
      disabled: !!item.disabled,
      run: () => {
        if (!emit(el, 'drubricks:menu', { value: item.value ?? item.label, label: item.label, item })) return;
        if (item.url) window.location.href = item.url;
        else if (item.hud && UI) UI.hud(item.hud);
      },
    };
  });
  window.DrubricksKit = { options, emit, menuItems };
})(window.UI);
