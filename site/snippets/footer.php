  </main>

  <footer class="site-footer">
    <div class="container">
      <?php if ($cta ?? true): ?>
      <section class="footer-cta">
        <p class="kicker">Write for us</p>
        <h2 class="footer-cta__title">Have something worth reading <em>slowly</em>?</h2>
        <a class="btn btn--accent" href="<?= page('about') ? page('about')->url() : url('panel') ?>">Pitch a story →</a>
      </section>
      <?php endif ?>

      <div class="site-footer__cols">
        <div>
          <p class="kicker">About</p>
          <p class="site-footer__about"><?= $site->description()->or($site->tagline())->esc() ?></p>
        </div>
        <nav aria-label="Sections">
          <p class="kicker">Read</p>
          <?php foreach (['stories', 'papers', 'authors', 'about'] as $slug): ?>
            <?php if ($item = page($slug)): ?><a href="<?= $item->url() ?>"><?= $item->title()->esc() ?></a><?php endif ?>
          <?php endforeach ?>
        </nav>
        <nav aria-label="Elsewhere">
          <p class="kicker">Follow</p>
          <a href="<?= url('feed.xml') ?>">RSS feed</a>
          <?php foreach ($site->social()->toStructure() as $link): ?>
            <a href="<?= $link->url()->esc('attr') ?>" rel="me noopener"><?= ucfirst($link->platform()->esc()) ?></a>
          <?php endforeach ?>
          <a href="<?= $kirby->user() ? url('write') : url('login') ?>"><?= $kirby->user() ? 'Write a story' : 'Editor login' ?></a>
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
