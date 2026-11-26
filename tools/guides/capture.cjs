/**
 * Generates the illustrated how-to guides (content/5_guides) by driving a
 * real browser through the site: it signs up, writes a story, adds a paper,
 * takes a screenshot at every step and measures where the button or field
 * to highlight is, so the overlay always points at the right spot.
 *
 * Runs one language at a time, against a fresh, throwaway instance (it
 * creates accounts and content):
 *
 *   LANG_CODE=de BASE=http://localhost:8020 ADMIN_EMAIL=… ADMIN_PASSWORD=… node capture.cjs
 *   LANG_CODE=en BASE=http://localhost:8021 ADMIN_EMAIL=… ADMIN_PASSWORD=… node capture.cjs
 *
 * German is the default language: the German run writes guide.de.txt with
 * the shared fields, the English run writes guide.en.txt. See README.
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const puppeteer = require('puppeteer-core');

// LANG_CODE, not LANG: the shell's own LANG (e.g. en_US.UTF-8) must not switch languages
const LANG = process.env.LANG_CODE || 'de';
const BASE = (process.env.BASE || 'http://localhost:8020') + '/' + LANG;
const DISPLAY_HOST = process.env.DISPLAY_HOST || 'inkwellpress.vercel.app';
const OUT = path.resolve(__dirname, '../../content/5_guides');
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const VIEWPORT = { width: 1280, height: 800, deviceScaleFactor: 2 };
const DEFAULT_LANG = 'de';

const CONTRIBUTOR = { name: 'Jane Doe', email: `jane.guide.${LANG}@example.com`, password: 'guide-password-1' };
const ADMIN = { email: process.env.ADMIN_EMAIL, password: process.env.ADMIN_PASSWORD };

const uid = (seed) => crypto.createHash('sha1').update(seed).digest('hex').slice(0, 16);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const yaml = (s) => JSON.stringify(String(s)); // JSON strings are valid YAML scalars
const L = (v) => (v && typeof v === 'object' && LANG in v ? v[LANG] : v);

async function newPage(browser) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport(VIEWPORT);
  await page.emulateMediaFeatures([
    { name: 'prefers-color-scheme', value: 'light' },
    { name: 'prefers-reduced-motion', value: 'reduce' },
  ]);
  page.on('dialog', (d) => d.accept());
  page.on('pageerror', (e) => console.warn('  page error:', e.message));
  return page;
}

async function login(page, { email, password }) {
  await page.goto(BASE + '/login');
  await page.type('#email', email);
  await page.type('#password', password);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

/** Scrolls the target into view, screenshots the viewport, measures the target. */
async function shoot(page, guideDir, n, slug, target) {
  await page.evaluate(() => document.fonts.ready);
  const el = await page.waitForSelector(target, { visible: true });
  await el.evaluate((e) => e.scrollIntoView({ block: 'center', inline: 'nearest' }));
  await sleep(400);

  const pad = 8;
  const r = await el.evaluate((e) => {
    const b = e.getBoundingClientRect();
    return { x: b.x, y: b.y, w: b.width, h: b.height };
  });
  const x = Math.max(0, r.x - pad), y = Math.max(0, r.y - pad);
  const w = Math.min(VIEWPORT.width - x, r.w + pad * 2), h = Math.min(VIEWPORT.height - y, r.h + pad * 2);
  const box = [x / VIEWPORT.width, y / VIEWPORT.height, w / VIEWPORT.width, h / VIEWPORT.height]
    .map((v) => (v * 100).toFixed(2)).join(',');

  const file = `${String(n).padStart(2, '0')}-${slug}-${LANG}.webp`;
  await page.screenshot({ path: path.join(guideDir, file), type: 'webp', quality: 82 });
  return { file, box, url: DISPLAY_HOST + new URL(page.url()).pathname };
}

