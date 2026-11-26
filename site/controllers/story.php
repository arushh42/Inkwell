<?php

use Kirby\Exception\NotFoundException;

/**
 * Unpublished stories (drafts and "in review") are only visible to editors
 * and to their own author. Kirby would otherwise serve unlisted pages and
 * draft preview links to anyone with the URL — which would let contributors
 * bypass editorial review by sharing a link.
 */
return function ($kirby, $page) {
    if ($page->isListed()) {
        return [];
    }

    $user = $kirby->user();
    $ownsStory = ($author = inkwell_author_for($user))
        && in_array($author->uuid()->toString(), $page->author()->yaml(), true);

    if (!inkwell_is_editor($user) && !$ownsStory) {
        throw new NotFoundException('Story not found');
    }

    return [];
};
