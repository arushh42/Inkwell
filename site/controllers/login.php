<?php

/**
 * Editor sign-in. Uses Kirby's own auth (incl. brute-force protection)
 * and sends editors to the writing studio instead of the Panel.
 */
return function ($kirby) {
    // only allow redirects to local paths like "write" or "write/my-story"
    $redirect = trim((string)get('redirect', 'write'), '/');
    if (preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', $redirect) !== 1) {
        $redirect = 'write';
    }

    if ($kirby->user()) {
        go($redirect);
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
            go($redirect);
        } catch (Throwable $e) {
            // never reveal whether the email exists
            $error = str_contains($e->getMessage(), 'session')
                ? $e->getMessage()
                : 'That email and password don’t match. Please try again.';
        }
    }

    return compact('error', 'email', 'redirect');
};
