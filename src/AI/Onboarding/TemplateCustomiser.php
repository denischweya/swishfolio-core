<?php
/**
 * Personalises a cloned template site with the owner's details.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Generation\SocialProof;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolRunner;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;

/**
 * One provider call per page (copy) plus one for the design, each followed by
 * one journalled write through the tool runner. The calls are independent, so
 * the wizard runs them as parallel background jobs under one run ID and the
 * whole customisation is one undo in the audit log.
 *
 * The owner's facts go in the system prompt, identical for every page of the
 * run; the page outline goes in the user message.
 */
class TemplateCustomiser
{
    const MAX_PAGES      = 8;
    const MAX_DOC_CHARS  = 24000;
    const MAX_IMAGES     = 12;

    /** @var AIProviderInterface */
    private $provider;

    /** @var PromptManager */
    private $prompts;

    /** @var ToolRunner */
    private $runner;

    /**
     * @param AIProviderInterface $provider Provider.
     * @param PromptManager       $prompts  Prompts.
     * @param ToolRunner          $runner   Runner holding rewrite-copy and set-design-tokens.
     */
    public function __construct(AIProviderInterface $provider, PromptManager $prompts, ToolRunner $runner)
    {
        $this->provider = $provider;
        $this->prompts  = $prompts;
        $this->runner   = $runner;
    }

    /**
     * What a customisation would rewrite: published pages (front page first)
     * and the header and footer template parts that have text to change.
     *
     * @return array[] {postId, title, type}
     */
    public static function targets()
    {
        $front = (int) get_option('page_on_front');
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        usort($pages, static function ($a, $b) use ($front) {
            return ((int) ($b->ID === $front)) - ((int) ($a->ID === $front));
        });

        $out = [];

        foreach ($pages as $page) {
            if (count($out) >= self::MAX_PAGES) {
                break;
            }
            if (! current_user_can('edit_post', $page->ID) || (new CopySlots($page->post_content))->isEmpty()) {
                continue;
            }
            $out[] = ['postId' => (int) $page->ID, 'title' => $page->post_title, 'type' => 'page'];
        }

        if (current_user_can('edit_theme_options')) {
            $parts = get_posts([
                'post_type'      => 'wp_template_part',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'no_found_rows'  => true,
                'tax_query'      => [[
                    'taxonomy' => 'wp_theme',
                    'field'    => 'name',
                    'terms'    => get_stylesheet(),
                ]],
            ]);

            foreach ($parts as $part) {
                if (! preg_match('/^(header|footer)/', $part->post_name) || (new CopySlots($part->post_content))->isEmpty()) {
                    continue;
                }
                $out[] = ['postId' => (int) $part->ID, 'title' => strpos($part->post_name, 'header') === 0 ? __('Header', 'swishfolio-core') : __('Footer', 'swishfolio-core'), 'type' => 'part'];
            }
        }

        return $out;
    }

    /**
     * Rewrite one page or template part.
     *
     * @param int         $postId  Target.
     * @param array       $brief   Owner facts (see facts()).
     * @param string      $model   Model id.
     * @param ToolContext $context Confirmed run context.
     *
     * @return array {postId, updated, summary, counts, usage}
     */
    public function customisePost($postId, array $brief, $model, ToolContext $context)
    {
        $post  = get_post((int) $postId);
        $slots = new CopySlots((string) $post->post_content);

        if ($slots->isEmpty()) {
            return ['postId' => (int) $postId, 'updated' => false, 'summary' => '', 'counts' => [], 'usage' => [0, 0]];
        }

        $isPart = 'wp_template_part' === $post->post_type;
        $kind   = $isPart ? 'template part' : 'page';
        $target = $isPart
            ? sprintf('This is the site %s (shown on every page).', strpos($post->post_name, 'header') === 0 ? 'header' : 'footer')
            : sprintf('This is the page "%s"%s.', $post->post_title, (int) get_option('page_on_front') === (int) $post->ID ? ' (the home page)' : '');

        $system = str_replace('{{facts}}', $this->facts($brief), $this->prompts->system('customise'));
        $user   = $this->prompts->task('customise-copy', [
            'target'  => $target,
            'outline' => $slots->outline(),
            'kind'    => $kind,
        ]);

        $response = $this->provider->generate(new AIRequest($system, $user, self::copySchema(), $model, 16000));
        $decoded  = $response->getDecoded();
        $edits    = self::edits($decoded, $this->imageIds($brief));

        $result = $this->runner->run('swishfolio/rewrite-copy', [
            'postId'      => (int) $postId,
            'contentHash' => $slots->hash(),
            'edits'       => wp_json_encode($edits),
        ], $context);

        return [
            'postId'  => (int) $postId,
            'title'   => $isPart ? ucfirst(strtok($post->post_name, '-')) : $post->post_title,
            'updated' => ! empty($result['updated']),
            'summary' => isset($decoded['summary']) ? (string) $decoded['summary'] : '',
            'counts'  => [
                'texts'  => count($edits['texts']),
                'images' => count($edits['images']),
                'removed' => count($edits['remove']),
                'added'  => count($edits['clones']) + count($edits['append']),
            ],
            'response' => $response,
        ];
    }

