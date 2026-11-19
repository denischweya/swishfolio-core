<?php
/**
 * Shared wiring for the AI evaluation suite.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Generation/wp-generation-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIManager;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanValidator;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * An eval is not a unit test with a bigger fixture. A unit test asks whether one
 * class behaves; an eval asks whether the whole chain still turns a plausible
 * model answer into something safe to write — so these run the real assemblers,
 * the real registry, the real validators and the real composer, and substitute
 * only the model.
 *
 * Design tokens come from a committed copy of the theme's own theme.json rather
 * than a hand-made fixture, so "is this a real token slug" means what it means on
 * a real site. When the theme is checked out beside the plugin the live file is
 * used instead, and a separate integrity test fails if the two have drifted.
 */
abstract class EvalTestCase extends TestCase
{
    /** @var BlockRegistry|null */
    private $registryInstance;

    /** @var PatternLibrary|null */
    private $libraryInstance;

    /** @var TokenResolver|null */
    private $tokensInstance;

    /** @var BlockCatalogue|null */
    private $catalogueInstance;

    /** @var ManifestIndex|null */
    private $manifestsInstance;

    protected function setUp(): void
    {
        parent::setUp();

        // Discovery caches in transients keyed by plugin version, so a stale one
        // from a neighbouring test would silently serve the wrong catalogue.
        $GLOBALS['sfcore_test_transients'] = [];
        $GLOBALS['sfcore_test_options']    = [];

        sfcore_test_wp_reset();

        $this->registryInstance  = null;
        $this->libraryInstance   = null;
        $this->tokensInstance    = null;
        $this->catalogueInstance = null;
        $this->manifestsInstance = null;
    }

    /**
     * @return BlockRegistry The shipped manifests, plugin and core alike.
     */
    protected function registry()
    {
        if ($this->registryInstance === null) {
            $this->registryInstance = new BlockRegistry([
                SFCORE_PLUGIN_DIR . 'blocks',
                SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
            ]);
        }

        return $this->registryInstance;
    }

    /**
     * @return PatternLibrary The shipped kits.
     */
    protected function library()
    {
        if ($this->libraryInstance === null) {
            $this->libraryInstance = PatternLibrary::shipped();
        }

        return $this->libraryInstance;
    }

    /**
     * The kit a plan case composes with: the brief's, else the plan's own.
     *
     * @param array|null $case Corpus case.
     *
     * @return \SwishfolioCore\AI\Generation\Kit
     */
    protected function kitFor($case)
    {
        $slug = '';

        if (is_array($case) && isset($case['brief']['kit'])) {
            $slug = (string) $case['brief']['kit'];
        } elseif (is_array($case) && isset($case['response']['kit'])) {
            $slug = (string) $case['response']['kit'];
        }

        $kit = $this->library()->kit($slug);

        return $kit !== null ? $kit : $this->library()->kit(PatternLibrary::DEFAULT_KIT);
    }

    /**
     * @return TokenResolver
     */
    protected function tokens()
    {
        if ($this->tokensInstance === null) {
            $this->tokensInstance = TokenResolver::fromArray(self::themeJson());
        }

        return $this->tokensInstance;
    }

    /**
     * @return BlockCatalogue
     */
    protected function catalogue()
    {
        if ($this->catalogueInstance === null) {
            $this->catalogueInstance = BlockCatalogue::discover();
        }

        return $this->catalogueInstance;
    }

    /**
     * @return ManifestIndex
     */
    protected function manifests()
    {
        if ($this->manifestsInstance === null) {
            $this->manifestsInstance = ManifestIndex::discover();
        }

        return $this->manifestsInstance;
    }

    /**
     * @return array Decoded theme.json: the live theme when present, else the committed copy.
     */
    protected static function themeJson()
    {
        $live = self::liveThemeJsonPath();
        $file = $live === null ? Corpus::dir() . '/theme.json' : $live;

        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException(sprintf('theme.json at "%s" is not valid JSON.', $file));
        }

