<?php

namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;

class BlockRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_transients'] = [];
    }

    private function registry()
    {
        return new BlockRegistry([__DIR__ . '/../../fixtures/blocks']);
    }

    public function test_discovers_manifests_from_directory()
    {
        $registry = $this->registry();

        $this->assertTrue($registry->has('test/alpha'));
        $this->assertTrue($registry->has('test/beta'));
        $this->assertSame('full', $registry->get('test/alpha')->getTier());
        $this->assertSame('content', $registry->get('test/beta')->getTier());
    }

    public function test_unknown_block_returns_null()
    {
        $this->assertNull($this->registry()->get('test/nope'));
        $this->assertFalse($this->registry()->has('test/nope'));
    }

    public function test_manual_registration_overrides_discovery()
    {
        $registry = $this->registry();
        $registry->register('test/alpha', [
            'version'      => 1,
            'tier'         => 'content',
            'purpose'      => 'text',
            'summary'      => 'Overridden.',
            'capabilities' => ['content' => ['content' => ['type' => 'richtext']]],
        ]);

        $this->assertSame('content', $registry->get('test/alpha')->getTier());
    }

    public function test_malformed_manifest_is_skipped_not_fatal()
    {
        $registry = new BlockRegistry([__DIR__ . '/../../fixtures/blocks-broken']);

        $this->assertSame([], $registry->all());
    }

    public function test_parsed_manifests_are_cached_under_a_version_keyed_transient()
    {
        $this->registry()->all();

        $this->assertArrayHasKey(
            'sfcore_ai_manifests_' . SFCORE_VERSION,
            $GLOBALS['sfcore_test_transients']
        );
    }

    public function test_cached_raw_manifests_are_reused_without_touching_disk()
    {
        $GLOBALS['sfcore_test_transients']['sfcore_ai_manifests_' . SFCORE_VERSION] = [
            'test/cached' => [
                'version'      => 1,
                'tier'         => 'full',
                'purpose'      => 'cached',
                'summary'      => 'From cache.',
                'capabilities' => ['content' => ['title' => ['type' => 'text']]],
            ],
        ];

        $registry = $this->registry();

        $this->assertTrue($registry->has('test/cached'));
        $this->assertFalse($registry->has('test/alpha'), 'Disk should not be read when the cache is warm.');
    }

    public function test_cache_stores_raw_arrays_not_objects()
    {
        $this->registry()->all();
        $cached = $GLOBALS['sfcore_test_transients']['sfcore_ai_manifests_' . SFCORE_VERSION];

        $this->assertIsArray($cached['test/alpha']);
        $this->assertSame(1, $cached['test/alpha']['version']);
    }
}
