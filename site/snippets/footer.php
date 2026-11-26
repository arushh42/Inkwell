  </main>

  <footer class="site-footer">
    <div class="container">
      <?php if ($cta ?? true): ?>
      <section class="footer-cta">
        <p class="kicker"><?= t('footer.cta.kicker') ?></p>
        <h2 class="footer-cta__title"><?= kti(t('footer.cta.title')) ?></h2>
        <a class="btn btn--accent" href="<?= $kirby->user() ? lurl('write') : lurl('signup') ?>"><?= $kirby->user() ? t('footer.cta.write') : t('footer.cta.join') ?> →</a>
      </section>
      <?php endif ?>

      <div class="site-footer__cols">
        <div>
          <p class="kicker"><?= t('footer.about') ?></p>
          <p class="site-footer__about"><?= $site->description()->or($site->tagline())->esc() ?></p>
        </div>
        <nav aria-label="<?= t('footer.read') ?>">
          <p class="kicker"><?= t('footer.read') ?></p>
          <?php foreach (['stories', 'papers', 'authors', 'about', 'guides'] as $slug): ?>
            <?php if ($item = page($slug)): ?><a href="<?= $item->url() ?>"><?= $item->title()->esc() ?></a><?php endif ?>
          <?php endforeach ?>
        </nav>
        <nav aria-label="<?= t('footer.follow') ?>">
          <p class="kicker"><?= t('footer.follow') ?></p>
          <a href="<?= lurl('feed.xml') ?>"><?= t('footer.rss') ?></a>
          <?php foreach ($site->social()->toStructure() as $link): ?>
            <a href="<?= $link->url()->esc('attr') ?>" rel="me noopener"><?= ucfirst($link->platform()->esc()) ?></a>
          <?php endforeach ?>
          <a href="<?= $kirby->user() ? lurl('write') : lurl('login') ?>"><?= $kirby->user() ? t('footer.cta.write') : t('footer.signin') ?></a>
        </nav>
      </div>
    </div>

    <p class="site-footer__wordmark" aria-hidden="true"><?= $site->title()->esc() ?></p>

    <div class="container site-footer__legal">
      <span>© <?= date('Y') ?> <?= $site->title()->esc() ?></span>
    </div>
  </footer>
</body>
</html>
