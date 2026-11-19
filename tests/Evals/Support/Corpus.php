<?php
/**
 * The eval corpus: one case per kind of site the plugin is built for.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tests\Evals\Support;

/**
 * Six verticals, chosen because they exercise different shapes rather than
 * different words: a portfolio leans on a dynamic query block, a landing page on
 * long-form copy and calls to action, a restaurant on internal links between
 * pages, and so on. A corpus of six paraphrases of "make me a website" would run
 * green forever and prove nothing.
 *
 * Both tracks carry the same six verticals so a regression can be read across
 * them: if the photography case fails in generation but passes in editing, the
 * fault is in the plan pipeline rather than in the manifests.
 */
final class Corpus
{
    /**
     * Every vertical the corpus must cover. A fixture missing from either track
     * is a failure, not a smaller corpus.
     */
    const VERTICALS = [
        'photography-portfolio',
        'saas-landing',
        'agency-site',
        'consultant-site',
        'restaurant',
        'freelancer-portfolio',
    ];

    /**
     * @return array PHPUnit data provider rows for the generation track.
     */
    public static function planCases()
    {
        return self::rows('plan');
    }

    /**
     * @return array PHPUnit data provider rows for the block-editing track.
     */
    public static function editCases()
    {
        return self::rows('edit');
    }

    /**
     * @return array PHPUnit data provider rows for the rejection corpus.
     */
    public static function adversarialCases()
    {
        $rows = [];

        foreach ((array) glob(self::dir() . '/adversarial/*.json') as $file) {
            $case = self::read($file);

            $rows[basename($file, '.json')] = [$case];
        }

        ksort($rows);

        return $rows;
    }

    /**
     * @param string $track "plan" or "edit".
     *
     * @return array
     */
    private static function rows($track)
    {
        $rows = [];

        foreach (self::VERTICALS as $vertical) {
            $file = self::dir() . '/' . $track . '/' . $vertical . '.json';

            if (! is_file($file)) {
                continue;
            }

            $rows[$vertical] = [self::read($file)];
        }

        return $rows;
    }

    /**
     * @param string $track "plan" or "edit".
     *
     * @return string[] Verticals with no fixture on this track.
     */
    public static function missing($track)
    {
        $missing = [];

        foreach (self::VERTICALS as $vertical) {
            if (! is_file(self::dir() . '/' . $track . '/' . $vertical . '.json')) {
                $missing[] = $vertical;
            }
        }

        return $missing;
    }

    /**
     * @return string
     */
    public static function dir()
    {
        return SFCORE_PLUGIN_DIR . 'tests/Evals/fixtures';
    }

    /**
     * @param string $file Fixture path.
     *
     * @throws \RuntimeException When the fixture is not decodable JSON.
     *
     * @return array
     */
    private static function read($file)
    {
        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException(sprintf('Eval fixture "%s" is not valid JSON.', $file));
        }

        $decoded['fixture'] = $file;

        return $decoded;
    }
}
