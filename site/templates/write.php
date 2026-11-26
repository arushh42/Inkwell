<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Page|null $story */
$status = match (true) {
    $story === null      => ['new', t('status.new')],
    $story->isListed()   => ['published', t('status.published')],
    $story->isUnlisted() => ['in-review', t('status.review')],
    default              => ['draft', t('status.draft')],
};
$action   = $story ? lurl('write/' . $story->slug()) : lurl('write');
$original = $versions[0] ?? null;
?>
<?php snippet('header', ['description' => t('header.write')]) ?>

<form class="writer" method="post" action="<?= $action ?>" enctype="multipart/form-data" data-writer>
  <input type="hidden" name="csrf" value="<?= csrf() ?>">

  <div class="container writer__layout">
    <div class="writer__main">
      <p class="writer__status">
        <span class="writer__dot writer__dot--<?= $status[0] ?>"></span>
        <?= $status[1] ?>
        <?php if ($story): ?> · <?= tt('status.saved', null, ['date' => inkwell_date($story->modified(), 'j M, H:i')]) ?><?php endif ?>
      </p>

      <?php if ($story): ?>
        <nav class="lang-bar" aria-label="<?= t('lang.version') ?>">
          <span class="lang-bar__label"><?= t('lang.version') ?>:</span>
          <?php foreach ($kirby->languages() as $language): ?>
            <?php $code = $language->code(); $exists = in_array($code, $versions, true) ?>
            <a href="<?= lurl('write/' . $story->slug(), [], $code) ?>" class="lang-bar__item <?= e($code === $lang, 'is-current') ?> <?= e(!$exists, 'is-missing') ?>" <?= e($code === $lang, 'aria-current="true"') ?>>
              <?= t('lang.' . $code) ?><?php if (!$exists): ?> <small>(<?= t('lang.missing') ?>)</small><?php endif ?>
            </a>
          <?php endforeach ?>
        </nav>
      <?php endif ?>

      <?php if ($error): ?>
        <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
      <?php elseif ($setupPending): ?>
        <div class="notice" role="status"><?= t('write.setup') ?></div>
      <?php elseif ($welcome): ?>
        <div class="notice" role="status"><?= esc(tt('write.welcome', null, ['name' => $kirby->user()->name()->value()])) ?></div>
      <?php elseif ($readOnly): ?>
        <div class="notice" role="status"><?= t('write.readonly') ?></div>
      <?php elseif ($saved === 'draft'): ?>
        <div class="notice" role="status"><?= t('saved.draft') ?></div>
      <?php elseif ($saved === 'submitted'): ?>
        <div class="notice" role="status"><?= $isEditor ? t('saved.submitted.editor') : t('saved.submitted') ?></div>
      <?php elseif ($saved === 'withdrawn'): ?>
        <div class="notice" role="status"><?= t('saved.withdrawn') ?></div>
      <?php elseif ($saved === 'unpublished'): ?>
        <div class="notice" role="status"><?= t('saved.unpublished') ?></div>
      <?php elseif ($translating && $original): ?>
        <div class="notice notice--soft" role="status"><?= esc(tt('lang.translating', null, ['language' => inkwell_lang_name($lang), 'original' => inkwell_lang_name($original)])) ?></div>
      <?php endif ?>

      <label class="visually-hidden" for="title"><?= t('write.title.placeholder') ?></label>
      <textarea class="writer__title" id="title" name="title" rows="1" placeholder="<?= esc(t('write.title.placeholder'), 'attr') ?>" required data-autogrow <?= e($story === null, 'autofocus') ?>><?= esc($values['title']) ?></textarea>

      <label class="visually-hidden" for="subtitle"><?= t('write.subtitle.placeholder') ?></label>
      <textarea class="writer__subtitle" id="subtitle" name="subtitle" rows="1" placeholder="<?= esc(t('write.subtitle.placeholder'), 'attr') ?>" data-autogrow><?= esc($values['subtitle']) ?></textarea>

      <div class="toolbar" role="toolbar" aria-label="<?= t('tb.label') ?>">
        <button type="button" data-cmd="h2" title="<?= t('tb.h2') ?>">H2</button>
        <button type="button" data-cmd="h3" title="<?= t('tb.h3') ?>">H3</button>
        <span class="toolbar__sep" aria-hidden="true"></span>
        <button type="button" data-cmd="bold" title="<?= t('tb.bold') ?>"><b>B</b></button>
        <button type="button" data-cmd="italic" title="<?= t('tb.italic') ?>"><i>I</i></button>
        <button type="button" data-cmd="link" title="<?= t('tb.link') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
          <span class="visually-hidden"><?= t('tb.link') ?></span>
        </button>
        <span class="toolbar__sep" aria-hidden="true"></span>
        <button type="button" data-cmd="quote" title="<?= t('tb.quote') ?>">“ ”</button>
        <button type="button" data-cmd="ul" title="<?= t('tb.ul') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>
          <span class="visually-hidden"><?= t('tb.ul') ?></span>
        </button>
        <button type="button" data-cmd="ol" title="<?= t('tb.ol') ?>">1.</button>
        <button type="button" data-cmd="code" title="<?= t('tb.code') ?>">{ }</button>
        <span class="toolbar__spacer"></span>
        <span class="toolbar__stats" data-stats></span>
      </div>

      <div class="writer__body prose prose--story" contenteditable="<?= $readOnly ? 'false' : 'true' ?>" role="textbox" aria-multiline="true" aria-label="<?= t('write.body.label') ?>" data-placeholder="<?= esc(t('write.body.placeholder'), 'attr') ?>" data-editor><?= $values['text'] ?></div>
      <textarea name="text" hidden data-editor-input></textarea>
    </div>

    <aside class="writer__aside">
      <div class="publish-card">
        <p class="kicker"><?= t('pub.kicker') ?></p>

        <fieldset class="field">
          <legend class="field__label"><?= t('pub.type') ?></legend>
          <div class="segmented">
            <label><input type="radio" name="kind" value="article" <?= e($values['kind'] !== 'blog', 'checked') ?>><span><?= t('kind.article') ?></span></label>
            <label><input type="radio" name="kind" value="blog" <?= e($values['kind'] === 'blog', 'checked') ?>><span><?= t('kind.blog') ?></span></label>
          </div>
        </fieldset>

        <div class="field">
          <?php if ($isEditor): ?>
            <label class="field__label" for="author"><?= t('pub.author') ?></label>
            <select class="input" id="author" name="author">
              <?php foreach ($authors as $author): ?>
                <option value="<?= $author->id() ?>" <?= e($author->id() === $values['author'], 'selected') ?>><?= $author->title()->esc() ?><?= $author->isDraft() ? ' ' . t('pub.newAuthor') : '' ?></option>
              <?php endforeach ?>
            </select>
          <?php else: ?>
            <span class="field__label"><?= t('pub.author') ?></span>
            <p class="writer__byline"><?php if ($myAuthor) snippet('avatar', ['author' => $myAuthor]) ?> <?= $myAuthor?->title()->esc() ?? esc($kirby->user()->name()) ?></p>
          <?php endif ?>
        </div>

        <div class="field">
          <label class="field__label" for="tags"><?= t('pub.tags') ?></label>
          <input class="input" id="tags" name="tags" value="<?= esc($values['tags'], 'attr') ?>" placeholder="<?= esc(t('pub.tags.placeholder'), 'attr') ?>" list="tag-suggestions">
          <datalist id="tag-suggestions">
            <?php foreach (page('stories')->children()->pluck('tags', ',', true) as $tag): ?>
              <option value="<?= esc($tag, 'attr') ?>">
            <?php endforeach ?>
          </datalist>
          <p class="field__help"><?= t('pub.tags.help') ?></p>
        </div>

        <div class="field">
          <span class="field__label"><?= t('pub.cover') ?></span>
          <label class="dropzone <?= e($cover, 'has-image') ?>" data-dropzone>
            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/avif" data-cover-input>
            <img src="<?= $cover ? $cover->resize(600)->url() : '' ?>" alt="" data-cover-preview <?= e(!$cover, 'hidden') ?>>
            <span class="dropzone__hint">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/></svg>
              <?= $cover ? t('pub.cover.replace') : t('pub.cover.add') ?>
            </span>
          </label>
          <p class="field__help"><?= t('pub.cover.help') ?></p>
        </div>

        <div class="publish-card__actions">
          <?php if ($readOnly): ?>
            <a class="btn btn--block" href="<?= $story->url() ?>"><?= t('btn.viewStory') ?></a>
          <?php elseif ($isEditor && $story && $story->isListed()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= t('btn.update') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="unpublish"><?= t('btn.unpublish') ?></button>
          <?php elseif ($isEditor): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= $story && $story->isUnlisted() ? t('btn.approve') : t('btn.publish') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft"><?= $story && $story->isUnlisted() ? t('btn.save') : t('btn.saveDraft') ?></button>
          <?php elseif ($story && $story->isUnlisted()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit"><?= t('btn.saveChanges') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="withdraw"><?= t('btn.withdraw') ?></button>
          <?php else: ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit"><?= t('btn.submit') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft"><?= t('btn.saveDraft') ?></button>
          <?php endif ?>
        </div>

        <?php if (!$isEditor && !$readOnly): ?>
          <p class="field__help publish-card__note"><?= t('pub.note.story') ?></p>
        <?php endif ?>
        <?php if ($guide = page('guides/write-a-story')): ?>
          <p class="publish-card__guide"><?= t('pub.guide.q') ?> <a href="<?= $guide->url() ?>"><?= t('pub.guide.link') ?></a></p>
        <?php endif ?>

        <?php if ($story): ?>
          <p class="publish-card__links">
            <a href="<?= $story->isDraft() ? $story->previewUrl() : $story->url() ?>" target="_blank" rel="noopener"><?= $story->isListed() ? t('pub.live') : t('pub.preview') ?></a>
            <?php if ($isEditor): ?>
              <a href="<?= $story->panel()->url() ?>"><?= t('pub.advanced') ?></a>
            <?php endif ?>
          </p>
        <?php endif ?>
      </div>

      <div class="drafts-card">
        <div class="drafts-card__head">
          <p class="kicker"><?= $isEditor ? t('desk.stories.editor') : t('desk.stories') ?></p>
          <a href="<?= lurl('write') ?>" class="drafts-card__new"><?= t('desk.new') ?></a>
        </div>
        <?php
        $groups = [
            ['label' => $isEditor ? t('desk.awaiting') : t('desk.submitted'), 'items' => $inReview, 'count' => $isEditor, 'date' => 'modified'],
            ['label' => t('desk.drafts'), 'items' => $drafts, 'count' => false, 'date' => 'modified'],
            ['label' => $isEditor ? t('desk.recent') : t('desk.published'), 'items' => $published, 'count' => false, 'date' => 'date'],
        ];
        $empty = true;
        ?>
        <?php foreach ($groups as $group): ?>
          <?php if ($group['items']->isEmpty()) continue; $empty = false; ?>
          <p class="drafts-card__label"><?= $group['label'] ?><?php if ($group['count']): ?> <span class="count"><?= $group['items']->count() ?></span><?php endif ?></p>
          <ul class="drafts-card__list">
            <?php foreach ($group['items'] as $item): ?>
              <li <?= e($story && $item->is($story), 'aria-current="true"') ?>>
                <a href="<?= lurl('write/' . $item->slug()) ?>"><?= $item->title()->esc() ?></a>
                <span><?= $group['date'] === 'date' ? inkwell_date($item->date(), 'j M') : inkwell_date($item->modified(), 'j M') ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endforeach ?>
        <?php if ($empty): ?>
          <p class="drafts-card__empty"><?= t('desk.empty') ?></p>
        <?php endif ?>
      </div>
    </aside>
  </div>
</form>

<script>window.INKWELL_I18N = <?= json_encode([
    'wordsOne'  => t('js.words.one'),
    'wordsOther'=> t('js.words.other'),
    'minRead'   => t('minRead'),
    'link'      => t('js.link'),
    'linkBad'   => t('js.link.invalid'),
    'imageBig'  => t('js.image.big'),
    'empty'     => t('js.empty'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= asset_url('assets/js/editor.js') ?>" defer></script>

<?php snippet('footer', ['cta' => false]) ?>
