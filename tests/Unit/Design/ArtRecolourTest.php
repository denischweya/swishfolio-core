<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\ArtRecolour;
use SwishfolioCore\AI\Design\Color;

class ArtRecolourTest extends TestCase
{
    /** Kinetic (agency): black page, off-white text, acid lime accent. */
    const KIT = ['base' => '#0b0b0c', 'contrast' => '#f4f2ec', 'accent-1' => '#c6ff3d', 'accent-2' => '#151517', 'accent-3' => '#2a2a2e'];

    /** The same slugs as a light pink custom design writes them. */
    const PINK = ['base' => '#fdf2f7', 'contrast' => '#3b1030', 'accent-1' => '#c2407f', 'accent-2' => '#f8e1ec', 'accent-3' => '#e9cddb'];

    private function pink()
    {
        return ArtRecolour::fromPalettes(self::KIT, self::PINK, '#a98be0');
    }

    public function testSamePaletteIsIdentity()
    {
        $same = ArtRecolour::fromPalettes(self::KIT, self::KIT);

        $this->assertTrue($same->isIdentity());
        foreach (['#c6ff3d', '#0b0b0c', '#808080', '#5b3fd0', '#ff6a2b'] as $hex) {
            $this->assertLessThan(0.02, self::distance($hex, $same->mapHex($hex)), $hex);
        }
    }

    public function testLimeBecomesTheNewAccent()
    {
        $map = $this->pink();
        $this->assertFalse($map->isIdentity());

        $out = $map->mapHex('#c6ff3d');
        $this->assertLessThan(0.04, self::distance('#c2407f', $out), $out);
    }

    public function testShadingIsKept()
    {
        $map    = $this->pink();
        $light  = $map->mapHex('#e0ff9a');
        $mid    = $map->mapHex('#c6ff3d');
        $shadow = $map->mapHex('#5f7a1d');

        $this->assertGreaterThan(Color::lightness($mid), Color::lightness($light));
        $this->assertLessThan(Color::lightness($mid), Color::lightness($shadow));
        // All the new hue.
        foreach ([$light, $mid, $shadow] as $hex) {
            $this->assertLessThan(25, self::hueGap(Color::hue($hex), Color::hue('#c2407f')), $hex);
        }
    }

    public function testNeutralsKeepLightnessAndTakeTheTint()
    {
        $map = $this->pink();
        foreach (['#0b0b0c', '#555555', '#c0c0c0'] as $hex) {
            $out = $map->mapHex($hex);
            $this->assertEqualsWithDelta(Color::lightness($hex), Color::lightness($out), 0.03, $hex);
        }
        // Black takes the plum of the new text colour.
        $this->assertGreaterThan(Color::chroma('#0b0b0c'), Color::chroma($map->mapHex('#0b0b0c')));
    }

    public function testFarHuesGoToTheSecondAccent()
    {
        $out = $this->pink()->mapHex('#2f6bff');

        $this->assertLessThan(30, self::hueGap(Color::hue($out), Color::hue('#a98be0')), $out);
    }

    public function testNoForeignHueSurvives()
    {
        $map     = $this->pink();
        $allowed = [Color::hue('#c2407f'), Color::hue('#a98be0'), Color::hue('#3b1030')];
        foreach (['#c6ff3d', '#2f6bff', '#ff6a2b', '#14b8a6', '#ffd400', '#7c3aed'] as $hex) {
            $out = $map->mapHex($hex);
            if (Color::chroma($out) < 0.04) {
                continue;
            }
            $gap = min(array_map(static function ($h) use ($out) {
                return self::hueGap(Color::hue($out), $h);
            }, $allowed));
            $this->assertLessThan(40, $gap, $hex . ' -> ' . $out);
        }
    }

    public function testKeyFollowsThePalette()
    {
        $this->assertSame($this->pink()->key(), $this->pink()->key());
        $this->assertNotSame($this->pink()->key(), ArtRecolour::fromPalettes(self::KIT, array_merge(self::PINK, ['accent-1' => '#1f8a70']), '#a98be0')->key());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $this->pink()->key());
    }

    public function testHaldTableSize()
    {
        $this->assertSame(36 * 36 * 36 * 3, strlen($this->pink()->haldPixels(6)));
    }

    public function testSvgIsRecolouredValueByValue()
    {
        $dir = sys_get_temp_dir() . '/sf-art-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.svg', '<svg><circle fill="#C6FF3D"/><rect fill="#0b0b0c"/></svg>');

        $this->assertTrue($this->pink()->recolourFile($dir . '/a.svg', $dir . '/b.svg'));
        $svg = (string) file_get_contents($dir . '/b.svg');
        $this->assertStringNotContainsString('#C6FF3D', $svg);
        $this->assertMatchesRegularExpression('/<circle fill="#[0-9a-f]{6}"\/>/', $svg);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testRasterIsRecoloured()
    {
        if (! function_exists('imagecreatetruecolor') && ! class_exists('\Imagick')) {
            $this->markTestSkipped('No image library.');
        }
        $dir = sys_get_temp_dir() . '/sf-art-' . uniqid();
        mkdir($dir);
        $img = imagecreatetruecolor(8, 8);
        imagefill($img, 0, 0, imagecolorallocate($img, 0xc6, 0xff, 0x3d));
        imagepng($img, $dir . '/a.png');
        imagedestroy($img);

        $this->assertTrue($this->pink()->recolourFile($dir . '/a.png', $dir . '/b.png'));
        $out = imagecreatefrompng($dir . '/b.png');
        $c   = imagecolorsforindex($out, imagecolorat($out, 4, 4));
        $hex = Color::fromRgb([$c['red'], $c['green'], $c['blue']]);
        $this->assertLessThan(0.06, self::distance('#c2407f', $hex), $hex);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    private static function distance($a, $b)
    {
        $x = Color::oklab($a);
        $y = Color::oklab($b);

        return sqrt(pow($x[0] - $y[0], 2) + pow($x[1] - $y[1], 2) + pow($x[2] - $y[2], 2));
    }

    private static function hueGap($a, $b)
    {
        $d = abs($a - $b);

        return $d > 180 ? 360 - $d : $d;
    }
}
