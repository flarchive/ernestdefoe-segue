<?php

namespace ErnestDefoe\Segue\Tests\integration\forum;

use ErnestDefoe\Segue\Settings;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HeadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-segue');
    }

    private function page(): string
    {
        $response = $this->send($this->request('GET', '/'));
        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    #[Test]
    public function the_loading_screen_goes_up_before_the_stylesheet()
    {
        $html = $this->page();

        $script = strpos($html, 'classList.add("segue-loading")');
        $this->assertNotFalse($script);
        $this->assertLessThan(strpos($html, 'rel="stylesheet"'), $script);
        $this->assertStringContainsString('"data-segue-icon","ring"', $html);
        $this->assertStringContainsString('--segue-size:48px', $html);
        $this->assertStringContainsString('@view-transition{navigation:auto}', $html);
    }

    #[Test]
    public function bad_settings_fall_back_to_safe_values()
    {
        $this->setting(Settings::ICON, 'file:../../etc/passwd');
        $this->setting(Settings::COLOR, 'red;}</style><script>alert(1)</script>');
        $this->setting(Settings::SIZE, 'enormous');

        $html = $this->page();

        $this->assertStringContainsString('"data-segue-icon","ring"', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('--segue-color', $html);
        $this->assertStringContainsString('--segue-size:48px', $html);
    }

    #[Test]
    public function turning_the_loading_screen_and_transitions_off_leaves_the_page_alone()
    {
        $this->setting(Settings::LOADER, false);
        $this->setting(Settings::TRANSITIONS, 'off');

        $html = $this->page();

        $this->assertStringNotContainsString('segue-loading', $html);
        $this->assertStringNotContainsString('@view-transition', $html);
    }
}
