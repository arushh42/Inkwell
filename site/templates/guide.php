<?php
/** @var Kirby\Cms\Page $page */
$steps = $page->steps()->toStructure();
$total = $steps->count();
?>
<?php snippet('header') ?>

<article class="guide container section">
  <header class="guide__head">
    <a class="guide__back" href="<?= $page->parent()->url() ?>">← All guides</a>
    <p class="kicker">Guide<?php if ($page->duration()->isNotEmpty()): ?> · <?= $page->duration()->esc() ?><?php endif ?> · <?= $total ?> steps</p>
    <h1 class="page-head__title"><?= $page->title()->esc() ?></h1>
    <p class="page-head__sub"><?= $page->intro()->esc() ?></p>
    <?php if ($page->audience()->isNotEmpty()): ?>
      <p class="guide__audience">For: <?= $page->audience()->esc() ?></p>
    <?php endif ?>
  </header>

  <div class="tour" data-tour>
    <div class="tour__progress" aria-hidden="true" hidden data-progress>
      <?php for ($i = 0; $i < $total; $i++): ?>
        <button type="button" class="tour__bar" data-go="<?= $i ?>" tabindex="-1"><span></span></button>
      <?php endfor ?>
    </div>

    <ol class="tour__steps">
      <?php foreach ($steps->values() as $i => $step): ?>
        <?php
        $img = $step->image()->toFile();
        [$x, $y, $w, $h] = array_map('floatval', array_pad(explode(',', $step->box()->value()), 4, 0));
        $above = $y + $h > 72;                 // put the callout above boxes near the bottom
        $right = $x + $w / 2 > 62;             // and right-align it on the right-hand side
        ?>
        <li class="tour__step" id="step-<?= $i + 1 ?>" data-step>
          <figure class="tour__shot">
            <div class="browser">
              <div class="browser__bar" aria-hidden="true">
                <span class="browser__dots"><i></i><i></i><i></i></span>
                <span class="browser__url"><?= $step->url()->esc() ?></span>
              </div>
              <div class="browser__screen">
                <?php if ($img): ?>
                  <img src="<?= $img->resize(1600)->url() ?>"
                       srcset="<?= $img->srcset([800, 1280, 1600, 2560]) ?>"
                       sizes="(min-width: 1100px) 860px, 100vw"
                       width="<?= $img->width() ?>" height="<?= $img->height() ?>"
                       alt="<?= $img->alt()->esc('attr') ?>" <?= e($i > 0, 'loading="lazy"') ?>>
                <?php endif ?>
                <?php if ($w > 0): ?>
                  <div class="tour__box" style="left:<?= $x ?>%;top:<?= $y ?>%;width:<?= $w ?>%;height:<?= $h ?>%" aria-hidden="true">
                    <span class="tour__badge"><?= $i + 1 ?></span>
                    <?php if ($step->callout()->isNotEmpty()): ?>
                      <span class="tour__callout <?= $above ? 'is-above' : 'is-below' ?> <?= e($right, 'is-right') ?>"><?= $step->callout()->esc() ?></span>
                    <?php endif ?>
                  </div>
                <?php endif ?>
              </div>
            </div>
          </figure>

          <div class="tour__text">
            <p class="tour__count">Step <?= $i + 1 ?> of <?= $total ?></p>
            <h2 class="tour__title"><?= $step->title()->esc() ?></h2>
            <div class="tour__body"><?= $step->text()->kt() ?></div>
            <div class="tour__nav" hidden data-nav>
              <button type="button" class="btn" data-prev <?= e($i === 0, 'disabled') ?>>← Back</button>
              <?php if ($i < $total - 1): ?>
                <button type="button" class="btn btn--accent" data-next>Next step →</button>
              <?php elseif ($page->cta()->isNotEmpty()): ?>
                <a class="btn btn--accent" href="<?= url($page->ctaLink()->or('/')->value()) ?>"><?= $page->cta()->esc() ?> →</a>
              <?php endif ?>
            </div>
            <p class="tour__hint" hidden data-hint>Tip: use the ← → arrow keys<span class="tour__hint-touch"> or swipe</span>.</p>
          </div>
        </li>
      <?php endforeach ?>
    </ol>
  </div>

  <?php if ($page->cta()->isNotEmpty()): ?>
    <div class="guide__cta" data-cta>
      <p>Ready to try it yourself?</p>
      <a class="btn btn--accent" href="<?= url($page->ctaLink()->or('/')->value()) ?>"><?= $page->cta()->esc() ?> →</a>
    </div>
  <?php endif ?>

  <?php if ($other = $page->siblings(false)->listed()->first()): ?>
    <p class="guide__next">Next guide: <a href="<?= $other->url() ?>"><?= $other->title()->esc() ?> →</a></p>
  <?php endif ?>
</article>

<script src="<?= asset_url('assets/js/guide.js') ?>" defer></script>

<?php snippet('footer') ?>
