<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\ArtRecolour;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Onboarding\KitArt;

if (! defined('SFCORE_PLUGIN_URL')) {
    define('SFCORE_PLUGIN_URL', 'https://example.test/wp-content/plugins/swishfolio-core/');
}

class KitArtTest extends TestCase
{
    /** @var string */
    public static $uploads = '';

    public static function setUpBeforeClass(): void
    {
        self::$uploads = sys_get_temp_dir() . '/sf-kitart-' . uniqid();
        mkdir(self::$uploads);
        require_once __DIR__ . '/kit-art-stubs.php';
    }

    public static function tearDownAfterClass(): void
    {
        $rm = static function ($dir) use (&$rm) {
            foreach ((array) glob($dir . '/*') as $f) {
                is_dir($f) ? $rm($f) : unlink($f);
            }
            rmdir($dir);
        };
        $rm(self::$uploads);
    }

    private function kit($slug)
    {
        return Kit::fromDir(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits/' . $slug);
    }

    public function testEveryKitAssetIsClassified()
    {
        foreach (glob(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits/*/kit.json') as $file) {
            $kit = Kit::fromDir(dirname($file));
            foreach ($kit->getAssets() as $name => $asset) {
                $this->assertContains($asset['kind'] ?? null, ['photo', 'decorative'], $kit->getSlug() . '/' . $name);
            }
        }
    }

    public function testAbstractArtIsDecorativePhotosAreNot()
    {
        $this->assertEqualsCanonicalizing(['mesh', 'orbs', 'reel', 'type'], KitArt::decorative($this->kit('agency')));
        $this->assertCount(6, KitArt::decorative($this->kit('accountant')));
        $this->assertSame([], KitArt::decorative($this->kit('marketer')));
        $this->assertSame([], KitArt::decorative($this->kit('photographer')));
    }

    public function testUnchangedPaletteNeedsNoRecolouring()
    {
        $kit = $this->kit('agency');
        $this->assertNull(KitArt::recolourer($kit, KitArt::kitPalette($kit), '#000001'));
    }

    public function testRewriteSwapsDecorativeArtOnly()
    {
        $kit      = $this->kit('agency');
        $site     = array_merge(KitArt::kitPalette($kit), ['base' => '#fdf2f7', 'contrast' => '#3b1030', 'accent-1' => '#c2407f']);
        $recolour = KitArt::recolourer($kit, $site, '#a98be0');
        $this->assertInstanceOf(ArtRecolour::class, $recolour);

        $orbs   = $kit->assetUrl('orbs');
        $photo  = $kit->assetUrl('kora');
        $markup = '<!-- wp:image {"url":"' . str_replace('/', '\\/', $orbs) . '"} --><figure><img src="' . $orbs . '"/><img src="' . $photo . '"/></figure><!-- /wp:image -->';

        $out = KitArt::rewrite($markup, $kit, $recolour);

        $this->assertStringNotContainsString($orbs, $out);
        $this->assertStringNotContainsString(str_replace('/', '\\/', $orbs), $out);
        $this->assertStringContainsString($photo, $out, 'photos are untouched');
        $variant = 'https://uploads.test/swish-art/agency/orbs-' . $recolour->key() . '.jpg';
        $this->assertStringContainsString('<img src="' . $variant . '"', $out);
        $this->assertStringContainsString(str_replace('/', '\\/', $variant), $out);
        $this->assertFileExists(self::$uploads . '/swish-art/agency/orbs-' . $recolour->key() . '.jpg');

        // Idempotent, and a later palette replaces the earlier copy.
        $this->assertSame($out, KitArt::rewrite($out, $kit, $recolour));
        $other = KitArt::recolourer($kit, array_merge($site, ['accent-1' => '#1f8a70']), '#a98be0');
        $again = KitArt::rewrite($out, $kit, $other);
        $this->assertStringContainsString('orbs-' . $other->key() . '.jpg', $again);
        $this->assertStringNotContainsString('orbs-' . $recolour->key() . '.jpg', $again);

        // Back to the kit's palette: back to the shipped file.
        $this->assertSame($markup, KitArt::rewrite($out, $kit, null));
    }
}
