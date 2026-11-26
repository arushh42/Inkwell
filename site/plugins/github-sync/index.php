<?php

use Inkwell\GitHubSync\GitHubSync;
use Kirby\Cms\App as Kirby;

load([
    'Inkwell\\GitHubSync\\GitHubSync' => __DIR__ . '/src/GitHubSync.php',
]);

/**
 * Commits Panel changes to GitHub when running on a read-only (serverless)
 * deployment. Inactive locally and whenever GITHUB_SYNC_TOKEN is not set.
 */
$sync = function (string $what) {
    $kirby  = kirby();
    $syncer = GitHubSync::fromEnv($kirby);

    if ($syncer === null) {
        return;
    }

    $user    = $kirby->user();
    $message = $what . "\n\nPublished from the Kirby Panel" . ($user ? ' by ' . ($user->name()->or($user->email())) : '') . '.';

    try {
        $syncer->push($message);
    } catch (Throwable $e) {
        // The change is saved on this instance; tell the editor it isn't
        // persisted yet. The next successful save will include it.
        error_log($e->getMessage());
        throw new Kirby\Exception\Exception(
            'Saved, but not yet persisted to GitHub: ' . $e->getMessage() . ' — it will be retried with your next save.'
        );
    }
};

// Kirby binds hook arguments by parameter name, so we accept every variant
$pageHook = fn (string $verb) => function ($newPage = null, $oldPage = null, $duplicatePage = null) use ($sync, $verb) {
    $page = $newPage ?? $duplicatePage ?? $oldPage;
    $sync(sprintf('%s page “%s”', $verb, $page?->title()->value() ?? 'unknown'));
};

$fileHook = fn (string $verb) => function ($newFile = null, $oldFile = null) use ($sync, $verb) {
    $file = $newFile ?? $oldFile;
    $sync(sprintf('%s file “%s”', $verb, $file?->filename() ?? 'unknown'));
};

Kirby::plugin('inkwell/github-sync', [
    'hooks' => [
        'page.create:after'         => fn ($page) => $sync('Create page “' . $page->title() . '”'),
        'page.update:after'         => $pageHook('Update'),
        'page.changeTitle:after'    => $pageHook('Rename'),
        'page.changeSlug:after'     => $pageHook('Change URL of'),
        'page.changeStatus:after'   => $pageHook('Change status of'),
        'page.changeNum:after'      => $pageHook('Reorder'),
        'page.changeTemplate:after' => $pageHook('Change template of'),
        'page.duplicate:after'      => $pageHook('Duplicate'),
        'page.move:after'           => $pageHook('Move'),
        'page.delete:after'         => fn ($status, $page) => $sync('Delete page “' . $page->title() . '”'),
        'file.create:after'         => fn ($file) => $sync('Upload file “' . $file->filename() . '”'),
        'file.update:after'         => $fileHook('Update'),
        'file.replace:after'        => $fileHook('Replace'),
        'file.changeName:after'     => $fileHook('Rename'),
        'file.changeSort:after'     => $fileHook('Reorder'),
        'file.delete:after'         => fn ($status, $file) => $sync('Delete file “' . $file->filename() . '”'),
        'site.update:after'         => fn () => $sync('Update site settings'),
        'site.changeTitle:after'    => fn () => $sync('Rename site'),
    ],
]);
