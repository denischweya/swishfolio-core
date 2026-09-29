<?php

namespace SwishfolioCore\Tests\Unit\Actions;

use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationException;

class ActionValidatorTest extends StageTestCase
{
    private function validator()
    {
        return ActionValidator::withDefaultStages();
    }

    public function test_a_valid_response_returns_its_actions()
    {
        $result = $this->validator()->validate(
            $this->response([$this->updateAction('hero-1', ['heading' => 'Marketing that compounds'])]),
            $this->context()
        );

        $this->assertCount(1, $result['actions']);
        $this->assertSame('Marketing that compounds', $result['actions'][0]['changes']['heading']);
    }

    public function test_one_bad_action_rejects_the_entire_response()
    {
        $response = $this->response([
            $this->updateAction('hero-1', ['heading' => 'Fine']),
            $this->updateAction('hero-1', ['decoShape1Color' => 'accent-1']),
        ]);

        $this->expectException(ValidationException::class);
        $this->validator()->validate($response, $this->context());
    }

    public function test_stages_run_in_declared_order()
    {
        // Fails envelope (bad version) AND allowlist (bogus attribute) AND
        // target (unknown clientId). Envelope must win, proving order.
        $response = [
            'schemaVersion' => '9.9',
            'summary'       => 's',
            'actions'       => [$this->updateAction('ghost-9', ['bogus' => 1])],
        ];

        try {
            $this->validator()->validate($response, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('envelope', $e->getStage());
        }
    }

    public function test_target_runs_before_allowlist()
    {
        $response = $this->response([$this->updateAction('ghost-9', ['bogus' => 1])]);

        try {
            $this->validator()->validate($response, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('target', $e->getStage());
        }
    }

    public function test_allowlist_runs_before_value()
    {
        $response = $this->response([$this->updateAction('hero-1', ['decoShape1Color' => 'not-a-token'])]);

        try {
            $this->validator()->validate($response, $this->context());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('allowlist', $e->getStage());
        }
    }

    public function test_tokens_are_expanded_and_text_sanitised_in_one_pass()
    {
        $result = $this->validator()->validate(
            $this->response([$this->updateAction('hero-1', [
                'backgroundColor' => 'accent-2',
                'heading'         => '<b>Premium</b>',
            ])]),
            $this->context()
        );

        $changes = $result['actions'][0]['changes'];

        $this->assertSame('var(--wp--preset--color--accent-2)', $changes['backgroundColor']);
        $this->assertSame('Premium', $changes['heading']);
    }

    /**
     * An ampersand in a headline is ordinary content and must survive.
     *
     * The generation pipeline's PlanValueStage deliberately REJECTS "&" and ">"
     * because it writes attribute JSON into pattern markup itself. The editor
     * path does not: Gutenberg's own serializer escapes attribute JSON, and
     * sanitize_text_field leaves "&" alone. Mirroring that guard here would
     * refuse "Design & build" for no benefit, so this test exists to stop
     * anyone "fixing" the asymmetry.
     */
    public function test_an_ampersand_in_a_text_attribute_is_preserved_not_rejected()
    {
        $result = $this->validator()->validate(
            $this->response([$this->updateAction('hero-1', ['heading' => 'Design & build'])]),
            $this->context()
        );

        $this->assertSame('Design & build', $result['actions'][0]['changes']['heading']);
    }

    public function test_failure_reports_the_offending_path()
    {
        try {
            $this->validator()->validate(
                $this->response([$this->updateAction('hero-1', ['headingSize' => 'gigantic'])]),
                $this->context()
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('value', $e->getStage());
            $this->assertSame('actions.0.changes.headingSize', $e->getPath());
        }
    }
}
