<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header', ['description' => 'Become a contributor to ' . $site->title()]) ?>

<section class="login signup container">
  <div class="signup__pitch">
    <p class="kicker">Write for <?= $site->title()->esc() ?></p>
    <h1 class="signup__title">Become a <em>contributor</em>.</h1>
    <ol class="signup__steps">
      <li><b>Sign up</b> — it takes thirty seconds.</li>
      <li><b>Write</b> your story in our distraction-free studio.</li>
      <li><b>Submit</b> it for review. An editor reads every piece before it goes live.</li>
    </ol>
  </div>

  <div class="login__card">
    <?php if ($error): ?>
      <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form method="post" action="<?= url('signup') ?>" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <!-- honeypot: hidden from people, irresistible to bots -->
      <div class="hp" aria-hidden="true">
        <label for="website">Website</label>
        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label class="field__label" for="name">Your name</label>
        <input class="input" id="name" name="name" type="text" autocomplete="name" required maxlength="80" value="<?= esc($values['name'], 'attr') ?>" autofocus>
        <p class="field__help">Shown on your stories and author page.</p>
      </div>

      <div class="field">
        <label class="field__label" for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= esc($values['email'], 'attr') ?>">
      </div>

      <div class="field">
        <label class="field__label" for="password">Password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
        <p class="field__help">At least 8 characters.</p>
      </div>

      <button class="btn btn--accent btn--block" type="submit">Create my account →</button>
    </form>

    <p class="login__links">
      <span>Already writing with us?</span>
      <a href="<?= url('login') ?>">Sign in →</a>
    </p>
  </div>
</section>

<?php snippet('footer', ['cta' => false]) ?>
