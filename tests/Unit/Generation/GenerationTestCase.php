<?php

namespace SwishfolioCore\Tests\Unit\Generation;

require_once __DIR__ . '/wp-generation-stubs.php';

if (! defined('SFCORE_PLUGIN_URL')) {
    define('SFCORE_PLUGIN_URL', 'https://example.test/wp-content/plugins/swishfolio-core/');
}

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Shared fixture: kit "alpha", one hero section (text, list items, a button
 * link, a phone link and a photo) and one call to action, plus a header unit,
 * a malformed unit, kit "beta" (styled by a variation only) and a version 1 kit
 * that must be ignored.
 */
abstract class GenerationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];
        $GLOBALS['sfcore_test_options']    = [];

        sfcore_test_wp_reset();
    }

    protected function fixtureDir()
    {
        return SFCORE_PLUGIN_DIR . 'tests/Unit/Generation/fixtures/kits';
    }

    /**
     * @return PatternLibrary
     */
    protected function library()
    {
        return new PatternLibrary($this->fixtureDir());
    }

    /**
     * @return Kit
     */
    protected function kit()
    {
        return $this->library()->kit('alpha');
    }

    /**
     * The real shipped kits, for the conformance test.
     *
     * @return PatternLibrary
     */
    protected function shippedLibrary()
    {
        return PatternLibrary::shipped();
    }

    /**
     * @return BlockRegistry
     */
    protected function registry()
    {
        return new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);
    }

    /**
     * @return TokenResolver
     */
    protected function tokens()
    {
        $json = json_decode(file_get_contents(SFCORE_PLUGIN_DIR . 'tests/fixtures/theme.json'), true);

        return TokenResolver::fromArray($json);
    }

    /**
     * @param string $scope         "site" or "page".
     * @param array  $existingSlugs slug => post ID.
     *
     * @return PlanContext
     */
    protected function context($scope = 'site', array $existingSlugs = [])
    {
        $context = new PlanContext($this->kit(), $this->registry(), $this->tokens(), $scope);

        return $context
            ->withCustomTemplates(['page-no-title'])
            ->withExistingSlugs($existingSlugs);
    }

    /**
     * A minimal valid site plan: one page, one section, filled from the fixture.
     *
     * @param array $overrides Top-level overrides.
     *
     * @return array
     */
    protected function plan(array $overrides = [])
    {
        return array_merge([
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'summary'       => 'A one page site.',
            'kit'           => 'alpha',
            'site'          => [
                'title'      => 'Acme',
                'frontPage'  => 'home',
                'navigation' => ['home'],
            ],
            'pages' => [
                $this->page(),
            ],
        ], $overrides);
    }

    /**
     * @param array $overrides Page overrides.
     *
     * @return array
     */
    protected function page(array $overrides = [])
    {
        return array_merge([
            'key'      => 'home',
            'title'    => 'Home',
            'slug'     => 'home',
            'sections' => [
                $this->patternSection(),
            ],
        ], $overrides);
    }

    /**
     * Every demo slot of the fixture hero, rewritten.
     *
     * @return array
     */
    protected function heroTexts()
    {
        return [
            ['id' => 't0', 'html' => 'Built to last'],
            ['id' => 't1', 'html' => 'Joinery for kitchens and stairs.'],
            ['id' => 't2', 'html' => 'Kitchens'],
            ['id' => 't3', 'html' => 'Stairs'],
            ['id' => 't4', 'html' => '<a class="wp-block-button__link wp-element-button" href="{{link:contact}}">Get a quote</a>'],
            ['id' => 't5', 'html' => 'Ring the workshop'],
        ];
    }

    /**
     * @return array
     */
    protected function heroAttrs()
    {
        return [
            ['id' => 'a0', 'value' => 'tel:+447700900123'],
            ['id' => 'a1', 'value' => 'Call Oak and Iron'],
        ];
    }

    /**
     * A fully rewritten kit section.
     *
     * @param array  $overrides Section keys to replace (texts, attrs, remove, clones).
     * @param string $unitId    Unit id.
     *
     * @return array
     */
    protected function patternSection(array $overrides = [], $unitId = 'home-01-hero')
    {
        $section = $unitId === 'home-01-hero'
            ? ['pattern' => $unitId, 'texts' => $this->heroTexts(), 'attrs' => $this->heroAttrs()]
            : ['pattern' => $unitId, 'texts' => [['id' => 't0', 'html' => 'Ask for a quote']]];

        return array_merge($section, $overrides);
    }

    /**
     * @param array  $attributes Block attributes.
     * @param string $blockName  Block name.
     *
     * @return array
     */
    protected function blockSection(array $attributes = ['columns' => 3], $blockName = 'swishfolio-core/portfolio')
    {
        return ['block' => $blockName, 'attributes' => $attributes];
    }

    /**
     * @param int    $id     Post ID.
     * @param array  $fields Post fields.
     *
     * @return void
     */
    protected function givenPost($id, array $fields = [])
    {
        $GLOBALS['sfcore_test_wp']['posts'][(int) $id] = array_merge([
            'ID'           => (int) $id,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Existing',
            'post_name'    => 'existing',
            'post_content' => '',
            'post_author'  => 1,
        ], $fields);
    }
}
