<?php

namespace ErnestDefoe\Segue\Api;

use ErnestDefoe\Segue\Settings;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Admins add their own loading icons (POST) and remove them (DELETE). Every
 * upload is decoded and re-encoded, so only a real image is ever stored:
 * animated images stay animated as GIF, still ones become WebP. SVG is not
 * accepted, since it could carry script when opened on its own.
 */
class IconsController implements RequestHandlerInterface
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ICONS = 24;
    public const MAX_SIDE = 320;

    private Filesystem $disk;

    public function __construct(
        private Settings $segue,
        private ImageManager $images,
        private TranslatorInterface $translator,
        Factory $filesystem,
    ) {
        $this->disk = $filesystem->disk('flarum-assets');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return $request->getMethod() === 'DELETE'
            ? $this->delete((string) Arr::get($request->getAttribute('routeParameters', []), 'name'))
            : $this->upload(Arr::get($request->getUploadedFiles(), 'icon'));
    }

    private function upload(mixed $file): ResponseInterface
    {
        $icons = $this->segue->icons();

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            throw $this->invalid('missing');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw $this->invalid('too_big');
        }

        if (count($icons) >= self::MAX_ICONS) {
            throw $this->invalid('too_many', ['count' => self::MAX_ICONS]);
        }

        try {
            // Bytes only: a string could otherwise be read as a path on the server.
            $image = $this->images->read((string) $file->getStream(), BinaryImageDecoder::class);
        } catch (Throwable) {
            throw $this->invalid('not_an_image');
        }

        if ($image->width() > self::MAX_SIDE || $image->height() > self::MAX_SIDE) {
            $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
        }

        $animated = $image->isAnimated();
        $name = Str::lower(Str::random(20)).($animated ? '.gif' : '.webp');

        $this->disk->put(Settings::DIR.'/'.$name, (string) ($animated ? $image->toGif() : $image->toWebp()));

        $icons[] = $name;
        $this->segue->saveIcons($icons);

        return new JsonResponse(['icons' => $icons, 'added' => $name]);
    }

    /** @param array<string, int> $parameters */
    private function invalid(string $reason, array $parameters = []): ValidationException
    {
        return new ValidationException(['icon' => $this->translator->trans('ernestdefoe-segue.api.'.$reason, $parameters)]);
    }

    private function delete(string $name): ResponseInterface
    {
        $icons = $this->segue->icons();

        if (Settings::isIconName($name) && in_array($name, $icons, true)) {
            $this->disk->delete(Settings::DIR.'/'.$name);
            $icons = array_values(array_diff($icons, [$name]));
            $this->segue->saveIcons($icons);
        }

        return new JsonResponse(['icons' => $icons]);
    }
}
