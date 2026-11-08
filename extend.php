<?php

/*
 * Segue — a loading screen in place of the blank flash while the forum
 * starts, and page transitions where titles and avatars glide between pages.
 *
 * The loading screen is drawn by CSS on <body> before any JavaScript runs:
 * the server only adds a class to <html> and a few custom properties. The
 * transitions are the browser's own View Transitions.
 */

use ErnestDefoe\Segue\Api\IconsController;
use ErnestDefoe\Segue\Content\Head;
use ErnestDefoe\Segue\Settings;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(Head::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/segue/icons', 'segue.icons.upload', IconsController::class)
        ->delete('/segue/icons/{name:[a-z0-9]+\.(?:gif|webp)}', 'segue.icons.delete', IconsController::class),

    (new Extend\Settings())
        ->default(Settings::LOADER, true)
        ->default(Settings::ICON, 'ring')
        ->default(Settings::SIZE, 'medium')
        ->default(Settings::COLOR, '')
        ->default(Settings::ICONS, '[]')
        ->default(Settings::TRANSITIONS, 'glide')
        ->default(Settings::SPEED, 'normal')
        ->default(Settings::FULL_PAGE, true)
        ->serializeToForum('segueTransitions', Settings::TRANSITIONS),
];
