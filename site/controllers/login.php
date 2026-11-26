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
    $target = $redirect === '' ? site()->url() : url($redirect);

    if ($kirby->user()) {
        go($target);
    }

    $error = null;
    $email = '';

    if ($kirby->request()->is('POST')) {
        $email = trim((string)get('email'));

        try {
            if (csrf(get('csrf')) !== true) {
                throw new Exception('Your session expired. Please try again.');
            }

            $kirby->auth()->login($email, (string)get('password'), get('remember') === 'on');
            go($target);
        } catch (Throwable $e) {
            // never reveal whether the email exists
            $error = str_contains($e->getMessage(), 'session')
                ? $e->getMessage()
                : 'That email and password don’t match. Please try again.';
        }
    }

    return compact('error', 'email', 'redirect');
};
