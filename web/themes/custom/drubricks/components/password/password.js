/**
 * @file
 * drubricks:password — show / hide the password and score the strength meter.
 * Registered as a kit behaviour, so it works on HTMX-swapped content too.
 */
((Drupal, once, UI) => {
  if (!UI) return;
  // 0–4: length, mixed case, digits, symbols (same scale as the kit's auth pages).
  const score = (v) => {
    if (!v) return 0;
    let s = 0;
    if (v.length >= 8) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    return Math.max(1, s);
  };
  Drupal.behaviors.drubricksPassword = {
    attach(context) {
      once('drubricks-password', '[data-drubricks-password]', context).forEach((wrap) => {
        const input = wrap.querySelector('input');
        const toggle = wrap.querySelector('.input-group > button');
        const meter = wrap.querySelector('.pw-meter');
        toggle.addEventListener('click', () => {
          const show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          toggle.setAttribute('aria-pressed', String(show));
          const label = show ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
          toggle.title = label;
          toggle.setAttribute('aria-label', label);
          toggle.innerHTML = UI.icon(show ? 'eye-off' : 'eye');
        });
        if (meter) input.addEventListener('input', () => { meter.dataset.score = String(score(input.value)); });
      });
    },
  };
})(Drupal, once, window.UI);
