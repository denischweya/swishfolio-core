<?php

namespace SwishfolioCore\Tests\Unit\Generation;

require_once __DIR__ . '/wp-generation-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Shared fixture: a small generation library with one slot-bearing section, one
 * whole-page unit resolved through a fake pattern registry, one unit that needs
 * images and one that is variation-specific — so exclusion can be tested as well
 * as inclusion.
 */
abstract class GenerationTestCase extends TestCase
{
    const FAKE_PATTERN_MARKUP = "<!-- wp:paragraph -->\n<p>Registered pattern body.</p>\n<!-- /wp:paragraph -->";

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];
        $GLOBALS['sfcore_test_options']    = [];

        sfcore_test_wp_reset();
    }

    protected function fixtureDir()
    {
        return SFCORE_PLUGIN_DIR . 'tests/Unit/Generation/fixtures/units';
    }

    /**
     * @return PatternLibrary
     */
    protected function library()
    {
        return new PatternLibrary(
            [$this->fixtureDir()],
            function ($ref) {
                return $ref === 'test/alpha-page' ? self::FAKE_PATTERN_MARKUP : null;
            }
        );
    }

    /**
     * The real shipped library, for the conformance test.
     *
     * @return PatternLibrary
     */
    protected function shippedLibrary()
    {
        return new PatternLibrary([SFCORE_PLUGIN_DIR . 'src/AI/Generation/Patterns']);
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
        $context = new PlanContext($this->library(), $this->registry(), $this->tokens(), $scope);

        return $context
            ->withStyleVariations(['Default', 'Neo-Brutalist', 'Swiss International'])
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
     * @param array  $content Slot values.
     * @param string $unitId  Unit id.
     *
     * @return array
     */
    protected function patternSection(array $content = ['headline' => 'Built to last'], $unitId = 'alpha-hero')
    {
        return ['pattern' => $unitId, 'content' => $content];
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
