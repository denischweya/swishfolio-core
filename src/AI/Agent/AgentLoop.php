<?php
/**
 * The loop that lets a model operate the site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Agent;

use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Conversation\ToolCall;
use SwishfolioCore\AI\Conversation\ToolDefinition;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tools\CallSummary;
use SwishfolioCore\Tools\ConfirmToken;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRunner;

/**
 * The missing link: the tool layer could do everything and no model could reach
 * it.
 *
 * Three decisions shape this class.
 *
 * A tool failure is handed back to the model rather than raised. The whole point
 * of stable error codes and field paths was to let a model correct its own call;
 * aborting on a schema_invalid would throw that away and show the user an error
 * for something the model could have fixed itself on the next turn.
 *
 * A confirmation refusal stops the loop instead. Reads and drafts proceed
 * freely, but the first call that would change what visitors see ends the turn
 * with the proposal and a token for exactly it. The user approves, the same
 * calls are resubmitted with the token, and the loop runs them. The model is
 * never told which tools are gated, so it proposes the work rather than planning
 * around the gate.
 *
 * Iterations are capped, because a model that keeps calling tools is otherwise
 * an unbounded bill against the owner's own API key.
 */
class AgentLoop
{
    const MAX_ITERATIONS = 8;
    const MAX_TOKENS     = 8192;

    /** @var AIProviderInterface */
    private $provider;

    /** @var ToolRunner */
    private $runner;

    /** @var PromptManager */
    private $prompts;

    /** @var string */
    private $model;

    /** @var string */
    private $brandContext;

    /**
     * @param AIProviderInterface $provider     Provider that will answer.
     * @param ToolRunner          $runner       Tool pipeline.
     * @param PromptManager       $prompts      Prompt loader.
     * @param string              $model        Model identifier.
     * @param string              $brandContext Optional brand paragraph appended to the system prompt.
     */
    public function __construct(
        AIProviderInterface $provider,
        ToolRunner $runner,
        PromptManager $prompts,
        $model,
        $brandContext = ''
    ) {
        $this->provider     = $provider;
        $this->runner       = $runner;
        $this->prompts      = $prompts;
        $this->model        = (string) $model;
        $this->brandContext = (string) $brandContext;
    }

    /**
     * Runs one turn of the conversation.
     *
     * @param string      $instruction  What the owner asked for; '' when resuming an approval.
     * @param ToolContext $context      Run state.
     * @param Message[]   $history      Prior conversation.
     * @param string      $confirmToken Token approving the calls that were pending.
     *
     * @throws ProviderException When the provider cannot answer at all.
     *
     * @return AgentRun
     */
    public function run($instruction, ToolContext $context, array $history = [], $confirmToken = '')
    {
        if (! $this->provider->supportsTools()) {
            throw new ProviderException(
                __('The connected AI provider cannot use tools, so it cannot manage the site.', 'swishfolio-core'),
                0
            );
        }

        $messages = $history;

        if ('' !== (string) $instruction) {
            $messages[] = Message::user((string) $instruction);
        }

        $pendingApproved = $this->approvedCalls($messages, $confirmToken, $context);
        $steps           = [];
        $inputTokens     = 0;
        $outputTokens    = 0;
        $maxIterations   = (int) apply_filters('swishfolio_ai_agent_max_iterations', self::MAX_ITERATIONS);

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            // On a resumed turn the model has already asked; replay its calls
            // rather than paying for the same decision twice.
            $replaying = $pendingApproved !== null;

            if ($replaying) {
                $calls           = $pendingApproved;
                $pendingApproved = null;
                $reply           = null;
            } else {
                $reply = $this->provider->converse(
                    new ConversationRequest(
                        $this->systemPrompt(),
                        $messages,
                        $this->toolDefinitions(),
                        $this->model,
                        self::MAX_TOKENS
                    )
                );

                $inputTokens  += $reply->getInputTokens();
                $outputTokens += $reply->getOutputTokens();

                if (! $reply->hasToolCalls()) {
                    $messages[] = Message::assistant($reply->getText());

                    return new AgentRun(
                        AgentRun::STATUS_DONE,
                        $reply->getText(),
                        $steps,
                        $messages,
                        [],
                        '',
                        $inputTokens,
                        $outputTokens,
                        $iteration
                    );
                }

                $calls      = $reply->getToolCalls();
                $messages[] = Message::assistant($reply->getText(), $calls);
            }

            $results = [];

            foreach ($calls as $call) {
                $outcome = $this->runCall($call, $context);

                if ($outcome['pending']) {
                    return $this->pause(
                        $calls,
                        $steps,
                        $messages,
                        $context,
                        null === $reply ? '' : $reply->getText(),
                        $inputTokens,
                        $outputTokens,
                        $iteration
                    );
                }

                $steps[]   = $outcome['step'];
                $results[] = $outcome['message'];
            }

            // The owner approved exactly the calls just replayed. Anything the
            // model decides next (possibly after reading injected text in a
            // page or CV) must ask again, not ride on that approval.
            if ($replaying) {
                $context->setConfirmed(false);
            }

            foreach ($results as $result) {
                $messages[] = $result;
            }
        }