/** Selects a word in the first paragraph of the editor. */
async function selectWord(page, word) {
  await page.evaluate((w) => {
    const node = document.querySelector('[data-editor] p').firstChild;
    const start = node.textContent.indexOf(w);
    const range = document.createRange();
    range.setStart(node, start);
    range.setEnd(node, start + w.length);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }, word);
}

/* ------------------------------------------------------------------ */

const SAMPLE = {
  headline: { en: 'Notes from my first week of fieldwork', de: 'Notizen aus meiner ersten Woche Feldforschung' },
  subtitle: { en: 'Mud, mosquitoes and a very patient supervisor.', de: 'Schlamm, Mücken und eine sehr geduldige Betreuerin.' },
  body1: {
    en: 'The first thing nobody tells you about fieldwork is how early it starts. By six in the morning we were already knee-deep in the wetland, counting birds before the heat set in.',
    de: 'Das Erste, was einem niemand über Feldforschung sagt: Sie fängt früh an. Um sechs Uhr morgens standen wir schon knietief im Feuchtgebiet und zählten Vögel, bevor es heiß wurde.',
  },
  body2: {
    en: 'By the end of the week I had filled two notebooks, learned to tell a reed warbler from a sedge warbler by ear, and gained a lot of respect for the people who do this every year.',
    de: 'Am Ende der Woche hatte ich zwei Notizbücher gefüllt, gelernt, einen Teichrohrsänger am Gesang von einem Schilfrohrsänger zu unterscheiden, und großen Respekt vor allen bekommen, die das jedes Jahr machen.',
  },
  boldWord: { en: 'fieldwork', de: 'Feldforschung' },
  tags: { en: 'Fieldwork, Ecology', de: 'Feldforschung, Ökologie' },
  doi: '10.1038/s41586-024-07487-w',
  summary: {
    en: 'AlphaFold 3 predicts how proteins, DNA and small molecules fit together, far more accurately than earlier tools. That could speed up drug discovery.',
    de: 'AlphaFold 3 sagt voraus, wie Proteine, DNA und kleine Moleküle zusammenpassen, viel genauer als frühere Werkzeuge. Das könnte die Entwicklung neuer Medikamente beschleunigen.',
  },
};

