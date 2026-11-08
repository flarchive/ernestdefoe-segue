<?php

namespace ErnestDefoe\Segue\Tests\integration\api;

use ErnestDefoe\Segue\Settings;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

class IconsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-segue');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);
    }

    private function upload(string $bytes, string $name, int $actor = 1): array
    {
        $path = tempnam(sys_get_temp_dir(), 'segue');
        file_put_contents($path, $bytes);

        $request = $this->request('POST', '/api/segue/icons', ['authenticatedAs' => $actor])
            ->withUploadedFiles(['icon' => new UploadedFile($path, strlen($bytes), UPLOAD_ERR_OK, $name)]);

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(400, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function storedIcons(): array
    {
        return json_decode($this->app()->getContainer()->make('flarum.settings')->get(Settings::ICONS), true);
    }

    #[Test]
    public function an_admin_uploads_a_still_image_and_it_is_stored_as_webp_no_larger_than_320px()
    {
        [$status, $body] = $this->upload($this->png(), 'icon.png');

        $this->assertSame(200, $status);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}\.webp$/', $body['added']);
        $this->assertSame([$body['added']], $this->storedIcons());

        $file = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets')->path(Settings::DIR.'/'.$body['added']);
        [$width, $height] = getimagesize($file);
        $this->assertSame([320, 160], [$width, $height]);
    }

    #[Test]
    public function an_animated_gif_stays_animated()
    {
        $images = $this->app()->getContainer()->make(ImageManager::class);
        $gif = (string) $images->animate(function ($animation) use ($images) {
            $animation->add($images->create(40, 40)->fill('ff0000'), 0.2);
            $animation->add($images->create(40, 40)->fill('0000ff'), 0.2);
        })->toGif();

        [$status, $body] = $this->upload($gif, 'spin.gif');

        $this->assertSame(200, $status);
        $this->assertStringEndsWith('.gif', $body['added']);

        $stored = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets')->get(Settings::DIR.'/'.$body['added']);
        $this->assertTrue($images->read($stored)->isAnimated());
    }

    #[Test]
    public function a_file_that_is_not_an_image_is_refused()
    {
        [$status] = $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg');
        $this->assertSame(422, $status);

        // A path on the server is bytes like any others, not a file to read.
        [$status] = $this->upload(__FILE__, 'path.png');
        $this->assertSame(422, $status);

        $this->assertSame([], $this->storedIcons());
    }

    #[Test]
    public function members_and_guests_cannot_add_or_remove_icons()
    {
        [$status] = $this->upload($this->png(), 'icon.png', 2);
        $this->assertSame(403, $status);

        $response = $this->send($this->request('POST', '/api/segue/icons'));
        // A guest is stopped by the CSRF check (400) before the admin check.
        $this->assertContains($response->getStatusCode(), [400, 401, 403]);

        [, $body] = $this->upload($this->png(), 'icon.png');
        $response = $this->send($this->request('DELETE', '/api/segue/icons/'.$body['added'], ['authenticatedAs' => 2]));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([$body['added']], $this->storedIcons());
    }

    #[Test]
    public function an_admin_removes_an_icon_and_its_file()
    {
        [, $body] = $this->upload($this->png(), 'icon.png');
        $disk = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
        $this->assertTrue($disk->exists(Settings::DIR.'/'.$body['added']));

        $response = $this->send($this->request('DELETE', '/api/segue/icons/'.$body['added'], ['authenticatedAs' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->storedIcons());
        $this->assertFalse($disk->exists(Settings::DIR.'/'.$body['added']));
    }
}
