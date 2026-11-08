<?php

namespace ErnestDefoe\Segue;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Segue's settings, read and cleaned in one place.
 */
class Settings
{
    public const LOADER = 'ernestdefoe-segue.loader';
    public const ICON = 'ernestdefoe-segue.icon';
    public const SIZE = 'ernestdefoe-segue.size';
    public const COLOR = 'ernestdefoe-segue.color';
    public const ICONS = 'ernestdefoe-segue.icons';
    public const TRANSITIONS = 'ernestdefoe-segue.transitions';
    public const SPEED = 'ernestdefoe-segue.speed';
    public const FULL_PAGE = 'ernestdefoe-segue.full_page';

    /** The icons drawn in CSS, plus the forum's own logo. */
    public const BUILT_IN = ['ring', 'dots', 'bars', 'pulse', 'orbit', 'logo'];

    public const SIZES = ['small' => 32, 'medium' => 48, 'large' => 72];

    public const SPEEDS = ['fast' => 0.7, 'normal' => 1, 'slow' => 1.4];

    /** Where uploaded icons live on the flarum-assets disk. */
    public const DIR = 'segue';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function loader(): bool
    {
        return (bool) $this->settings->get(self::LOADER);
    }

    /** @return list<string> uploaded icon file names, newest last */
    public function icons(): array
    {
        $icons = json_decode((string) $this->settings->get(self::ICONS), true);

        return is_array($icons) ? array_values(array_filter($icons, [self::class, 'isIconName'])) : [];
    }

    /** @param list<string> $icons */
    public function saveIcons(array $icons): void
    {
        $this->settings->set(self::ICONS, json_encode(array_values($icons)));
    }

    /**
     * The chosen icon: a built-in name or "file:<name>" for an upload that
     * still exists. Anything else falls back to the ring.
     */
    public function icon(): string
    {
        $icon = (string) $this->settings->get(self::ICON);

        if (in_array($icon, self::BUILT_IN, true)) {
            return $icon;
        }

        if (str_starts_with($icon, 'file:') && in_array(substr($icon, 5), $this->icons(), true)) {
            return $icon;
        }

        return 'ring';
    }

    public function size(): int
    {
        return self::SIZES[$this->settings->get(self::SIZE)] ?? self::SIZES['medium'];
    }

    /** A #rgb or #rrggbb colour, or null to follow the forum's primary colour. */
    public function color(): ?string
    {
        $color = trim((string) $this->settings->get(self::COLOR));

        return preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $color) ? $color : null;
    }

    public function transitions(): string
    {
        $value = $this->settings->get(self::TRANSITIONS);

        return in_array($value, ['glide', 'fade', 'off'], true) ? $value : 'glide';
    }

    public function speed(): float
    {
        return self::SPEEDS[$this->settings->get(self::SPEED)] ?? 1.0;
    }

    public function fullPage(): bool
    {
        return (bool) $this->settings->get(self::FULL_PAGE) && $this->transitions() !== 'off';
    }

    public static function isIconName(mixed $name): bool
    {
        return is_string($name) && (bool) preg_match('/^[a-z0-9]{20}\.(?:gif|webp)$/', $name);
    }
}
