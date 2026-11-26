<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header', ['description' => 'Editor sign-in for ' . $site->title()]) ?>

<section class="login container">
  <div class="login__card">
    <p class="kicker">Editors only</p>
    <h1 class="login__title">Welcome <em>back</em>.</h1>
    <p class="login__intro">Sign in to write, edit and publish on <?= $site->title()->esc() ?>.</p>

    <?php if ($error): ?>
      <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form method="post" action="<?= $redirect !== '' ? url('login', ['query' => ['redirect' => $redirect]]) : url('login') ?>">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= esc($email, 'attr') ?>">
      </div>

      <div class="field">
        <label class="field__label" for="password">Password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required minlength="8">
      </div>

      <label class="check">
        <input type="checkbox" name="remember">
        <span>Keep me signed in for two weeks</span>
      </label>

      <button class="btn btn--accent btn--block" type="submit">Sign in →</button>
    </form>

    <p class="login__signup">New here? <a href="<?= url('signup') ?>">Become a contributor →</a></p>

    <p class="login__links">
      <a href="<?= $site->url() ?>">← Back to the site</a>
      <a href="<?= url('panel') ?>">Classic admin panel ↗</a>
    </p>
  </div>

  <svg class="login__seal" viewBox="0 0 120 120" aria-hidden="true">
    <defs><path id="login-circle" d="M60 60m-44 0a44 44 0 1 1 88 0a44 44 0 1 1-88 0"/></defs>
    <circle class="contents__stamp-bg" cx="60" cy="60" r="58"/>
    <text><textPath href="#login-circle" textLength="272" lengthAdjust="spacing">WRITE BOLDLY ✳ EDIT KINDLY ✳ </textPath></text>
    <circle class="contents__stamp-dot" cx="60" cy="60" r="16"/>
  </svg>
</section>

<?php snippet('footer', ['cta' => false]) ?>
