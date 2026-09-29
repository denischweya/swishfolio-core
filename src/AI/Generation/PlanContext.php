<?php
/**
 * Everything a plan validation stage needs to judge one site plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Blocks\BlockRegistry;

/**
 * The generation counterpart of Actions\ValidationContext. It carries different
 * facts because a plan is judged against different things: a catalogue of
 * generation units, the theme's custom templates and style variations, and the
 * slugs already taken on this site.
 */
class PlanContext
{
    /** @var PatternLibrary */
    private $library;

    /** @var BlockRegistry */
    private $registry;

    /** @var TokenResolver */
    private $tokens;

    /** @var string */
    private $scope;

    /** @var string[] Style variation titles this theme ships. */
    private $styleVariations = [];

    /** @var string[] Custom template names from theme.json. */
    private $customTemplates = [];

    /** @var array slug => post ID for pages that already exist. */
    private $existingSlugs = [];

    /** @var int */
    private $maxPages;

    /** @var int */
    private $maxSectionsPerPage;

    /** @var int */
    private $maxSectionsTotal;

    /**
     * @param PatternLibrary $library  Generation catalogue.
     * @param BlockRegistry  $registry Capability registry.
     * @param TokenResolver  $tokens   Live design tokens.
     * @param string         $scope    "site" or "page".
     */
    public function __construct(PatternLibrary $library, BlockRegistry $registry, TokenResolver $tokens, $scope = 'site')
    {
        $this->library            = $library;
        $this->registry           = $registry;
        $this->tokens             = $tokens;
        $this->scope              = $scope === 'page' ? 'page' : 'site';
        $this->maxPages           = PlanSchema::MAX_PAGES;
        $this->maxSectionsPerPage = PlanSchema::MAX_SECTIONS_PER_PAGE;
        $this->maxSectionsTotal   = PlanSchema::MAX_SECTIONS_TOTAL;
    }

    /**
     * @param string[] $titles Style variation titles.
     *
     * @return self
     */
    public function withStyleVariations(array $titles)
    {
        $this->styleVariations = array_values(array_map('strval', $titles));

        return $this;
    }

    /**
     * @param string[] $names Custom template names.
     *
     * @return self
     */
    public function withCustomTemplates(array $names)
    {
        $this->customTemplates = array_values(array_map('strval', $names));

        return $this;
    }

    /**
     * @param array $slugs slug => post ID.
     *
     * @return self
     */
    public function withExistingSlugs(array $slugs)
    {
        $this->existingSlugs = $slugs;

        return $this;
    }

    /**
     * @param int $pages      Page cap.
     * @param int $perPage    Sections per page cap.
     * @param int $total      Total sections cap.
     *
     * @return self
     */
    public function withLimits($pages, $perPage, $total)
    {
        $this->maxPages           = (int) $pages;
        $this->maxSectionsPerPage = (int) $perPage;
        $this->maxSectionsTotal   = (int) $total;

        return $this;
    }

    public function getLibrary()
    {
        return $this->library;
    }

    public function getRegistry()
    {
        return $this->registry;
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getStyleVariations()
    {
        return $this->styleVariations;
    }

    public function getCustomTemplates()
    {
        return $this->customTemplates;
    }

    public function getExistingSlugs()
    {
        return $this->existingSlugs;
    }

    /**
     * @param string $slug Page slug.
     *
     * @return int|null Post ID of the page already using that slug.
     */
    public function getExistingPostId($slug)
    {
        return isset($this->existingSlugs[$slug]) ? (int) $this->existingSlugs[$slug] : null;
    }

    public function getMaxPages()
    {
        return $this->maxPages;
    }

    public function getMaxSectionsPerPage()
    {
        return $this->maxSectionsPerPage;
    }

    public function getMaxSectionsTotal()
    {
        return $this->maxSectionsTotal;
    }
}
