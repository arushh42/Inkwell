/**
 * Generates the illustrated how-to guides (content/5_guides) by driving a
 * real browser through the site: it signs up, writes a story, adds a paper,
 * takes a screenshot at every step and measures where the button/field to
 * highlight is, so the overlay always points at the right spot.
 *
 * Usage (against a throwaway instance — it creates accounts and content):
 *   BASE=http://localhost:8020 ADMIN_EMAIL=… ADMIN_PASSWORD=… \
 *   CHROME=/path/to/chrome node tools/guides/capture.cjs
 *
 * See README → "Regenerating the guides".
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://localhost:8020';
const DISPLAY_HOST = process.env.DISPLAY_HOST || 'inkwellpress.vercel.app';
const OUT = path.resolve(__dirname, '../../content/5_guides');
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const VIEWPORT = { width: 1280, height: 800, deviceScaleFactor: 2 };

const CONTRIBUTOR = { name: 'Jane Doe', email: 'jane.guide@example.com', password: 'guide-password-1' };
const ADMIN = { email: process.env.ADMIN_EMAIL, password: process.env.ADMIN_PASSWORD };

const uid = (seed) => crypto.createHash('sha1').update(seed).digest('hex').slice(0, 16);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const yaml = (s) => JSON.stringify(String(s)); // JSON strings are valid YAML scalars

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

  const file = `${String(n).padStart(2, '0')}-${slug}.webp`;
  await page.screenshot({ path: path.join(guideDir, file), type: 'webp', quality: 82 });
  return { file, box, url: DISPLAY_HOST + new URL(page.url()).pathname };
}

/* ------------------------------------------------------------------ */

