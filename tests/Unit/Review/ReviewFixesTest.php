<?php

namespace SwishfolioCore\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Review\ReviewFixes;

class ReviewFixesTest extends TestCase
{
    private function facts()
    {
        $sections = [];
        foreach (range(1, 5) as $n) {
            $sections[$n] = ['unit' => 'home-0' . $n, 'role' => $n === 1 ? 'hero' : 'features', 'form' => $n === 5, 'alternatives' => $n === 3 ? ['about-04-features' => 'Three cards'] : []];
        }

        return [
            'pages'     => [
                'home' => [
                    'postId'   => 10,
                    'title'    => 'Home',
                    'sections' => $sections,
                    'texts'    => [
                        't4' => ['text' => str_repeat('Long words here ', 6), 'len' => 96, 'html' => false, 'section' => 2],
                        't9' => ['text' => 'Read about our services and prices', 'len' => 34, 'html' => true, 'section' => 2],
                        't12' => ['text' => str_repeat('Another long line ', 5), 'len' => 90, 'html' => false, 'section' => 3],
                    ],
                    'images'   => ['i2' => ['file' => 'hero.jpg', 'alt' => '', 'section' => 1], 'i5' => ['file' => 'card.jpg', 'alt' => '', 'section' => 3], 'i7' => ['file' => 'swish-placeholder-ab12.png', 'alt' => '', 'section' => 2, 'neutral' => true]],
                ],
            ],
            'kitImages' => ['studio-desk' => 'A desk by a window'],
            'tokens'    => ['base' => '#fbf6ee', 'contrast' => '#17110f', 'accent-1' => '#c9a27a'],
        ];
    }

    private function validate(array $fixes, $max = ReviewFixes::MAX_FIXES)
    {
        return ReviewFixes::validate(['verdict' => 'needs-fixes', 'issues' => [], 'fixes' => $fixes], $this->facts(), $max);
    }

    private function reasons(array $result)
    {
        return array_column($result['rejected'], 'reason');
    }

    public function test_every_fix_type_is_accepted_when_it_points_at_listed_things()
    {
        $r = $this->validate([
            ['type' => 'remove_section', 'page' => 'home', 'section' => 4, 'reason' => 'repeats section 2'],
            ['type' => 'swap_section', 'page' => 'home', 'section' => 3, 'unit' => 'about-04-features', 'reason' => 'empty cards'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i2', 'image' => 'neutral', 'reason' => 'steak on a law firm'],
            ['type' => 'adjust_token', 'token' => 'accent-1', 'color' => '#8a6440', 'reason' => 'low contrast on base'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't4', 'text' => 'Shorter words here', 'reason' => 'wraps badly'],
        ]);

        $this->assertSame([], $r['rejected']);
        $this->assertSame(['remove_section', 'swap_section', 'swap_image', 'adjust_token', 'shorten_copy'], array_column($r['accepted'], 'type'));
        $this->assertSame('#c9a27a', $r['accepted'][3]['from']);
    }

    public function test_anything_outside_the_fixed_list_is_rejected()
    {
        $r = $this->validate([
            ['type' => 'rewrite_page', 'page' => 'home', 'reason' => 'x'],
            ['type' => 'add_css', 'reason' => 'x'],
            'not an object',
        ]);

        $this->assertSame([], $r['accepted']);
        $this->assertSame(['not a fix from the list', 'not a fix from the list', 'not an object'], $this->reasons($r));
    }

    public function test_targets_must_exist()
    {
        $r = $this->validate([
            ['type' => 'remove_section', 'page' => 'about', 'section' => 2, 'reason' => 'x'],
            ['type' => 'remove_section', 'page' => 'home', 'section' => 9, 'reason' => 'x'],
            ['type' => 'swap_section', 'page' => 'home', 'section' => 2, 'unit' => 'about-04-features', 'reason' => 'x'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i99', 'image' => 'neutral', 'reason' => 'x'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i2', 'image' => 'https://evil.example/x.jpg', 'reason' => 'x'],
            ['type' => 'adjust_token', 'token' => 'accent-9', 'color' => '#000000', 'reason' => 'x'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't99', 'text' => 'Hi there', 'reason' => 'x'],
        ]);

        $this->assertSame([], $r['accepted']);
        $this->assertSame(['unknown page', 'unknown section', 'not an alternative offered for that section', 'unknown image slot', 'not a kit image', 'unknown palette token', 'unknown text slot'], $this->reasons($r));
    }

    public function test_sections_that_must_stay_are_protected()
    {
        $r = $this->validate([
            ['type' => 'remove_section', 'page' => 'home', 'section' => 1, 'reason' => 'x'],
            ['type' => 'remove_section', 'page' => 'home', 'section' => 5, 'reason' => 'x'],
            ['type' => 'swap_section', 'page' => 'home', 'section' => 5, 'unit' => 'about-04-features', 'reason' => 'x'],
        ]);

        $this->assertSame([], $r['accepted']);
        $this->assertSame(['the first section of a page is never removed', 'the section holds the contact form', 'the section holds the contact form'], $this->reasons($r));
    }

    public function test_section_changes_are_capped_per_page()
    {
        $r = $this->validate([
            ['type' => 'remove_section', 'page' => 'home', 'section' => 2, 'reason' => 'x'],
            ['type' => 'remove_section', 'page' => 'home', 'section' => 3, 'reason' => 'x'],
            ['type' => 'remove_section', 'page' => 'home', 'section' => 4, 'reason' => 'x'],
        ]);

        $this->assertCount(2, $r['accepted']);
        $this->assertSame(['too many section changes on one page'], $this->reasons($r));
    }

    public function test_a_page_is_never_cut_too_short()
    {
        $facts = $this->facts();
        unset($facts['pages']['home']['sections'][5]);

        $r = ReviewFixes::validate(['fixes' => [
            ['type' => 'remove_section', 'page' => 'home', 'section' => 2, 'reason' => 'x'],
            ['type' => 'remove_section', 'page' => 'home', 'section' => 3, 'reason' => 'x'],
        ]], $facts);

        $this->assertCount(1, $r['accepted']);
        $this->assertSame(['the page would be left too short'], $this->reasons($r));
    }

    public function test_colour_changes_stay_within_bounds()
    {
        $r = $this->validate([
            ['type' => 'adjust_token', 'token' => 'accent-1', 'color' => '#2a7fd4', 'reason' => 'hue swap'],
            ['type' => 'adjust_token', 'token' => 'accent-1', 'color' => 'red', 'reason' => 'x'],
            ['type' => 'adjust_token', 'token' => 'contrast', 'color' => '#8a817c', 'reason' => 'lighter text'],
        ]);

        $this->assertSame([], $r['accepted']);
        $this->assertSame(['hue changes too much', 'not a #rrggbb colour', 'lightness changes too much'], $this->reasons($r));
    }

    public function test_a_colour_change_may_not_lower_contrast()
    {
        // A mid tone that reads on neither the page colour nor the text colour.
        $r = $this->validate([['type' => 'adjust_token', 'token' => 'accent-1', 'color' => '#9a6b3f', 'reason' => 'x']]);

        $this->assertSame(['lowers contrast'], $this->reasons($r));
    }

    public function test_shortened_copy_must_be_shorter_plain_text_and_not_a_linked_slot()
    {
        $r = $this->validate([
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't4', 'text' => str_repeat('Long words here ', 6) . 'more', 'reason' => 'x'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't12', 'text' => 'Short <b>bold</b>', 'reason' => 'x'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't9', 'text' => 'Our services', 'reason' => 'x'],
        ]);

        $this->assertSame([], $r['accepted']);
        $this->assertSame(['not clearly shorter than the original', 'not plain text', 'the slot holds links or markup'], $this->reasons($r));
    }

