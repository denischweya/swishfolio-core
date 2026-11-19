<?php

namespace SwishfolioCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function test_harness_boots_with_constants_and_stubs()
    {
        $this->assertTrue(defined('SFCORE_PLUGIN_DIR'));
        $this->assertSame('hello', sanitize_text_field('  hello  '));
        $this->assertSame('', esc_url_raw('javascript:alert(1)'));
    }
}
