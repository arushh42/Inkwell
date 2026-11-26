<?php

/**
 * Editor sign-in. Uses Kirby's own auth (incl. brute-force protection)
 * and keeps editors in the site design instead of the Panel.
 */
return function ($kirby) {
    // After signing in, editors land on the homepage — unless they were
    // heading somewhere specific (e.g. /write sends them back there).
    // Only local paths like "write" or "write/my-story" are accepted.
    $redirect = trim((string)get('redirect', ''), '/');
    if ($redirect !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', $redirect) !== 1) {
        $redirect = '';
    }
    $target = $redirect === '' ? site()->url() : lurl($redirect);

    if ($kirby->user()) {
        go($target);
    }

    $error = null;
    $email = '';

    if ($kirby->request()->is('POST')) {
        $email = trim((string)get('email'));

        try {
            if (csrf(get('csrf')) !== true) {
                throw new Exception(t('error.session'));
            }

            $kirby->auth()->login($email, (string)get('password'), get('remember') === 'on');
            go($target);
        } catch (Throwable $e) {
            // never reveal whether the email exists
            $error = $e->getMessage() === t('error.session') ? $e->getMessage() : t('login.error');
        }
    }

    return compact('error', 'email', 'redirect');
};
