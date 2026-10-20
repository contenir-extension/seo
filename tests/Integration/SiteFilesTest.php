<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Seo\Check\SiteUrlCheck;
use Contenir\Extension\Seo\SeoSettings;
use Contenir\Extension\Seo\SiteSettingsFiles;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use Contenir\Seo\Visibility\Visibility;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;

/**
 * The add-on reading the site's own settings files, as the CMS writes them,
 * for the site URL and name its sections don't hold.
 */
#[Group('integration')]
final class SiteFilesTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function buildsTheSitesSeoFromItsFilesAndSections(): void
    {
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => [
                'site'   => ['url' => 'https://www.example.com', 'title' => 'Example'],
                'social' => ['social_instagram' => 'example'],
            ],
        ]);

        $config = (new SeoSettings())->config(new SettingsContext(
            [
                'seo'              => ['site_name' => 'Example Studio', 'visibility' => 'hide'],
                'seo-verification' => ['google_site_verification' => 'g-token'],
                'seo-sitemap'      => [
                    'sitemap'         => false,
                    'sitemap_exclude' => 'redirect',
                    'robots_rules'    => 'Disallow: /x',
                ],
            ],
            $this->directory,
        ));

        static::assertSame(
            [
                'https://www.example.com',
                'Example Studio',
                Visibility::Hide,
                ['google' => 'g-token'],
                ['https://www.instagram.com/example'],
                false,
                ['redirect'],
                ['Disallow: /x'],
            ],
            [
                $config->site->origin,
                $config->siteName(),
                $config->visibility,
                $config->verification,
                $config->organisation->profiles,
                $config->sitemap->enabled,
                $config->sitemap->excluded,
                $config->robotsRules->lines,
            ],
        );
    }

    #[Test]
    public function passesWhenTheSiteHasAUrl(): void
    {
        $this->writeConfig('config/autoload/settings.local.php', [
            'settings' => ['site' => ['url' => 'https://www.example.com/']],
        ]);

        $result = (new SiteUrlCheck())->check(new SettingsContext(sitePath: $this->directory));

        static::assertSame(
            [
                Status::Success,
                'Links for search engines are built on https://www.example.com.',
                'Site URL for search engines',
            ],
            [$result->status, $result->message, (new SiteUrlCheck())->label()],
        );
    }

    #[Test]
    public function readsLocalSettingsOverGlobal(): void
    {
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://a.example', 'title' => 'A']],
        ]);
        $this->writeConfig('config/autoload/settings.local.php', [
            'settings' => ['site' => ['url' => 'https://b.example']],
        ]);

        static::assertSame(
            ['site' => ['url' => 'https://b.example', 'title' => 'A']],
            (new SiteSettingsFiles())->read($this->directory),
        );
    }

    #[Test]
    public function readsNothingFromFilesWithoutSettings(): void
    {
        mkdir("{$this->directory}/config/autoload", recursive: true);
        file_put_contents("{$this->directory}/config/autoload/settings.global.php", data: '<?php return "not a list";');
        $this->writeConfig('config/autoload/settings.local.php', ['settings' => 'not a list']);

        static::assertSame([], (new SiteSettingsFiles())->read($this->directory));
    }

    #[Test]
    public function warnsWhenTheSiteHasNoUrl(): void
    {
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'www.example.com']],
        ]);

        $result = (new SiteUrlCheck())->check(new SettingsContext(sitePath: $this->directory));
        $fix    = $result->fix;

        static::assertSame(
            [
                Status::Warning,
                'The site has no site URL, so search engines can\'t be kept away from copies of the site.',
                'Set the site URL',
                'Enter the live site\'s address, such as https://www.example.com, in Site Settings > Site > Site URL'
                    . ' (site.url). Until then, every copy of the site is shown to search engines, robots.txt and'
                    . ' the sitemap link from whatever address a visitor used, and the home page has no structured'
                    . ' data.',
                null,
            ],
            [
                $result->status,
                $result->message,
                $fix instanceof GuidedFix ? $fix->label : null,
                $fix instanceof GuidedFix ? $fix->explanation : null,
                $fix instanceof GuidedFix ? $fix->handler : 'none',
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}
