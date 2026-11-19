<?php
/**
 * Picks the model for one AI request from what the request is for.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI;

/**
 * The single place that turns "what kind of request is this" into a model id.
 * No controller or job runner names a model; they all ask modelFor($kind).
 *
 * The rule
 * --------
 * A site's FIRST design-heavy request (planning a whole site or page, the
 * onboarding build, a builder request that writes or redesigns a full page)
 * runs on the strong model: Settings::getModel(), the network model on the
 * shared key. Once one design-heavy request has SUCCEEDED, the site is marked
 * (option FIRST_DESIGN_OPTION) and from then on every request, design-heavy or
 * not, runs on the follow-up model. Everything that is not design-heavy (small
 * edits, agent chat, CV extraction, builder edits of a selection) always runs
 * on the follow-up model.
 *
 * The follow-up model comes from Settings::getFollowupModel(): on the network
 * key it is the network admin's "Follow-up model" setting (swishfolio-extra);
 * on a site's own key it is empty. An empty follow-up model, or one equal to
 * the strong model, means "no downgrade": every request uses the strong model.
 *
 * The mark is written only after success (markSucceeded()), so a first build
 * that fails, or whose plan the validator rejects, still gets the strong model
 * on the retry.
 *
 * Background jobs: a queued job runs the same controller method as the
 * synchronous request, and that method derives its kind from the job payload
 * (scope etc.), so a job always follows the same rule as the request that
 * queued it. Keep it that way: derive the kind from the payload, never from
 * request-only state.
 *
 * Adding a design-heavy endpoint (e.g. onboarding template customisation)
 * ----------------------------------------------------------------------
 * 1. Pick a kind: one of the KIND_* constants below (KIND_TEMPLATE_CUSTOMISE
 *    exists for exactly this), or a new constant added to DESIGN_KINDS, or any
 *    string returned from the swishfolio_ai_design_heavy_kinds filter.
 * 2. In the handler (or the job handler it queues):
 *        $router = new ModelRouter($this->settings);
 *        $model  = $router->modelFor(ModelRouter::KIND_TEMPLATE_CUSTOMISE);
 *        ... provider call with $model ...
 *        $router->markSucceeded(ModelRouter::KIND_TEMPLATE_CUSTOMISE); // only on success
 * Non-design endpoints just call modelFor() with a non-design kind (KIND_EDIT,
 * KIND_AGENT, KIND_EXTRACT, or a new string) and never mark.
 */
class ModelRouter
{
    /** Site option: unix time of the first successful design-heavy request. */
    const FIRST_DESIGN_OPTION = 'swishfolio_ai_first_design_done';

    // Design-heavy kinds.
    const KIND_SITE_DESIGN         = 'site-design';
    const KIND_PAGE_DESIGN         = 'page-design';
    const KIND_TEMPLATE_CUSTOMISE  = 'template-customise';
    const KIND_BUILDER_PAGE        = 'builder-page';
    // Onboarding: a complete custom design system for the site (CustomDesigner).
    const KIND_CUSTOM_DESIGN       = 'custom-design';

    // Follow-up kinds.
    const KIND_BUILDER_SELECTION = 'builder-selection';
    const KIND_EDIT              = 'edit';
    const KIND_AGENT             = 'agent';
    const KIND_EXTRACT           = 'extract';
    // Onboarding: pick the closest site template for a brief (a short classification).
    const KIND_TEMPLATE_MATCH    = 'template-match';
    // Staged site plan: one page's copy, written from an outline the strong
    // model already decided (KIND_SITE_DESIGN), so the follow-up model.
    const KIND_SITE_PAGE         = 'site-page';

    const DESIGN_KINDS = [
        self::KIND_SITE_DESIGN,
        self::KIND_PAGE_DESIGN,
        self::KIND_TEMPLATE_CUSTOMISE,
        self::KIND_BUILDER_PAGE,
        self::KIND_CUSTOM_DESIGN,
    ];

    /** @var Settings */
    private $settings;

    /**
     * @param Settings $settings AI settings (strong and follow-up model).
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param string $kind Request kind (KIND_* or a filtered-in string).
     *
     * @return string Model id for this request ('' = provider default).
     */
    public function modelFor($kind)
    {
        $kind     = (string) $kind;
        $strong   = (string) $this->settings->getModel();
        $followup = (string) $this->settings->getFollowupModel();

        $model = $strong;

        if ($followup !== '' && $followup !== $strong
            && ! ($this->isDesignHeavy($kind) && ! $this->firstDesignDone())) {
            $model = $followup;
        }

        /**
         * Filters the model chosen for one AI request.
         *
         * @param string $model    Chosen model.
         * @param string $kind     Request kind.
         * @param string $strong   Strong (primary) model.
         * @param string $followup Follow-up model ('' = none).
         */
        return (string) apply_filters('swishfolio_ai_model_for_kind', $model, $kind, $strong, $followup);
    }

    /**
     * @param string $kind Request kind.
     *
     * @return bool
     */
    public function isDesignHeavy($kind)
    {
        /**
         * Filters which request kinds count as design-heavy (first one gets the
         * strong model).
         *
         * @param string[] $kinds Design-heavy kinds.
         */
        $kinds = (array) apply_filters('swishfolio_ai_design_heavy_kinds', self::DESIGN_KINDS);

        return in_array((string) $kind, $kinds, true);
    }

    /**
     * @return bool Whether this site already had a successful design-heavy request.
     */
    public function firstDesignDone()
    {
        return (bool) get_option(self::FIRST_DESIGN_OPTION, false);
    }

    /**
     * Record that a request of this kind succeeded. Only design-heavy kinds
     * mark the site; calling it for any other kind is a no-op.
     *
     * @param string $kind Request kind.
     *
     * @return void
     */
    public function markSucceeded($kind)
    {
        if (! $this->isDesignHeavy($kind) || $this->firstDesignDone()) {
            return;
        }

        update_option(self::FIRST_DESIGN_OPTION, time(), false);
    }
}
