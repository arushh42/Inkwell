<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header', ['description' => t('login.intro')]) ?>

<section class="login container">
  <div class="login__card">
    <p class="kicker"><?= t('login.kicker') ?></p>
    <h1 class="login__title"><?= kti(t('login.title')) ?></h1>
    <p class="login__intro"><?= t('login.intro') ?></p>

    <?php if ($error): ?>
      <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form method="post" action="<?= $redirect !== '' ? lurl('login', ['redirect' => $redirect]) : lurl('login') ?>">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="field">
        <label class="field__label" for="email"><?= t('field.email') ?></label>
        <input class="input" id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= esc($email, 'attr') ?>">
      </div>

      <div class="field">
        <label class="field__label" for="password"><?= t('field.password') ?></label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required minlength="8">
      </div>

      <label class="check">
        <input type="checkbox" name="remember">
        <span><?= t('login.remember') ?></span>
      </label>

      <button class="btn btn--accent btn--block" type="submit"><?= t('login.submit') ?> →</button>
    </form>

    <p class="login__signup"><?= t('login.new') ?> <a href="<?= lurl('signup') ?>"><?= t('login.signup') ?></a></p>

    <p class="login__links">
      <a href="<?= $site->url() ?>"><?= t('login.back') ?></a>
      <a href="<?= url('panel') ?>"><?= t('login.panel') ?></a>
    </p>
  </div>

  <svg class="login__seal" viewBox="0 0 120 120" aria-hidden="true">
    <defs><path id="login-circle" d="M60 60m-44 0a44 44 0 1 1 88 0a44 44 0 1 1-88 0"/></defs>
    <circle class="contents__stamp-bg" cx="60" cy="60" r="58"/>
    <text><textPath href="#login-circle" textLength="272" lengthAdjust="spacing"><?= t('login.stamp') ?></textPath></text>
    <circle class="contents__stamp-dot" cx="60" cy="60" r="16"/>
  </svg>
</section>

<?php snippet('footer', ['cta' => false]) ?>
