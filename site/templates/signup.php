<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header', ['description' => strip_tags(kti(t('signup.step1')))]) ?>

<section class="login signup container">
  <div class="signup__pitch">
    <p class="kicker"><?= t('signup.kicker') ?></p>
    <h1 class="signup__title"><?= kti(t('signup.title')) ?></h1>
    <ol class="signup__steps">
      <li><?= kti(t('signup.step1')) ?></li>
      <li><?= kti(t('signup.step2')) ?></li>
      <li><?= kti(t('signup.step3')) ?></li>
    </ol>
  </div>

  <div class="login__card">
    <?php if ($error): ?>
      <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form method="post" action="<?= lurl('signup') ?>" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <!-- honeypot: hidden from people, irresistible to bots -->
      <div class="hp" aria-hidden="true">
        <label for="website">Website</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label class="field__label" for="name"><?= t('signup.name') ?></label>
        <input class="input" id="name" name="name" type="text" autocomplete="name" required maxlength="80" value="<?= esc($values['name'], 'attr') ?>" autofocus>
        <p class="field__help"><?= t('signup.name.help') ?></p>
      </div>

      <div class="field">
        <label class="field__label" for="email"><?= t('field.email') ?></label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= esc($values['email'], 'attr') ?>">
      </div>

      <div class="field">
        <label class="field__label" for="password"><?= t('field.password') ?></label>
        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
        <p class="field__help"><?= t('signup.password.help') ?></p>
      </div>

      <button class="btn btn--accent btn--block" type="submit"><?= t('signup.submit') ?> →</button>
    </form>

    <p class="login__links">
      <span><?= t('signup.have') ?></span>
      <a href="<?= lurl('login') ?>"><?= t('signup.signin') ?></a>
    </p>
  </div>
</section>

<?php snippet('footer', ['cta' => false]) ?>
