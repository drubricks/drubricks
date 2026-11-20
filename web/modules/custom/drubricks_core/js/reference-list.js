/**
 * @file
 * Reference list widget: chosen records one under another + an "Add" search.
 *
 * Progressive enhancement of core's tags autocomplete input
 * (ReferenceListWidget): that input stays the form value ("Name (12), Other
 * (34)") and is hidden; this UI keeps it in sync. Suggestions come from the
 * same core endpoint (data-autocomplete-path), so only records the user may
 * reference are offered. Keyboard: arrows move through suggestions, Enter
 * adds, Escape closes; every row has a labelled remove button.
 */
((Drupal, once) => {
  // "Name (12)", "\"Name, with comma\" (12)" → [{ label, value }].
  const parse = (text) => {
    const items = [];
    const re = /\s*(?:"((?:[^"]|"")*)"|([^,]*?))\s*(?:,|$)/g;
    let m;
    while ((m = re.exec(text)) && m[0] !== '') {
      const raw = (m[1] !== undefined ? m[1].replace(/""/g, '"') : m[2] || '').trim();
      if (raw) {
        const id = raw.match(/\((\d+)\)\s*$/);
        items.push({ value: raw, label: id ? raw.slice(0, id.index).trim() : raw });
      }
      if (re.lastIndex >= text.length) break;
    }
    return items;
  };
  const encode = (value) => (/[,"]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value);
  const decode = (html) => new DOMParser().parseFromString(html, 'text/html').body.textContent || '';

  Drupal.behaviors.drubricksReferenceList = {
    attach(context) {
      once('drubricks-reference-list', 'input[data-drubricks-reference-list]', context).forEach((input) => {
        const isUser = input.dataset.drubricksReferenceList === 'user';
        const path = input.dataset.autocompletePath;
        const label = input.closest('.form-item, .js-form-item')?.querySelector('label');
        let items = parse(input.value);

        const root = document.createElement('div');
        root.className = 'drubricks-reflist';
        const list = document.createElement('ul');
        list.className = 'reflist-items';
        if (label) list.setAttribute('aria-label', label.textContent.trim());
        const add = document.createElement('div');
        add.className = 'reflist-add';
        const listboxId = `${input.id}-suggestions`;
        // A div, not a label: form styles lay labels out as blocks.
        add.innerHTML = `<div class="search">
            <svg class="ic" aria-hidden="true"><use href="${document.querySelector('use[href*="#i-search"]')?.getAttribute('href') || '#i-search'}"/></svg>
            <input type="text" class="reflist-search" autocomplete="off" role="combobox" aria-autocomplete="list"
              aria-expanded="false" aria-controls="${listboxId}" placeholder="${Drupal.checkPlain(input.dataset.addLabel || '')}"
              aria-label="${Drupal.checkPlain(input.dataset.addLabel || '')}">
          </div>
          <ul class="reflist-suggestions" id="${listboxId}" role="listbox" hidden></ul>`;
        root.append(list, add);
        input.after(root);
        // The tags input stays the form value; people use the list above.
        input.type = 'hidden';
        if (label) label.htmlFor = `${input.id}-search`;
        const search = add.querySelector('.reflist-search');
        search.id = `${input.id}-search`;
        const box = add.querySelector('.reflist-suggestions');

        const avatar = (name) => (isUser && window.UI?.avatar ? window.UI.avatar(name, { size: 'sm' }) : '');
        const sync = () => { input.value = items.map((i) => encode(i.value)).join(', '); };
        const render = () => {
          list.innerHTML = items.length ? '' : `<li class="reflist-empty">${Drupal.checkPlain(input.dataset.emptyLabel || '')}</li>`;
          items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'reflist-item';
            li.innerHTML = `${avatar(item.label)}<span class="reflist-name">${Drupal.checkPlain(item.label)}</span>
              <button type="button" class="icon-btn small reflist-remove" aria-label="${Drupal.checkPlain(Drupal.t('Remove @name', { '@name': item.label }))}" title="${Drupal.checkPlain(Drupal.t('Remove'))}">
                <svg class="ic" aria-hidden="true"><use href="${(document.querySelector('use[href*="#i-"]')?.getAttribute('href') || '#i-x').replace(/#i-.*$/, '#i-xmark')}"/></svg>
              </button>`;
            li.querySelector('button').addEventListener('click', () => {
              items.splice(index, 1);
              sync();
              render();
              window.UI?.announce?.(Drupal.t('Removed @name', { '@name': item.label }));
              (list.querySelectorAll('.reflist-remove')[index] || search).focus();
            });
            list.append(li);
          });
        };

        let options = [];
        let active = -1;
        let timer = null;
        const close = () => { box.hidden = true; search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant'); active = -1; };
        const highlight = (i) => {
          active = i;
          box.querySelectorAll('[role="option"]').forEach((el, n) => el.setAttribute('aria-selected', String(n === i)));
          if (i >= 0) search.setAttribute('aria-activedescendant', `${listboxId}-${i}`);
        };
        const choose = (option) => {
          if (!items.some((i) => i.value === option.value)) {
            items.push(option);
            sync();
            render();
            window.UI?.announce?.(Drupal.t('Added @name', { '@name': option.label }));
          }
          search.value = '';
          close();
          search.focus();
        };
        const show = () => {
          const chosen = new Set(items.map((i) => i.value));
          options = options.filter((o) => !chosen.has(o.value));
          box.innerHTML = options.length ? '' : `<li class="reflist-none">${Drupal.checkPlain(Drupal.t('No matches'))}</li>`;
          options.forEach((option, i) => {
            const li = document.createElement('li');
            li.id = `${listboxId}-${i}`;
            li.setAttribute('role', 'option');
            li.className = 'reflist-option';
            li.innerHTML = `${avatar(option.label)}<span>${Drupal.checkPlain(option.label)}</span>`;
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(option); });
            box.append(li);
          });
          box.hidden = false;
          search.setAttribute('aria-expanded', 'true');
          highlight(options.length ? 0 : -1);
        };
        const lookup = () => {
          const term = search.value.trim();
          if (!term || !path) { close(); return; }
          // The path may already carry a query (entity_type, entity_id).
          const url = new URL(path, window.location.href);
          url.searchParams.set('q', term);
          fetch(url.pathname + url.search, { credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => {
              if (search.value.trim() !== term) return;
              options = (data || []).map((d) => ({ value: decode(d.value), label: decode(d.label) }));
              show();
            })
            .catch(close);
        };

        search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 200); });
        search.addEventListener('keydown', (e) => {
          if (e.key === 'ArrowDown' && !box.hidden) { e.preventDefault(); highlight(Math.min(active + 1, options.length - 1)); }
          else if (e.key === 'ArrowUp' && !box.hidden) { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
          else if (e.key === 'Enter') {
            // Never submit the form from the search field.
            e.preventDefault();
            if (!box.hidden && options[active]) choose(options[active]);
          }
          else if (e.key === 'Escape' && !box.hidden) { e.preventDefault(); e.stopPropagation(); close(); }
        });
        search.addEventListener('blur', () => setTimeout(close, 150));
        render();
      });
    },
  };
})(Drupal, once);
