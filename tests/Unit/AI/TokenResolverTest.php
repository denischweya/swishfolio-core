<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\TokenResolver;

class TokenResolverTest extends TestCase
{
    private function resolver()
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../fixtures/theme.json'), true);

        return TokenResolver::fromArray($json);
    }

    public function test_reads_colour_slugs_from_palette()
    {
        $this->assertSame(['base', 'contrast', 'accent-1', 'accent-2'], $this->resolver()->getSlugs('color'));
    }

    public function test_reads_spacing_and_font_size_slugs()
    {
        $this->assertSame(['50', '60'], $this->resolver()->getSlugs('spacing'));
        $this->assertSame(['medium', 'large', 'display'], $this->resolver()->getSlugs('fontSize'));
    }

    public function test_expands_slug_to_css_custom_property()
    {
        $this->assertSame(
            'var(--wp--preset--color--accent-1)',
            $this->resolver()->toStorageValue('color', 'accent-1')
        );
    }

    public function test_font_size_uses_hyphenated_preset_path()
    {
        $this->assertSame(
            'var(--wp--preset--font-size--large)',
            $this->resolver()->toStorageValue('fontSize', 'large')
        );
    }

    public function test_inverts_storage_value_back_to_slug()
    {
        $this->assertSame(
            'accent-2',
            $this->resolver()->toSlug('color', 'var(--wp--preset--color--accent-2)')
        );
    }

    public function test_inverting_an_unknown_value_returns_null()
    {
        $this->assertNull($this->resolver()->toSlug('color', '#123456'));
    }

    public function test_unknown_slug_is_invalid()
    {
        $this->assertFalse($this->resolver()->isValid('color', 'accent-9'));
        $this->assertTrue($this->resolver()->isValid('color', 'accent-1'));
    }

    public function test_literal_hex_is_never_a_valid_slug()
    {
        $this->assertFalse($this->resolver()->isValid('color', '#FF6B6B'));
    }

    public function test_unknown_token_set_yields_no_slugs()
    {
        $this->assertSame([], $this->resolver()->getSlugs('shadow'));
        $this->assertFalse($this->resolver()->isValid('shadow', 'anything'));
    }

    public function test_missing_theme_section_yields_empty_set_not_error()
    {
        $resolver = TokenResolver::fromArray(['version' => 3, 'settings' => []]);

        $this->assertSame([], $resolver->getSlugs('color'));
    }
}
