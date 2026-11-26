<?php

use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;

/**
 * Contributor sign-up.
 *
 * Creates a "contributor" account plus a hidden author profile, then signs
 * the new member in. Contributors can write and submit stories for review;
 * only editors/admins publish. Spam protection: honeypot field, a minimum
 * fill-in time and a per-IP rate limit.
 */
return function ($kirby) {
    if ($kirby->user()) {
        go('write');
    }

    $session = $kirby->session();
    $error   = null;
    $values  = ['name' => '', 'email' => ''];

    if ($kirby->request()->is('POST')) {
        $values = [
            'name'  => trim((string)get('name')),
            'email' => strtolower(trim((string)get('email'))),
        ];
        $password = (string)get('password');

        try {
            if (csrf(get('csrf')) !== true) {
                throw new Exception('Your session expired. Please try again.');
            }

            // bots fill in every field, humans never see this one
            if ((string)get('website') !== '') {
                throw new Exception('Something went wrong. Please try again.');
            }

            // bots submit instantly
            if (time() - (int)$session->get('inkwell.signup.started', 0) < 3) {
                throw new Exception('That was quick! Please check your details and submit again.');
            }

            if ($values['name'] === '' || Str::length($values['name']) > 80) {
                throw new Exception('Please enter your name (up to 80 characters).');
            }

            if (V::email($values['email']) === false) {
                throw new Exception('Please enter a valid email address.');
            }

            if (Str::length($password) < 8) {
                throw new Exception('Your password needs at least 8 characters.');
            }

            if ($kirby->users()->findBy('email', $values['email'])) {
                throw new Exception('There’s already an account with this email. Try signing in instead.');
            }

            if (inkwell_rate_limit('signup', 5, 3600) === false) {
                throw new Exception('Too many sign-ups from your network. Please try again in an hour.');
            }

            // create the account and the author profile as the system user;
            // the new member only gets the restricted "contributor" role
            $user = $kirby->impersonate('kirby', function () use ($kirby, $values, $password) {
                $user = $kirby->users()->create([
                    'email'    => $values['email'],
                    'name'     => $values['name'],
                    'password' => $password,
                    'role'     => 'contributor',
                    'language' => 'en',
                ]);

                $authors = page('authors');
                $base    = Str::slug($values['name']) ?: 'author';
                $slug    = $base;
                for ($i = 2; $authors->childrenAndDrafts()->find($slug); $i++) {
                    $slug = $base . '-' . $i;
                }

                // stays hidden until the member's first story is published
                $authors->createChild([
                    'slug'     => $slug,
                    'template' => 'author',
                    'content'  => [
                        'title'   => $values['name'],
                        'role'    => 'Contributor',
                        'account' => [$user->uuid()->toString()],
                    ],
                ]);

                return $user;
            });

            $user->loginPasswordless();
            $session->remove('inkwell.signup.started');

            go(url('write', ['query' => ['welcome' => 1]]));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $session->set('inkwell.signup.started', time());

    return compact('error', 'values');
};
