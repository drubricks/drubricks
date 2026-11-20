/**
 * @file
 * Dropbuttons (core's #type dropbutton, e.g. the operations of a View) as
 * kit controls: the first link stays a button, the arrow opens the other
 * links in the kit menu (UI.menu: keyboard, focus, Escape).
 *
 * Replaces core's misc/dropbutton/dropbutton.js in this theme
 * (libraries-override in drubricks.info.yml). A menu item clicks the
 * original link, so its HTMX / modal attributes keep working.
 */
((Drupal, once) => {
  Drupal.behaviors.drubricksDropbutton = {
    attach(context) {
      once('drubricks-dropbutton', '.dropbutton-wrapper', context).forEach((wrapper) => {
        const items = [...wrapper.querySelectorAll('.dropbutton > li')];
        items.forEach((li) => li.classList.add('dropbutton-action'));
        if (items.length < 2) {
          return;
        }
        wrapper.classList.add('dropbutton-multiple');
        const others = items.slice(1);
        others.forEach((li) => li.classList.add('secondary-action'));

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'dropbutton-arrow-btn';
        toggle.setAttribute('aria-haspopup', 'menu');
        toggle.setAttribute('aria-expanded', 'false');
        // Not core's "List additional actions": its Polish translation reads
        // "show more columns".
        const label = Drupal.t('More actions');
        toggle.title = label;
        toggle.innerHTML = `<span class="visually-hidden">${label}</span>`;
        const li = document.createElement('li');
        li.className = 'dropbutton-toggle';
        li.append(toggle);
        items[0].after(li);

        toggle.addEventListener('click', () => {
          const menu = others.map((item) => {
            const link = item.querySelector('a, button, input[type="submit"]');
            return link && {
              label: (link.textContent || link.value || '').trim(),
              danger: item.dataset.actionKey === 'delete',
              run: () => link.click(),
            };
          }).filter(Boolean);
          // The kit may load after this file (core's library order): read
          // it when the menu opens.
          const UI = window.UI;
          if (!UI || !UI.menu) {
            return;
          }
          toggle.setAttribute('aria-expanded', 'true');
          UI.menu(menu, {
            anchor: toggle,
            align: 'right',
            onClose: () => toggle.setAttribute('aria-expanded', 'false'),
          });
        });
      });
    },
  };
})(Drupal, once);
