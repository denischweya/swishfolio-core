<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\KitDesign;

class KitDesignTest extends TestCase
{
    const UPLOADS = 'https://restaurant.swishfolio.ddev.site/wp-content/uploads/sites/6';

    public function test_upload_paths_are_found_in_plain_and_json_escaped_urls()
    {
        $json = '{"styles":{"css":"@font-face{src:url(\"https:\/\/restaurant.swishfolio.ddev.site\/wp-content\/uploads\/sites\/6\/sf-fonts\/fraunces-roman.woff2\")}"},'
            . '"settings":{"typography":{"fontFamilies":{"custom":[{"fontFace":[{"src":["https:\/\/restaurant.swishfolio.ddev.site\/wp-content\/uploads\/sites\/6\/sf-fonts\/dm-mono-400.woff2"]}]}]}}}}';
        $html = '<img src="http://restaurant.swishfolio.ddev.site/wp-content/uploads/sites/6/2026/11/hero.jpg">';

        $this->assertSame(['sf-fonts/fraunces-roman.woff2', 'sf-fonts/dm-mono-400.woff2'], KitDesign::uploadPaths($json, self::UPLOADS));
        $this->assertSame(['2026/11/hero.jpg'], KitDesign::uploadPaths($html, self::UPLOADS));
    }

    public function test_upload_paths_skip_traversal_unsafe_types_and_other_sites()
    {
        $body = self::UPLOADS . '/../../wp-config.php ' . self::UPLOADS . '/x/../../secret.woff2 ' . self::UPLOADS . '/evil.php '
            . 'https://other.swishfolio.ddev.site/wp-content/uploads/sites/9/a.png';

        $this->assertSame([], KitDesign::uploadPaths($body, self::UPLOADS));
    }

    public function test_urls_are_rewritten_uploads_first_in_every_form()
    {
        $map = KitDesign::urlMap(self::UPLOADS, 'https://new.test/wp-content/uploads/sites/60/swish-kit/restaurant', 'https://restaurant.swishfolio.ddev.site', 'https://new.test');

        $body = 'a https://restaurant.swishfolio.ddev.site/menu/ b http://restaurant.swishfolio.ddev.site/wp-content/uploads/sites/6/sf-fonts/a.woff2'
            . ' c https:\/\/restaurant.swishfolio.ddev.site\/wp-content\/uploads\/sites\/6\/sf-fonts\/b.woff2';

        $this->assertSame(
            'a https://new.test/menu/ b https://new.test/wp-content/uploads/sites/60/swish-kit/restaurant/sf-fonts/a.woff2'
            . ' c https:\/\/new.test\/wp-content\/uploads\/sites\/60\/swish-kit\/restaurant\/sf-fonts\/b.woff2',
            KitDesign::rewrite($body, $map)
        );
    }

    public function test_navigation_refs_are_remapped_or_dropped()
    {
        $header = '<!-- wp:navigation {"ref":274,"className":"lu-nav"} /--> <!-- wp:navigation {"ref":9,"className":"x"} /-->';

        $this->assertSame([274, 9], KitDesign::navigationRefs($header));

        $out = KitDesign::remapNavigationRefs($header, [274 => 1201]);
        $this->assertStringContainsString('<!-- wp:navigation {"ref":1201,"className":"lu-nav"} /-->', $out);
        $this->assertStringContainsString('<!-- wp:navigation {"className":"x"} /-->', $out);
    }

    public function test_menu_links_become_custom_links_and_report_their_paths()
    {
        $nav = '<!-- wp:navigation-link {"label":"Menu","url":"https://new.test/menu/","kind":"post-type","type":"page","id":270} /-->'
            . "\n" . '<!-- wp:navigation-link {"label":"Private dining","url":"https://new.test/private-dining/","kind":"post-type","type":"page","id":272} /-->';

        $out = KitDesign::customLinks($nav);

        $this->assertStringContainsString('<!-- wp:navigation-link {"label":"Menu","url":"https://new.test/menu/","kind":"custom"} /-->', $out);
        $this->assertStringNotContainsString('"id":', $out);
        $this->assertSame([
            ['label' => 'Menu', 'path' => '/menu/'],
            ['label' => 'Private dining', 'path' => '/private-dining/'],
        ], KitDesign::linkPaths($out));
    }
}
