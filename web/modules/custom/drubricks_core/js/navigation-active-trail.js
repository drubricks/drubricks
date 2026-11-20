/**
 * @file
 * Admin sidebar (core's Navigation): the active trail of the current page.
 *
 * Core marks a sidebar link only when its URL is exactly the page's, and
 * opening a section (a popover) collapses all its submenus. Here the link
 * whose path is the longest prefix of the page's path (so also on sub-pages,
 * e.g. Manage fields of an entity type) is marked the way core marks it (the
 * classes and core's "toolbar-active-url" event), and its submenus open again
 * whenever its section opens: the section shows where the page is, like the
 * Gin admin theme's navigation does.
 */
((Drupal, once) => {
  // Core's event of Navigation's submenu buttons (toolbar-menu.js).
  const SET_TOGGLE = 'toolbar-menu-set-toggle';

  /**
   * The sidebar link of the current page: the longest matching path.
   *
   * @param {HTMLElement} sidebar
   *   The admin toolbar.
   *
   * @return {HTMLAnchorElement|null}
   *   The link, or null.
   */
  const currentLink = (sidebar) => {
    const path = window.location.pathname.replace(/\/$/, '');
    let found = null;
    let length = 0;
    sidebar.querySelectorAll('a.toolbar-menu__link, a.toolbar-button').forEach((link) => {
      const url = new URL(link.href, window.location.href);
      const linkPath = url.pathname.replace(/\/$/, '');
      if (url.origin === window.location.origin && linkPath
        && (path === linkPath || path.startsWith(`${linkPath}/`))
        && linkPath.length > length) {
        found = link;
        length = linkPath.length;
      }
    });
    return found;
  };

  /**
   * Opens the submenus that contain a link, outermost first.
   *
   * @param {HTMLElement} link
   *   The link.
   */
  const openTrail = (link) => {
    const triggers = [];
    let menu = link.closest('.toolbar-menu');
    while (menu) {
      const trigger = menu.previousElementSibling;
      if (trigger && trigger.matches('[data-toolbar-menu-trigger]')) {
        triggers.unshift(trigger);
      }
      menu = menu.parentElement ? menu.parentElement.closest('.toolbar-menu') : null;
    }
    triggers.forEach((trigger) => {
      trigger.dispatchEvent(new CustomEvent(SET_TOGGLE, { detail: { state: true } }));
    });
  };

  Drupal.behaviors.drubricksNavigationActiveTrail = {
    attach(context) {
      once('drubricks-navigation-trail', '.admin-toolbar', context).forEach((sidebar) => {
        // After core's Navigation behaviors have set the sidebar up.
        setTimeout(() => {
          const link = currentLink(sidebar);
          if (!link) {
            return;
          }
          if (!link.classList.contains('is-active')) {
            link.classList.add('current', 'is-active');
            link.dispatchEvent(new CustomEvent('toolbar-active-url', { bubbles: true }));
          }
          openTrail(link);
          // Opening a section collapses its submenus (core's sidebar.js):
          // open the trail of the current page again.
          sidebar.addEventListener('toolbar-popover-toggled', (event) => {
            if (event.detail && event.detail.state && event.target.contains(link)) {
              openTrail(link);
            }
          });
        });
      });
    },
  };
})(Drupal, once);
