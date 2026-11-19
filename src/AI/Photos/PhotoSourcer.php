<?php
/**
 * Finds, checks and imports stock photos for a new site's photo spots.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Generation\Staged\ParallelRunner;
use SwishfolioCore\AI\Prompt\PromptManager;

/**
 * For each spot with a search query (KitImages::stockQueries()):
 *
 * 1. search  The libraries in order (Unsplash first), up to CANDIDATES results.
 * 2. check   One vision call per spot on the cheap model, all side by side:
 *            the candidates' hotlinked thumbnails plus the spot's context. The
 *            model picks one or rejects them all.
 * 3. place   Unsplash picks are hotlinked (urls.regular at w=1600) as their
 *            terms require, with the credit carried in the placement for the
 *            page's meta; other picks are imported into the media library at
 *            about 1600px wide with alt text and attribution meta. The library
 *            is told it was used where its terms ask (Unsplash download endpoint).
 *
 * Bounded: searches stop after SEARCH_BUDGET seconds, every call has its own
 * timeout and imports stop once BUDGET seconds have gone. Never throws: any
 * failure leaves that spot as it was and adds a note.
 */
class PhotoSourcer
{
    const BUDGET           = 20.0;
    const SEARCH_BUDGET    = 7.0;
    const SEARCH_TIMEOUT   = 4;
    const CHECK_TIMEOUT    = 10;
    const DOWNLOAD_TIMEOUT = 6;
    const CANDIDATES       = 3;

    /** @var StockPhotoProvider[] */
    private $libraries;

    /** @var AIProviderInterface */
    private $ai;

    /** @var PromptManager */
    private $prompts;

    /** @var callable fn(array $photo, string $alt, int $timeout): array{id:int,url:string} (throws on failure). */
    private $import;

    /** @var ParallelRunner */
    private $runner;

    /** @var float */
    private $start = 0.0;

    /**
     * @param StockPhotoProvider[] $libraries Libraries, in order of preference.
     * @param AIProviderInterface  $ai        Provider for the vision check.
     * @param PromptManager        $prompts   Prompts.
     * @param callable             $import    Media library import (PhotoSideloader::sideload).
     * @param ParallelRunner|null  $runner    Runner for the vision calls.
     */
    public function __construct(array $libraries, AIProviderInterface $ai, PromptManager $prompts, callable $import, ParallelRunner $runner = null)
    {
        $this->libraries = array_values($libraries);
        $this->ai        = $ai;
        $this->prompts   = $prompts;
        $this->import    = $import;
        $this->runner    = $runner !== null ? $runner : new ParallelRunner($ai);
    }

    /**
     * @param array[] $wants    {ref, postId, slot, page, position, alt, query, orientation}.
     * @param string  $business Business name and brief.
     * @param string  $model    Vision model (ModelRouter::KIND_PHOTO_CHECK).
     *
     * @return array {placements: {postId, slot, id, url, alt}[], responses: AIResponse[], notes: string[], checked: int}
     */
    public function source(array $wants, $business, $model)
    {
        $this->start = microtime(true);
        $out         = ['placements' => [], 'responses' => [], 'notes' => [], 'checked' => 0];

        if (! $wants || ! $this->libraries) {
            return $out;
        }
        // The check needs a model that sees images; only the Anthropic request carries them.
        if ($this->ai->getSlug() !== 'anthropic') {
            $out['notes'][] = 'Photo check skipped: it needs the Anthropic provider.';

            return $out;
        }

        try {
            $candidates = $this->search($wants, $out['notes']);
            $picks      = $this->check($wants, $candidates, (string) $business, (string) $model, $out);
            $this->importPicks($wants, $picks, $out);
        } catch (\Throwable $e) {
            $out['notes'][] = 'Photo sourcing stopped: ' . self::safe($e->getMessage());
        }

        return $out;
    }

    /**
     * @param array[]  $wants Spots.
     * @param string[] $notes Notes (appended).
     *
     * @return array ref => photo[]
     */
    private function search(array $wants, array &$notes)
    {
        $found = [];
        $cache = [];

        foreach ($wants as $want) {
            $left = self::SEARCH_BUDGET - $this->elapsed();
            if ($left < 1) {
                $notes[] = 'Photo search stopped at its time limit.';
                break;
            }

            $key = strtolower($want['query']) . '|' . $want['orientation'];
            if (! isset($cache[$key])) {
                $cache[$key] = [];
                foreach ($this->libraries as $library) {
                    $results = $library->search($want['query'], $want['orientation'], self::CANDIDATES + 1, (int) max(1, min(self::SEARCH_TIMEOUT, floor($left))));
                    if ($library->lastError() !== '') {
                        $notes[] = 'Photo search failed: ' . self::safe($library->lastError());
                    }
                    if ($results) {
                        $cache[$key] = array_slice($results, 0, self::CANDIDATES);
                        break;
                    }
                }
            }

            if ($cache[$key]) {
                $found[$want['ref']] = $cache[$key];
            }
        }

        return $found;
    }

