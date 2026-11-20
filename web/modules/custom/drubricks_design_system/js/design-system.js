/**
 * @file
 * Design system page: table of contents (scroll spy, deep links, filter),
 * Twig highlighting, copy buttons and the generated HTML of each preview.
 * Works after an HTMX swap too (Drupal behaviour + cleanup on unload).
 */
((Drupal, once) => {
  const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const KW = 'include|embed|block|endblock|endembed|with|only|set|endset|if|elseif|else|endif|for|endfor|in|not|and|or|is|defined|use|return|new|function|public|static|array';
  const TOKEN = new RegExp(`(\\{#[\\s\\S]*?#\\}|//[^\\n]*)|('(?:[^'\\\\\\n]|\\\\.)*')|(\\{[%{]-?|-?[%}]\\})|\\b(${KW})\\b|(?<![\\w$#])([#$]?[A-Za-z_][A-Za-z0-9_]*)(?=\\s*(?::|=>))`, 'g');
  function highlight(code) {
    return esc(code).replace(TOKEN, (m, com, str, tag, kw, key) => {
      if (com) return `<span class="tk-com">${com}</span>`;
      if (str) return `<span class="tk-str">${str}</span>`;
      if (tag) return `<span class="tk-tag">${tag}</span>`;
      if (kw) return `<span class="tk-kw">${kw}</span>`;
      return `<span class="tk-attr">${key}</span>`;
    });
  }

  // Readable HTML of a live preview: kit / Drupal runtime attributes removed, indented.
  const VOID = /^(area|base|br|col|embed|hr|img|input|link|meta|source|track|wbr|use|path|circle|rect|line|polyline)$/i;
  function htmlOf(preview) {
    const clone = preview.cloneNode(true);
    clone.querySelectorAll('*').forEach((el) => {
      ['data-component-id', 'data-component-variant', 'data-once', 'aria-controls', 'aria-labelledby'].forEach((a) => el.removeAttribute(a));
      if (el.matches('.tab, .row, .p-row.click, [data-tab-panel]')) ['role', 'tabindex', 'aria-selected'].forEach((a) => el.removeAttribute(a));
      if (/^ui-/.test(el.id)) el.removeAttribute('id');
    });
    clone.querySelectorAll('.calendar, .board, [data-picker]').forEach((el) => { el.innerHTML = '<!-- rendered by the kit -->'; });
    const parts = clone.innerHTML.replace(/>\s+</g, '><').trim().split(/(<[^>]+>)/).filter((p) => p.trim());
    let depth = 0;
    const out = [];
    parts.forEach((p) => {
      if (p.startsWith('</')) depth = Math.max(0, depth - 1);
      out.push('  '.repeat(depth) + p.trim());
      const name = (p.match(/^<([a-z0-9-]+)/i) || [])[1];
      if (name && !p.endsWith('/>') && !VOID.test(name) && !p.startsWith('<!')) depth++;
    });
    // Join text and short closing tags back onto their line.
    return out.join('\n').replace(/>\n\s*([^<\n]+)\n\s*<\//g, '>$1</').replace(/(<([a-z0-9-]+)[^>]*>)\n\s*(<\/\2>)/gi, '$1$3');
  }

  function copy(text) {
    const done = () => window.UI && window.UI.hud(Drupal.t('Copied to clipboard'));
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, fallback);
    }
    else {
      fallback();
    }
    function fallback() {
      const t = document.createElement('textarea');
      t.value = text;
      document.body.appendChild(t);
      t.select();
      try { document.execCommand('copy'); done(); }
      catch (e) { /* ignore */ }
      t.remove();
    }
  }

  let cleanup = null;

  Drupal.behaviors.drubricksDesignSystem = {
    attach(context) {
      once('drubricks-ds', '#ds-docs', context).forEach((docs) => {
        const toc = document.querySelector('.ds-toc');
        const items = new Map(Array.from(document.querySelectorAll('[data-ds-section]')).map((a) => [a.dataset.dsSection, a]));
        const setActive = (id) => items.forEach((a, key) => {
          a.classList.toggle('active', key === id);
          if (key === id) a.setAttribute('aria-current', 'location');
          else a.removeAttribute('aria-current');
        });

        // Code: Twig highlighting, copy, and an HTML block generated from the preview.
        docs.querySelectorAll('.demo').forEach((demo) => {
          const code = demo.querySelector('.demo-code code');
          if (code) {
            code.dataset.source = code.textContent;
            code.innerHTML = highlight(code.textContent);
          }
          const preview = demo.querySelector('.demo-preview');
          if (preview && !demo.closest('#drubricks-design-system-demo-form')) {
            demo.insertAdjacentHTML('beforeend', `<div class="demo-code" data-lang="html"><details><summary>${Drupal.t('Rendered HTML')}</summary><button class="copy" type="button">${Drupal.t('Copy')}</button><pre><code></code></pre></details></div>`);
            const details = demo.lastElementChild.querySelector('details');
            details.addEventListener('toggle', () => {
              if (!details.open) return;
              const c = details.querySelector('code');
              c.dataset.source = htmlOf(preview);
              c.innerHTML = esc(c.dataset.source).replace(/(&lt;\/?)([\w-]+)|(\s)([\w:@.-]+)=("[^"]*")|(&lt;!--[\s\S]*?--&gt;)/g, (m, lt, tag, sp, attr, val, com) => {
                if (com) return `<span class="tk-com">${com}</span>`;
                if (tag) return `${lt}<span class="tk-tag">${tag}</span>`;
                return `${sp}<span class="tk-attr">${attr}</span>=<span class="tk-str">${val}</span>`;
              });
            });
          }
        });
        docs.addEventListener('click', (e) => {
          const c = e.target.closest('.copy');
          if (!c) return;
          e.preventDefault();
          const code = c.closest('.demo-code').querySelector('code');
          copy(code.dataset.source || code.textContent);
        });

        // Table of contents: smooth scroll + deep link (#section) without a reload.
        const go = (id, smooth) => {
          const sec = document.getElementById(id);
          if (!sec || !docs.contains(sec)) return false;
          sec.hidden = false;
          sec.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
          setActive(id);
          return true;
        };
        const onToc = (e) => {
          const a = e.target.closest('[data-ds-section]');
          if (!a || e.metaKey || e.ctrlKey) return;
          e.preventDefault();
          history.replaceState(history.state, '', '#' + a.dataset.dsSection);
          go(a.dataset.dsSection, true);
          if (window.UI && window.UI.showDetail) window.UI.showDetail();
        };
        toc && toc.addEventListener('click', onToc);
        docs.addEventListener('click', (e) => {
          const a = e.target.closest('a[href^="#"]:not([data-ds-section])');
          if (!a || a.getAttribute('href').length < 2) return;
          const target = document.getElementById(a.getAttribute('href').slice(1));
          if (!target || !docs.contains(target)) return;
          e.preventDefault();
          history.replaceState(history.state, '', a.getAttribute('href'));
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        // Scroll spy.
        const spy = new IntersectionObserver((entries) => {
          entries.forEach((en) => { if (en.isIntersecting) setActive(en.target.id); });
        }, { root: docs, rootMargin: '0px 0px -70% 0px' });
        docs.querySelectorAll('.doc-section').forEach((s) => spy.observe(s));

        // Filter sections by title / keywords.
        const filter = document.querySelector('[data-ds-filter] input');
        const onFilter = () => {
          const q = filter.value.trim().toLowerCase();
          docs.querySelectorAll('.doc-section').forEach((s) => { s.hidden = !!q && !s.dataset.title.toLowerCase().includes(q); });
          items.forEach((a, key) => { a.hidden = !!q && document.getElementById(key).hidden; });
          document.querySelectorAll('.ds-toc-group').forEach((g) => { g.hidden = !g.querySelector('[data-ds-section]:not([hidden])'); });
        };
        filter && filter.addEventListener('input', onFilter);

        // Demo inspector: the kit's openInspector with a title.
        docs.addEventListener('click', (e) => {
          const b = e.target.closest('[data-ds-inspect]');
          if (!b || !window.UI) return;
          const title = document.getElementById('ds-insp-title');
          if (title) title.textContent = b.dataset.dsInspect;
          window.UI.openInspector();
        });

        // Live examples write their events into the nearest .demo-out.
        const out = (name, text) => { const o = docs.querySelector(`[data-ds-out="${name}"]`); if (o) o.textContent = text; };
        const show = (e) => {
          const d = Object.assign({}, e.detail);
          ['calendar', 'board', 'picker', 'item'].forEach((k) => delete d[k]);
          out(e.type.split(':')[1], `${e.type} → ${JSON.stringify(d)}`);
        };
        ['drubricks:date', 'drubricks:calendar', 'drubricks:menu', 'drubricks:modal'].forEach((n) => docs.addEventListener(n, show));

        const hash = decodeURIComponent(window.location.hash.slice(1));
        if (hash) requestAnimationFrame(() => { if (!go(hash, false)) document.getElementById(hash)?.scrollIntoView({ block: 'center' }); });
        else setActive(docs.querySelector('.doc-section')?.id);

        cleanup = () => {
          spy.disconnect();
          toc && toc.removeEventListener('click', onToc);
          filter && filter.removeEventListener('input', onFilter);
        };
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload' && cleanup && (context === document || context.contains?.(document.getElementById('ds-docs')) || !document.getElementById('ds-docs'))) {
        cleanup();
        cleanup = null;
      }
    },
  };
})(Drupal, once);
