(() => {
  const form = document.getElementById('appu-sidebar-search');
  const input = form?.querySelector('input[type="search"]');
  const navigation = document.getElementById('appu-sidebar-navigation');
  const results = document.getElementById('appu-sidebar-results');
  const status = document.getElementById('appu-sidebar-search-status');
  if (!input || !navigation || !results || !status) return;

  const normalize = text => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
  const entries = Array.from(navigation.querySelectorAll('a[href]'), link => {
    const parents = [];
    for (let parent = link.closest('details'); parent; parent = parent.parentElement.closest('details')) {
      parents.unshift(parent.querySelector('summary').textContent.trim());
    }
    const label = link.textContent.trim();
    return { link, label, context: parents.join(' / '), search: normalize([...parents, label].join(' ')) };
  });

  function search() {
    const terms = normalize(input.value).split(/\s+/).filter(Boolean);
    const searching = terms.length > 0;
    navigation.hidden = searching;
    results.hidden = !searching;
    results.replaceChildren();
    status.textContent = '';
    if (!searching) return;

    for (const entry of entries) {
      if (!terms.every(term => entry.search.includes(term))) continue;
      const result = document.createElement('li');
      const link = document.createElement('a');
      link.className = 'nav-link';
      link.href = entry.link.href;
      link.textContent = entry.label;
      if (entry.context) {
        const context = document.createElement('span');
        context.className = 'd-block';
        context.textContent = entry.context;
        link.append(context);
      }
      result.append(link);
      results.append(result);
    }
    status.textContent = results.childElementCount ? '' : 'No se encontraron resultados.';
  }

  input.addEventListener('input', search);
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape' && input.value) {
      event.preventDefault();
      input.value = '';
      search();
    }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    search();
    results.querySelector('a')?.focus();
  });
})();
