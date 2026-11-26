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
      <?php elseif ($saved === 'draft'): ?>
        <div class="notice" role="status">Draft saved. Only signed-in editors can see it.</div>
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

      <div class="writer__body prose prose--story" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Story" data-placeholder="Tell your story…" data-editor><?= $values['text'] ?></div>
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
          <label class="field__label" for="author">Author</label>
          <select class="input" id="author" name="author">
            <?php foreach ($authors as $author): ?>
              <option value="<?= $author->id() ?>" <?= e($author->id() === $values['author'], 'selected') ?>><?= $author->title()->esc() ?></option>
            <?php endforeach ?>
          </select>
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
          <?php if ($story && $story->isListed()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish">Update story</button>
            <button class="btn btn--block" type="submit" name="action" value="unpublish">Unpublish</button>
          <?php else: ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish">Publish</button>
            <button class="btn btn--block" type="submit" name="action" value="draft">Save draft</button>
          <?php endif ?>
        </div>

        <?php if ($story): ?>
          <p class="publish-card__links">
            <a href="<?= $story->isListed() ? $story->url() : $story->previewUrl() ?>" target="_blank" rel="noopener"><?= $story->isListed() ? 'View live ↗' : 'Preview ↗' ?></a>
            <a href="<?= $story->panel()->url() ?>">Advanced settings (classic admin) ↗</a>
          </p>
        <?php endif ?>
      </div>

      <div class="drafts-card">
        <div class="drafts-card__head">
          <p class="kicker">Your desk</p>
          <a href="<?= url('write') ?>" class="drafts-card__new">+ New</a>
        </div>
        <?php if ($drafts->isNotEmpty()): ?>
          <p class="drafts-card__label">Drafts</p>
          <ul class="drafts-card__list">
            <?php foreach ($drafts as $draft): ?>
              <li <?= e($story && $draft->is($story), 'aria-current="true"') ?>>
                <a href="<?= url('write/' . $draft->slug()) ?>"><?= $draft->title()->esc() ?></a>
                <span><?= $draft->modified('j M') ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
        <p class="drafts-card__label">Recently published</p>
        <ul class="drafts-card__list">
          <?php foreach ($mine as $item): ?>
            <li <?= e($story && $item->is($story), 'aria-current="true"') ?>>
              <a href="<?= url('write/' . $item->slug()) ?>"><?= $item->title()->esc() ?></a>
              <span><?= $item->date()->toDate('j M') ?></span>
            </li>
          <?php endforeach ?>
        </ul>
      </div>
    </aside>
  </div>
</form>

<script src="<?= asset_url('assets/js/editor.js') ?>" defer></script>

<?php snippet('footer', ['cta' => false]) ?>
