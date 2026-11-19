<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\ProjectsPolicy;
use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Staged\SiteOutline;
use SwishfolioCore\AI\Generation\Staged\StagedAssembly;

/**
 * Whether a site shows projects: the outline's decision, what it removes
 * (sections, frame-only pages, menu entries) and the sample project entries.
 * Uses the shipped designer kit, whose Work page is header + projects + CTA.
 */
class ProjectsPolicyTest extends GenerationTestCase
{
    private function designer()
    {
        return $this->shippedLibrary()->kit('designer');
    }

    private function designerContext()
    {
        return new PlanContext($this->designer(), $this->registry(), $this->tokens(), 'site');
    }

    private function outline(array $projects = null)
    {
        $outline = [
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'kit'           => 'designer',
            'summary'       => 'A studio site.',
            'site'          => ['title' => 'Ink', 'tagline' => '', 'frontPage' => 'home', 'navigation' => ['work', 'about', 'contact']],
            'facts'         => ['businessName' => 'Ink', 'voice' => '', 'audience' => '', 'keyMessages' => [], 'contact' => ''],
            'pages'         => [
                ['key' => 'home', 'title' => 'Home', 'slug' => 'home', 'purpose' => '', 'sections' => [
                    ['pattern' => 'home-01-hero', 'brief' => ''],
                    ['pattern' => 'work-04-portfolio', 'brief' => ''],
                ]],
                ['key' => 'work', 'title' => 'Work', 'slug' => 'work', 'purpose' => '', 'sections' => [
                    ['pattern' => 'work-01-page-header', 'brief' => ''],
                    ['pattern' => 'work-04-portfolio', 'brief' => ''],
                    ['pattern' => 'work-06-cta', 'brief' => ''],
                ]],
                ['key' => 'contact', 'title' => 'Contact', 'slug' => 'contact', 'purpose' => '', 'sections' => [
                    ['pattern' => 'contact-01-page-header', 'brief' => ''],
                    ['block' => ProjectsPolicy::BLOCK, 'brief' => ''],
                    ['pattern' => 'about-02-about', 'brief' => ''],
                ]],
            ],
        ];

        if ($projects !== null) {
            $outline['projects'] = $projects;
        }

        return $outline;
    }

    public function test_the_designer_kit_has_the_units_this_test_relies_on()
    {
        $kit = $this->designer();

        $this->assertTrue(ProjectsPolicy::unitShowsProjects($kit->get('work-04-portfolio')));
        $this->assertFalse(ProjectsPolicy::unitShowsProjects($kit->get('work-06-cta')));
        $this->assertNotNull($kit->get('about-02-about'));
        $this->assertCount(6, $kit->getProjectCovers(), 'The extractor ships the demo project covers.');
        $this->assertCount(6, $kit->getDemoProjectTitles());
    }

    public function test_a_business_without_projects_loses_the_sections_the_frame_page_and_its_menu_link()
    {
        $clean = SiteOutline::normalise($this->outline(['show' => false, 'items' => [['title' => 'X', 'excerpt' => '', 'body' => [], 'category' => '']]]), $this->designer(), $this->designerContext());

        $this->assertSame(['home', 'contact'], array_column($clean['pages'], 'key'), 'Work only framed the projects list.');
        $this->assertSame(['home-01-hero'], array_column($clean['pages'][0]['sections'], 'pattern'), 'The front page stays, without its projects section.');
        $this->assertSame(['contact-01-page-header', 'about-02-about'], array_column($clean['pages'][1]['sections'], 'pattern'), 'A page with other content keeps it; the portfolio block goes.');
        $this->assertSame(['contact'], $clean['site']['navigation']);
        $this->assertSame(['show' => false, 'items' => []], $clean['projects']);
        $this->assertFalse(ProjectsPolicy::planShowsProjects($clean, $this->designer()));
    }

