# Inkwell — a Medium-style publishing platform on Kirby CMS

Inkwell is a content platform for **articles, blog posts and research papers**, built in **PHP** on the flat-file CMS [Kirby](https://getkirby.com) and deployable to **Vercel**.

Editors sign in to the Kirby Panel, write in a block editor, attach PDFs, and publish with one click. Readers get a fast, typography-first site with search, tag filters, author pages, RSS and one-click BibTeX citations.

## Features

**For readers**
- Home page with featured stories, latest stories and recent research
- Stories (articles and blog posts): reading time, cover images, tags, author box, "more from this author"
- Research papers: abstract, authors and external co-authors, venue, DOI link, PDF download, BibTeX export (`/papers/<slug>.bib`)
- Author profiles listing each author's stories and papers
- Full-text search across stories, papers and authors
- Filter by tag (`/stories/tag:PHP`), by type (`/stories/kind:blog`) and by paper keyword
- RSS feed (`/feed.xml`), XML sitemap (`/sitemap.xml`), Open Graph meta tags
- Responsive layout, dark mode, accessible markup (skip link, landmarks, focus styles)

**For editors: the writing studio at `/write`**
- A Medium-style editor inside the site design: headline, subtitle, and a rich-text body with a formatting toolbar and Markdown shortcuts (`## `, `> `, `- `, `1. `)
- Type, author, tags and a cover image upload in a sidebar, with Save draft / Publish / Unpublish buttons
- Live word count and reading time, an "Edit this story" link on every story, and a "Your desk" list of drafts
- Pasted HTML is sanitized and stored as native Kirby blocks, so the Panel and the studio edit the same content

**For contributors: sign-up and editorial review**
- Anyone can sign up at `/signup` to become a **Contributor**: name, email and password, protected by a honeypot field, a minimum fill-in time and a rate limit of 5 sign-ups per hour per network
- Contributors write in the studio and **submit for review**. They can only open their own stories, can't publish, and can't edit a story once it's live
- Editors and admins see an **Awaiting review** queue in the studio and the Panel, and approve stories with one click
- Stories in review stay private (404 for everyone except the author and editors), so a contributor can't bypass review by sharing a link
- A contributor's author profile stays hidden until their first story is published
- Contributors have no Kirby permissions, no Panel access and no API access. Every action goes through the studio, which checks ownership and then writes as the system user

**Add a paper from its DOI (`/add-paper`)**
- Paste a DOI in any form (`10.1038/…`, `doi:…`, `https://doi.org/…`) and the title, authors, journal, volume, pages, date, arXiv preprint and abstract are filled in automatically
- Uses only free, keyless APIs: doi.org content negotiation (Crossref and DataCite) plus the arXiv API. The lookup runs server-side, only contacts these fixed hosts, is cached for a day and rate-limited per user
- **Licence-aware:** abstracts are only copied when they may be republished (CC BY, CC BY-SA, CC0, DataCite or arXiv metadata). Otherwise the member writes a short description, which is credited to them. The source is shown on the paper page
- Citation superscripts are stripped from abstracts, while powers of ten such as 10^6 are kept
- Duplicate DOIs are detected, with a link back to the existing paper or your own draft
- Same workflow as stories: contributors submit for review and editors publish. Works without JavaScript too (server-side "Fetch details")

**Illustrated guides (`/guides`)**
- Step-by-step how-tos for writing a story and adding a paper, aimed at non-technical contributors, plus an FAQ
- Each step shows a real screenshot with a spotlight on exactly where to click: a numbered marker and a callout, drawn in HTML/CSS over the image rather than baked into it
- Story-style stepper with progress bars, Back/Next, arrow keys, swipe and `#step-N` deep links. Without JavaScript the steps show as a simple list, and all instructions are real text (screen-reader friendly)
- Linked from the header and from the publishing panels of the writing studio and Add a paper

**Bilingual: German and English**
- German is the default language and English a translation. Every page lives under `/de/…` or `/en/…`, and the bare domain redirects visitors to their browser's language. Old unprefixed links are redirected too (301)
- All interface text comes from `site/translations/de.php` and `en.php` (about 280 strings). Dates are localised ("26. November 2026")
- A **DE | EN** switch in the header always goes to the same page in the other language
- **Single-language pages** (as described in the technical proposal): if a story exists in only one language, the other URL shows the original text with a note ("Diese Seite gibt es nur auf Englisch."). That URL is `noindex` with a canonical pointing to the original, and it's left out of the sitemap and that language's RSS feed. `hreflang` links only name versions that exist
- Fields that are the same in both languages (date, DOI, author, cover, abstract, …) are entered once. Only the text is translated
- The writing studio and Add a paper show which language versions exist and let authors add the missing one. When a German version is added to an English-only story, the English text is moved to its own file first, so nothing is overwritten (`inkwell_save_version()` in `site/plugins/inkwell/i18n.php`)
- The guides exist in both languages, with German screenshots of the German interface

**For editors: the Kirby Panel at `/panel`** (for everything else: papers, authors, users, settings)
- A dashboard showing drafts, recently published stories, latest papers and counts
- A draft → in review → published workflow
- A block editor with headings, text, lists, quotes, images, galleries, code and video
- Role-based access: **Admin** (everything) and **Editor** (content only, no users or system settings)

## Design

A "modern literary magazine" identity: warm paper and ink, one vermilion accent, a highlighter yellow, and bold Bauhaus-style cover art.

- **Type:** Fraunces (a variable display serif, using its soft and "wonky" axes), Instrument Sans for the interface and JetBrains Mono for metadata. All fonts are self-hosted, so the site makes no third-party font requests (GDPR-friendly).
- **Details:** a newspaper-style date strip, a rotating seal, a scrolling topics ticker, numbered story lists, drop caps, § section marks, an index-card abstract for papers, hard offset shadows, paper grain and a reading-progress bar. The progress bar uses CSS scroll-driven animations, so it needs no JavaScript.
- **Accessibility:** light and dark themes follow the system setting, and all motion respects `prefers-reduced-motion`.

## Tech stack

| Layer | Choice |
| --- | --- |
| Language | PHP 8.2 – 8.5 |
| CMS | Kirby 5.6 (flat files, no database) |
| Frontend | Kirby PHP templates and snippets, hand-written CSS, no build step |
| Hosting | Vercel via the community runtime [`vercel-php`](https://github.com/vercel-community/php) |
| Persistence on Vercel | Panel edits auto-committed to GitHub → automatic redeploy |
| Sessions on Vercel | Upstash Redis (free tier) |

## Project structure

```
api/index.php                 Vercel entry point (serverless adapter)
index.php                     Local / classic hosting entry point
content/                      All content as folders + .txt files (this is the "database")
site/blueprints/              Panel forms: site, pages (story, paper, author…), files, users
site/templates/               Page templates (home, stories, story, papers, paper, authors…)
site/snippets/                Header, footer, cards, byline, pagination, RSS, sitemap
site/controllers/             Filtering, pagination and search logic
site/plugins/inkwell/         Page methods (readingTime, excerpt, bibtex…), routes (feed, sitemap, .bib), language helpers (i18n.php)
site/languages/               German (default, /de) and English (/en)
site/translations/            All interface text in German and English
site/plugins/vercel/          Serverless compatibility (image fallback, Redis session store)
site/plugins/github-sync/     Commits Panel edits to GitHub when running serverless
assets/css/main.css           All styles
```

## Run locally

Requirements: PHP 8.2 or newer and Composer.

```bash
composer install
composer start          # → http://localhost:8000
```

Open <http://localhost:8000/panel> and create the first admin account. Browser-based installation of the first account only works on localhost.

## Deploy to Vercel

1. Push the repo to GitHub and import it in Vercel. No framework preset is needed; `vercel.json` configures the PHP runtime.
2. Generate a password hash locally:
   ```bash
   php -r 'echo password_hash("choose-a-strong-password", PASSWORD_DEFAULT), PHP_EOL;'
   ```
3. Add environment variables (see `.env.example`): `KIRBY_ADMIN_EMAIL`, `KIRBY_ADMIN_NAME`, `KIRBY_ADMIN_PASSWORD_HASH` (or a plain `KIRBY_ADMIN_PASSWORD` marked *Secret*), and optionally `SITE_URL`. Values are trimmed, and the account is refreshed whenever they change.
4. Create a [fine-grained GitHub token](https://github.com/settings/personal-access-tokens/new) with access to **only this repository** and the permission **Contents: Read and write**. Add it as `GITHUB_SYNC_TOKEN`. Repository and branch are detected from Vercel's system variables; override them with `GITHUB_SYNC_REPO` / `GITHUB_SYNC_BRANCH` if needed.
5. Add the **Upstash Redis** integration from the Vercel Marketplace (free tier). It sets `KV_REST_API_URL` / `KV_REST_API_TOKEN`, and Panel logins then stay valid across serverless instances.

### How Kirby runs on a serverless platform

Kirby expects a writable, persistent disk. Vercel functions have a read-only file system and a small, per-instance `/tmp`. `api/index.php` bridges the gap:

- Writable roots (`media`, `sessions`, `cache`, `accounts`, `content`) are pointed at `/tmp`. Committed content is copied there on a cold start.
- Panel assets and generated media are streamed straight from disk without booting Kirby.
- The admin account is provisioned from environment variables, so no password hash is ever committed.
- `vercel.json` routes only `/assets/*` as static files. `/content` and `/site` are never publicly reachable.
- The `vercel-php` runtime ships without GD, so images are served at their original size instead of failing.

### How member accounts work on serverless

Kirby stores user accounts as files, and on Vercel those files live in each instance's private `/tmp`. Accounts created by sign-ups are therefore **mirrored to Upstash Redis** (`site/plugins/vercel/src/AccountMirror.php`):

- After any account change, the changed files are uploaded and a version counter is bumped.
- Before Kirby boots, an instance downloads accounts if the version has changed. This only happens on requests that can involve a signed-in user, so anonymous readers cost no extra Redis calls.
- Each instance remembers what it last synced, so an account is only deleted when it really was deleted, not because an instance hadn't seen it yet.
- File modification times are preserved. Kirby ends any session that started before the password file's mtime, so a fresh mtime would log members out everywhere. For the same reason, the environment-provisioned admin's password file gets a fixed timestamp.
- Password hashes live only in the private Redis database, never in the public Git repository.

### How Panel edits are persisted (GitHub sync)

Edits in `/tmp` would disappear on the next cold start, so `site/plugins/github-sync` makes Git the database:

```
Editor saves in Panel ──▶ Kirby writes /tmp/content ──▶ hook diffs /tmp vs. last synced state
        ▲                                                         │
        │                                             one atomic commit via GitHub's Git Data API
        │                                             (blobs → tree → commit → ref, rebased on HEAD)
        │                                                         ▼
  new deployment  ◀────────── Vercel builds on push ◀──── content/ updated in the repo
```

- Every Panel action (create, update, publish, upload, rename, reorder, delete) produces **one commit**, with a message such as *Update page "…" — Published from the Kirby Panel by Jane*.
- Saves that change nothing don't create commits. Kirby's unsaved-changes and lock files (`_changes/`) are never committed.
- If GitHub is unreachable, the editor sees an error and the change is included with the next successful save.
- Locally the plugin does nothing, because Kirby already writes into your Git working copy. Commit as usual.
- Trade-off: an edit is visible immediately on the instance that handled it, and everywhere else after the redeploy (typically under a minute).

## Regenerating the guides

The guide screenshots and highlight positions are produced automatically. `tools/guides/capture.cjs` drives a real browser through each task: signing up, writing and submitting a story, adding a paper from a DOI, and approving as an editor. It screenshots every step and measures the on-screen position of the button or field to highlight. After design changes, run it against a **throwaway** instance, because it creates accounts and content:

```bash
# 1. start a disposable copy of the site (content is copied to a temp folder)
TMPDIR=$(mktemp -d) KIRBY_ADMIN_EMAIL=editor@example.com KIRBY_ADMIN_PASSWORD=guide-admin-123 \
  SITE_URL=http://localhost:8020 php -S localhost:8020 api/index.php

# 2. in another terminal (one language per run, each against a fresh instance)
cd tools/guides && npm install          # puppeteer-core only; uses your installed Chrome
LANG_CODE=de BASE=http://localhost:8020 ADMIN_EMAIL=editor@example.com ADMIN_PASSWORD=guide-admin-123 node capture.cjs
# restart the disposable instance (fresh TMPDIR), then:
LANG_CODE=en BASE=http://localhost:8020 ADMIN_EMAIL=editor@example.com ADMIN_PASSWORD=guide-admin-123 node capture.cjs
```

Each run rewrites that language's screenshots (`*-de.webp`, `*-en.webp`) and `guide.de.txt` / `guide.en.txt` in `content/5_guides/*`. The German run also writes the shared fields. Step wording lives in `capture.cjs` and can also be edited in the Panel, but Panel edits are overwritten by the next regeneration. The `tools/` folder is excluded from Vercel uploads.

## Licensing note

Kirby is free to try locally and on test servers. A public production site needs a [Kirby licence](https://getkirby.com/buy). The stories and author profiles are fictional demo content. The **papers are real**: five landmark machine-learning papers published in *Nature* in 2024–25, credited to their actual authors and linked to the publications by DOI. Their abstracts are reproduced under CC BY 4.0, or for Aurora from arXiv's CC0 metadata, and each page states its source.
