/**
 * @file
 * drubricks:alert — the dismiss button removes its alert (delegated, once).
 */
(() => {
  if (window.drubricksAlertDismiss) return;
  window.drubricksAlertDismiss = true;
  document.addEventListener('click', (e) => {
    const x = e.target.closest('.alert [data-dismiss]');
    if (!x) return;
    const alert = x.closest('.alert');
    if (window.UI && window.UI.detach) window.UI.detach(alert);
    alert.remove();
  });
})();
