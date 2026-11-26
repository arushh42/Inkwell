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
    sourceLabel.textContent = p.abstractSource ? `Source: ${p.abstractSource}` : 'Written by you — it will be credited to you.';
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
    fetchBtn.textContent = 'Fetching…';
    form.classList.add('is-fetching');
    message('Looking up <b>' + escape(doi) + '</b>…');

    try {
      const res = await fetch(`${form.dataset.lookup}?doi=${encodeURIComponent(doi)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: inFlight.signal,
      });
      const data = await res.json().catch(() => ({ error: 'Unexpected response. Please try again.' }));

      if (!res.ok) {
        let html = escape(data.error || 'Lookup failed.');
        if (data.duplicate?.edit) html += ` <a href="${escape(data.duplicate.edit)}">Continue editing your draft →</a>`;
        else if (data.duplicate?.url) html += ` <a href="${escape(data.duplicate.url)}">View “${escape(data.duplicate.title)}” →</a>`;
        message(html, 'notice--error');
        return;
      }

      fill(data.paper);
      const p = data.paper;
      message(
        `Fetched <b>${escape(p.title)}</b> from ${escape(p.registry)}` +
          (p.arxiv ? ` (preprint found on arXiv)` : '') +
          '. Please check the details' +
          (p.abstractOpen ? '' : ' and describe the paper in your own words, since its abstract isn’t openly licensed') +
          '.'
      );
    } catch (e) {
      if (e.name !== 'AbortError') message('Couldn’t reach the lookup service. Check your connection and try again.', 'notice--error');
    } finally {
      fetchBtn.disabled = false;
      fetchBtn.textContent = 'Fetch details';
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
