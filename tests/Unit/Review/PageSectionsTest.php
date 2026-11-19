<?php

namespace SwishfolioCore\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Review\PageSections;

class PageSectionsTest extends TestCase
{
    private function page()
    {
        return implode("\n\n", [
            '<!-- wp:group {"tagName":"section","className":"rw-hero"} --><section class="wp-block-group rw-hero"><!-- wp:heading --><h1>Hi</h1><!-- /wp:heading --></section><!-- /wp:group -->',
            '<!-- wp:group {"className":"rw-logos"} --><div class="wp-block-group rw-logos"><!-- wp:group {"className":"inner"} --><div>x</div><!-- /wp:group --></div><!-- /wp:group -->',
            '<!-- wp:spacer {"height":"40px"} /-->',
            '<!-- wp:group {"className":"rw-cta"} --><div class="wp-block-group rw-cta"><p>Call</p></div><!-- /wp:group -->',
        ]);
    }

    public function test_it_finds_top_level_sections_with_their_classes()
    {
        $sections = PageSections::split($this->page());

        $this->assertSame([1, 2, 3, 4], array_keys($sections));
        $this->assertSame(['rw-hero', 'rw-logos', '', 'rw-cta'], array_column($sections, 'className'));
        $this->assertSame('core/spacer', $sections[3]['name']);
    }

    public function test_splice_removes_and_replaces_keeping_every_other_byte()
    {
        $page = $this->page();
        $out  = PageSections::splice($page, [2 => '', 4 => '<!-- wp:group {"className":"rw-cta2"} --><div>New</div><!-- /wp:group -->']);

        $this->assertStringNotContainsString('rw-logos', $out);
        $this->assertStringContainsString('rw-cta2', $out);
        $this->assertStringStartsWith(substr($page, 0, 150), $out);
        $this->assertSame(['rw-hero', '', 'rw-cta2'], array_column(PageSections::split($out), 'className'));
    }
}
