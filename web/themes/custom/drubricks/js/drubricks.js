/**
 * @file
 * Theme behaviour: Drupal messages in the kit look, and the auth pages.
 *
 * Server-rendered messages (status-messages.html.twig) and client-side ones
 * (core's Drupal.Message API: AJAX / HTMX responses, scripts) look the same:
 * status messages become a HUD toast, warnings and errors stay as kit alerts
 * with a close button.
 */
((Drupal, once) => {
  const ALERTS = {
    status: { class: 'success', icon: 'checkmark-circle' },
    warning: { class: 'warning', icon: 'alert' },
    error: { class: 'danger', icon: 'alert' },
  };

  const hud = (text) => {
    if (text && typeof window.UI === 'object') window.UI.hud(text);
  };

  /**
   * Client-side message markup (core's Drupal.Message), like the template.
   *
   * @param {object} message
   *   The message object: {text}.
   * @param {object} options
   *   The message context: {type, id}.
   *
   * @return {HTMLElement}
   *   The message element (hidden for a status message, shown as a toast).
   */
  Drupal.theme.message = ({ text }, { type, id }) => {
    const alert = ALERTS[type] || { class: 'info', icon: 'info' };
    const element = document.createElement('div');
    element.className = `alert ${alert.class}`;
    element.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
    element.setAttribute('data-drubricks-message', type);
    element.setAttribute('data-drupal-message-id', id);
    element.setAttribute('data-drupal-message-type', type);
    const icon = typeof window.UI === 'object' ? window.UI.icon(alert.icon) : '';
    const close = type === 'status' ? ''
      : `<button type="button" class="icon-btn small" data-drubricks-dismiss title="${Drupal.checkPlain(Drupal.t('Close'))}">${typeof window.UI === 'object' ? window.UI.icon('xmark') : '×'}</button>`;
    element.innerHTML = `${icon}<div class="alert-main">${text}</div>${close}`;
    if (type === 'status') {
      hud(element.querySelector('.alert-main').textContent.replace(/\s+/g, ' ').trim());
      element.hidden = true;
    }
    return element;
  };

  // Close button of warning / error alerts.
  document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-drubricks-dismiss]');
    if (button) button.closest('.alert')?.remove();
  });

  // Server-rendered status messages on a full page → toast.
  Drupal.behaviors.drubricksMessages = {
    attach(context) {
      once('drubricks-hud', '[data-drubricks-message="status"]:not([data-drupal-message-id])', context).forEach((el) => {
        hud((el.querySelector('.alert-main') || el).textContent.replace(/\s+/g, ' ').trim());
        el.remove();
      });
    },
  };
})(Drupal, once);