const GUIDES = [
  {
    slug: 'write-a-story', num: 1,
    title: { en: 'How to write a story', de: 'So schreiben Sie einen Beitrag' },
    intro: {
      en: 'From signing up to a published story in eight short steps, plus one for editors. You don’t need any technical knowledge.',
      de: 'Von der Registrierung bis zum veröffentlichten Beitrag in acht kurzen Schritten, dazu einer für die Redaktion. Technisches Vorwissen brauchen Sie nicht.',
    },
    audience: { en: 'Anyone who wants to write for Inkwell', de: 'Alle, die für Inkwell schreiben möchten' },
    duration: { en: '5 minutes', de: '5 Minuten' },
    cta: { en: 'Write your first story', de: 'Ersten Beitrag schreiben' }, ctaLink: 'write',
    steps: [
      {
        slug: 'sign-up', target: 'form button[type=submit]',
        title: { en: 'Create an account', de: 'Konto anlegen' },
        callout: { en: 'Fill in, then click', de: 'Ausfüllen, dann klicken' },
        text: {
          en: 'Click **Create an account** at the bottom of any page, or **Sign in → Sign up**. Enter your name, your email address and a password with at least 8 characters, then click **Create account**.',
          de: 'Klicken Sie unten auf einer beliebigen Seite auf **Konto erstellen** oder auf **Anmelden → Registrieren**. Geben Sie Ihren Namen, Ihre E-Mail-Adresse und ein Passwort mit mindestens 8 Zeichen ein und klicken Sie auf **Konto erstellen**.',
        },
        alt: { en: 'The sign-up page with fields for name, email and password.', de: 'Die Registrierungsseite mit Feldern für Name, E-Mail und Passwort.' },
        async before({ page }) {
          await page.goto(BASE + '/signup');
          await sleep(3200); // the sign-up form ignores instant (bot) submissions
          await page.type('#name', CONTRIBUTOR.name);
          await page.type('#email', CONTRIBUTOR.email);
          await page.type('#password', CONTRIBUTOR.password);
        },
        async after({ page }) {
          await Promise.all([page.waitForNavigation(), page.click('form button[type=submit]')]);
        },
      },
      {
        slug: 'open-studio', target: '.site-header .btn--accent',
        title: { en: 'Open the editor', de: 'Editor öffnen' },
        callout: { en: 'Click “Write a story”', de: 'Auf „Beitrag schreiben“ klicken' },
        text: {
          en: 'When you are signed in, the header shows two extra buttons. Click **Write a story** to open the editor.',
          de: 'Wenn Sie angemeldet sind, zeigt die Kopfzeile zwei zusätzliche Schaltflächen. Klicken Sie auf **Beitrag schreiben**, um den Editor zu öffnen.',
        },
        alt: { en: 'The home page with the “Write a story” button highlighted.', de: 'Die Startseite, die Schaltfläche „Beitrag schreiben“ ist hervorgehoben.' },
        async before({ page }) { await page.goto(BASE); },
      },
      {
        slug: 'headline', target: '#title',
        title: { en: 'Add a headline', de: 'Überschrift eingeben' },
        callout: { en: 'Type the headline', de: 'Überschrift eingeben' },
        text: {
          en: 'Click **Headline** and type the title of your story. Press **Enter** to move to the subtitle: one sentence that tells readers what the story is about.',
          de: 'Klicken Sie auf **Überschrift** und geben Sie den Titel Ihres Beitrags ein. Mit **Enter** springen Sie zum Untertitel: ein Satz, der sagt, worum es geht.',
        },
        alt: { en: 'The editor with a headline and subtitle typed in.', de: 'Der Editor mit eingegebener Überschrift und Untertitel.' },
        async before({ page }) {
          await page.goto(BASE + '/write');
          await page.click('#title');
          await page.keyboard.type(L(SAMPLE.headline), { delay: 5 });
          await page.keyboard.press('Enter');
          await page.keyboard.type(L(SAMPLE.subtitle), { delay: 5 });
        },
      },
      {
        slug: 'write', target: '[data-editor]',
        title: { en: 'Write the text', de: 'Text schreiben' },
        callout: { en: 'Write here', de: 'Hier schreiben' },
        text: {
          en: 'Click below the toolbar and start writing. Press **Enter** to start a new paragraph. Your text is saved when you click one of the buttons on the right, so don’t close the tab before that.',
          de: 'Klicken Sie unter die Werkzeugleiste und beginnen Sie zu schreiben. Mit **Enter** beginnt ein neuer Absatz. Gespeichert wird, wenn Sie rechts auf eine der Schaltflächen klicken. Schließen Sie den Tab also nicht vorher.',
        },
        alt: { en: 'The text area of the editor with two paragraphs.', de: 'Das Textfeld des Editors mit zwei Absätzen.' },
        async before({ page }) {
          await page.click('[data-editor]');
          await page.keyboard.type(L(SAMPLE.body1), { delay: 2 });
          await page.keyboard.press('Enter');
          await page.keyboard.type(L(SAMPLE.body2), { delay: 2 });
        },
      },
      {
        slug: 'format', target: '.toolbar',
        title: { en: 'Format the text', de: 'Text formatieren' },
        callout: { en: 'Headings, bold, lists', de: 'Überschriften, Fett, Listen' },
        text: {
          en: 'Select some text and use the toolbar to make it **bold**, *italic* or a link, or to turn a line into a heading, quote or list. Shortcut: typing `## ` at the start of a line makes it a heading.',
          de: 'Markieren Sie Text und machen Sie ihn über die Werkzeugleiste **fett**, *kursiv* oder zu einem Link. Sie können eine Zeile auch zur Überschrift, zum Zitat oder zur Liste machen. Tipp: `## ` am Zeilenanfang macht die Zeile zur Überschrift.',
        },
        alt: { en: 'The toolbar with buttons for headings, bold, italic, links, quotes and lists.', de: 'Die Werkzeugleiste mit Schaltflächen für Überschriften, Fett, Kursiv, Links, Zitate und Listen.' },
        async before({ page }) {
          await selectWord(page, L(SAMPLE.boldWord));
          await page.click('[data-cmd="bold"]');
        },
      },
      {
        slug: 'details', target: '.publish-card',
        title: { en: 'Add the details', de: 'Angaben ergänzen' },
        callout: { en: 'Type, tags, cover image', de: 'Art, Schlagwörter, Titelbild' },
        text: {
          en: 'On the right, choose **Article** or **Blog post**, add a few **tags** separated by commas and, if you like, a **cover image**. Click the dotted box to pick a photo of up to 4 MB.',
          de: 'Wählen Sie rechts **Artikel** oder **Blogbeitrag**, ergänzen Sie ein paar **Schlagwörter** (mit Kommas getrennt) und auf Wunsch ein **Titelbild**. Klicken Sie dazu auf das gestrichelte Feld und wählen Sie ein Foto mit bis zu 4 MB.',
        },
        alt: { en: 'The publishing panel with type, tags and cover image.', de: 'Der Bereich Veröffentlichung mit Art, Schlagwörtern und Titelbild.' },
        async before({ page }) {
          await page.click('#tags');
          await page.keyboard.type(L(SAMPLE.tags), { delay: 5 });
        },
      },
      {
        slug: 'submit', target: 'button[value="submit"]',
        title: { en: 'Submit it', de: 'Einreichen' },
        callout: { en: 'Click when you’re done', de: 'Klicken, wenn Sie fertig sind' },
        text: {
          en: 'When you’re happy with it, click **Submit for review**. If you’re not finished yet, click **Save draft** and come back later. An editor reads every story before it goes online.',
          de: 'Wenn Sie zufrieden sind, klicken Sie auf **Zur Prüfung einreichen**. Sind Sie noch nicht fertig, klicken Sie auf **Entwurf speichern** und machen später weiter. Die Redaktion liest jeden Beitrag, bevor er online geht.',
        },
        alt: { en: 'The “Submit for review” and “Save draft” buttons.', de: 'Die Schaltflächen „Zur Prüfung einreichen“ und „Entwurf speichern“.' },
        async after({ page, state }) {
          await Promise.all([page.waitForNavigation(), page.click('button[value="submit"]')]);
          state.storySlug = new URL(page.url()).pathname.split('/').pop();
        },
      },
      {
        slug: 'track', target: '.drafts-card',
        title: { en: 'Keep track of it', de: 'Den Überblick behalten' },
        callout: { en: 'Your stories are listed here', de: 'Hier stehen Ihre Beiträge' },
        text: {
          en: 'Your story now appears under **Submitted** in the list on the right. You can keep editing it until an editor publishes it, or **withdraw** it to turn it back into a draft. Once it is published, it appears on the site with your name.',
          de: 'Ihr Beitrag steht jetzt rechts in der Liste unter **Eingereicht**. Bis die Redaktion ihn veröffentlicht, können Sie weiter daran arbeiten oder ihn **zurückziehen**, dann ist er wieder ein Entwurf. Nach der Veröffentlichung erscheint er mit Ihrem Namen auf der Website.',
        },
        alt: { en: 'The editor after submitting, with a confirmation and the story listed under “Submitted”.', de: 'Der Editor nach dem Einreichen, mit Bestätigung und dem Beitrag unter „Eingereicht“.' },
      },
      {
        slug: 'editor-approve', target: 'button[value="publish"]', asAdmin: true,
        title: { en: 'For editors: publish it', de: 'Für die Redaktion: freigeben' },
        callout: { en: 'Approve and publish', de: 'Freigeben' },
        text: {
          en: 'Editors see new submissions under **Waiting for review** in the editor. Open one, make any changes, then click **Approve and publish**.',
          de: 'Die Redaktion sieht neue Einreichungen im Editor unter **Warten auf Freigabe**. Öffnen Sie eine, nehmen Sie bei Bedarf Änderungen vor und klicken Sie auf **Freigeben**.',
        },
        alt: { en: 'An editor’s view of a submitted story with the “Approve and publish” button.', de: 'Die Ansicht der Redaktion mit der Schaltfläche „Freigeben“.' },
        async before({ page, state }) { await page.goto(BASE + '/write/' + state.storySlug); },
      },
    ],
  },
  {
    slug: 'add-a-paper', num: 2,
    title: { en: 'How to add a paper', de: 'So tragen Sie ein Paper ein' },
    intro: {
      en: 'Share a research paper by pasting its DOI. Title, authors and journal are filled in for you, so it only takes a minute or two.',
      de: 'Teilen Sie ein Forschungspaper, indem Sie seine DOI einfügen. Titel, Autor:innen und Zeitschrift werden automatisch ausgefüllt, das dauert nur ein, zwei Minuten.',
    },
    audience: { en: 'Authors and editors who share research', de: 'Autor:innen und Redaktion, die Forschung teilen' },
    duration: { en: '2 minutes', de: '2 Minuten' },
    cta: { en: 'Add a paper', de: 'Paper eintragen' }, ctaLink: 'add-paper',
    steps: [
      {
        slug: 'open', target: '.site-header a[href$="/add-paper"]',
        title: { en: 'Open “Add a paper”', de: '„Paper eintragen“ öffnen' },
        callout: { en: 'Click “Add a paper”', de: 'Auf „Paper eintragen“ klicken' },
        text: {
          en: 'Sign in, then click **Add a paper** in the header. No account yet? Step 1 of *How to write a story* explains how to create one.',
          de: 'Melden Sie sich an und klicken Sie in der Kopfzeile auf **Paper eintragen**. Noch kein Konto? Schritt 1 der Anleitung *So schreiben Sie einen Beitrag* erklärt, wie Sie eines anlegen.',
        },
        alt: { en: 'The home page with the “Add a paper” button highlighted.', de: 'Die Startseite, die Schaltfläche „Paper eintragen“ ist hervorgehoben.' },
        async before({ page }) { await page.goto(BASE); },
      },
      {
        slug: 'paste-doi', target: '#doi',
        title: { en: 'Paste the DOI', de: 'DOI einfügen' },
        callout: { en: 'Paste the DOI here', de: 'DOI hier einfügen' },
        text: {
          en: 'Every published paper has a **DOI**, a permanent ID that starts with **10.** You’ll find it on the paper’s web page. Copy it (the full *https://doi.org/…* link works too) and paste it into this box. The details load automatically.',
          de: 'Jedes veröffentlichte Paper hat eine **DOI**, eine dauerhafte Kennung, die mit **10.** beginnt. Sie steht auf der Webseite des Papers. Kopieren Sie sie (der vollständige Link *https://doi.org/…* geht auch) und fügen Sie sie in dieses Feld ein. Die Daten werden automatisch geladen.',
        },
        alt: { en: 'The “Add a paper” page with a DOI pasted into the DOI box.', de: 'Die Seite „Paper eintragen“ mit einer eingefügten DOI.' },
        async before({ page }) {
          await page.goto(BASE + '/add-paper');
          await page.type('#doi', SAMPLE.doi, { delay: 5 });
        },
      },
      {
        slug: 'check', target: '.field:has(#title)',
        title: { en: 'Check the details', de: 'Angaben prüfen' },
        callout: { en: 'Check title and authors', de: 'Titel und Autor:innen prüfen' },
        text: {
          en: 'After a few seconds the **title, authors, journal, date, volume and pages** appear. Read them over. You can still change everything. If the paper is already on Inkwell, you get a link to it instead.',
          de: 'Nach ein paar Sekunden erscheinen **Titel, Autor:innen, Zeitschrift, Datum, Band und Seiten**. Lesen Sie alles kurz durch, Sie können noch alles ändern. Ist das Paper schon auf Inkwell, bekommen Sie stattdessen einen Link dorthin.',
        },
        alt: { en: 'The form filled in with the paper’s title, authors and journal.', de: 'Das Formular mit Titel, Autor:innen und Zeitschrift des Papers.' },
        async before({ page }) {
          await page.click('[data-fetch]');
          await page.waitForFunction(() => document.querySelector('#title').value.length > 0, { timeout: 30000 });
        },
      },
      {
        slug: 'abstract', target: '.field:has(#abstract)',
        title: { en: 'Check the abstract', de: 'Abstract prüfen' },
        callout: { en: 'Abstract and source', de: 'Abstract und Quelle' },
        text: {
          en: 'If the paper’s summary (the **abstract**) is openly licensed, it is copied in, with its source shown below. If not, the box stays empty. Then write two or three sentences of your own, which will be credited to you.',
          de: 'Ist die Zusammenfassung des Papers (das **Abstract**) frei lizenziert, wird sie übernommen und darunter die Quelle angezeigt. Wenn nicht, bleibt das Feld leer. Schreiben Sie dann zwei, drei eigene Sätze, die unter Ihrem Namen erscheinen.',
        },
        alt: { en: 'The abstract field filled in, with its source shown below.', de: 'Das ausgefüllte Abstract mit der Quelle darunter.' },
      },
      {
        slug: 'summary', target: '#summary',
        title: { en: 'Explain it in plain words', de: 'Einfach erklären' },
        callout: { en: 'Optional', de: 'Optional' },
        text: {
          en: 'Under **In plain language**, explain in a couple of sentences what the researchers found and why it matters, for someone outside the field. This is optional, but it is what makes the papers on Inkwell readable.',
          de: 'Erklären Sie unter **Kurz erklärt** in ein paar Sätzen, was die Forschenden herausgefunden haben und warum das wichtig ist, für Leute, die nicht vom Fach sind. Das ist freiwillig, macht die Paper auf Inkwell aber erst richtig lesbar.',
        },
        alt: { en: 'The “In plain language” field with a short summary.', de: 'Das Feld „Kurz erklärt“ mit einer kurzen Zusammenfassung.' },
        async before({ page }) {
          await page.click('#summary');
          await page.keyboard.type(L(SAMPLE.summary), { delay: 2 });
        },
      },
      {
        slug: 'submit', target: 'button[value="submit"]',
        title: { en: 'Submit it', de: 'Einreichen' },
        callout: { en: 'Click when you’re done', de: 'Klicken, wenn Sie fertig sind' },
        text: {
          en: 'Click **Submit for review**. An editor checks the paper and publishes it. Until then you’ll find it under **Your papers** on the right.',
          de: 'Klicken Sie auf **Zur Prüfung einreichen**. Die Redaktion prüft das Paper und veröffentlicht es. Bis dahin finden Sie es rechts unter **Ihre Paper**.',
        },
        alt: { en: 'The “Submit for review” button on the “Add a paper” page.', de: 'Die Schaltfläche „Zur Prüfung einreichen“ auf der Seite „Paper eintragen“.' },
        async after({ page, state }) {
          await Promise.all([page.waitForNavigation(), page.click('button[value="submit"]')]);
          state.paperSlug = new URL(page.url()).pathname.split('/').pop();
        },
      },
      {
        slug: 'editor-approve', target: 'button[value="publish"]', asAdmin: true,
        title: { en: 'For editors: publish it', de: 'Für die Redaktion: freigeben' },
        callout: { en: 'Approve and publish', de: 'Freigeben' },
        text: {
          en: 'Editors find new papers under **Waiting for review** on the *Add a paper* page. Open one, check it and click **Approve and publish**.',
          de: 'Die Redaktion findet neue Paper auf der Seite *Paper eintragen* unter **Warten auf Freigabe**. Öffnen Sie eines, prüfen Sie es und klicken Sie auf **Freigeben**.',
        },
        alt: { en: 'An editor’s view of a submitted paper with the “Approve and publish” button.', de: 'Die Ansicht der Redaktion mit der Schaltfläche „Freigeben“.' },
        async before({ page, state }) { await page.goto(BASE + '/add-paper/' + state.paperSlug); },
      },
    ],
  },
];