    /**
     * Light design changes from the owner's requests and logo colours.
     *
     * @param array       $brief   Owner facts.
     * @param string      $model   Model id.
     * @param ToolContext $context Confirmed run context.
     *
     * @return array {applied, summary, changes, response}
     */
    public function customiseDesign(array $brief, $model, ToolContext $context)
    {
        $postId = SetDesignTokensTool::globalStylesPostId();
        $config = json_decode((string) get_post_field('post_content', $postId), true);
        $config = is_array($config) ? $config : [];
        $fonts  = SetDesignTokensTool::fontOptions($config);

        $names = [];
        foreach (SetDesignTokensTool::themePalette() as $entry) {
            if (! empty($entry['color'])) {
                $names[SetDesignTokensTool::hex($entry['color'])] = isset($entry['name']) ? (string) $entry['name'] : '';
            }
        }
        if (isset($config['settings']['color']['palette']['theme'])) {
            foreach ((array) $config['settings']['color']['palette']['theme'] as $entry) {
                if (! empty($entry['color'])) {
                    $names[SetDesignTokensTool::hex($entry['color'])] = isset($entry['name']) ? (string) $entry['name'] : '';
                }
            }
        }

        $colors = [];
        foreach (SetDesignTokensTool::usedColors($config) as $hex => $count) {
            $colors[] = $hex . ': ' . $count . (! empty($names[$hex]) ? ' (' . $names[$hex] . ')' : '');
        }

        $logo = '';
        if (! empty($brief['logoColors'])) {
            $logo = 'The owner wants the accent colours taken from their logo. The logo\'s main colours are: '
                . implode(', ', $brief['logoColors'])
                . '. Swap the template\'s main accent (and its variants) to the most distinctive logo colour, adjusted only as much as readability needs. Leave the neutral background and text colours alone.';
        }

        $user = $this->prompts->task('customise-design', [
            'requests' => trim((string) (isset($brief['prompt']) ? $brief['prompt'] : '')) ?: '(none)',
            'logo'     => $logo,
            'colors'   => implode("\n", $colors) ?: '(none found)',
            'fonts'    => implode(', ', array_keys($fonts)) ?: '(none)',
        ]);

        $response = $this->provider->generate(new AIRequest($this->prompts->system('agent'), $user, self::designSchema(), $model, 2000));
        $decoded  = $response->getDecoded();

        $input = [
            'colorSwaps'  => array_slice(array_values(array_filter((array) (isset($decoded['colorSwaps']) ? $decoded['colorSwaps'] : []), 'is_array')), 0, SetDesignTokensTool::MAX_SWAPS),
            'headingFont' => isset($decoded['headingFont']) ? (string) $decoded['headingFont'] : '',
            'bodyFont'    => isset($decoded['bodyFont']) ? (string) $decoded['bodyFont'] : '',
        ];

        $input['colorSwaps'] = array_map(static function ($swap) {
            return ['from' => substr((string) (isset($swap['from']) ? $swap['from'] : ''), 0, 7), 'to' => substr((string) (isset($swap['to']) ? $swap['to'] : ''), 0, 7)];
        }, $input['colorSwaps']);

        // A light page turned dark (or the reverse) turns every neutral with it.
        $base = '';
        foreach (isset($config['settings']['color']['palette']['theme']) ? (array) $config['settings']['color']['palette']['theme'] : SetDesignTokensTool::themePalette() as $entry) {
            if (isset($entry['slug'], $entry['color']) && 'base' === $entry['slug']) {
                $base = (string) $entry['color'];
            }
        }
        $input['colorSwaps'] = array_slice(PolarityGuard::complete($input['colorSwaps'], array_keys(SetDesignTokensTool::usedColors($config)), $base), 0, SetDesignTokensTool::MAX_SWAPS);

        $result = ['applied' => false, 'changes' => []];

        if ($input['colorSwaps'] || $input['headingFont'] !== '' || $input['bodyFont'] !== '') {
            $result = $this->runner->run('swishfolio/set-design-tokens', $input, $context);
        }

        return [
            'applied'  => ! empty($result['applied']),
            'changes'  => isset($result['changes']) ? $result['changes'] : [],
            'summary'  => ! empty($result['applied']) && isset($decoded['summary']) ? (string) $decoded['summary'] : '',
            'response' => $response,
        ];
    }

