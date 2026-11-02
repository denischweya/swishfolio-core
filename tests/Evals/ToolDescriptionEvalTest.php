<?php

namespace SwishfolioCore\Tests\Evals;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolInterface;
use SwishfolioCore\Tools\ToolRegistry;

/**
 * An eval on the tool catalogue itself.
 *
 * In practice a tool layer fails less often from bugs than from descriptions the
 * model cannot choose between: two tools described in the same words, and it
 * calls the wrong one, or calls one and gives up. Those failures look like model
 * stupidity and are actually catalogue defects.
 *
 * The eval the spec asked for — give the model the tool list and a prompt, check
 * which tool it picks — needs provider tool-calling, which the provider adapter
 * does not have yet. So this measures the property that eval would be testing,
 * deterministically and offline: are the descriptions distinguishable, complete
 * and affordable. When tool-calling lands, this stays as the cheap guard and the
 * model-in-the-loop version joins it.
 */
class ToolDescriptionEvalTest extends TestCase
{
    /** Below this a description cannot say what the tool does and when to use it. */
    const MIN_LENGTH = 150;

    /** Above this it is prose the model pays for on every request. */
    const MAX_LENGTH = 450;

    /** Pairwise content-word overlap above this makes two tools confusable. */
    const MAX_SIMILARITY = 0.40;

    /** The whole catalogue's cost, as JSON, in bytes. Roughly 2,000 tokens today. */
    const MAX_CATALOGUE_BYTES = 14000;

    const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'to', 'of', 'for', 'it', 'is', 'in', 'on',
        'that', 'this', 'with', 'you', 'your', 'they', 'them', 'as', 'be', 'are',
        'not', 'by', 'from', 'its', 'so', 'if', 'when', 'what', 'which', 'their',
        'has', 'have', 'can', 'call', 'use', 'each', 'one', 'at', 'into', 'than',
        'but', 'will', 'all', 'any', 'does', 'do', 'how',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_transients'] = [];

        sfcore_test_tools_reset();
    }

    private function registry()
    {
        return ToolRegistry::withDefaultTools(
            new MutationJournal(new InMemoryJournalRepository()),
            new BlockRegistry(
                [
                    SFCORE_PLUGIN_DIR . 'blocks',
                    SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
                ]
            )
        );
    }

    /**
     * @param string $description Tool description.
     *
     * @return string[]
     */
    private function contentWords($description)
    {
        $words = preg_split('/[^a-z]+/', strtolower($description), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_diff($words, self::STOP_WORDS)));
    }

    public function test_every_tool_is_namespaced_and_hyphenated()
    {
        foreach ($this->registry()->names() as $name) {
            $this->assertMatchesRegularExpression(
                '#^swishfolio/[a-z][a-z0-9-]*$#',
                $name,
                $name . ' does not follow the namespace/name convention the Abilities API expects.'
            );
        }
    }

    public function test_every_description_is_long_enough_to_choose_by_and_short_enough_to_afford()
    {
        foreach ($this->registry()->all() as $name => $tool) {
            $length = strlen($tool->getDescription());

            $this->assertGreaterThanOrEqual(self::MIN_LENGTH, $length, $name . ' is too terse.');
            $this->assertLessThanOrEqual(self::MAX_LENGTH, $length, $name . ' is too verbose.');
        }
    }

    public function test_no_two_tools_are_described_in_the_same_words()
    {
        $words = [];

        foreach ($this->registry()->all() as $name => $tool) {
            $words[$name] = $this->contentWords($tool->getDescription());
        }

        foreach ($words as $a => $first) {
            foreach ($words as $b => $second) {
                if ($a >= $b) {
                    continue;
                }

                $shared = count(array_intersect($first, $second));
                $union  = count(array_unique(array_merge($first, $second)));

                $this->assertLessThan(
                    self::MAX_SIMILARITY,
                    $union > 0 ? $shared / $union : 0.0,
                    sprintf('%s and %s are described too similarly to tell apart.', $a, $b)
                );
            }
        }
    }

    public function test_every_tool_that_changes_the_live_site_says_it_must_be_confirmed()
    {
        foreach ($this->registry()->all() as $name => $tool) {
            if (
                ! in_array(
                    $tool->getSideEffect(),
                    [ToolInterface::EFFECT_WRITE_PUBLISHED, ToolInterface::EFFECT_DESTRUCTIVE],
                    true
                )
            ) {
                continue;
            }

            $this->assertStringContainsStringIgnoringCase(
                'confirm',
                $tool->getDescription(),
                $name . ' needs confirmation, and a model that is not told that will plan around a refusal.'
            );
        }
    }

    public function test_the_catalogue_covers_reading_writing_and_undoing()
    {
        $effects = [];

        foreach ($this->registry()->all() as $tool) {
            $effects[$tool->getSideEffect()] = true;
        }

        foreach (
            [
                ToolInterface::EFFECT_READ,
                ToolInterface::EFFECT_WRITE_DRAFT,
                ToolInterface::EFFECT_WRITE_PUBLISHED,
                ToolInterface::EFFECT_DESTRUCTIVE,
            ] as $effect
        ) {
            $this->assertArrayHasKey($effect, $effects, 'No tool declares ' . $effect . '.');
        }
    }

    public function test_advertising_the_whole_catalogue_stays_affordable()
    {
        $bytes = strlen((string) wp_json_encode($this->registry()->describe()));

        $this->assertLessThan(
            self::MAX_CATALOGUE_BYTES,
            $bytes,
            sprintf(
                'The tool list costs %d bytes per request. Past this, the catalogue crowds out the conversation.',
                $bytes
            )
        );
    }

    public function test_every_schema_property_carries_enough_for_the_model_to_fill_it_in()
    {
        foreach ($this->registry()->all() as $name => $tool) {
            $schema = $tool->getInputSchema();

            foreach ($schema['properties'] as $property => $spec) {
                $this->assertArrayHasKey(
                    'type',
                    $spec,
                    sprintf('%s.%s has no type, so the validator cannot check it.', $name, $property)
                );

                $required = isset($schema['required']) ? $schema['required'] : [];

                if (in_array($property, $required, true)) {
                    continue;
                }

                $this->assertTrue(
                    isset($spec['default']) || array_key_exists('default', $spec) || isset($spec['description']),
                    sprintf(
                        '%s.%s is optional with neither a default nor a description, so its absence is unexplained.',
                        $name,
                        $property
                    )
                );
            }
        }
    }
}
