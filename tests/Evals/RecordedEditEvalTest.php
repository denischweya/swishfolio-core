<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\Tests\Evals\Support\Corpus;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;
use SwishfolioCore\Tests\Evals\Support\RecordedProvider;

/**
 * The block-editing half of the evaluation suite, in recorded mode.
 *
 * Each case is a real selection from one of the six verticals the plugin is built
 * for, a real instruction, and a committed answer of the shape a model gives. The
 * answer never changes, which is the point: what is under test is everything
 * between the answer and the editor — the registry, the manifests, the token
 * sets and the five validation stages. A manifest that loses an attribute, an
 * enum that gains a value, or a token that disappears from theme.json all fail
 * here rather than in a user's editor.
 *
 * The assertions are deliberately made twice over. The audit reads the manifests
 * directly and says whether the answer was acceptable; the pipeline says whether
 * it was accepted. Agreement is the signal; a disagreement in either direction is
 * a regression.
 */
class RecordedEditEvalTest extends EvalTestCase
{
    /**
     * @return array
     */
    public function corpus()
    {
        return Corpus::editCases();
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_every_block_and_attribute_the_model_touches_is_manifested($case)
    {
        $this->assertNoViolations(
            $this->actionAudit()->audit((array) $case['response'], (array) $case['blockTree']),
            $case
        );
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_the_whole_edit_pipeline_accepts_the_answer($case)
    {
        $provider  = new RecordedProvider((array) $case['response']);
        $validated = $this->runEdit($case, $provider);

        $this->assertSame(1, $provider->getCallCount());
        $this->assertCount(
            count((array) $case['response']['actions']),
            $validated['actions'],
            'Validation must not silently drop actions.'
        );
        $this->assertSame((string) $case['response']['schemaVersion'], $validated['schemaVersion']);
    }

    /**
     * The prompt is part of what an eval measures: a manifest that never reaches
     * the model is as broken as one that is wrong, and it would otherwise pass
     * every assertion here, because the recorded answer arrives regardless.
     *
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_the_prompt_carries_the_manifest_for_the_selected_block($case)
    {
        $provider = new RecordedProvider((array) $case['response']);

        $this->runEdit($case, $provider);

        $request   = $provider->getLastRequest();
        $blockName = (string) $case['selection']['blockName'];

        $this->assertNotNull($request);
        $this->assertStringContainsString(
            $blockName,
            $request->getSystemPrompt(),
            'The selected block\'s manifest never reached the model.'
        );
        $this->assertStringContainsString(
            (string) $case['instruction'],
            $request->getUserPrompt(),
            'The user\'s instruction never reached the model.'
        );

        $capability = $this->registry()->get($blockName);

        foreach (array_keys($capability->getFields()) as $attribute) {
            $this->assertStringContainsString(
                '"' . $attribute . '"',
                $request->getSystemPrompt(),
                sprintf('Attribute "%s" is manifested but absent from the prompt.', $attribute)
            );
        }
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_design_tokens_are_expanded_to_the_value_a_block_stores($case)
    {
        $validated = $this->runEdit($case, new RecordedProvider((array) $case['response']));
        $tree      = (array) $case['blockTree'];
        $checked   = 0;

        foreach ((array) $case['response']['actions'] as $index => $action) {
            if ($action['type'] === 'update_block_attributes') {
                $blockName = (string) $tree[$action['clientId']]['name'];
                $raw       = (array) $action['changes'];
                $final     = (array) $validated['actions'][$index]['changes'];
            } elseif ($action['type'] === 'insert_block') {
                $blockName = (string) $action['blockName'];
                $raw       = isset($action['attributes']) ? (array) $action['attributes'] : [];
                $final     = (array) $validated['actions'][$index]['attributes'];
            } else {
                continue;
            }

            $capability = $this->registry()->get($blockName);

            foreach ($raw as $attribute => $slug) {
                $field = $capability->getField($attribute);

                if ($field === null || $field->getType() !== 'token') {
                    continue;
                }

                $checked++;

                $this->assertSame(
                    $this->tokens()->toStorageValue($field->getTokenSet(), (string) $slug),
                    $final[$attribute],
                    sprintf('actions.%d.%s: token slug was not expanded to its stored value.', $index, $attribute)
                );
            }
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'This case sets no design token, so it cannot prove token expansion works.'
        );
    }

    /**
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_validated_attributes_survive_storage_in_a_block_comment($case)
    {
        $validated = $this->runEdit($case, new RecordedProvider((array) $case['response']));

        $this->assertNoViolations(
            $this->actionAudit()->auditStorageStability($validated, (array) $case['blockTree']),
            $case
        );
    }

    /**
     * Validation transforms values; it must never widen the set of attributes an
     * action writes. A stage that invented a key would be invisible to every
     * check made before it ran.
     *
     * @dataProvider corpus
     *
     * @param array $case Corpus case.
     */
    public function test_validation_writes_no_attribute_the_model_did_not_ask_for($case)
    {
        $validated = $this->runEdit($case, new RecordedProvider((array) $case['response']));

        foreach ((array) $case['response']['actions'] as $index => $action) {
            if ($action['type'] === 'update_block_attributes') {
                $this->assertSame(
                    array_keys((array) $action['changes']),
                    array_keys((array) $validated['actions'][$index]['changes']),
                    sprintf('actions.%d: the validated change set differs from what was proposed.', $index)
                );
                continue;
            }

            if ($action['type'] === 'insert_block') {
                $this->assertSame(
                    array_keys(isset($action['attributes']) ? (array) $action['attributes'] : []),
                    array_keys((array) $validated['actions'][$index]['attributes']),
                    sprintf('actions.%d: the validated attribute set differs from what was proposed.', $index)
                );
            }
        }
    }
}
