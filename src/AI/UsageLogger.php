<?php
/**
 * Records AI usage for product analytics and future credit pricing.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

/**
 * Stores lifetime counters plus a rolling window of recent requests in a single
 * option — no custom table. Token counts are captured from the first request so
 * credit costs can later be set from measurement rather than guesses.
 *
 * Prompt text, block content and response bodies are never recorded.
 */
class UsageLogger
{
    const OPTION        = 'swishfolio_ai_usage';
    const RECENT_LIMIT  = 100;

    /**
     * @param AIResponse $response    Provider response.
     * @param int        $actionCount Actions proposed.
     * @param int        $durationMs  Round-trip duration.
     * @param string     $outcome     Outcome slug, e.g. "applied".
     *
     * @return void
     */
    public function record(AIResponse $response, $actionCount, $durationMs, $outcome)
    {
        $usage = $this->getUsage();

        $usage['counters']['requests']++;
        $usage['counters']['inputTokens']     += $response->getInputTokens();
        $usage['counters']['outputTokens']    += $response->getOutputTokens();
        $usage['counters']['actionsProposed'] += (int) $actionCount;

        $this->push($usage, [
            'provider'     => $response->getProviderSlug(),
            'model'        => $response->getModel(),
            'inputTokens'  => $response->getInputTokens(),
            'outputTokens' => $response->getOutputTokens(),
            'actions'      => (int) $actionCount,
            'durationMs'   => (int) $durationMs,
            'outcome'      => (string) $outcome,
            'at'           => time(),
        ]);
    }

    /**
     * @param string $stage      Stage that rejected the response.
     * @param string $provider   Provider slug.
     * @param string $model      Model identifier.
     * @param int    $durationMs Round-trip duration.
     *
     * @return void
     */
    public function recordFailure($stage, $provider, $model, $durationMs)
    {
        $usage = $this->getUsage();

        $usage['counters']['requests']++;

        if (! isset($usage['counters']['failures'][$stage])) {
            $usage['counters']['failures'][$stage] = 0;
        }

        $usage['counters']['failures'][$stage]++;

        $this->push($usage, [
            'provider'   => (string) $provider,
            'model'      => (string) $model,
            'durationMs' => (int) $durationMs,
            'outcome'    => 'rejected:' . $stage,
            'at'         => time(),
        ]);
    }

    /**
     * @param int $accepted Actions the user actually applied.
     *
     * @return void
     */
    public function recordAccepted($accepted)
    {
        $usage = $this->getUsage();
        $usage['counters']['actionsAccepted'] += (int) $accepted;

        update_option(self::OPTION, $usage, false);
    }

    /**
     * @return array
     */
    public function getUsage()
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        $counters = isset($stored['counters']) ? $stored['counters'] : [];

        return [
            'counters' => array_merge([
                'requests'        => 0,
                'inputTokens'     => 0,
                'outputTokens'    => 0,
                'actionsProposed' => 0,
                'actionsAccepted' => 0,
                'failures'        => [],
            ], $counters),
            'recent' => isset($stored['recent']) ? (array) $stored['recent'] : [],
        ];
    }

    private function push(array $usage, array $entry)
    {
        $usage['recent'][] = $entry;

        if (count($usage['recent']) > self::RECENT_LIMIT) {
            $usage['recent'] = array_slice($usage['recent'], -self::RECENT_LIMIT);
        }

        update_option(self::OPTION, $usage, false);
    }
}
