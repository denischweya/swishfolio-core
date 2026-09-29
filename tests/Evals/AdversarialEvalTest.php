<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\Tests\Evals\Support\Corpus;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;
use SwishfolioCore\Tests\Evals\Support\RecordedProvider;

/**
 * The rejection corpus: answers a model plausibly gives that must never reach a
 * database or an editor.
 *
 * A suite made only of good answers measures nothing useful. Every check in the
 * positive corpus passes trivially on a pipeline that has stopped validating
 * anything at all, and the day an allowlist stage is accidentally short-circuited
 * the green run would be the last thing to tell you. These cases fail loudly
 * instead, and each names the stage that is supposed to catch it, so a rule that
 * moves between stages shows up as a changed expectation rather than as silence.
 *
 * Each fixture carries a "why" explaining what the refusal protects, because a
 * rejection test without one turns into a rule nobody dares change.
 */
class AdversarialEvalTest extends EvalTestCase
{
    /**
     * @return array
     */
    public function corpus()
    {
        return Corpus::adversarialCases();
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Adversarial case.
     */
    public function test_the_pipeline_refuses_it_naming_the_stage_that_caught_it($case)
    {
        $expected = (string) $case['expectStage'];

        try {
            if ((string) $case['track'] === 'plan') {
                $this->runPlan($case, new RecordedProvider((array) $case['response']));
            } else {
                $this->runEdit($case, new RecordedProvider((array) $case['response']));
            }
        } catch (ValidationException $e) {
            $this->assertSame(
                $expected,
                $e->getStage(),
                sprintf(
                    "%s was refused by the \"%s\" stage rather than \"%s\".\nWhy it must be refused: %s\nReason given: %s",
                    (string) $case['name'],
                    $e->getStage(),
                    $expected,
                    (string) $case['why'],
                    $e->getMessage()
                )
            );

            $this->assertNotSame(
                '',
                (string) $e->getMessage(),
                'A refusal with no reason cannot be shown to a user.'
            );

            $this->assertNotNull(
                $e->getPath(),
                sprintf('%s: the refusal names no offending path, so nothing can be highlighted.', (string) $case['name'])
            );

            return;
        }

        $this->fail(sprintf(
            "%s was accepted, and it must not be.\nWhy it must be refused: %s\nExpected stage: %s",
            (string) $case['name'],
            (string) $case['why'],
            $expected
        ));
    }

    /**
     * The audits are the eval suite's own reading of the manifests, and they have
     * to agree with the pipeline about what is unacceptable — otherwise the
     * positive corpus is being graded by something laxer than the runtime.
     *
     * Only the cases an audit is in a position to judge are checked: the envelope
     * and target stages are about the request, not about what the model wrote.
     *
     * @dataProvider corpus
     *
     * @param array $case Adversarial case.
     */
    public function test_the_eval_audits_reach_the_same_verdict($case)
    {
        $unjudgeable = ['envelope', 'target', 'plan-envelope', 'plan-collision'];

        if (in_array((string) $case['expectStage'], $unjudgeable, true)) {
            $this->assertTrue(true, 'This refusal is about the request, not about what the model wrote.');

            return;
        }

        $violations = (string) $case['track'] === 'plan'
            ? $this->planAudit($case)->auditProposal((array) $case['response'])
            : $this->actionAudit()->audit((array) $case['response'], (array) $case['blockTree']);

        $this->assertNotSame(
            [],
            $violations,
            sprintf(
                "%s is refused by the pipeline but the eval audit finds nothing wrong with it.\nWhy it must be refused: %s",
                (string) $case['name'],
                (string) $case['why']
            )
        );
    }
}
