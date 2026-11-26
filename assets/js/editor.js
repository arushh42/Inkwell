/**
 * Inkwell writing studio — a small, dependency-free rich-text editor.
 * The edited HTML is sanitized and converted to Kirby blocks on the server.
 */
(() => {
  const form = document.querySelector('[data-writer]');
  if (!form) return;

  const editor = form.querySelector('[data-editor]');
  const input = form.querySelector('[data-editor-input]');
  const stats = form.querySelector('[data-stats]');
  const toolbar = form.querySelector('.toolbar');
  const WPM = 230;

  let dirty = false;
  let submitting = false;

  document.execCommand('defaultParagraphSeparator', false, 'p');

  // --- helpers --------------------------------------------------------------

  const ensureParagraph = () => {
    if (editor.innerHTML.trim() === '' || editor.innerHTML === '<br>') {
      editor.innerHTML = '<p><br></p>';
    }
  };

  const currentBlock = () => {
    const sel = window.getSelection();
    if (!sel.rangeCount) return null;
    let node = sel.anchorNode;
    while (node && node !== editor) {
      if (node.nodeType === 1 && /^(P|H2|H3|BLOCKQUOTE|PRE|LI)$/.test(node.nodeName)) return node;
      node = node.parentNode;
    }
    return null;
  };

  const isEmpty = () => editor.textContent.trim() === '' && !editor.querySelector('img');

  const updateStats = () => {
    const words = (editor.innerText.match(/\S+/g) || []).length;
    const minutes = Math.max(1, Math.ceil(words / WPM));
    stats.textContent = `${words.toLocaleString()} word${words === 1 ? '' : 's'} · ${minutes} min read`;
    editor.classList.toggle('is-empty', isEmpty());
  };

  const updateToolbar = () => {
    const block = currentBlock();
    const tag = block ? block.nodeName : '';
    const inList = block && block.nodeName === 'LI' ? block.parentNode.nodeName : '';
    const state = {
      h2: tag === 'H2',
      h3: tag === 'H3',
      quote: tag === 'BLOCKQUOTE' || !!(block && block.closest && block.closest('blockquote')),
      code: tag === 'PRE',
      ul: inList === 'UL',
      ol: inList === 'OL',
      bold: document.queryCommandState('bold'),
      italic: document.queryCommandState('italic'),
    };
    toolbar.querySelectorAll('[data-cmd]').forEach((btn) => {
      btn.setAttribute('aria-pressed', state[btn.dataset.cmd] ? 'true' : 'false');
    });
  };

  const toggleBlock = (tag) => {
    const block = currentBlock();
    const active = block && block.nodeName === tag.toUpperCase();
    document.execCommand('formatBlock', false, active ? '<p>' : `<${tag}>`);
  };

  // --- toolbar ---------------------------------------------------------------

  const commands = {
    h2: () => toggleBlock('h2'),
    h3: () => toggleBlock('h3'),
    quote: () => toggleBlock('blockquote'),
    code: () => toggleBlock('pre'),
    bold: () => document.execCommand('bold'),
    italic: () => document.execCommand('italic'),
    ul: () => document.execCommand('insertUnorderedList'),
    ol: () => document.execCommand('insertOrderedList'),
    link: () => {
      const url = window.prompt('Link to (https://…)');
      if (!url) return;
      if (!/^(https?:\/\/|mailto:|\/)/i.test(url)) return window.alert('Please use a full https:// link.');
      document.execCommand('createLink', false, url);
    },
  };

  // keep the text selection when clicking toolbar buttons
  toolbar.addEventListener('mousedown', (e) => {
    if (e.target.closest('[data-cmd]')) e.preventDefault();
  });

  toolbar.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cmd]');
    if (!btn) return;
    editor.focus();
    commands[btn.dataset.cmd]?.();
    dirty = true;
    updateToolbar();
    updateStats();
  });

  // --- editor behaviour ----------------------------------------------------

  // paste as clean paragraphs, never foreign styling
  editor.addEventListener('paste', (e) => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    const escape = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const html = text
      .split(/\n{2,}/)
      .map((para) => `<p>${escape(para).replace(/\n/g, '<br>')}</p>`)
      .join('');
    document.execCommand('insertHTML', false, html);
  });

  // Markdown-style shortcuts at the start of a paragraph: "## ", "### ", "> ", "- ", "1. "
  editor.addEventListener('keyup', (e) => {
    if (e.key !== ' ') return;
    const block = currentBlock();
    if (!block || block.nodeName !== 'P') return;
    const shortcuts = { '##': 'h2', '###': 'h3', '>': 'quote', '-': 'ul', '*': 'ul', '1.': 'ol' };
    const prefix = block.textContent.replace(/ /g, ' ').trimEnd();
    const cmd = shortcuts[prefix];
    if (!cmd || block.textContent.trim() !== prefix) return;
    block.innerHTML = '<br>';
    const range = document.createRange();
    range.setStart(block, 0);
    range.collapse(true);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    commands[cmd]();
    updateToolbar();
  });

  editor.addEventListener('input', () => {
    dirty = true;
    ensureParagraph();
    updateStats();
  });

  document.addEventListener('selectionchange', () => {
    if (editor.contains(document.getSelection().anchorNode)) updateToolbar();
  });

  // --- headline & subtitle ------------------------------------------------

  form.querySelectorAll('[data-autogrow]').forEach((area) => {
    const grow = () => {
      area.style.height = 'auto';
      area.style.height = area.scrollHeight + 'px';
    };
    area.addEventListener('input', () => { dirty = true; grow(); });
    area.addEventListener('keydown', (e) => {
      // Enter moves on instead of adding a line break
      if (e.key !== 'Enter') return;
      e.preventDefault();
      if (area.name === 'title') form.querySelector('[name="subtitle"]').focus();
      else editor.focus();
    });
    grow();
  });

  // --- cover image preview --------------------------------------------------

  const coverInput = form.querySelector('[data-cover-input]');
  const coverPreview = form.querySelector('[data-cover-preview]');
  const dropzone = form.querySelector('[data-dropzone]');

  coverInput?.addEventListener('change', () => {
    const file = coverInput.files[0];
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) {
      window.alert('That image is larger than 4 MB. Please choose a smaller one.');
      coverInput.value = '';
      return;
    }
    coverPreview.src = URL.createObjectURL(file);
    coverPreview.hidden = false;
    dropzone.classList.add('has-image');
    dirty = true;
  });

  ['dragenter', 'dragover'].forEach((t) => dropzone?.addEventListener(t, () => dropzone.classList.add('is-over')));
  ['dragleave', 'drop'].forEach((t) => dropzone?.addEventListener(t, () => dropzone.classList.remove('is-over')));

  // --- submit -----------------------------------------------------------------

  form.addEventListener('submit', (e) => {
    const action = e.submitter?.value;
    if (action === 'publish' && isEmpty()) {
      e.preventDefault();
      window.alert('Your story is still empty — write something before publishing.');
      editor.focus();
      return;
    }
    input.value = isEmpty() ? '' : editor.innerHTML;
    submitting = true;
    form.classList.add('is-saving');
  });

  window.addEventListener('beforeunload', (e) => {
    if (dirty && !submitting) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  ensureParagraph();
  updateStats();
})();