    public function test_shortened_copy_loses_its_dashes()
    {
        $r = $this->validate([['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't4', 'text' => 'Fast work — fair prices', 'reason' => 'x']]);

        $this->assertStringNotContainsString('—', $r['accepted'][0]['text']);
    }

    public function test_at_most_max_fixes_and_one_per_target()
    {
        $r = $this->validate([
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i2', 'image' => 'neutral', 'reason' => 'x'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i2', 'image' => 'studio-desk', 'reason' => 'x'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i5', 'image' => 'neutral', 'reason' => 'x'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't4', 'text' => 'Short', 'reason' => 'x'],
        ], 2);

        $this->assertCount(2, $r['accepted']);
        $this->assertSame(['a second fix for the same target', 'over the limit of 2 fixes'], $this->reasons($r));
    }

    public function test_a_slot_fix_inside_a_removed_section_is_dropped()
    {
        $r = $this->validate([
            ['type' => 'remove_section', 'page' => 'home', 'section' => 3, 'reason' => 'x'],
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i5', 'image' => 'neutral', 'reason' => 'x'],
            ['type' => 'shorten_copy', 'page' => 'home', 'slot' => 't12', 'text' => 'Short line', 'reason' => 'x'],
        ]);

        $this->assertSame(['remove_section'], array_column($r['accepted'], 'type'));
        $this->assertSame(['inside a section another fix changes', 'inside a section another fix changes'], $this->reasons($r));
    }

    public function test_a_neutral_placeholder_is_never_swapped_for_a_kit_photo()
    {
        $r = $this->validate([['type' => 'swap_image', 'page' => 'home', 'slot' => 'i7', 'image' => 'studio-desk', 'reason' => 'looks unfinished']]);

        $this->assertSame(['the build left a neutral placeholder there on purpose'], $this->reasons($r));
    }

    public function test_schema_offers_only_the_fixed_list()
    {
        $this->assertSame(ReviewFixes::FIXED_TYPES, ReviewFixes::schema()['properties']['fixes']['items']['properties']['type']['enum']);
    }
}
