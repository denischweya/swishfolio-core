<?php
/**
 * One unit of composition in a generation kit.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * A unit is one verbatim section of a template page (or its header or footer
 * part), extracted by seed/extract-patterns.php. It carries no hand-written
 * slot tokens: its copy is addressed with CopySlots ids (t*, a*, u*), found in
 * the markup itself, so a section looks exactly as it does on the template and
 * every word of demo copy can still be replaced.
 *
 * The planner chooses a unit by its role, summary and outline; the markup never
 * goes into a prompt.
 */
class PatternUnit
{
    const SCOPES = ['section', 'header', 'footer'];

    /** @var string */
    private $id;

    /** @var array */
    private $data;

    private function __construct($id, array $data)
    {
        $this->id   = $id;
        $this->data = $data;
    }

    /**
     * @param string $id   Unit id, unique within its kit.
     * @param array  $data Entry from the kit's "units" map.
     *
     * @throws \InvalidArgumentException When the entry is unusable.
     *
     * @return self
     */
    public static function fromArray($id, array $data)
    {
        $scope = isset($data['scope']) ? (string) $data['scope'] : 'section';

        if (! in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException(sprintf('%s: invalid scope "%s".', $id, $scope));
        }

        if (empty($data['file']) || empty($data['role']) || ! isset($data['summary']) || $data['summary'] === '') {
            throw new \InvalidArgumentException(sprintf('%s: a file, a role and a summary are required.', $id));
        }

        $data['scope'] = $scope;

        return new self((string) $id, $data);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getScope()
    {
        return $this->data['scope'];
    }

    public function getRole()
    {
        return (string) $this->data['role'];
    }

    public function getSummary()
    {
        return (string) $this->data['summary'];
    }

    public function getFile()
    {
        return (string) $this->data['file'];
    }

    /**
     * @return string[] Page types this unit suits; "any" means every page.
     */
    public function getPageTypes()
    {
        return $this->strings('pageTypes');
    }

    /**
     * @return string[] Kit image names the unit shows (placeholders the owner replaces).
     */
    public function getImages()
    {
        return $this->strings('images');
    }

    /**
     * @return string[] Any of "projects", "form", "script".
     */
    public function getNeeds()
    {
        return $this->strings('needs');
    }

    /**
     * @return string[] Text drawn inside SVG, which no edit can reach.
     */
    public function getFixedText()
    {
        return $this->strings('fixedText');
    }

    public function getSourcePage()
    {
        return isset($this->data['sourcePage']) ? (string) $this->data['sourcePage'] : '';
    }

    /**
     * Whether the markup survives wp_kses_post() unchanged. Kit markup is
     * written as trusted (see TrustedMarkup::vouch()), so this is informational.
     *
     * @return bool
     */
    public function isKsesStable()
    {
        return ! isset($this->data['ksesStable']) || ! empty($this->data['ksesStable']);
    }

    /**
     * The one-line header the planner reads above the unit's outline.
     *
     * @return string
     */
    public function describe()
    {
        $line = '## ' . $this->id . ' | role: ' . $this->getRole();

        if ($this->getPageTypes() !== []) {
            $line .= ' | suits: ' . implode(', ', $this->getPageTypes());
        }

        $notes = [];
        $images = count($this->getImages());

        if ($images > 0) {
            $notes[] = $images . ' placeholder photo' . ($images > 1 ? 's' : '') . ' the owner replaces later';
        }

        if (in_array('projects', $this->getNeeds(), true)) {
            $notes[] = 'lists the site\'s projects: use it only when the outline\'s "projects" show is true (the sample projects written there fill it)';
        }

        if (in_array('form', $this->getNeeds(), true)) {
            $notes[] = 'its form becomes a working contact form that emails the owner: write its labels, choices and placeholders for the owner\'s enquiries';
        }

        if ($this->getFixedText() !== []) {
            // Drawn text (a badge ring) is a t* slot like any other: rewrite it in plain words.
            $notes[] = 'words drawn in its graphics, which are t* slots too and must be rewritten: "' . implode('", "', $this->getFixedText()) . '"';
        }

        // The extractor's summary still calls the form a demo; it is swapped for a working one.
        $summary = str_replace(' Has a demo form that does not send anything yet.', '', $this->getSummary());

        return $line . "\n" . $summary . ($notes !== [] ? "\nNote: " . implode('; ', $notes) . '.' : '');
    }

    /**
     * @param string $key Entry key.
     *
     * @return string[]
     */
    private function strings($key)
    {
        return isset($this->data[$key]) ? array_values(array_map('strval', (array) $this->data[$key])) : [];
    }
}
