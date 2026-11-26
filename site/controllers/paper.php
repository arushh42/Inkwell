<?php

use Kirby\Exception\NotFoundException;

/**
 * Papers that aren't published yet (drafts, "in review") are only visible to
 * editors and to the member who added them — same rule as stories.
 */
return function ($kirby, $page) {
    if ($page->isListed()) {
        return [];
    }

    $user = $kirby->user();

    if (!inkwell_is_editor($user) && !($user && $page->submittedBy()->toUsers()->has($user))) {
        throw new NotFoundException('Paper not found');
    }

    return [];
};
