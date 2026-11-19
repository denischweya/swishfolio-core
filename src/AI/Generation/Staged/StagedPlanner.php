<?php
/**
 * Plans a whole site in stages: one outline call, then every page at once.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Staged;

use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Generation\PlanCoverage;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Jobs\JobProgress;

/**
 * Why: one call that rewrites every slot of every page runs to 26-32k output
 * tokens on a large kit, takes four to five minutes and can hit its cap, which
 * fails the whole site. Here no single call is large:
 *
 * 1. Outline (strong model, small budget): pages, their sections in order,
 *    front page, menu and the shared facts. See SiteOutline.
 * 2. Pages (one call each, all at once, each with its own cap and timeout):
 *    every call gets the same system prompt (kit catalogue, brand), cached
 *    by the provider, plus the outline and its own page. Each reply is
 *    validated on its own; a page that fails is retried alone, once.
 * 3. Check: one copy-fill call for slots still holding demo copy, then the
 *    full site validation. A page the check still refuses is dropped and
 *    reported, never the whole site (unless it is the front page).
 *
 * Metering: every HTTP reply fires swishfolio_ai_response as usual; a reply
 * that is then rejected (or a page later dropped) fires
 * swishfolio_ai_usage_void with the same body, so the owner pays only for
 * the pages they get. A retry is charged once, for the attempt that is kept.
 */
class StagedPlanner
{
    const OUTLINE_MAX_TOKENS = 12000;
    const PAGE_MAX_TOKENS    = 12000;
    const OUTLINE_TIMEOUT    = 240;
    const PAGE_TIMEOUT       = 180;
    /** The cache warm-up only has to read the prompt, not answer it. */
    const WARM_MAX_TOKENS    = 1;

    /** A page reply that leaves more than this share of its slots unwritten is retried. */
    const MAX_GAP_SHARE = 0.5;

    /** @var AIProviderInterface */
    private $provider;

    /** @var PlanAssembler */
    private $assembler;

    /** @var PromptManager */
    private $prompts;

    /** @var Kit */
    private $kit;

    /** @var callable fn(string $scope): PlanContext */
    private $contextFor;

    /** @var UsageLogger|null */
    private $logger;

    /** @var ParallelRunner */
    private $runner;

    /** @var array[] key => decoded body of the kept page reply (for voiding a dropped page). */
    private $kept = [];

    /** @var array Usage per call, for the job result: [{call, model, in, out, cacheRead, cacheWrite, seconds, kept}]. */
    private $calls = [];

    /**
     * @param AIProviderInterface $provider   Provider.
     * @param PlanAssembler       $assembler  Shared system prompt and copy-fill request.
     * @param PromptManager       $prompts    Task prompts.
     * @param Kit                 $kit        The run's kit.
     * @param callable            $contextFor fn("site"|"page"): PlanContext.
     * @param UsageLogger|null    $logger     Usage log.
     * @param ParallelRunner|null $runner     Concurrent transport (tests inject one).
     */
    public function __construct(
        AIProviderInterface $provider,
        PlanAssembler $assembler,
        PromptManager $prompts,
        Kit $kit,
        callable $contextFor,
        UsageLogger $logger = null,
        ParallelRunner $runner = null
    ) {
        $this->provider   = $provider;
        $this->assembler  = $assembler;
        $this->prompts    = $prompts;
        $this->kit        = $kit;
        $this->contextFor = $contextFor;
        $this->logger     = $logger;
        $this->runner     = $runner !== null ? $runner : new ParallelRunner($provider);
    }

