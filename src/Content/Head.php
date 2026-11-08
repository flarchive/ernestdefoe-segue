<?php

namespace ErnestDefoe\Segue\Content;

use ErnestDefoe\Segue\Settings;
use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Puts the loading screen up before the first paint. The class goes on <html>
 * from an inline script, so a browser without JavaScript never sees a screen
 * that nothing would take down. forum.less draws it; forum.js removes it.
 */
class Head
{
    public function __construct(
        private Settings $segue,
        private SettingsRepositoryInterface $settings,
        private Factory $filesystem,
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $vars = ['--segue-k:'.$this->segue->speed()];
        $darkImage = null;

        if ($this->segue->loader()) {
            $icon = $this->segue->icon();
            $image = null;
            $assets = $this->filesystem->disk('flarum-assets');

            if ($icon === 'logo') {
                $logo = $this->settings->get('logo_path');
                $dark = $this->settings->get('logo_dark_mode_path');
                $image = $logo ? $assets->url($logo) : null;
                $darkImage = $logo && $dark ? $assets->url($dark) : null;
                $icon = $image ? 'logo' : 'ring';
            } elseif (str_starts_with($icon, 'file:')) {
                $image = $assets->url(Settings::DIR.'/'.substr($icon, 5));
                $icon = 'image';
            }

            $document->preHead[] = '<script>(function(d){d.classList.add("segue-loading");d.setAttribute("data-segue-icon",'
                .json_encode($icon).')})(document.documentElement)</script>';

            $vars[] = '--segue-size:'.$this->segue->size().'px';

            if ($image) {
                $vars[] = '--segue-image:'.$this->cssUrl($image);
            }

            if ($color = $this->segue->color()) {
                $vars[] = '--segue-color:'.$color;
            }
        }

        $css = ':root{'.implode(';', $vars).'}';

        if ($darkImage) {
            $css .= '[data-theme^=dark]{--segue-image:'.$this->cssUrl($darkImage).'}';
        }

        if ($this->segue->fullPage()) {
            // Full page loads within the forum (a sign-in, a forced refresh)
            // cross-fade instead of blanking. Same-page moves are forum.js's.
            $css .= '@media (prefers-reduced-motion:no-preference){@view-transition{navigation:auto}}';
        }

        $document->head[] = '<style>'.$css.'</style>';
    }

    /**
     * The URL is built from our own file names or the logo's path; encoding
     * the quote, backslash and angle brackets keeps it inside url("") and the
     * <style> tag whatever it holds.
     */
    private function cssUrl(string $url): string
    {
        return 'url("'.str_replace(['\\', '"', '<', '>'], ['%5C', '%22', '%3C', '%3E'], $url).'")';
    }
}
