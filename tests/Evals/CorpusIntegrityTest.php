<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Tests\Evals\Support\BlockCatalogue;
use SwishfolioCore\Tests\Evals\Support\Corpus;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;
use SwishfolioCore\Tests\Evals\Support\LiveMode;

/**
 * Tests about the eval suite rather than about the plugin.
 *
 * An eval suite fails quietly in two ways and neither shows up as a red run: a
 * fixture is deleted and the corpus silently shrinks, or the ground truth it
 * grades against drifts away from the ground truth production uses. Both are
 * checked here, because a corpus that has stopped covering something is worse
 * than no corpus — it still reports green.
 */
class CorpusIntegrityTest extends EvalTestCase
{
    /**
     * Stages that can actually refuse something. Sanitisation transforms rather
     * than rejects, which is why it is absent from both lists.
     */
    const REJECTING_STAGES = [
        'envelope',
        'target',
        'allowlist',
        'value',
        'plan-envelope',
        'plan-reference',
        'plan-slot',
        'plan-value',
        'plan-collision',
    ];

    public function test_every_vertical_has_a_generation_fixture()
    {
        $this->assertSame(
            [],
            Corpus::missing('plan'),
            'The generation corpus is missing verticals: ' . implode(', ', Corpus::missing('plan'))
        );
        $this->assertCount(count(Corpus::VERTICALS), Corpus::planCases());
    }

    public function test_every_vertical_has_a_block_editing_fixture()
    {
        $this->assertSame(
            [],
            Corpus::missing('edit'),
            'The editing corpus is missing verticals: ' . implode(', ', Corpus::missing('edit'))
        );
        $this->assertCount(count(Corpus::VERTICALS), Corpus::editCases());
    }

    public function test_the_rejection_corpus_covers_every_stage_that_can_refuse()
    {
        $covered = [];

        foreach (Corpus::adversarialCases() as $row) {
            $covered[(string) $row[0]['expectStage']] = true;
        }

        foreach (self::REJECTING_STAGES as $stage) {
            $this->assertArrayHasKey(
                $stage,
                $covered,
                sprintf('No rejection fixture exercises the "%s" stage.', $stage)
            );
        }
    }

    public function test_every_rejection_fixture_explains_what_the_refusal_protects()
    {
        foreach (Corpus::adversarialCases() as $name => $row) {
            $case = $row[0];

            $this->assertArrayHasKey('why', $case, sprintf('%s has no "why".', $name));
            $this->assertGreaterThan(
                40,
                strlen((string) $case['why']),
                sprintf('%s: "why" is too short to be a reason anyone could act on.', $name)
            );
            $this->assertContains((string) $case['track'], ['plan', 'edit'], sprintf('%s: unknown track.', $name));
        }
    }

    /**
     * The committed theme.json is a copy, and a copy drifts. Design tokens are the
     * one thing an AI is allowed to emit, so a corpus grading against a stale
     * palette would pass slugs that no longer render.
     */
    public function test_the_committed_theme_json_still_matches_the_theme()
    {
        $live = self::liveThemeJsonPath();

        if ($live === null) {
            $this->markTestSkipped('The Swishfolio theme is not checked out beside this plugin.');
        }

        $committed = TokenResolver::fromArray(
            json_decode((string) file_get_contents(Corpus::dir() . '/theme.json'), true)
        );
        $actual = TokenResolver::fromArray(json_decode((string) file_get_contents($live), true));

        foreach (['color', 'spacing', 'fontSize'] as $set) {
            $this->assertSame(
                $actual->getSlugs($set),
                $committed->getSlugs($set),
                sprintf(
                    'tests/Evals/fixtures/theme.json has drifted from the theme for the "%s" token set.',
                    $set
                )
            );
        }
    }

    /**
     * "Every block used is registered" is only a real assertion while the block
     * registry on disk can be read. If WordPress is not reachable the core half
     * of that check degrades to nothing, and a skip is the honest way to say so.
     */
    public function test_registered_block_names_are_readable_from_disk()
    {
        $catalogue = $this->catalogue();

        $this->assertContains('swishfolio-core/hero', $catalogue->pluginBlocks());
        $this->assertContains('swishfolio-core/portfolio', $catalogue->pluginBlocks());

        if (! $catalogue->isCoreAvailable()) {
            $this->markTestSkipped(
                'WordPress core is not reachable from this checkout, so core block names cannot be verified.'
            );
        }

        $this->assertContains('core/paragraph', $catalogue->coreBlocks());
        $this->assertContains('core/heading', $catalogue->coreBlocks());
        $this->assertContains('core/spacer', $catalogue->coreBlocks());
    }

