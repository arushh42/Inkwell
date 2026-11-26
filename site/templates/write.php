<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Page|null $story */
$status = match (true) {
    $story === null      => 'New story',
    $story->isListed()   => 'Published',
    $story->isUnlisted() => 'In review',
    default              => 'Draft',
};
$action = $story ? url('write/' . $story->slug()) : url('write');
?>
<?php snippet('header', ['description' => 'Write a story for ' . $site->title()]) ?>

<form class="writer" method="post" action="<?= $action ?>" enctype="multipart/form-data" data-writer>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">

  <div class="container writer__layout">
    <div class="writer__main">
      <p class="writer__status">
        <span class="writer__dot writer__dot--<?= Kirby\Toolkit\Str::slug($status) ?>"></span>
        <?= $status ?>
        <?php if ($story): ?> · last saved <?= $story->modified('j M, H:i') ?><?php endif ?>
      </p>

      <?php if ($error): ?>
        <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
      <?php elseif ($setupPending): ?>
        <div class="notice" role="status">We’re still setting up your author profile — this takes about a minute. You can start writing now; saving will work once it’s ready (just refresh this page in a moment).</div>
      <?php elseif ($welcome): ?>
        <div class="notice" role="status">Welcome to <?= $site->title()->esc() ?>, <?= $kirby->user()->name()->esc() ?>! Write your first story below. When it’s ready, submit it for review and an editor will publish it.</div>
      <?php elseif ($readOnly): ?>
        <div class="notice" role="status">This story is published. If it needs changes, ask an editor.</div>
      <?php elseif ($saved === 'draft'): ?>
        <div class="notice" role="status">Draft saved. Only you<?= $isEditor ? ' and other editors' : ' and the editors' ?> can see it.</div>
      <?php elseif ($saved === 'submitted'): ?>
        <div class="notice" role="status"><?= $isEditor ? 'Story moved to “In review”.' : 'Submitted for review! An editor will read it and publish it. You can keep editing until then.' ?></div>
      <?php elseif ($saved === 'withdrawn'): ?>
        <div class="notice" role="status">Submission withdrawn. The story is a draft again.</div>
      <?php elseif ($saved === 'unpublished'): ?>
        <div class="notice" role="status">Story unpublished and moved back to drafts.</div>
      <?php endif ?>

      <label class="visually-hidden" for="title">Headline</label>
      <textarea class="writer__title" id="title" name="title" rows="1" placeholder="Headline" required data-autogrow <?= e($story === null, 'autofocus') ?>><?= esc($values['title']) ?></textarea>

      <label class="visually-hidden" for="subtitle">Subtitle</label>
      <textarea class="writer__subtitle" id="subtitle" name="subtitle" rows="1" placeholder="Add a subtitle that hooks the reader…" data-autogrow><?= esc($values['subtitle']) ?></textarea>

      <div class="toolbar" role="toolbar" aria-label="Formatting">
        <button type="button" data-cmd="h2" title="Heading">H2</button>
        <button type="button" data-cmd="h3" title="Subheading">H3</button>
        <span class="toolbar__sep" aria-hidden="true"></span>
        <button type="button" data-cmd="bold" title="Bold (⌘B)"><b>B</b></button>
        <button type="button" data-cmd="italic" title="Italic (⌘I)"><i>I</i></button>
        <button type="button" data-cmd="link" title="Link">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
          <span class="visually-hidden">Link</span>
        </button>
        <span class="toolbar__sep" aria-hidden="true"></span>
        <button type="button" data-cmd="quote" title="Quote">“ ”</button>
        <button type="button" data-cmd="ul" title="Bulleted list">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>
          <span class="visually-hidden">Bulleted list</span>
        </button>
        <button type="button" data-cmd="ol" title="Numbered list">1.</button>
        <button type="button" data-cmd="code" title="Code block">{ }</button>
        <span class="toolbar__spacer"></span>
        <span class="toolbar__stats" data-stats>0 words</span>
      </div>

      <div class="writer__body prose prose--story" contenteditable="<?= $readOnly ? 'false' : 'true' ?>" role="textbox" aria-multiline="true" aria-label="Story" data-placeholder="Tell your story…" data-editor><?= $values['text'] ?></div>
      <textarea name="text" hidden data-editor-input></textarea>
    </div>

    <aside class="writer__aside">
      <div class="publish-card">
        <p class="kicker">Publishing</p>

        <fieldset class="field">
          <legend class="field__label">Type</legend>
          <div class="segmented">
            <label><input type="radio" name="kind" value="article" <?= e($values['kind'] !== 'blog', 'checked') ?>><span>Article</span></label>
            <label><input type="radio" name="kind" value="blog" <?= e($values['kind'] === 'blog', 'checked') ?>><span>Blog post</span></label>
          </div>
        </fieldset>

        <div class="field">
          <?php if ($isEditor): ?>
            <label class="field__label" for="author">Author</label>
            <select class="input" id="author" name="author">
              <?php foreach ($authors as $author): ?>
                <option value="<?= $author->id() ?>" <?= e($author->id() === $values['author'], 'selected') ?>><?= $author->title()->esc() ?><?= $author->isDraft() ? ' (new contributor)' : '' ?></option>
              <?php endforeach ?>
            </select>
          <?php else: ?>
            <span class="field__label">Author</span>
            <p class="writer__byline"><?php if ($myAuthor) snippet('avatar', ['author' => $myAuthor]) ?> <?= $myAuthor?->title()->esc() ?? esc($kirby->user()->name()) ?></p>
          <?php endif ?>
        </div>

        <div class="field">
          <label class="field__label" for="tags">Tags</label>
          <input class="input" id="tags" name="tags" value="<?= esc($values['tags'], 'attr') ?>" placeholder="Design, Open science" list="tag-suggestions">
          <datalist id="tag-suggestions">
            <?php foreach (page('stories')->children()->pluck('tags', ',', true) as $tag): ?>
              <option value="<?= esc($tag, 'attr') ?>">
            <?php endforeach ?>
          </datalist>
          <p class="field__help">Separate with commas.</p>
        </div>

        <div class="field">
          <span class="field__label">Cover image</span>
          <label class="dropzone <?= e($cover, 'has-image') ?>" data-dropzone>
            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/avif" data-cover-input>
            <img src="<?= $cover ? $cover->resize(600)->url() : '' ?>" alt="" data-cover-preview <?= e(!$cover, 'hidden') ?>>
            <span class="dropzone__hint">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/></svg>
              <?= $cover ? 'Replace image' : 'Add a cover image' ?>
            </span>
          </label>
          <p class="field__help">JPG, PNG, WebP or AVIF, up to 4&nbsp;MB.</p>
        </div>

        <div class="publish-card__actions">
          <?php if ($readOnly): ?>
            <a class="btn btn--block" href="<?= $story->url() ?>">View published story</a>
          <?php elseif ($isEditor && $story && $story->isListed()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish">Update story</button>
            <button class="btn btn--block" type="submit" name="action" value="unpublish">Unpublish</button>
          <?php elseif ($isEditor): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= $story && $story->isUnlisted() ? 'Approve &amp; publish' : 'Publish' ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft">Save <?= $story && $story->isUnlisted() ? '' : 'draft' ?></button>
          <?php elseif ($story && $story->isUnlisted()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit">Save changes</button>
            <button class="btn btn--block" type="submit" name="action" value="withdraw">Withdraw submission</button>
          <?php else: ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit">Submit for review</button>
            <button class="btn btn--block" type="submit" name="action" value="draft">Save draft</button>
          <?php endif ?>
        </div>

        <?php if (!$isEditor && !$readOnly): ?>
          <p class="field__help publish-card__note">An editor reviews every story before it goes live.</p>
        <?php endif ?>
        <?php if ($guide = page('guides/write-a-story')): ?>
          <p class="publish-card__guide">New here? <a href="<?= $guide->url() ?>">Read the step-by-step guide →</a></p>
        <?php endif ?>

        <?php if ($story): ?>
          <p class="publish-card__links">
            <a href="<?= $story->isDraft() ? $story->previewUrl() : $story->url() ?>" target="_blank" rel="noopener"><?= $story->isListed() ? 'View live ↗' : 'Preview ↗' ?></a>
            <?php if ($isEditor): ?>
              <a href="<?= $story->panel()->url() ?>">Advanced settings (classic admin) ↗</a>
            <?php endif ?>
          </p>
        <?php endif ?>
      </div>

      <div class="drafts-card">
        <div class="drafts-card__head">
          <p class="kicker">Your desk</p>
          <a href="<?= url('write') ?>" class="drafts-card__new">+ New</a>
        </div>
        <?php
        $groups = [
            ($isEditor ? 'Awaiting review' : 'Submitted for review') => [$inReview, 'modified'],
            'Drafts'             => [$drafts, 'modified'],
            ($isEditor ? 'Recently published' : 'Published') => [$published, 'date'],
        ];
        $empty = true;
        ?>
        <?php foreach ($groups as $label => [$items, $dateField]): ?>
          <?php if ($items->isEmpty()) continue; $empty = false; ?>
          <p class="drafts-card__label"><?= $label ?><?php if ($label === 'Awaiting review'): ?> <span class="count"><?= $items->count() ?></span><?php endif ?></p>
          <ul class="drafts-card__list">
            <?php foreach ($items as $item): ?>
              <li <?= e($story && $item->is($story), 'aria-current="true"') ?>>
                <a href="<?= url('write/' . $item->slug()) ?>"><?= $item->title()->esc() ?></a>
                <span><?= $dateField === 'date' ? $item->date()->toDate('j M') : $item->modified('j M') ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endforeach ?>
        <?php if ($empty): ?>
          <p class="drafts-card__empty">Nothing here yet — your stories will show up as you write them.</p>
        <?php endif ?>
      </div>
    </aside>
  </div>
</form>

<script src="<?= asset_url('assets/js/editor.js') ?>" defer></script>

<?php snippet('footer', ['cta' => false]) ?>