        return $decoded;
    }

    /**
     * @return string|null
     */
    protected static function liveThemeJsonPath()
    {
        $file = dirname(rtrim(SFCORE_PLUGIN_DIR, '/'), 2) . '/themes/swishfolio/theme.json';

        return is_file($file) ? $file : null;
    }

    /**
     * @return ActionAudit
     */
    protected function actionAudit()
    {
        return new ActionAudit($this->registry(), $this->tokens(), $this->catalogue(), $this->manifests());
    }

    /**
     * @param array|null $case Corpus case, for the site facts a plan is judged against.
     *
     * @return PlanAudit
     */
    protected function planAudit($case = null)
    {
        $audit = new PlanAudit(
            $this->kitFor($case),
            $this->registry(),
            $this->tokens(),
            $this->catalogue(),
            $this->manifests()
        );

        $brief = $case === null || ! isset($case['brief']) ? [] : (array) $case['brief'];

        return $audit->withSiteFacts(
            isset($brief['customTemplates']) ? (array) $brief['customTemplates'] : []
        );
    }

    /**
     * @return PromptManager
     */
    protected function prompts()
    {
        return new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt');
    }

    /**
     * The whole /ai/edit path with the model replaced: assemble the prompt, ask
     * the provider, validate the answer.
     *
     * @param array                                           $case     Corpus case.
     * @param \SwishfolioCore\AI\Contracts\AIProviderInterface $provider Provider to ask.
     *
     * @throws \SwishfolioCore\Actions\ValidationException When the answer is refused.
     *
     * @return array Validated response.
     */
    protected function runEdit(array $case, $provider)
    {
        $manager = new AIManager(
            $provider,
            new ContextAssembler($this->registry(), $this->tokens(), $this->prompts()),
            ActionValidator::withDefaultStages(),
            new UsageLogger()
        );

        return $manager->edit(
            (array) $case['selection'],
            (string) $case['instruction'],
            $this->validationContextFor($case),
            'eval-model'
        );
    }

    /**
     * @param array $case Corpus case.
     *
     * @return ValidationContext
     */
    protected function validationContextFor(array $case)
    {
        $tree = [];

        foreach ((array) $case['blockTree'] as $clientId => $block) {
            $tree[(string) $clientId] = [
                'name'           => (string) $block['name'],
                'attributes'     => isset($block['attributes']) ? (array) $block['attributes'] : [],
                'parentClientId' => isset($block['parentClientId']) ? $block['parentClientId'] : null,
            ];
        }

        return new ValidationContext(
            $tree,
            (string) $case['selection']['clientId'],
            $this->registry(),
            $this->tokens()
        );
    }

    /**
     * The whole /ai/plan path with the model replaced.
     *
     * @param array                                           $case     Corpus case.
     * @param \SwishfolioCore\AI\Contracts\AIProviderInterface $provider Provider to ask.
     *
     * @throws \SwishfolioCore\Actions\ValidationException When the plan is refused.
     *
     * @return array ['raw' => array, 'validated' => array]
     */
    protected function runPlan(array $case, $provider)
    {
        $assembler = new PlanAssembler($this->kitFor($case), $this->registry(), $this->tokens(), $this->prompts());

        $brief = $this->briefFor($case);
        $scope = $this->scopeFor($case);

        $request = $scope === 'page'
            ? $assembler->forPagePlan($brief, 'eval-model')
            : $assembler->forSitePlan($brief, 'eval-model');

        $response = $provider->generate($request);
        $raw      = $response->getDecoded();

        return [
            'request'   => $request,
            'raw'       => $raw,
            'validated' => PlanValidator::withDefaultStages()->validate($raw, $this->planContextFor($case)),
        ];
    }

    /**
     * @param array $case Corpus case.
     *
     * @return array
     */
    protected function briefFor(array $case)
    {
        $brief = isset($case['brief']) ? (array) $case['brief'] : [];

        return [
            'instruction'     => (string) $case['instruction'],
            'siteTitle'       => isset($brief['siteTitle']) ? (string) $brief['siteTitle'] : 'Eval Site',
            'customTemplates' => isset($brief['customTemplates']) ? (array) $brief['customTemplates'] : [],
            'existingSlugs'   => array_keys(isset($brief['existingSlugs']) ? (array) $brief['existingSlugs'] : []),
            'page'            => isset($brief['page']) ? (array) $brief['page'] : [],
        ];
    }

    /**
     * @param array $case Corpus case.
     *
     * @return PlanContext
     */
    protected function planContextFor(array $case)
    {
        $brief   = isset($case['brief']) ? (array) $case['brief'] : [];
        $context = new PlanContext($this->kitFor($case), $this->registry(), $this->tokens(), $this->scopeFor($case));

        return $context
            ->withCustomTemplates(isset($brief['customTemplates']) ? (array) $brief['customTemplates'] : [])
            ->withExistingSlugs(isset($brief['existingSlugs']) ? (array) $brief['existingSlugs'] : []);
    }

    /**
     * @param array $case Corpus case.
     *
     * @return string
     */
    protected function scopeFor(array $case)
    {
        return isset($case['scope']) && $case['scope'] === 'page' ? 'page' : 'site';
    }

    /**
     * @param array $validated Validated plan.
     *
     * @return array post_content keyed by page index.
     */
    protected function compose(array $validated)
    {
        $composer = new BlockComposer($this->library()->kit((string) $validated['kit']));
        $composed = [];

        foreach ((array) $validated['pages'] as $index => $page) {
            $composed[$index] = $composer->compose($page['sections']);
        }

        return $composed;
    }

    /**
     * @param string[] $violations Violations from an auditor.
     * @param array    $case       Corpus case, for the failure message.
     *
     * @return void
     */
    protected function assertNoViolations(array $violations, array $case)
    {
        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%s (%s) produced %d violation(s):\n  - %s",
                isset($case['label']) ? $case['label'] : 'eval case',
                isset($case['fixture']) ? basename($case['fixture']) : 'unknown fixture',
                count($violations),
                implode("\n  - ", $violations)
            )
        );
    }
}