    /**
     * Every block the corpus touches must carry a manifest, or the corpus is
     * quietly exercising a narrower surface than it appears to.
     */
    public function test_every_block_the_editing_corpus_selects_is_manifested()
    {
        $selected = [];

        foreach (Corpus::editCases() as $row) {
            $selected[(string) $row[0]['selection']['blockName']] = true;

            foreach ((array) $row[0]['blockTree'] as $block) {
                $selected[(string) $block['name']] = true;
            }
        }

        $this->assertGreaterThanOrEqual(6, count($selected), 'The editing corpus exercises too few block types.');

        foreach (array_keys($selected) as $blockName) {
            if ($blockName === 'core/spacer') {
                continue;
            }

            $this->assertNotNull(
                $this->registry()->get($blockName),
                sprintf('The corpus uses "%s", which carries no capability manifest.', $blockName)
            );
        }
    }

    public function test_live_mode_is_off_unless_it_is_asked_for_exactly()
    {
        $key = LiveMode::KEY_VAR;

        $this->assertFalse(LiveMode::enabledFor([]));
        $this->assertFalse(LiveMode::enabledFor([$key => 'sk-real-key']));
        $this->assertFalse(LiveMode::enabledFor([LiveMode::ENABLE_VAR => '0', $key => 'sk-real-key']));
        $this->assertFalse(LiveMode::enabledFor([LiveMode::ENABLE_VAR => 'true', $key => 'sk-real-key']));
        $this->assertFalse(LiveMode::enabledFor([LiveMode::ENABLE_VAR => 'yes', $key => 'sk-real-key']));
        $this->assertFalse(LiveMode::enabledFor([LiveMode::ENABLE_VAR => '1']));
        $this->assertFalse(LiveMode::enabledFor([LiveMode::ENABLE_VAR => '1', $key => '  ']));

        $this->assertTrue(LiveMode::enabledFor([LiveMode::ENABLE_VAR => '1', $key => 'sk-real-key']));
    }

    public function test_live_mode_says_why_it_is_off()
    {
        $this->assertStringContainsString(LiveMode::ENABLE_VAR, LiveMode::reasonDisabledFor([]));
        $this->assertStringContainsString(
            LiveMode::KEY_VAR,
            LiveMode::reasonDisabledFor([LiveMode::ENABLE_VAR => '1'])
        );
        $this->assertSame(
            '',
            LiveMode::reasonDisabledFor([LiveMode::ENABLE_VAR => '1', LiveMode::KEY_VAR => 'sk-real-key'])
        );
    }

    /**
     * Recorded mode has to be able to run with no network at all. The provider
     * that can reach one is a separate class, and nothing in the recorded path
     * may reference it.
     */
    public function test_nothing_in_recorded_mode_can_reach_the_network()
    {
        $recorded = [
            SFCORE_PLUGIN_DIR . 'tests/Evals/RecordedEditEvalTest.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/RecordedGenerationEvalTest.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/AdversarialEvalTest.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/Support/RecordedProvider.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/Support/ActionAudit.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/Support/PlanAudit.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/Support/BlockMarkup.php',
            SFCORE_PLUGIN_DIR . 'tests/Evals/Support/EvalTestCase.php',
        ];

        foreach ($recorded as $file) {
            $source = (string) file_get_contents($file);

            foreach (['LiveEvalProvider', 'curl_', 'wp_remote_', 'file_get_contents(\'http', 'fsockopen'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    sprintf('%s references "%s", which recorded mode must never do.', basename($file), $needle)
                );
            }
        }

        $this->assertFalse(
            function_exists('wp_remote_post'),
            'wp_remote_post() is defined in the eval bootstrap, so a provider could reach the network by accident.'
        );
    }

    public function test_the_wordpress_core_block_directory_is_where_it_is_expected()
    {
        $dir = BlockCatalogue::coreBlockDir();

        if ($dir === null) {
            $this->markTestSkipped('WordPress core is not reachable from this checkout.');
        }

        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir . '/paragraph/block.json');
    }
}