const GUIDES = [
  {
    slug: 'write-a-story', num: 1,
    title: 'How to write a story',
    intro: 'From signing up to seeing your story on the site, in eight short steps (plus one for editors). No technical knowledge needed.',
    audience: 'Everyone who wants to write for Inkwell',
    duration: '5 minutes',
    cta: 'Write your first story', ctaLink: '/write',
    steps: [
      {
        slug: 'sign-up', title: 'Create your free account', callout: 'Fill in & click',
        text: 'Click **Become a contributor** at the bottom of any page (or **Sign in → Become a contributor**). Enter your name, email and a password with at least 8 characters, then click **Create my account**.',
        alt: 'The sign-up page with fields for name, email and password and a “Create my account” button.',
        target: 'form button[type=submit]',
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
        slug: 'open-studio', title: 'Open the writing studio', callout: 'Click “Write a story”',
        text: 'Once you’re signed in, the header shows two new buttons. Click **Write a story** to open the writing studio.',
        alt: 'The Inkwell home page with the “Write a story” button in the header highlighted.',
        target: '.site-header .btn--accent',
        async before({ page }) { await page.goto(BASE + '/'); },
      },
      {
        slug: 'headline', title: 'Give it a headline', callout: 'Type your headline',
        text: 'Click on **Headline** and type the title of your story. Press **Enter** to jump to the subtitle: one sentence that makes people want to read on.',
        alt: 'The writing studio with a headline and subtitle typed in.',
        target: '#title',
        async before({ page }) {
          await page.goto(BASE + '/write');
          await page.click('#title');
          await page.keyboard.type('Notes from my first week of fieldwork', { delay: 5 });
          await page.keyboard.press('Enter');
          await page.keyboard.type('Mud, mosquitoes and one very patient supervisor.', { delay: 5 });
        },
      },
      {
        slug: 'write', title: 'Write your story', callout: 'Write here',
        text: 'Click below the toolbar and start writing. Press **Enter** for a new paragraph. Your text is only saved when you click a button on the right, so don’t close the tab before saving.',
        alt: 'The story area of the writing studio with two paragraphs of text.',
        target: '[data-editor]',
        async before({ page }) {
          await page.click('[data-editor]');
          await page.keyboard.type('The first thing nobody tells you about fieldwork is how early it starts. By six in the morning we were already knee-deep in the wetland, counting birds before the heat set in.', { delay: 2 });
          await page.keyboard.press('Enter');
          await page.keyboard.type('By the end of the week I had filled two notebooks, learned to tell a reed warbler from a sedge warbler by ear, and gained a lot of respect for the people who do this every year.', { delay: 2 });
        },
      },
      {
        slug: 'format', title: 'Format with the toolbar', callout: 'Headings, bold, lists…',
        text: 'Select some text and use the toolbar to make it **bold**, *italic* or a link, or turn a line into a heading, quote or list. Tip: typing `## ` at the start of a line also makes a heading.',
        alt: 'The formatting toolbar with buttons for headings, bold, italic, links, quotes and lists.',
        target: '.toolbar',
        async before({ page }) {
          // select the word "fieldwork" in the first paragraph and make it bold
          await page.evaluate(() => {
            const p = document.querySelector('[data-editor] p');
            const node = p.firstChild;
            const start = node.textContent.indexOf('fieldwork');
            const range = document.createRange();
            range.setStart(node, start);
            range.setEnd(node, start + 'fieldwork'.length);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
          });
          await page.click('[data-cmd="bold"]');
        },
      },
      {
        slug: 'details', title: 'Add the details', callout: 'Type, tags & cover image',
        text: 'On the right, choose whether it’s an **Article** or a **Blog post**, add a few **tags** (separated by commas) and, if you like, a **cover image**: click the dotted box and pick a photo up to 4 MB.',
        alt: 'The publishing panel with article/blog toggle, tags field and cover image upload.',
        target: '.publish-card .segmented',
        targetBox: '.publish-card',
        async before({ page }) {
          await page.click('#tags');
          await page.keyboard.type('Fieldwork, Ecology', { delay: 5 });
        },
      },
      {
        slug: 'submit', title: 'Submit it for review', callout: 'Click when you’re done',
        text: 'Happy with it? Click **Submit for review**. Not finished yet? Click **Save draft** and come back later. An editor reads every story before it goes live.',
        alt: 'The “Submit for review” and “Save draft” buttons.',
        target: 'button[value="submit"]',
        async after({ page, state }) {
          await Promise.all([page.waitForNavigation(), page.click('button[value="submit"]')]);
          state.storySlug = new URL(page.url()).pathname.split('/').pop();
        },
      },
      {
        slug: 'track', title: 'Keep track on your desk', callout: 'Your stories live here',
        text: 'Your story now shows under **Submitted for review** on your desk. You can keep editing until an editor publishes it, or **withdraw** it to make it a draft again. Once it’s published, it appears on the site with your name.',
        alt: 'The writing studio after submitting, with a confirmation message and the story listed under “Submitted for review”.',
        target: '.drafts-card',
      },
      {
        slug: 'editor-approve', title: 'For editors: approve and publish', callout: 'Approve & publish',
        text: 'Editors see new submissions under **Awaiting review** in the writing studio. Open one, make any edits, then click **Approve & publish** to put it live.',
        alt: 'An editor’s view of a submitted story with the “Approve & publish” button.',
        target: 'button[value="publish"]',
        asAdmin: true,
        async before({ page, state }) { await page.goto(BASE + '/write/' + state.storySlug); },
      },
    ],
  },
  {
    slug: 'add-a-paper', num: 2,
    title: 'How to add a paper',
    intro: 'Share a research paper by pasting its DOI. Inkwell fills in the title, authors and journal for you, so it only takes a minute.',
    audience: 'Contributors and editors sharing research',
    duration: '2 minutes',
    cta: 'Add a paper', ctaLink: '/add-paper',
    steps: [
      {
        slug: 'open', title: 'Open “Add a paper”', callout: 'Click “Add a paper”',
        text: 'Sign in, then click **Add a paper** in the header. (No account yet? See *How to write a story*, step 1.)',
        alt: 'The Inkwell home page with the “Add a paper” button in the header highlighted.',
        target: '.site-header a[href$="/add-paper"]',
        async before({ page }) { await page.goto(BASE + '/'); },
      },
      {
        slug: 'paste-doi', title: 'Paste the DOI', callout: 'Paste the DOI here',
        text: 'Every published paper has a **DOI**, a permanent ID that usually starts with **10.** You’ll find it on the paper’s web page. Copy it (the full *https://doi.org/…* link works too) and paste it into the box. The details are fetched automatically.',
        alt: 'The “Add a paper” page with a DOI pasted into the DOI box.',
        target: '#doi',
        async before({ page }) {
          await page.goto(BASE + '/add-paper');
          await page.type('#doi', '10.1038/s41586-024-07487-w', { delay: 5 });
        },
      },
      {
        slug: 'check', title: 'Check the details', callout: 'Check title & authors',
        text: 'In a few seconds the **title, authors, journal, date, volume and pages** appear. Read them over. Everything can still be edited. If the paper is already on Inkwell, you’ll get a link to it instead.',
        alt: 'The form filled in with the paper’s title, authors and journal.',
        target: '#title',
        targetBox: '.field:has(#title)',
        async before({ page }) {
          await page.click('[data-fetch]');
          await page.waitForFunction(() => document.querySelector('#title').value.length > 0, { timeout: 30000 });
        },
      },
      {
        slug: 'abstract', title: 'Look at the abstract', callout: 'Abstract & its source',
        text: 'If the paper’s summary (the **abstract**) is openly licensed, it’s copied in for you, with its source shown underneath. If not, the box stays empty: write two or three sentences in your own words, and they’ll be credited to you.',
        alt: 'The abstract field filled in, with the licence source shown below it.',
        target: '#abstract',
        targetBox: '.field:has(#abstract)',
      },
      {
        slug: 'summary', title: 'Explain it in plain language', callout: 'Optional, but loved',
        text: 'In **In plain language**, explain in a couple of sentences what the researchers found and why it matters, for someone who isn’t an expert. This is optional, but it’s what makes Inkwell readable.',
        alt: 'The “In plain language” field with a short summary typed in.',
        target: '#summary',
        async before({ page }) {
          await page.click('#summary');
          await page.keyboard.type('AlphaFold 3 predicts how proteins, DNA and small molecules fit together, far more accurately than earlier tools. That could speed up drug discovery.', { delay: 2 });
        },
      },
      {
        slug: 'submit', title: 'Submit it for review', callout: 'Click when you’re done',
        text: 'Click **Submit for review**. An editor checks it and publishes it, and you’ll find it under **Your papers** on the right in the meantime.',
        alt: 'The “Submit for review” button on the “Add a paper” page.',
        target: 'button[value="submit"]',
        async after({ page, state }) {
          await Promise.all([page.waitForNavigation(), page.click('button[value="submit"]')]);
          state.paperSlug = new URL(page.url()).pathname.split('/').pop();
        },
      },
      {
        slug: 'editor-approve', title: 'For editors: approve and publish', callout: 'Approve & publish',
        text: 'Editors find new papers under **Awaiting review** on the *Add a paper* page. Open one, check it, and click **Approve & publish**.',
        alt: 'An editor’s view of a submitted paper with the “Approve & publish” button.',
        target: 'button[value="publish"]',
        asAdmin: true,
        async before({ page, state }) { await page.goto(BASE + '/add-paper/' + state.paperSlug); },
      },
    ],
  },
];

