<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tests\Evals\Support\Corpus;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;
use SwishfolioCore\Tests\Evals\Support\LiveMode;
use SwishfolioCore\Tests\Evals\Support\LiveEvalProvider;

/**
 * The live half of the evaluation suite. It calls a real provider, costs real
 * money, and never runs in CI.
 *
 * What it measures is the one thing recorded mode structurally cannot: whether a
 * model, given today's prompts and today's catalogue, still produces an answer
 * that survives validation. A prompt regression — a manifest that stopped being
 * described, a token set that stopped being listed, an instruction that stopped
 * being clear — leaves every recorded test green and breaks the feature for every
 * user. Only this catches it.
 *
 * Every case runs the same auditors as recorded mode, so a live answer is held to
 * exactly the standard a committed one is, rather than to a looser "did it return
 * something" bar.
 *
 * To run it:
 *
 *     SWISHFOLIO_AI_EVAL_LIVE=1 \
 *     SWISHFOLIO_AI_EVAL_API_KEY=sk-... \
 *     SWISHFOLIO_AI_EVAL_MODEL=gpt-4o-mini \
 *     vendor/bin/phpunit --testsuite evals --group live
 *
 * SWISHFOLIO_AI_EVAL_BASE_URL points it at any OpenAI-compatible endpoint.
 *
 * @group live
 */
class LiveProviderEvalTest extends EvalTestCase
{
    protected function setUp(): void
    {
        // Before anything else, and before any fixture is even read: a run that
        // was not explicitly asked for must not get as far as building a request.
        if (! LiveMode::isEnabled()) {
            $this->markTestSkipped(LiveMode::reasonDisabled());
        }

        parent::setUp();
    }

    /**
     * @return array
     */
    public function editCorpus()
    {
        return Corpus::editCases();
    }

    /**
     * @return array
     */
    public function planCorpus()
    {
        return Corpus::planCases();
    }

    /**
     * @dataProvider editCorpus
     *
     * @param array $case Corpus case.
     */
    public function test_a_real_model_proposes_edits_that_pass_every_check($case)
    {
        $model = LiveMode::modelFor(LiveMode::environment());

        try {
            $validated = $this->runEdit($case, $this->provider());
        } catch (ProviderException $e) {
            $this->fail(sprintf('%s: the provider failed — %s', $model, $e->getMessage()));
        } catch (ValidationException $e) {
            $this->fail(sprintf(
                "%s: %s\nThe model's answer was refused by the \"%s\" stage at \"%s\": %s",
                $model,
                isset($case['label']) ? $case['label'] : 'eval case',
                $e->getStage(),
                (string) $e->getPath(),
                $e->getMessage()
            ));
        }

        $this->assertNotSame([], $validated['actions'], 'The model proposed no change at all.');
        $this->assertNoViolations(
            $this->actionAudit()->auditStorageStability($validated, (array) $case['blockTree']),
            $case
        );
    }

    /**
     * @dataProvider planCorpus
     *
     * @param array $case Corpus case.
     */
    public function test_a_real_model_plans_a_site_that_passes_every_check($case)
    {
        $model = LiveMode::modelFor(LiveMode::environment());

        try {
            $result = $this->runPlan($case, $this->provider());
        } catch (ProviderException $e) {
            $this->fail(sprintf('%s: the provider failed — %s', $model, $e->getMessage()));
        } catch (ValidationException $e) {
            $this->fail(sprintf(
                "%s: %s\nThe model's plan was refused by the \"%s\" stage at \"%s\": %s",
                $model,
                isset($case['label']) ? $case['label'] : 'eval case',
                $e->getStage(),
                (string) $e->getPath(),
                $e->getMessage()
            ));
        }

        $this->assertNotSame([], $result['validated']['pages'], 'The model planned no pages.');

        $this->assertNoViolations($this->planAudit($case)->auditProposal($result['raw']), $case);
        $this->assertNoViolations(
            $this->planAudit($case)->auditComposedContent($result['validated'], $this->compose($result['validated'])),
            $case
        );
    }

    /**
     * @return LiveEvalProvider
     */
    private function provider()
    {
        return new LiveEvalProvider(LiveMode::credentialsFor(LiveMode::environment()));
    }
}