/* ------------------------------------------------------------------ */

function writeFields(file, fields) {
  fs.writeFileSync(file, Object.entries(fields).map(([k, v]) => `${k}: ${v}`).join('\n\n----\n\n') + '\n');
}

(async () => {
  if (!ADMIN.email || !ADMIN.password) throw new Error('Set ADMIN_EMAIL and ADMIN_PASSWORD');
  if (!['de', 'en'].includes(LANG)) throw new Error('LANG_CODE must be de or en');
  console.log(`Capturing guides in "${LANG}" from ${BASE}`);

  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
  const contributor = await newPage(browser);
  const admin = await newPage(browser);
  await login(admin, ADMIN);
  const state = {};
  let signedIn = false;

  for (const guide of GUIDES) {
    const dir = path.join(OUT, `${guide.num}_${guide.slug}`);
    fs.mkdirSync(dir, { recursive: true });
    // remove this language's old files only; the other language stays
    for (const f of fs.readdirSync(dir)) {
      // screenshots from before the site was bilingual (no -de/-en suffix)
      const legacy = /^\d\d-[a-z-]+\.webp(\.txt)?$/.test(f) && !/-(de|en)\.webp/.test(f);
      if (legacy || f.includes(`-${LANG}.webp`) || f === `guide.${LANG}.txt` || (LANG === DEFAULT_LANG && f === 'guide.txt')) {
        fs.rmSync(path.join(dir, f));
      }
    }
    console.log(`\n${L(guide.title)}`);

    const steps = [];
    for (const [i, step] of guide.steps.entries()) {
      const page = step.asAdmin ? admin : contributor;
      if (!step.asAdmin && !signedIn && step.slug !== 'sign-up') {
        await login(contributor, CONTRIBUTOR);
        signedIn = true;
      }
      if (step.before) await step.before({ page, state });
      const shot = await shoot(page, dir, i + 1, step.slug, step.target);
      if (step.after) await step.after({ page, state });
      if (step.slug === 'sign-up') signedIn = true;

      // file meta: shared fields in the default-language file
      const fileUuid = uid(`guide-${guide.slug}-${step.slug}-${LANG}`);
      writeFields(path.join(dir, `${shot.file}.de.txt`), { Template: 'screenshot', Alt: L(step.alt), Uuid: fileUuid });
      writeFields(path.join(dir, `${shot.file}.en.txt`), { Alt: L(step.alt) });
      steps.push({ ...step, ...shot, fileUuid });
      console.log(`  ${i + 1}. ${L(step.title)}  [${shot.box}]`);
    }

    const structure = '\n' + steps.map((s) => [
      '-',
      `  title: ${yaml(L(s.title))}`,
      `  text: ${yaml(L(s.text))}`,
      `  callout: ${yaml(L(s.callout))}`,
      `  image:\n    - file://${s.fileUuid}`,
      `  url: ${yaml(s.url)}`,
      `  box: ${yaml(s.box)}`,
    ].join('\n')).join('\n');

    const text = {
      Title: L(guide.title), Intro: L(guide.intro), Audience: L(guide.audience),
      Duration: L(guide.duration), Cta: L(guide.cta), Steps: structure,
    };
    const fields = LANG === DEFAULT_LANG
      ? { ...text, Ctalink: guide.ctaLink, Uuid: uid('guide-' + guide.slug) }
      : text;
    writeFields(path.join(dir, `guide.${LANG}.txt`), fields);
  }

  await browser.close();
  console.log('\nGuides written to', OUT);
})().catch((e) => { console.error(e); process.exit(1); });
