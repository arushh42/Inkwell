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
site/plugins/inkwell/         Page methods (readingTime, excerpt, bibtex…) and routes (feed, sitemap, .bib)
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

## Licensing note

Kirby is free to try locally and on test servers. A public production site needs a [Kirby licence](https://getkirby.com/buy). Demo content on this site is fictional; DOIs use the `10.5555` example prefix.