    public function test_a_business_with_projects_keeps_them_and_its_sample_projects()
    {
        $clean = SiteOutline::normalise($this->outline(['show' => true, 'items' => [
            ['title' => 'Harbour signage', 'excerpt' => 'Wayfinding for a ferry port.', 'body' => ['The brief.', 'What we did.'], 'category' => 'Signage'],
        ]]), $this->designer(), $this->designerContext());

        $this->assertSame(['home', 'work', 'contact'], array_column($clean['pages'], 'key'));
        $this->assertTrue($clean['projects']['show']);
        $this->assertSame('Harbour signage', $clean['projects']['items'][0]['title']);
        $this->assertTrue(ProjectsPolicy::planShowsProjects($clean, $this->designer()));
    }

    public function test_an_outline_without_a_decision_behaves_as_before()
    {
        $clean = SiteOutline::normalise($this->outline(), $this->designer(), $this->designerContext());

        $this->assertCount(3, $clean['pages']);
        $this->assertSame(['show' => true, 'items' => []], $clean['projects']);
    }

    public function test_a_projects_page_never_takes_the_project_archive_slug()
    {
        $outline                     = $this->outline();
        $outline['pages'][1]['slug'] = 'projects';

        $clean = SiteOutline::normalise($outline, $this->designer(), $this->designerContext());

        $this->assertSame('our-work', $clean['pages'][1]['slug']);
    }

    public function test_items_are_cleaned_deduplicated_and_capped()
    {
        $raw = [];
        for ($i = 0; $i < 9; $i++) {
            $raw[] = ['title' => ' Project <b>' . $i . '</b> ', 'excerpt' => "One\nline", 'body' => "First para.\n\nSecond para.", 'category' => 'Brand'];
        }
        $raw[] = ['title' => 'Project 0'];
        $raw[] = ['title' => ''];
        $raw[] = 'junk';

        $items = ProjectsPolicy::items($raw);

        $this->assertCount(ProjectsPolicy::MAX_ITEMS, $items);
        $this->assertSame('Project 0', $items[0]['title']);
        $this->assertSame('One line', $items[0]['excerpt']);
        $this->assertSame(['First para.', 'Second para.'], $items[0]['body']);
        $this->assertSame(['show' => false, 'items' => []], ProjectsPolicy::decision(['show' => false, 'items' => $raw]));
    }

    public function test_the_post_check_takes_projects_sections_a_page_call_added_when_the_site_shows_none()
    {
        // As written by the page calls: the outline had no projects, a page put the portfolio block back.
        $plan = [
            'site'     => ['frontPage' => 'home', 'navigation' => ['home', 'work', 'contact']],
            'projects' => ['show' => false, 'items' => []],
            'pages'    => [
                ['key' => 'home', 'sections' => [['pattern' => 'home-01-hero'], ['block' => ProjectsPolicy::BLOCK, 'attributes' => []]]],
                ['key' => 'work', 'sections' => [['pattern' => 'work-01-page-header'], ['pattern' => 'work-04-portfolio'], ['pattern' => 'work-06-cta']]],
                ['key' => 'contact', 'sections' => [['pattern' => 'contact-01-page-header'], ['pattern' => 'about-02-about']]],
            ],
        ];

        $out = ProjectsPolicy::enforce($plan, $this->designer());

        $this->assertSame(['home', 'contact'], array_column($out['plan']['pages'], 'key'), 'A page that only framed projects goes.');
        $this->assertSame([['pattern' => 'home-01-hero']], $out['plan']['pages'][0]['sections'], 'The portfolio block (an empty "No projects found.") goes.');
        $this->assertSame(['home', 'contact'], $out['plan']['site']['navigation']);
        $this->assertSame(['work'], $out['droppedPages']);
        $this->assertSame(4, $out['removed']);
    }

    public function test_the_post_check_leaves_a_site_that_shows_projects_or_has_no_decision()
    {
        $plan = ['site' => ['frontPage' => 'home'], 'pages' => [['key' => 'home', 'sections' => [['block' => ProjectsPolicy::BLOCK]]]]];

        $this->assertSame($plan, ProjectsPolicy::enforce($plan, $this->designer())['plan']);
        $plan['projects'] = ['show' => true, 'items' => []];
        $this->assertSame($plan, ProjectsPolicy::enforce($plan, $this->designer())['plan']);

        // A front page of nothing but projects keeps them rather than vanish.
        $plan['projects'] = ['show' => false, 'items' => []];
        $this->assertSame($plan['pages'], ProjectsPolicy::enforce($plan, $this->designer())['plan']['pages']);
    }