    /**
     * The owner's facts as prompt text.
     *
     * @param array $brief business, contact, brief, prompt, documents, images, profile.
     *
     * @return string
     */
    public function facts(array $brief)
    {
        $lines = [];
        $b     = isset($brief['business']) ? (array) $brief['business'] : [];
        $c     = isset($brief['contact']) ? (array) $brief['contact'] : [];

        $labels = [
            'name' => 'Business name', 'tagline' => 'Tagline', 'street' => 'Street', 'city' => 'City', 'region' => 'Region',
            'postcode' => 'Postcode', 'country' => 'Country', 'hours' => 'Opening hours',
        ];
        foreach ($labels as $key => $label) {
            if (! empty($b[$key])) {
                $lines[] = $label . ': ' . $b[$key];
            }
        }

        $labels = [
            'email' => 'Email', 'phone' => 'Phone', 'website' => 'Existing website', 'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram', 'x' => 'X', 'facebook' => 'Facebook',
        ];
        foreach ($labels as $key => $label) {
            if (! empty($c[$key])) {
                $lines[] = $label . ': ' . $c[$key];
            }
        }

        if (! empty($b['brief'])) {
            $lines[] = "\nIn their own words:\n" . $b['brief'];
        }

        if (! empty($brief['prompt'])) {
            $lines[] = "\nTheir requests for the site:\n" . $brief['prompt'];
        }

        // Clients, a testimonial and key numbers from the wizard: real social proof.
        $proof = SocialProof::asText(SocialProof::fromBusiness($b));
        if ($proof !== '') {
            $lines[] = "\nTheir social proof (use it as given):\n" . $proof;
        }

        // The customisation dropped the template's projects (TemplateProjects).
        if (! empty($brief['noProjects'])) {
            $lines[] = "\nThis site does not show projects or past work: do not mention a portfolio, case studies or past projects, and do not point any link or button at a work or projects page.";
        }

        // A kit build: the pages the new site really has, so header and footer
        // links name those pages and not the template's.
        if (! empty($brief['sitePages'])) {
            $lines[] = "\nPages on the new site (title: address). Header and footer links must name and point to these, never to pages that are not listed:\n" . $brief['sitePages'];
        }

        $profile = isset($brief['profile']) && is_array($brief['profile']) ? array_filter($brief['profile']) : [];
        unset($profile['missing'], $profile['designStyle'], $profile['copywritingTone']);
        if ($profile) {
            $lines[] = "\nProfile they reviewed (JSON):\n" . wp_json_encode($profile);
        }

        $docs = trim((string) (isset($brief['documents']) ? $brief['documents'] : ''));
        if ($docs !== '') {
            $lines[] = "\nText of documents they uploaded (CV, menu, brochure, price list and so on):\n" . mb_substr($docs, 0, self::MAX_DOC_CHARS);
        }

        $images = $this->images($brief);
        if ($images) {
            $lines[] = "\nImages they uploaded (attachment ID: what it is):";
            foreach ($images as $image) {
                $lines[] = '- ' . $image['id'] . ': ' . $image['role'] . ($image['note'] !== '' ? ', "' . $image['note'] . '"' : '') . ', file ' . $image['file'] . ($image['size'] ? ', ' . $image['size'] : '');
            }
        } else {
            $lines[] = "\nThey uploaded no images: leave the template's images in place.";
        }

        return $lines ? implode("\n", $lines) : '(Nothing beyond the business name.)';
    }

