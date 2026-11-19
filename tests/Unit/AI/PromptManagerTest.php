<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Prompt\PromptManager;

class PromptManagerTest extends TestCase
{
    private function manager()
    {
        return new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');
    }

    public function test_loads_a_system_prompt()
    {
        $this->assertStringContainsString('structured actions', $this->manager()->system('block-editing'));
    }

    public function test_design_prompts_open_with_the_persona()
    {
        foreach (PromptManager::DESIGN_PROMPTS as $name) {
            $this->assertStringStartsWith(
                'You are a senior UI/UX designer',
                $this->manager()->system($name),
                $name
            );
        }
    }

    public function test_substitutes_task_placeholders()
    {
        $rendered = $this->manager()->task('modify-block', [
            'selection'   => 'SELECTION',
            'context'     => 'CONTEXT',
            'instruction' => 'Make it bolder',
        ]);

        $this->assertStringContainsString('SELECTION', $rendered);
        $this->assertStringContainsString('Make it bolder', $rendered);
    }

    public function test_unreplaced_placeholder_raises()
    {
        $this->expectException(\RuntimeException::class);
        $this->manager()->task('modify-block', ['selection' => 'S']);
    }

    public function test_unknown_prompt_raises()
    {
        $this->expectException(\RuntimeException::class);
        $this->manager()->system('does-not-exist');
    }
}