/* ------------------------------------------------------------------ */

(async () => {
  if (!ADMIN.email || !ADMIN.password) throw new Error('Set ADMIN_EMAIL and ADMIN_PASSWORD');

  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
  const contributor = await newPage(browser);
  const admin = await newPage(browser);
  await login(admin, ADMIN);
  const state = {};
  let signedIn = false;

  for (const guide of GUIDES) {
    const dir = path.join(OUT, `${guide.num}_${guide.slug}`);
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    console.log(`\n${guide.title}`);

    const steps = [];
    for (const [i, step] of guide.steps.entries()) {
      const page = step.asAdmin ? admin : contributor;
      if (!step.asAdmin && !signedIn && step.slug !== 'sign-up') {
        await login(contributor, CONTRIBUTOR);
      }
      if (step.before) await step.before({ page, state });
      const shot = await shoot(page, dir, i + 1, step.slug, step.targetBox || step.target);
      if (step.after) await step.after({ page, state });
      if (step.slug === 'sign-up') signedIn = true;

      const fileUuid = uid(`guide-${guide.slug}-${step.slug}`);
      fs.writeFileSync(path.join(dir, shot.file + '.txt'),
        `Template: screenshot\n\n----\n\nAlt: ${step.alt}\n\n----\n\nUuid: ${fileUuid}\n`);
      steps.push({ ...step, ...shot, fileUuid });
      console.log(`  ${i + 1}. ${step.title}  [${shot.box}]`);
    }

    const structure = steps.map((s) => [
      '-',
      `  title: ${yaml(s.title)}`,
      `  text: ${yaml(s.text)}`,
      `  callout: ${yaml(s.callout)}`,
      `  image:\n    - file://${s.fileUuid}`,
      `  url: ${yaml(s.url)}`,
      `  box: ${yaml(s.box)}`,
    ].join('\n')).join('\n');

    const fields = {
      Title: guide.title, Intro: guide.intro, Audience: guide.audience, Duration: guide.duration,
      Cta: guide.cta, Ctalink: guide.ctaLink, Steps: '\n' + structure, Uuid: uid('guide-' + guide.slug),
    };
    fs.writeFileSync(path.join(dir, 'guide.txt'),
      Object.entries(fields).map(([k, v]) => `${k}: ${v}`).join('\n\n----\n\n') + '\n');
  }

  await browser.close();
  console.log('\nGuides written to', OUT);
})().catch((e) => { console.error(e); process.exit(1); });