    /**
     * Uploaded images the owner can use, with what they told us about each.
     *
     * @param array $brief Brief.
     *
     * @return array[]
     */
    public function images(array $brief)
    {
        $roles = [
            'logo'    => 'their logo',
            'people'  => 'photo of them or their team',
            'work'    => 'photo of their work, products or food',
            'place'   => 'photo of their premises or location',
            'other'   => 'other image',
        ];
        $out = [];

        foreach (array_slice(isset($brief['images']) ? (array) $brief['images'] : [], 0, self::MAX_IMAGES) as $image) {
            $id = is_array($image) && isset($image['id']) ? (int) $image['id'] : 0;

            if ($id <= 0 || ! wp_attachment_is_image($id) || ! current_user_can('edit_post', $id)) {
                continue;
            }

            $meta = wp_get_attachment_metadata($id);
            $role = isset($image['role'], $roles[$image['role']]) ? $image['role'] : 'other';

            $out[] = [
                'id'   => $id,
                'role' => $roles[$role],
                'kind' => $role,
                'note' => isset($image['note']) ? mb_substr(sanitize_text_field((string) $image['note']), 0, 120) : '',
                'file' => basename((string) get_attached_file($id)),
                'size' => is_array($meta) && ! empty($meta['width']) ? $meta['width'] . 'x' . $meta['height'] : '',
            ];
        }

        return $out;
    }

    /**
     * @param array $brief Brief.
     *
     * @return int[] Attachment IDs the model may place (logos excluded from photo slots by prompt, not here).
     */
    private function imageIds(array $brief)
    {
        return array_map(static function ($image) {
            return (int) $image['id'];
        }, $this->images($brief));
    }

    /**
     * Normalise the model's answer into CopySlots / rewrite-copy edits.
     *
     * @param array $decoded  Model output.
     * @param int[] $imageIds Allowed attachment IDs.
     *
     * @return array
     */
    public static function edits(array $decoded, array $imageIds)
    {
        $pairs = static function ($list, $valueKey) {
            $out = [];
            foreach ((array) $list as $item) {
                if (is_array($item) && isset($item['id'], $item[$valueKey]) && is_string($item['id'])) {
                    $out[$item['id']] = (string) $item[$valueKey];
                }
            }
            return $out;
        };

        $images = static function ($list) use ($imageIds) {
            $out = [];
            foreach ((array) $list as $item) {
                $id = is_array($item) && isset($item['attachmentId']) ? (int) $item['attachmentId'] : 0;
                if (isset($item['id']) && in_array($id, $imageIds, true)) {
                    $out[(string) $item['id']] = ['attachmentId' => $id, 'alt' => isset($item['alt']) ? (string) $item['alt'] : ''];
                }
            }
            return $out;
        };

        $clones = [];
        foreach (array_slice((array) (isset($decoded['clones']) ? $decoded['clones'] : []), 0, 12) as $clone) {
            if (is_array($clone) && ! empty($clone['unit'])) {
                $clones[] = [
                    'unit'   => (string) $clone['unit'],
                    'texts'  => $pairs(isset($clone['texts']) ? $clone['texts'] : [], 'html'),
                    'images' => $images(isset($clone['images']) ? $clone['images'] : []),
                ];
            }
        }

        $append = [];
        foreach (array_slice((array) (isset($decoded['append']) ? $decoded['append'] : []), 0, 1) as $section) {
            if (is_array($section) && ! empty($section['after']) && ! empty($section['markup'])) {
                $append[] = ['after' => (string) $section['after'], 'markup' => (string) $section['markup']];
            }
        }

        return [
            'texts'  => $pairs(isset($decoded['texts']) ? $decoded['texts'] : [], 'html'),
            'attrs'  => $pairs(isset($decoded['attrs']) ? $decoded['attrs'] : [], 'value'),
            'images' => $images(isset($decoded['images']) ? $decoded['images'] : []),
            'remove' => array_values(array_filter(array_map('strval', (array) (isset($decoded['remove']) ? $decoded['remove'] : [])))),
            'clones' => $clones,
            'append' => $append,
        ];
    }