    public function test_placeholders_are_neutral_and_between_three_and_six()
    {
        $this->assertCount(3, ProjectsPolicy::placeholders(1));
        $this->assertCount(6, ProjectsPolicy::placeholders(40));
        $this->assertSame('Sample project one', ProjectsPolicy::placeholders(3)[0]['title']);
    }

    public function test_the_site_plan_carries_the_decision_through_validation()
    {
        $outline = SiteOutline::normalise($this->outline(['show' => true, 'items' => [
            ['title' => 'Harbour signage', 'excerpt' => 'Wayfinding.', 'body' => ['One.'], 'category' => 'Signage'],
        ]]), $this->designer(), $this->designerContext());

        $plan = StagedAssembly::sitePlan($outline, ['home' => ['key' => 'home', 'title' => 'Home', 'slug' => 'home', 'sections' => [['pattern' => 'home-01-hero']]]], 'designer');
        $this->assertSame($outline['projects'], $plan['projects']);

        $checked = (new PlanEnvelopeStage())->process($plan, $this->designerContext());
        $this->assertSame($outline['projects'], $checked['projects']);
        $this->assertSame($checked, (new PlanEnvelopeStage())->process($checked, $this->designerContext()), 'A validated plan validates to itself.');

        unset($plan['projects']);
        $this->assertArrayNotHasKey('projects', (new PlanEnvelopeStage())->process($plan, $this->designerContext()));
    }

    public function test_frame_only_pages_need_more_than_a_header_and_a_cta()
    {
        $kit      = $this->designer();
        $sections = [['pattern' => 'work-01-page-header'], ['pattern' => 'work-04-portfolio'], ['pattern' => 'work-06-cta']];

        $this->assertNull(ProjectsPolicy::withoutProjects($sections, $kit, false));
        $this->assertSame([['pattern' => 'work-01-page-header'], ['pattern' => 'work-06-cta']], ProjectsPolicy::withoutProjects($sections, $kit, true), 'Never the front page.');
        $this->assertSame([['pattern' => 'work-01-page-header']], ProjectsPolicy::withoutProjects([['pattern' => 'work-01-page-header']], $kit, false), 'A page without projects is untouched.');
    }

    public function test_unlink_takes_links_to_dropped_pages_out()
    {
        $url    = 'https://ink.test/work/';
        $markup = '<!-- wp:navigation-link {"label":"Work","id":12,"url":"https://ink.test/work/"} /-->'
            . "\n" . '<!-- wp:navigation-link {"label":"About","id":13,"url":"https://ink.test/about/"} /-->'
            . '<p><a href="https://ink.test/work/">Work</a><br><a href="https://ink.test/about/">About</a></p>'
            . '<p><a href="https://ink.test/about/">About</a><br><a href="https://ink.test/work">Work</a></p>'
            . '<a class="wp-block-button__link" href="https://ink.test/work/">See the work</a>';

        $out = ProjectsPolicy::unlink($markup, [$url], [12], 'https://ink.test/contact/');

        $this->assertStringNotContainsString('"Work"', $out);
        $this->assertStringContainsString('"About"', $out);
        $this->assertStringContainsString('<p><a href="https://ink.test/about/">About</a></p><p><a href="https://ink.test/about/">About</a></p>', $out);
        $this->assertStringContainsString('href="https://ink.test/contact/">See the work</a>', $out);
        $this->assertSame($markup, ProjectsPolicy::unlink($markup, [], [], '/'));
    }

    public function test_markup_detection()
    {
        $this->assertTrue(ProjectsPolicy::markupShowsProjects('<!-- wp:swishfolio-core/portfolio {"columns":3} /-->'));
        $this->assertFalse(ProjectsPolicy::markupShowsProjects('<!-- wp:paragraph --><p>Portfolio</p><!-- /wp:paragraph -->'));
    }
}
