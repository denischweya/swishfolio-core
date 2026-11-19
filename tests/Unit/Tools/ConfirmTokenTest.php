<?php

namespace SwishfolioCore\Tests\Unit\Tools;

use SwishfolioCore\Tools\ConfirmToken;

/**
 * The gate has to tell "the user approved this" apart from "the model asserted
 * that the user approved this", and both arrive in the same payload. These are
 * the ways a weaker token would fail to.
 */
class ConfirmTokenTest extends ToolsTestCase
{
    private function calls()
    {
        return [['name' => 'swishfolio/set-front-page', 'input' => ['postId' => 10]]];
    }

    public function test_a_token_approves_the_plan_it_was_issued_for()
    {
        $token = ConfirmToken::issue($this->calls(), 7);

        $this->assertTrue(ConfirmToken::verify($token, $this->calls(), 7));
    }

    public function test_a_token_does_not_approve_a_different_plan()
    {
        $token = ConfirmToken::issue($this->calls(), 7);

        $different = [['name' => 'swishfolio/set-front-page', 'input' => ['postId' => 11]]];

        $this->assertFalse(ConfirmToken::verify($token, $different, 7));
    }

    public function test_a_token_does_not_approve_an_extra_call_appended_to_the_plan()
    {
        $token = ConfirmToken::issue($this->calls(), 7);

        $extended   = $this->calls();
        $extended[] = ['name' => 'swishfolio/rollback-run', 'input' => ['runId' => 'abc']];

        $this->assertFalse(ConfirmToken::verify($token, $extended, 7));
    }

    public function test_a_token_issued_to_one_user_does_not_approve_anothers_run()
    {
        $token = ConfirmToken::issue($this->calls(), 7);

        $this->assertFalse(ConfirmToken::verify($token, $this->calls(), 8));
    }

    public function test_key_order_in_the_input_does_not_change_whether_a_token_verifies()
    {
        $token = ConfirmToken::issue(
            [['name' => 'x/y', 'input' => ['a' => 1, 'b' => 2]]],
            7
        );

        $this->assertTrue(
            ConfirmToken::verify($token, [['name' => 'x/y', 'input' => ['b' => 2, 'a' => 1]]], 7)
        );
    }

    public function test_an_expired_token_is_refused()
    {
        $issuedAt = time() - ConfirmToken::TTL - 10;

        $this->assertFalse(ConfirmToken::verify($issuedAt . '.' . str_repeat('a', 32), $this->calls(), 7));
    }

    public function test_a_malformed_token_is_refused_rather_than_erroring()
    {
        foreach (['', 'nonsense', '.', 'abc.def', '12345'] as $token) {
            $this->assertFalse(ConfirmToken::verify($token, $this->calls(), 7), 'Accepted: ' . $token);
        }
    }

    public function test_a_forged_signature_is_refused()
    {
        $token = ConfirmToken::issue($this->calls(), 7);
        $parts = explode('.', $token, 2);

        $this->assertFalse(ConfirmToken::verify($parts[0] . '.' . strrev($parts[1]), $this->calls(), 7));
    }
}