    /**
     * @param array[] $wants      Spots.
     * @param array   $candidates ref => photo[].
     * @param string  $business   Business.
     * @param string  $model      Model.
     * @param array   $out        Result (responses, notes, checked appended).
     *
     * @return array ref => {photo, alt}
     */
    private function check(array $wants, array $candidates, $business, $model, array &$out)
    {
        $requests = [];
        foreach ($wants as $want) {
            if (! empty($candidates[$want['ref']])) {
                $requests[$want['ref']] = $this->request($want, $candidates[$want['ref']], $business, $model);
            }
        }
        if (! $requests) {
            return [];
        }

        $left = self::BUDGET - $this->elapsed() - 3;
        if ($left < 3) {
            $out['notes'][] = 'Photo check skipped: no time left.';

            return [];
        }

        $picks = [];
        try {
            $this->runner->run($requests, function ($ref, $response, $error, $decoded, $seconds) use ($candidates, &$picks, &$out) {
                $out['checked']++;
                if ($response instanceof AIResponse) {
                    $out['responses'][] = $response;
                    $pick = self::pick((array) $response->getDecoded(), count($candidates[$ref]));
                    if ($pick !== null) {
                        $picks[$ref] = ['photo' => $candidates[$ref][$pick['index']], 'alt' => $pick['alt']];
                    }

                    return;
                }
                $out['notes'][] = 'Photo check failed for one spot: ' . self::safe($error ? $error->getMessage() : 'no reply');
            }, (int) max(3, min(self::CHECK_TIMEOUT, floor($left))));
        } catch (\Throwable $e) {
            $out['notes'][] = 'Photo check failed: ' . self::safe($e->getMessage());
        }

        return $picks;
    }

    /**
     * @param array[] $wants Spots.
     * @param array   $picks ref => {photo, alt}.
     * @param array   $out   Result (placements, notes appended).
     *
     * @return void
     */
    private function importPicks(array $wants, array $picks, array &$out)
    {
        $used = [];
        foreach ($wants as $want) {
            if (! isset($picks[$want['ref']])) {
                continue;
            }
            $photo = $picks[$want['ref']]['photo'];
            $id    = $photo['provider'] . ':' . $photo['id'];
            // The same photo twice on one site looks like a template.
            if (isset($used[$id])) {
                continue;
            }

            $left = self::BUDGET - $this->elapsed();
            if ($left < 2) {
                $out['notes'][] = 'Photo import stopped at its time limit.';
                break;
            }

            $alt = $picks[$want['ref']]['alt'] !== '' ? $picks[$want['ref']]['alt'] : \SwishfolioCore\AI\Copy\Dashes::text(mb_substr($photo['alt'], 0, 160));

            $credit = null;
            if (! empty($photo['hotlink'])) {
                // Hotlinked: no attachment, so the page carries the credit.
                $media  = ['id' => 0, 'url' => (string) $photo['hotlink']];
                $credit = PhotoSideloader::creditMeta($photo) + ['url' => (string) $photo['hotlink']];
            } else {
                try {
                    $media = call_user_func($this->import, $photo, $alt, (int) max(1, min(self::DOWNLOAD_TIMEOUT, floor($left))));
                } catch (\Throwable $e) {
                    $out['notes'][] = 'Photo import failed: ' . self::safe($e->getMessage());
                    continue;
                }
            }

            $used[$id] = true;
            $this->library($photo['provider'])->trackUse($photo, 3);
            $out['placements'][] = ['postId' => $want['postId'], 'slot' => $want['slot'], 'id' => (int) $media['id'], 'url' => (string) $media['url'], 'alt' => $alt, 'credit' => $credit];
        }
    }

    /**
     * The vision request for one spot.
     *
     * @param array   $want       Spot.
     * @param array[] $candidates Photos.
     * @param string  $business   Business.
     * @param string  $model      Model.
     *
     * @return AIRequest
     */
    public function request(array $want, array $candidates, $business, $model)
    {
        $lines = [];
        foreach (array_values($candidates) as $i => $photo) {
            $lines[] = ($i + 1) . ': ' . ($photo['alt'] !== '' ? $photo['alt'] : '(no description)');
        }

        $user = $this->prompts->task('photo-check', [
            'business'   => mb_substr((string) $business, 0, 700),
            'spot'       => $want['page'] . ', ' . $want['position'],
            'query'      => $want['query'],
            'current'    => $want['alt'] !== '' ? $want['alt'] : $want['file'],
            'candidates' => implode("\n", $lines),
        ]);

        return (new AIRequest('You judge whether stock photos suit a spot on a small business website. You answer only with the structured result.', $user, self::schema(), (string) $model, 1024))
            ->withImages(array_column(array_values($candidates), 'thumb'))
            // A pick-one judgement: low effort keeps the cheap model quick.
            ->withEffort('low');
    }

    /**
     * Pure: the picked candidate, or null for "none fits".
     *
     * @param array $decoded Model answer.
     * @param int   $count   Candidates shown.
     *
     * @return array|null {index (0-based), alt}
     */
    public static function pick(array $decoded, $count)
    {
        $pick = isset($decoded['pick']) ? (int) $decoded['pick'] : 0;
        if ($pick < 1 || $pick > (int) $count) {
            return null;
        }

        return [
            'index' => $pick - 1,
            'alt'   => \SwishfolioCore\AI\Copy\Dashes::text(mb_substr(trim(sanitize_text_field(isset($decoded['alt']) ? (string) $decoded['alt'] : '')), 0, 160)),
        ];
    }

    /**
     * @return array
     */
    public static function schema()
    {
        return [
            'type'       => 'object',
            'required'   => ['pick', 'alt'],
            'properties' => [
                'pick' => ['type' => 'integer', 'description' => 'Number of the best fitting candidate, or 0 for none.'],
                'alt'  => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param string $slug Library slug.
     *
     * @return StockPhotoProvider
     */
    private function library($slug)
    {
        foreach ($this->libraries as $library) {
            if ($library->slug() === $slug) {
                return $library;
            }
        }

        return $this->libraries[0];
    }

    /**
     * @return float Seconds since source() started.
     */
    private function elapsed()
    {
        return microtime(true) - $this->start;
    }

    /**
     * A note never carries a URL's query string (where a key could be) or more than a line.
     *
     * @param string $message Message.
     *
     * @return string
     */
    private static function safe($message)
    {
        $message = preg_replace('#(https?://[^\s?]+)\?\S*#', '$1', (string) $message);

        return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $message)), 0, 200);
    }
}