    /**
     * @param array       $brief        Brief.
     * @param string      $outlineModel Model for the outline.
     * @param string      $pageModel    Model for the pages and the copy fill.
     * @param JobProgress $progress     Steps.
     *
     * @throws ValidationException When no usable site plan comes out.
     * @throws ProviderException   When the outline call fails.
     *
     * @return array {plan, outline, failed: [{key, title, reason}], calls}
     */
    public function plan(array $brief, $outlineModel, $pageModel, JobProgress $progress)
    {
        $outline = $this->outline($brief, $outlineModel, $progress);
        $result  = $this->writePages($outline, array_column($outline['pages'], 'key'), $brief, $pageModel, $progress);
        $pages   = $result['pages'];
        $failed  = $result['failed'];

        $progress->step('check', __('Checking the plan', 'swishfolio-core'));

        $plan = $this->check($outline, $pages, $failed, $brief, $pageModel, $progress);

        return [
            'plan'    => $plan,
            'outline' => $outline,
            'failed'  => $this->describeFailed($outline, $failed),
            'calls'   => $this->calls,
        ];
    }

    /**
     * Stage 1.
     *
     * @param array       $brief    Brief.
     * @param string      $model    Model.
     * @param JobProgress $progress Steps.
     *
     * @throws ValidationException When the outline is unusable twice.
     * @throws ProviderException   On provider failure.
     *
     * @return array Clean outline.
     */
    public function outline(array $brief, $model, JobProgress $progress)
    {
        $progress->step('ask', __('Outlining your site', 'swishfolio-core'), __('Deciding the pages and what each one says', 'swishfolio-core'));

        $request = new AIRequest(
            $this->assembler->siteSystemPrompt($brief),
            $this->prompts->task('plan-outline', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'site'        => $this->assembler->siteFactsJson($brief),
                'kit'         => $this->kit->getSlug(),
            ]),
            SiteOutline::schema($this->kit->getSlug()),
            $model,
            self::OUTLINE_MAX_TOKENS
        );

        $writing  = false;
        $listener = function (array $event) use ($progress, &$writing) {
            if (isset($event['type']) && $event['type'] === 'text' && ! $writing) {
                $writing = true;
                $progress->detail('ask', __('Writing the outline', 'swishfolio-core'));
            }
        };

        $timeout = function () {
            return (int) apply_filters('swishfolio_ai_staged_outline_timeout', self::OUTLINE_TIMEOUT);
        };
        add_filter('swishfolio_ai_http_timeout', $timeout, 20);

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $started  = microtime(true);
                $response = $this->generate($request->withProgress($listener), $decoded);

