<?php
/**
 * Screenshots of a site, taken by headless Chrome in a separate node process.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

/**
 * Writes a job file, runs bin/visual-review/render.mjs through the
 * ProcessRunner with a hard timeout and reads the JPEGs back. Never runs
 * inside a web request: the only caller is the visual review WP-CLI command.
 */
class ScreenshotRenderer
{
    /** Seconds the renderer may take for all shots. */
    const TIMEOUT = 120;

    /** JPEG bytes above this are not sent (a broken page, not a screenshot). */
    const MAX_BYTES = 3000000;

    /** @var VisualReviewConfig */
    private $config;

    /** @var ProcessRunner */
    private $runner;

    public function __construct(VisualReviewConfig $config, ProcessRunner $runner = null)
    {
        $this->config = $config;
        $this->runner = $runner !== null ? $runner : new ProcessRunner();
    }

    /**
     * The standard set: Home and one other page on desktop, Home on a phone.
     *
     * @param string $homeUrl  Front page URL.
     * @param string $otherUrl Second page URL ('' for none).
     *
     * @return array[] Shot specs for render().
     */
    public static function standardShots($homeUrl, $otherUrl)
    {
        // 1440 CSS px at scale 0.5 is 720 px wide; 5120 CSS px tall is 2560 px,
        // under the 2576 px long edge the vision models take without resizing.
        $shots = [
            ['name' => 'home-desktop', 'label' => 'Home page, desktop 1440 px wide (shown at half size, top part of the page)', 'url' => $homeUrl, 'width' => 1440, 'maxHeight' => 5120, 'scale' => 0.5, 'mobile' => false, 'quality' => 68],
        ];
        if ($otherUrl !== '') {
            $shots[] = ['name' => 'page-desktop', 'label' => 'Second page, desktop 1440 px wide (shown at half size, top part of the page)', 'url' => $otherUrl, 'width' => 1440, 'maxHeight' => 5120, 'scale' => 0.5, 'mobile' => false, 'quality' => 68];
        }
        $shots[] = ['name' => 'home-mobile', 'label' => 'Home page on a phone, 390 px wide (top part of the page)', 'url' => $homeUrl, 'width' => 390, 'maxHeight' => 2400, 'scale' => 1, 'mobile' => true, 'quality' => 68];

        return $shots;
    }

    /**
     * @param array[] $shots Shot specs (name, label, url, width, maxHeight, scale, mobile, quality).
     * @param string  $dir   Directory for the job file and images (created).
     *
     * @throws RenderException When nothing could be rendered.
     *
     * @return array[] {name, label, file, width, height, pageHeight, data (base64), bytes}
     */
    public function render(array $shots, $dir)
    {
        $chrome = $this->config->chromePath();
        $paths  = $this->config->paths();

        if ($chrome === '') {
            throw new RenderException('no Chrome binary found');
        }
        if ($paths['node'] === '') {
            throw new RenderException('no node binary found');
        }

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            throw new RenderException('cannot create ' . $dir);
        }

        $job = [
            'chrome'    => $chrome,
            'modules'   => $paths['modules'],
            'out'       => $dir,
            'hostMap'   => $this->config->hostMap(),
            'timeoutMs' => (self::TIMEOUT - 10) * 1000,
            'shots'     => array_map(static function ($s) {
                unset($s['label']);
                return $s;
            }, array_values($shots)),
        ];
        $jobFile = rtrim($dir, '/') . '/job.json';
        file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_SLASHES));

        $result = $this->runner->run([$paths['node'], $paths['script'], $jobFile], self::TIMEOUT);

        if ($result['timedOut']) {
            throw new RenderException('renderer timed out');
        }

        $decoded = self::lastJsonLine((string) $result['stdout']);
        if ($decoded === null || empty($decoded['ok'])) {
            $why = $decoded !== null && isset($decoded['error']) ? (string) $decoded['error'] : trim(substr((string) $result['stderr'], 0, 300));
            throw new RenderException('renderer failed (exit ' . (int) $result['exit'] . '): ' . ($why !== '' ? $why : 'no output'));
        }

        $labels = [];
        foreach ($shots as $s) {
            $labels[$s['name']] = isset($s['label']) ? (string) $s['label'] : (string) $s['name'];
        }

        $out = [];
        foreach ((array) $decoded['shots'] as $shot) {
            $file = isset($shot['file']) ? (string) $shot['file'] : '';
            // Only files the job was told to write.
            if ($file === '' || strpos(realpath($file) ?: '', realpath($dir) ?: "\0") !== 0) {
                continue;
            }
            $bytes = (string) @file_get_contents($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES || substr($bytes, 0, 2) !== "\xFF\xD8") {
                continue;
            }
            $out[] = [
                'name'       => (string) $shot['name'],
                'label'      => isset($labels[$shot['name']]) ? $labels[$shot['name']] : (string) $shot['name'],
                'file'       => $file,
                'width'      => (int) $shot['width'],
                'height'     => (int) $shot['height'],
                'pageHeight' => (int) $shot['pageHeight'],
                'bytes'      => strlen($bytes),
                'data'       => base64_encode($bytes),
            ];
        }

        if (! $out) {
            throw new RenderException('renderer wrote no usable image');
        }

        return $out;
    }

    /**
     * @param string $stdout Renderer output.
     *
     * @return array|null The last line that is a JSON object.
     */
    private static function lastJsonLine($stdout)
    {
        $lines = array_reverse(preg_split('/\r?\n/', trim($stdout)));
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
