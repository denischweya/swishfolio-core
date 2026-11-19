<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * Shared fixture: a small block tree rooted at a testimonials block, with an
 * unrelated hero as a sibling so "outside the selection" can be tested.
 */
abstract class StageTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_transients'] = [];
    }

    protected function registry()
    {
        return new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);
    }

    protected function tokens()
    {
        $json = json_decode(file_get_contents(SFCORE_PLUGIN_DIR . 'tests/fixtures/theme.json'), true);

        return TokenResolver::fromArray($json);
    }

    protected function blockTree()
    {
        return [
            'hero-1' => [
                'name'           => 'swishfolio-core/hero',
                'attributes'     => ['heading' => 'Old heading'],
                'parentClientId' => null,
            ],
            'testimonials-1' => [
                'name'           => 'swishfolio-core/testimonials',
                'attributes'     => ['columns' => 3],
                'parentClientId' => null,
            ],
            'card-1' => [
                'name'           => 'swishfolio-core/testimonial-card',
                'attributes'     => ['quote' => 'Great work'],
                'parentClientId' => 'testimonials-1',
            ],
            'para-1' => [
                'name'           => 'core/paragraph',
                'attributes'     => ['content' => 'Some copy'],
                'parentClientId' => null,
            ],
        ];
    }

    protected function context($selectionRoot = 'testimonials-1', $maxActions = 25)
    {
        return new ValidationContext(
            $this->blockTree(),
            $selectionRoot,
            $this->registry(),
            $this->tokens(),
            $maxActions
        );
    }

    protected function response(array $actions)
    {
        return ['schemaVersion' => '1.0', 'summary' => 'A change.', 'actions' => $actions];
    }

    protected function updateAction($clientId, array $changes)
    {
        return ['type' => 'update_block_attributes', 'clientId' => $clientId, 'changes' => $changes];
    }
}