                try {
                    $outline = SiteOutline::normalise(
                        $response->getDecoded(),
                        $this->kit,
                        call_user_func($this->contextFor, 'site')
                    );
                    $this->noteRaw('outline', $decoded, $model, round(microtime(true) - $started, 1), true);
                    break;
                } catch (ValidationException $e) {
                    $this->logFailure($e->getStage(), $response, $started);
                    // Retried: only the outline that is used is charged.
                    $this->noteRaw('outline', $decoded, $model, round(microtime(true) - $started, 1), false);
                    $this->void($decoded, $e->getMessage());

                    if ($attempt === 1) {
                        throw $e;
                    }
                }
            }
        } finally {
            remove_filter('swishfolio_ai_http_timeout', $timeout, 20);
        }

        $progress->done(
            'ask',
            /* translators: 1: number of pages, 2: number of sections. */
            sprintf(__('%1$d pages, %2$d sections', 'swishfolio-core'), count($outline['pages']), SiteOutline::sectionCount($outline))
        );

        return $outline;
    }

    /**
     * Stage 2: the given pages, all at once, each retried once alone.
     *
     * @param array       $outline  Clean outline.
     * @param string[]    $keys     Page keys to write.
     * @param array       $brief    Brief.
     * @param string      $model    Model.
     * @param JobProgress $progress Steps.
     *
     * @return array {pages: key => validated page, failed: key => reason}
     */
    public function writePages(array $outline, array $keys, array $brief, $model, JobProgress $progress)
    {
        $byKey = [];

        foreach ($outline['pages'] as $page) {
            $byKey[$page['key']] = $page;
        }

        $pages   = [];
        $failed  = [];
        $todo    = array_values(array_intersect($keys, array_keys($byKey)));
        $timeout = (int) apply_filters('swishfolio_ai_staged_page_timeout', self::PAGE_TIMEOUT);

        foreach ($todo as $key) {
            $progress->begin(
                'page:' . $key,
                /* translators: %s: page title. */
                sprintf(__('Writing %s', 'swishfolio-core'), $byKey[$key]['title']),
                /* translators: %d: number of sections. */
                sprintf(_n('%d section', '%d sections', count($byKey[$key]['sections']), 'swishfolio-core'), count($byKey[$key]['sections']))
            );
        }

        if (count($todo) > 1 && $this->runner->isParallel()) {
            $this->warmCache($outline, $byKey[$todo[0]], $brief, $model);
        }

        for ($attempt = 0; $attempt < 2 && $todo !== []; $attempt++) {
            if ($attempt > 0) {
                foreach ($todo as $key) {
                    $progress->begin(
                        'page:' . $key,
                        /* translators: %s: page title. */
                        sprintf(__('Writing %s', 'swishfolio-core'), $byKey[$key]['title']),
                        __('First try did not work, trying again', 'swishfolio-core')
                    );
                }
            }

            $requests = [];

            foreach ($todo as $key) {
                $requests[$key] = $this->pageRequest($outline, $byKey[$key], $brief, $model);
            }

            $retry = [];

            $this->runner->run($requests, function ($key, $response, $error, $decoded, $seconds) use ($byKey, $attempt, &$pages, &$failed, &$retry, $progress, $model) {
                $reason = '';

                if ($error !== null) {
                    $reason = $error->getMessage();
                } else {
                    try {
                        $pages[$key]      = $this->acceptPage($byKey[$key], $response);
                        $this->kept[$key] = $decoded;
                        unset($failed[$key]);
                        $this->noteRaw('page:' . $key, $decoded, $model, $seconds, true);

                        if ($this->logger !== null) {
                            $this->logger->record($response, 1, (int) round($seconds * 1000), 'planned');
                        }

                        $progress->finish(
                            'page:' . $key,
                            'done',
                            /* translators: %d: number of sections. */
                            sprintf(_n('%d section', '%d sections', count($pages[$key]['sections']), 'swishfolio-core'), count($pages[$key]['sections']))
                        );

                        return;
                    } catch (ValidationException $e) {
                        $reason = $e->getMessage();

                        if ($this->logger !== null) {
                            $this->logger->recordFailure($e->getStage(), $response->getProviderSlug(), $response->getModel(), (int) round($seconds * 1000));
                        }
                    }
                }

                // Not kept: the owner does not pay for it.
                $this->noteRaw('page:' . $key, $decoded, $model, $seconds, false);
                $this->void($decoded, $reason);
                $failed[$key] = $reason;
                $retry[]      = $key;

                if ($attempt > 0) {
                    $progress->finish(
                        'page:' . $key,
                        'failed',
                        __('This page could not be written. The rest of your site is built, and you can retry this page afterwards.', 'swishfolio-core'),
                        /* translators: %s: page title. */
                        sprintf(__('Could not write %s', 'swishfolio-core'), $byKey[$key]['title'])
                    );
                }
            }, $timeout, 0.0);

            $todo = $retry;
        }

        return ['pages' => $pages, 'failed' => $failed];
    }

    /**
     * Stage 3: fill demo-copy gaps, validate the site, drop pages it refuses.
     *
     * @param array       $outline  Outline.
     * @param array       $pages    key => validated page.
     * @param array       $failed   key => reason (added to).
     * @param array       $brief    Brief.
     * @param string      $model    Model for the copy fill.
     * @param JobProgress $progress Steps.
     *
     * @throws ValidationException When the front page is missing or the site still fails.
     *
     * @return array Validated site plan.
     */
    private function check(array $outline, array $pages, array &$failed, array $brief, $model, JobProgress $progress)
    {
        $front = $outline['site']['frontPage'];

        if (! isset($pages[$front])) {
            throw new ValidationException(
                SiteOutline::STAGE,
                sprintf('The front page could not be written: %s', isset($failed[$front]) ? $failed[$front] : 'no reply.'),
                'pages'
            );
        }

        $context = call_user_func($this->contextFor, 'site');
        $plan    = PlanValidator::withDefaultStages(false)->validate(StagedAssembly::sitePlan($outline, $pages, $this->kit->getSlug()), $context);
        $plan    = $this->fillGaps($plan, $brief, $model);

        for ($guard = 0; $guard <= count($pages); $guard++) {
            try {
                return PlanValidator::withDefaultStages()->validate($plan, $context);
            } catch (ValidationException $e) {
                $index = StagedAssembly::pageIndexOf($e->getPath());

                if ($index === null || ! isset($plan['pages'][$index]) || $plan['pages'][$index]['key'] === $front) {
                    throw $e;
                }

                $key          = $plan['pages'][$index]['key'];
                $failed[$key] = $e->getMessage();
                unset($pages[$key]);

                $this->void(isset($this->kept[$key]) ? $this->kept[$key] : null, $e->getMessage());
                unset($this->kept[$key]);
                $this->markDropped('page:' . $key);

                $progress->finish(
                    'page:' . $key,
                    'failed',
                    __('This page could not be written. The rest of your site is built, and you can retry this page afterwards.', 'swishfolio-core'),
                    /* translators: %s: page title. */
                    sprintf(__('Could not write %s', 'swishfolio-core'), $plan['pages'][$index]['title'])
                );

                array_splice($plan['pages'], $index, 1);
                $plan['site']['navigation'] = array_values(array_diff($plan['site']['navigation'], [$key]));
            }
        }

        throw new ValidationException(SiteOutline::STAGE, 'The plan could not be checked.', 'pages');
    }

    /**
     * Plan one page of an earlier outline again (the "retry this page" path).
     *
     * @param array       $outline  Saved outline.
     * @param string      $key      Page key.
     * @param array       $brief    Saved brief.
     * @param string      $model    Model.
     * @param JobProgress $progress Steps.
     *
     * @throws ValidationException When the page still fails.
     *
     * @return array Validated single-page plan.
     */
    public function retryPage(array $outline, $key, array $brief, $model, JobProgress $progress)
    {
        $result = $this->writePages($outline, [(string) $key], $brief, $model, $progress);

        if (! isset($result['pages'][$key])) {
            throw new ValidationException(SiteOutline::STAGE, isset($result['failed'][$key]) ? (string) $result['failed'][$key] : 'The page could not be written.', 'pages');
        }

        $progress->step('check', __('Checking the page', 'swishfolio-core'));

        $context = call_user_func($this->contextFor, 'page');
        $plan    = PlanValidator::withDefaultStages(false)->validate(StagedAssembly::pagePlan($result['pages'][$key], $this->kit->getSlug()), $context);
        $plan    = $this->fillGaps($plan, $brief, $model);

        return PlanValidator::withDefaultStages()->validate($plan, $context);
    }

    /**
     * @return array Usage per call so far.
     */
    public function calls()
    {
        return $this->calls;
    }

    /**
     * @param array  $outline Outline.
     * @param array  $page    Outline page.
     * @param array  $brief   Brief.
     * @param string $model   Model.
     *
     * @return AIRequest
     */
    private function pageRequest(array $outline, array $page, array $brief, $model, $maxTokens = null)
    {
        $json = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return (new AIRequest(
            $this->assembler->siteSystemPrompt($brief),
            $this->prompts->task('plan-page', [
                'instruction' => (string) (isset($brief['instruction']) ? $brief['instruction'] : ''),
                'site'        => $this->assembler->siteFactsJson($brief),
                'outline'     => json_encode(StagedAssembly::siteSpec($outline), $json),
                'page'        => json_encode(StagedAssembly::pageSpec($page), $json),
                'kit'         => $this->kit->getSlug(),
            ]),
            PlanSchema::build('page', 1, $this->kit->getSlug()),
            $model,
            $maxTokens !== null ? (int) $maxTokens : (int) apply_filters('swishfolio_ai_staged_page_max_tokens', self::PAGE_MAX_TOKENS)
        ))->withCachedSystem();
    }

    /**
     * Write the page calls' shared prefix (system prompt and page schema) to
     * the provider's cache with one tiny call before the pages start, so every
     * page reads it at a tenth of the price instead of each paying the write.
     * Requests that start together, or even a few seconds apart, do not see
     * each other's cache reliably; a finished request's cache they do. Its
     * cost is the one cache write a page would otherwise have paid, and it is
     * metered like any call. Whatever it answers is thrown away.
     *
     * @param array  $outline Clean outline.
     * @param array  $page    Any outline page.
     * @param array  $brief   Brief.
     * @param string $model   Page model.
     *
     * @return void
     */
    private function warmCache(array $outline, array $page, array $brief, $model)
    {
        $this->runner->run(
            ['cache-warm-up' => $this->pageRequest($outline, $page, $brief, $model, self::WARM_MAX_TOKENS)],
            function ($key, $response, $error, $decoded, $seconds) use ($model) {
                $this->noteRaw($key, $decoded, $model, $seconds, true);
            },
            60,
            0.0
        );
    }

    /**
     * Validate one page reply on its own (everything but demo-copy coverage,
     * which the copy-fill call repairs across the site afterwards).
     *
     * @param array      $outlinePage Outline page.
     * @param AIResponse $response    Page reply.
     *
     * @throws ValidationException When it cannot be used.
     *
     * @return array Validated page.
     */
    private function acceptPage(array $outlinePage, AIResponse $response)
    {
        $page = StagedAssembly::mergePage($outlinePage, $response->getDecoded());
        $plan = PlanValidator::withDefaultStages(false)->validate(
            StagedAssembly::pagePlan($page, $this->kit->getSlug()),
            call_user_func($this->contextFor, 'page')
        );

        // A reply that skipped most of its copy (cut short, or lazy) would
        // lean on the copy fill for the whole page: try it again instead.
        $required = 0;

        foreach ($plan['pages'][0]['sections'] as $section) {
            if (isset($section['pattern'])) {
                $required += count($this->kit->requiredSlots($section['pattern']));
            }
        }

        $gaps = count(PlanCoverage::missing($plan, $this->kit));

        if ($required >= 4 && $gaps / $required > self::MAX_GAP_SHARE) {
            throw new ValidationException(
                'plan-coverage',
                sprintf('%d of %d slots were left with the template\'s demo copy.', $gaps, $required),
                'pages.0'
            );
        }

        return $plan['pages'][0];
    }

    /**
     * One copy-fill call for slots still holding demo copy, then pruning.
     *
     * @param array  $plan  Plan validated without coverage.
     * @param array  $brief Brief.
     * @param string $model Model.
     *
     * @return array
     */
    private function fillGaps(array $plan, array $brief, $model)
    {
        $gaps = PlanCoverage::missing($plan, $this->kit);

        if ($gaps === []) {
            return $plan;
        }

        try {
            $started  = microtime(true);
            $response = $this->generate($this->assembler->forCopyFill($brief, $plan, $gaps, $model), $raw);
            $decoded  = $response->getDecoded();
            $plan     = PlanCoverage::fill($plan, $gaps, isset($decoded['fills']) ? (array) $decoded['fills'] : []);
            $this->noteRaw('copy-fill', $raw, $model, round(microtime(true) - $started, 1), true);

            if ($this->logger !== null) {
                $this->logger->record($response, 0, (int) round((microtime(true) - $started) * 1000), 'planned');
            }
        } catch (ProviderException $e) {
            // Fall through to pruning; the coverage stage has the last word.
        }

        $gaps = PlanCoverage::missing($plan, $this->kit);

        return $gaps === [] ? $plan : PlanCoverage::prune($plan, $gaps, $this->kit);
    }

    /**
     * @param array|null $decoded Reply body the meter already counted.
     * @param string     $reason  Why it is not kept.
     *
     * @return void
     */
    private function void($decoded, $reason)
    {
        if (! is_array($decoded) || ! function_exists('do_action')) {
            return;
        }

        /**
         * Fires when a provider reply that swishfolio_ai_response already
         * reported is thrown away (a staged plan page that failed validation,
         * or a page dropped by the final check), so a metering layer can take
         * its usage back off the request's bill.
         *
         * @param array  $decoded Decoded reply body, as reported before.
         * @param string $slug    Provider slug.
         * @param string $reason  Why it was not kept.
         */
        do_action('swishfolio_ai_usage_void', $decoded, $this->provider->getSlug(), (string) $reason);
    }

    /**
     * @param array      $outline Outline.
     * @param array      $failed  key => reason.
     *
     * @return array[]
     */
    private function describeFailed(array $outline, array $failed)
    {
        $out = [];

        foreach ($outline['pages'] as $page) {
            if (isset($failed[$page['key']])) {
                $out[] = ['key' => $page['key'], 'title' => $page['title'], 'reason' => (string) $failed[$page['key']]];
            }
        }

        return $out;
    }

    /**
     * generate(), keeping the raw reply body the meter saw (usage with cache
     * counts, and what to void if the reply is thrown away).
     *
     * @param AIRequest  $request Request.
     * @param array|null $decoded Receives the raw body.
     *
     * @throws ProviderException On provider failure.
     *
     * @return AIResponse
     */
    private function generate(AIRequest $request, &$decoded)
    {
        $decoded = null;
        $listen  = function ($body) use (&$decoded) {
            $decoded = (array) $body;
        };

        add_action('swishfolio_ai_response', $listen, 1, 1);

        try {
            return $this->provider->generate($request);
        } finally {
            remove_action('swishfolio_ai_response', $listen, 1);
        }
    }

    /**
     * @param string     $call    Call name.
     * @param array|null $decoded Raw reply body (usage incl. cache counts).
     * @param string     $model   Model asked for.
     * @param float      $seconds Seconds.
     * @param bool       $kept    Charged.
     *
     * @return void
     */
    private function noteRaw($call, $decoded, $model, $seconds, $kept)
    {
        $usage         = is_array($decoded) && isset($decoded['usage']) ? (array) $decoded['usage'] : [];
        $this->calls[] = [
            'call'       => $call,
            'model'      => is_array($decoded) && isset($decoded['model']) ? (string) $decoded['model'] : (string) $model,
            'in'         => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0,
            'out'        => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0,
            'cacheRead'  => isset($usage['cache_read_input_tokens']) ? (int) $usage['cache_read_input_tokens'] : 0,
            'cacheWrite' => isset($usage['cache_creation_input_tokens']) ? (int) $usage['cache_creation_input_tokens'] : 0,
            'seconds'    => (float) $seconds,
            'kept'       => (bool) $kept,
        ];
    }

    /**
     * @param string $call Call name whose kept attempt was dropped later.
     *
     * @return void
     */
    private function markDropped($call)
    {
        foreach ($this->calls as $i => $row) {
            if ($row['call'] === $call && $row['kept']) {
                $this->calls[$i]['kept'] = false;
            }
        }
    }

    /**
     * @param string     $stage    Stage.
     * @param AIResponse $response Reply.
     * @param float      $started  microtime.
     *
     * @return void
     */
    private function logFailure($stage, AIResponse $response, $started)
    {
        if ($this->logger !== null) {
            $this->logger->recordFailure($stage, $response->getProviderSlug(), $response->getModel(), (int) round((microtime(true) - $started) * 1000));
        }
    }
}
