/**
 * "Add a paper": fetch metadata for a pasted DOI and fill in the form.
 * Without JavaScript, the "Fetch details" button submits the form and the
 * server does the same lookup.
 */
(() => {
  const form = document.querySelector('[data-paper-form]');
  if (!form) return;

  const $ = (name) => form.querySelector(`[name="${name}"]`);
  const doiInput = $('doi');
  const fetchBtn = form.querySelector('[data-fetch]');
  const messages = form.querySelector('[data-messages]');
  const licenseNote = form.querySelector('[data-license-note]');
  const sourceLabel = form.querySelector('[data-source-label]');
  const authorCount = form.querySelector('[data-author-count]');
  let dirty = false;
  let submitting = false;

  // interface text in the page language (set by the template)
  const T = Object.assign({
    fetch: 'Fetch details', fetching: 'Fetching…', looking: 'Looking up {doi}…',
    fetched: 'Loaded “{title}” from {registry}.', preprint: 'Found a matching preprint on arXiv.',
    check: 'Please check the details.', ownWords: 'The abstract is not openly licensed, so please describe the paper in your own words.',
    continue: 'Continue editing your draft →', view: 'Open “{title}” →',
    network: 'Could not reach the lookup service. Please check your connection and try again.',
    unexpected: 'Unexpected response. Please try again.', source: 'Source: {source}', sourceYou: 'Written by you. It will be credited to you.',
  }, window.INKWELL_I18N || {});
  const fillIn = (text, values) => text.replace(/\{(\w+)\}/g, (m, k) => (k in values ? values[k] : m));

  const escape = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  const message = (html, type = '') => {
    messages.innerHTML = html ? `<div class="notice ${type}" role="${type ? 'alert' : 'status'}">${html}</div>` : '';
  };

  const updateAuthorCount = () => {
    const n = $('authors').value.split('\n').filter((l) => l.trim()).length;
    authorCount.textContent = n ? `· ${n}` : '';
  };

  const looksLikeDoi = (v) => /(^|doi\.org\/|doi:\s*)10\.\d{4,9}\/\S+/i.test(v.trim());

  const fill = (p) => {
    $('doi').value = p.doi;
    $('title').value = p.title;
    $('authors').value = p.authors.join('\n');
    $('authorTotal').value = '';
    $('date').value = p.date;
    $('venue').value = p.venue;
    $('volume').value = p.volume;
    $('pages').value = p.pages;
    $('arxiv').value = p.arxiv;
    $('keywords').value = p.keywords.join(', ');
    $('abstract').value = p.abstract;
    $('abstractSource').value = p.abstractSource;
    licenseNote.hidden = p.abstractOpen;
    sourceLabel.textContent = p.abstractSource ? fillIn(T.source, { source: p.abstractSourceLabel || p.abstractSource }) : T.sourceYou;
    form.querySelectorAll('[data-autogrow]').forEach((a) => a.dispatchEvent(new Event('input')));
    updateAuthorCount();
    dirty = true;
  };

  let inFlight = null;

  const lookup = async () => {
    const doi = doiInput.value.trim();
    if (!doi) {
      doiInput.focus();
      return;
    }
    if (inFlight) inFlight.abort();
    inFlight = new AbortController();

    fetchBtn.disabled = true;
    fetchBtn.textContent = T.fetching;
    form.classList.add('is-fetching');
    message(escape(fillIn(T.looking, { doi })));

    try {
      const res = await fetch(`${form.dataset.lookup}?doi=${encodeURIComponent(doi)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: inFlight.signal,
      });
      const data = await res.json().catch(() => ({ error: T.unexpected }));

      if (!res.ok) {
        let html = escape(data.error || T.unexpected);
        if (data.duplicate?.edit) html += ` <a href="${escape(data.duplicate.edit)}">${escape(T.continue)}</a>`;
        else if (data.duplicate?.url) html += ` <a href="${escape(data.duplicate.url)}">${escape(fillIn(T.view, { title: data.duplicate.title }))}</a>`;
        message(html, 'notice--error');
        return;
      }

      fill(data.paper);
      const p = data.paper;
      message(escape(
        fillIn(T.fetched, { title: p.title, registry: p.registry }) +
          (p.arxiv ? ' ' + T.preprint : '') + ' ' +
          (p.abstractOpen ? T.check : T.ownWords)
      ));
    } catch (e) {
      if (e.name !== 'AbortError') message(escape(T.network), 'notice--error');
    } finally {
      fetchBtn.disabled = false;
      fetchBtn.textContent = T.fetch;
      form.classList.remove('is-fetching');
    }
  };

  fetchBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    lookup();
  });

  // pasting a DOI fetches straight away; Enter in the DOI field too
  doiInput.addEventListener('paste', () => setTimeout(() => looksLikeDoi(doiInput.value) && lookup(), 0));
  doiInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      lookup();
    }
  });

  form.querySelectorAll('[data-autogrow]').forEach((area) => {
    const grow = () => {
      area.style.height = 'auto';
      area.style.height = area.scrollHeight + 'px';
    };
    area.addEventListener('input', grow);
    grow();
  });

  $('authors').addEventListener('input', updateAuthorCount);
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => {
    submitting = true;
    form.classList.add('is-saving');
  });
  window.addEventListener('beforeunload', (e) => {
    if (dirty && !submitting) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  updateAuthorCount();
})();