    /**
     * Main colours of a logo: up to four hex values, neutrals and transparency skipped.
     *
     * @param int $attachmentId Logo.
     *
     * @return string[]
     */
    public static function logoColors($attachmentId)
    {
        $file = get_attached_file((int) $attachmentId);

        if (! $file || ! is_readable($file)) {
            return [];
        }

        $counts = [];

        if (preg_match('/\.svg$/i', $file)) {
            preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', (string) file_get_contents($file), $m);
            foreach ($m[0] as $hex) {
                $hex = SetDesignTokensTool::hex($hex);
                if ($hex !== '') {
                    $counts[$hex] = (isset($counts[$hex]) ? $counts[$hex] : 0) + 1;
                }
            }
        } elseif (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring((string) file_get_contents($file)); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            if (! $img) {
                return [];
            }
            $small = imagecreatetruecolor(48, 48);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagecopyresampled($small, $img, 0, 0, 0, 0, 48, 48, imagesx($img), imagesy($img));
            for ($x = 0; $x < 48; $x++) {
                for ($y = 0; $y < 48; $y++) {
                    $rgba = imagecolorat($small, $x, $y);
                    if ((($rgba >> 24) & 0x7F) > 60) {
                        continue;
                    }
                    // Quantise to 32 levels per channel so near-identical pixels pool.
                    $r = (($rgba >> 16) & 0xFF) & 0xF8;
                    $g = (($rgba >> 8) & 0xFF) & 0xF8;
                    $b = ($rgba & 0xFF) & 0xF8;
                    $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
                    $counts[$hex] = (isset($counts[$hex]) ? $counts[$hex] : 0) + 1;
                }
            }
            imagedestroy($small);
            imagedestroy($img);
        }

        // Neutrals (near white, near black, greys) are rarely the brand colour.
        $counts = array_filter($counts, static function ($hex) {
            list($r, $g, $b) = sscanf($hex, '#%02x%02x%02x');
            return max($r, $g, $b) - min($r, $g, $b) > 40;
        }, ARRAY_FILTER_USE_KEY);

        arsort($counts);

        return array_slice(array_keys($counts), 0, 4);
    }

    /**
     * @return array Output schema for one page.
     */
    public static function copySchema()
    {
        $textItem  = ['type' => 'object', 'required' => ['id', 'html'], 'properties' => ['id' => ['type' => 'string'], 'html' => ['type' => 'string']]];
        $imageItem = ['type' => 'object', 'required' => ['id', 'attachmentId', 'alt'], 'properties' => ['id' => ['type' => 'string'], 'attachmentId' => ['type' => 'integer'], 'alt' => ['type' => 'string']]];

        return [
            'type'       => 'object',
            'required'   => ['summary', 'texts', 'attrs', 'images', 'remove', 'clones', 'append'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'texts'   => ['type' => 'array', 'description' => 'New inner HTML for text slots (t ids).', 'items' => $textItem],
                'attrs'   => ['type' => 'array', 'description' => 'New values for attribute slots (a ids).', 'items' => [
                    'type' => 'object', 'required' => ['id', 'value'], 'properties' => ['id' => ['type' => 'string'], 'value' => ['type' => 'string']],
                ]],
                'images'  => ['type' => 'array', 'description' => 'Owner images placed into image slots (i ids).', 'items' => $imageItem],
                'remove'  => ['type' => 'array', 'description' => 'Unit ids (u) to remove.', 'items' => ['type' => 'string']],
                'clones'  => ['type' => 'array', 'description' => 'Units to duplicate; each clone goes after the original, with its own slot values (ids of the original unit\'s slots).', 'items' => [
                    'type'       => 'object',
                    'required'   => ['unit', 'texts', 'images'],
                    'properties' => [
                        'unit'   => ['type' => 'string'],
                        'texts'  => ['type' => 'array', 'items' => $textItem],
                        'images' => ['type' => 'array', 'items' => $imageItem],
                    ],
                ]],
                'append'  => ['type' => 'array', 'description' => 'At most one new section, only when explicitly requested.', 'items' => [
                    'type' => 'object', 'required' => ['after', 'markup'], 'properties' => ['after' => ['type' => 'string'], 'markup' => ['type' => 'string']],
                ]],
            ],
        ];
    }

    /**
     * @return array Output schema for the design call.
     */
    public static function designSchema()
    {
        return [
            'type'       => 'object',
            'required'   => ['summary', 'colorSwaps', 'headingFont', 'bodyFont'],
            'properties' => [
                'summary'     => ['type' => 'string'],
                'colorSwaps'  => ['type' => 'array', 'items' => [
                    'type' => 'object', 'required' => ['from', 'to'], 'properties' => ['from' => ['type' => 'string'], 'to' => ['type' => 'string']],
                ]],
                'headingFont' => ['type' => 'string'],
                'bodyFont'    => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param AIResponse|null $response Response.
     *
     * @return array [in, out]
     */
    public static function usage($response)
    {
        return $response instanceof AIResponse ? [(int) $response->getInputTokens(), (int) $response->getOutputTokens()] : [0, 0];
    }
}
