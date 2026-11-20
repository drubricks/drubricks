/* =========================================================
   UI kit — theme bootstrap. Load in <head> BEFORE the Tailwind script:

     <script src="ui/theme.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3/dist/index.global.js"></script>
     <link rel="stylesheet" href="ui/ui.css">

   With a compiled stylesheet (no Tailwind script, e.g. dist/kit + Tailwind CLI with dist/kit/tailwind-theme.css)
   it is still needed in <head> for 1.; add data-no-tailwind to skip 2.:  <script src="kit/theme.js" data-no-tailwind></script>
   (without the attribute the extra <style type="text/tailwindcss"> is inert — nothing reads it).

   1. sets light / dark and the colour palette before the first paint (no flash);
      a page picks its default palette with <html data-default-palette="cobalt"> (the user's choice still wins)
   2. registers a Tailwind v4 theme that maps utilities to the kit's design tokens
      (tokens live in ui/ui.css as CSS variables, so light/dark switch automatically):

        bg-surface  bg-sidebar  bg-raised  bg-inspector  bg-lane  bg-card  bg-control
        text-ink  text-ink-2  text-ink-3  text-ink-4 (decorative only)   bg-accent  text-accent  bg-accent-soft
        border-line  border-line-strong  bg-hover
        red orange yellow green teal blue indigo purple pink gray   (text-red, bg-green/15 …)
        font-sans (Geist)  font-mono (Geist Mono)  font-serif (Fraunces)
        dark:…  → follows the app theme (data-scheme), not only the OS setting
   ========================================================= */
(function () {
  'use strict';

  try {
    var t = JSON.parse(localStorage.getItem('ui.theme') || '"auto"');
    var dark = t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.scheme = dark ? 'dark' : 'light';
  } catch (e) {
    document.documentElement.dataset.scheme = 'light';
  }
  // colour palette (lagoon = no attribute): the user's choice, else the page's <html data-default-palette="cobalt">
  try {
    var root = document.documentElement, pal = null;
    try { pal = JSON.parse(localStorage.getItem('ui.palette')); } catch (e) {}
    if (typeof pal !== 'string' || !pal) pal = root.getAttribute('data-default-palette') || 'lagoon';
    if (pal !== 'lagoon') root.dataset.palette = pal;
  } catch (e) {}

  var colors = ['red', 'orange', 'yellow', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink', 'gray'];
  var css =
    '@custom-variant dark (&:where([data-scheme=dark], [data-scheme=dark] *));\n' +
    '@theme inline {\n' +
    '  --font-sans: var(--font);\n' +
    '  --font-mono: var(--font-mono);\n' +
    '  --font-serif: var(--font-serif);\n' +
    '  --color-accent: var(--accent);\n' +
    '  --color-accent-soft: var(--accent-soft);\n' +
    '  --color-on-accent: var(--on-accent);\n' +
    '  --color-ink: var(--label);\n' +
    '  --color-ink-2: var(--label-2);\n' +
    '  --color-ink-3: var(--label-3);\n' +
    '  --color-ink-4: var(--label-4);\n' +
    '  --color-surface: var(--bg);\n' +
    '  --color-sidebar: var(--bg-sidebar);\n' +
    '  --color-inspector: var(--bg-inspector);\n' +
    '  --color-raised: var(--bg-raised);\n' +
    '  --color-control: var(--bg-control);\n' +
    '  --color-lane: var(--bg-lane);\n' +
    '  --color-card: var(--bg-card);\n' +
    '  --color-line: var(--sep);\n' +
    '  --color-line-strong: var(--sep-strong);\n' +
    '  --color-hover: var(--hover);\n' +
    colors.map(function (c) { return '  --color-' + c + ': var(--' + c + ');\n'; }).join('') +
    '}\n';

  // compiled CSS → no Tailwind in the browser, nothing to register (tools/vendor.mjs reads this block from a stub DOM)
  var me = document.currentScript;
  if (me && me.hasAttribute && me.hasAttribute('data-no-tailwind')) return;
  var style = document.createElement('style');
  style.type = 'text/tailwindcss';
  style.textContent = css;
  document.head.appendChild(style);
})();
