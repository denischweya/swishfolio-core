<?php

namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Guards the manifest corpus against drift from block.json.
 *
 * A manifest attribute that does not exist in block.json would produce an
 * action the editor silently ignores, so it must fail here instead.
 */
class ManifestConformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_transients'] = [];
    }

    private function registry()
    {
        return new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);
    }

    public function test_every_plugin_block_directory_has_a_manifest()
    {
        $missing = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/block.json') as $blockJson) {
            $dir = dirname($blockJson);

            if (! file_exists($dir . '/ai.json')) {
                $missing[] = basename($dir);
            }
        }

        $this->assertSame([], $missing, 'Blocks without an ai.json manifest: ' . implode(', ', $missing));
    }

    public function test_every_manifest_attribute_exists_in_its_block_json()
    {
        $unknown = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/ai.json') as $manifestFile) {
            $dir       = dirname($manifestFile);
            $blockJson = json_decode((string) file_get_contents($dir . '/block.json'), true);
            $manifest  = json_decode((string) file_get_contents($manifestFile), true);
            $declared  = isset($blockJson['attributes']) ? array_keys($blockJson['attributes']) : [];

            foreach ((array) $manifest['capabilities'] as $attributes) {
                foreach (array_keys((array) $attributes) as $attribute) {
                    if (! in_array($attribute, $declared, true)) {
                        $unknown[] = basename($dir) . '.' . $attribute;
                    }
                }
            }
        }

        $this->assertSame([], $unknown, 'Manifest attributes absent from block.json: ' . implode(', ', $unknown));
    }

    public function test_enum_values_match_the_block_json_enum_where_one_is_declared()
    {
        $mismatched = [];

        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/ai.json') as $manifestFile) {
            $dir       = dirname($manifestFile);
            $blockJson = json_decode((string) file_get_contents($dir . '/block.json'), true);
            $manifest  = json_decode((string) file_get_contents($manifestFile), true);
            $declared  = isset($blockJson['attributes']) ? $blockJson['attributes'] : [];

            foreach ((array) $manifest['capabilities'] as $attributes) {
                foreach ((array) $attributes as $attribute => $spec) {
                    if (! isset($spec['enum']) || ! isset($declared[$attribute]['enum'])) {
                        continue;
                    }

                    $extra = array_diff($spec['enum'], $declared[$attribute]['enum']);

                    if ($extra !== []) {
                        $mismatched[] = basename($dir) . '.' . $attribute . ' => ' . implode('|', $extra);
                    }
                }
            }
        }

        $this->assertSame([], $mismatched, 'Manifest enum values outside block.json enum: ' . implode(', ', $mismatched));
    }

    public function test_every_manifest_parses_into_a_capability()
    {
        $registry = $this->registry();

        $this->assertGreaterThanOrEqual(27, count($registry->all()));
        $this->assertNotNull($registry->get('swishfolio-core/hero'));
        $this->assertNotNull($registry->get('core/paragraph'));
    }

    public function test_colour_and_size_fields_use_tokens_or_enums_not_free_strings()
    {
        $offenders = [];

        foreach ($this->registry()->all() as $blockName => $capability) {
            foreach ($capability->getFields() as $attribute => $field) {
                if (! preg_match('/(Color|FontSize)$/', $attribute)) {
                    continue;
                }

                if (! in_array($field->getType(), ['token', 'enum'], true)) {
                    $offenders[] = $blockName . '.' . $attribute;
                }
            }
        }

        $this->assertSame([], $offenders, 'Colour/size fields must be token or enum: ' . implode(', ', $offenders));
    }

    public function test_declared_children_match_block_json_parent_relationships()
    {
        $invalid = [];

        foreach ($this->registry()->all() as $blockName => $capability) {
            foreach ($capability->getAllowedChildren() as $child) {
                // Only this plugin's own blocks declare a parent we can verify;
                // WordPress core children (core/list-item) are out of our control.
                if (strpos($child, 'swishfolio-core/') !== 0) {
                    continue;
                }

                $slug      = substr($child, strlen('swishfolio-core/'));
                $childJson = SFCORE_PLUGIN_DIR . 'blocks/' . $slug . '/block.json';

                if (! file_exists($childJson)) {
                    $invalid[] = $blockName . ' -> ' . $child . ' (no such block)';
                    continue;
                }

                $parents = json_decode((string) file_get_contents($childJson), true);
                $parents = isset($parents['parent']) ? $parents['parent'] : [];

                if (! in_array($blockName, $parents, true)) {
                    $invalid[] = $blockName . ' -> ' . $child . ' (not a declared parent)';
                }
            }
        }

        $this->assertSame([], $invalid, 'children.allowed contradicts block.json parent: ' . implode(', ', $invalid));
    }
}