        return new AgentRun(
            AgentRun::STATUS_CAPPED,
            __('I stopped after too many steps without finishing. Tell me a smaller piece of this and I will carry on.', 'swishfolio-core'),
            $steps,
            $messages,
            [],
            '',
            $inputTokens,
            $outputTokens,
            $maxIterations
        );
    }

    /**
     * Runs one call, turning every outcome into something the model can read.
     *
     * @param ToolCall    $call    Call to run.
     * @param ToolContext $context Run state.
     *
     * @return array {pending: bool, step: AgentStep|null, message: Message|null}
     */
    private function runCall(ToolCall $call, ToolContext $context)
    {
        try {
            $result = $this->runner->run($call->getName(), $call->getInput(), $context);
        } catch (ToolException $e) {
            if (ToolError::CONFIRM_REQUIRED === $e->getError()->getCode()) {
                return ['pending' => true, 'step' => null, 'message' => null];
            }

            return [
                'pending' => false,
                'step'    => new AgentStep(
                    $call->getName(),
                    $e->getMessage(),
                    false,
                    $e->getError()->getCode()
                ),
                'message' => Message::toolResult(
                    $call->getId(),
                    (string) wp_json_encode($e->getError()->toArray()),
                    true
                ),
            ];
        }

        $tool = $this->runner->registry()->get($call->getName());

        return [
            'pending' => false,
            'step'    => new AgentStep(
                $call->getName(),
                null === $tool ? $call->getName() : CallSummary::describe($tool, $call->getInput()),
                true
            ),
            'message' => Message::toolResult($call->getId(), (string) wp_json_encode($result)),
        ];
    }

    /**
     * Ends the turn with a proposal the owner has to approve.
     *
     * The whole batch is offered, not just the gated call: the model asked for
     * these together, and approving half of a batch it reasoned about as a unit
     * would run something it did not propose.
     *
     * @param ToolCall[]  $calls        Calls the model asked for.
     * @param AgentStep[] $steps        Steps already taken this turn.
     * @param Message[]   $messages     Conversation so far.
     * @param ToolContext $context      Run state.
     * @param string      $text         Assistant prose from this turn.
     * @param int         $inputTokens  Prompt tokens so far.
     * @param int         $outputTokens Completion tokens so far.
     * @param int         $iteration    Round trips used.
     *
     * @return AgentRun
     */
    private function pause(
        array $calls,
        array $steps,
        array $messages,
        ToolContext $context,
        $text,
        $inputTokens,
        $outputTokens,
        $iteration
    ) {
        $pending = [];

        foreach ($calls as $call) {
            $pending[] = ['name' => $call->getName(), 'input' => $call->getInput()];
        }

        return new AgentRun(
            AgentRun::STATUS_PENDING,
            '' !== (string) $text
                ? (string) $text
                : __('This changes what visitors see, so it needs your approval first.', 'swishfolio-core'),
            $steps,
            $messages,
            $pending,
            ConfirmToken::issue($pending, $context->getUserId()),
            $inputTokens,
            $outputTokens,
            $iteration
        );
    }

    /**
     * The calls a token approves, replayed from the history the client returned.
     *
     * The calls are taken from the last assistant turn rather than from the
     * request body, so a client cannot submit a token for one plan alongside a
     * different set of calls. The token still has to match, which is what makes
     * the history safe to replay at all.
     *
     * @param Message[]   $messages     Conversation, newest last.
     * @param string      $confirmToken Token from the request.
     * @param ToolContext $context      Run state; marked confirmed on a match.
     *
     * @return ToolCall[]|null The approved calls, or null when nothing is approved.
     */
    private function approvedCalls(array $messages, $confirmToken, ToolContext $context)
    {
        if ('' === (string) $confirmToken) {
            return null;
        }

        for ($index = count($messages) - 1; $index >= 0; $index--) {
            $message = $messages[$index];

            if ($message->getRole() !== Message::ROLE_ASSISTANT || $message->getToolCalls() === []) {
                continue;
            }

            $calls   = $message->getToolCalls();
            $pending = [];

            foreach ($calls as $call) {
                $pending[] = ['name' => $call->getName(), 'input' => $call->getInput()];
            }

            if (! ConfirmToken::verify($confirmToken, $pending, $context->getUserId())) {
                return null;
            }

            $context->setConfirmed(true);

            return $calls;
        }

        return null;
    }

    /**
     * @return ToolDefinition[]
     */
    private function toolDefinitions()
    {
        $definitions = [];

        foreach ($this->runner->registry()->all() as $tool) {
            $definitions[] = ToolDefinition::fromTool($tool);
        }

        return $definitions;
    }

    /**
     * @return string
     */
    private function systemPrompt()
    {
        $prompt = $this->prompts->system('agent');

        return '' === $this->brandContext ? $prompt : $prompt . "\n\n" . $this->brandContext;
    }
}
