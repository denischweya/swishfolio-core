<?php

namespace SwishfolioCore\Tests\Unit\Tools\Write;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\Read\ListNavigationTool;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\Write\SetTermsTool;
use SwishfolioCore\Tools\Write\UpdateNavigationTool;
use SwishfolioCore\Tools\Write\UpdateSiteSettingsTool;

class BreadthToolsTest extends ToolsTestCase
{
    private function givenTerm($id, $name, $taxonomy)
    {
        $GLOBALS['sfcore_test_wp']['terms'][(int) $id] = [
            'name'     => $name,
            'slug'     => sanitize_title($name),
            'taxonomy' => $taxonomy,
        ];
    }

    private function givenMenu($id, array $items = [], $title = 'Main')
    {
        $links = \SwishfolioCore\Tools\Write\NavigationLinks::serialise($items);

        $GLOBALS['sfcore_test_wp']['posts'][(int) $id] = [
            'ID'           => (int) $id,
            'post_type'    => 'wp_navigation',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => sanitize_title($title),
            'post_content' => implode("\n\n", $links),
            'post_author'  => 1,
        ];
    }

    /* Terms ------------------------------------------------------------- */

    public function test_terms_are_resolved_from_names_so_the_model_needs_no_ids()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);
        $this->givenTerm(301, 'Web Design', 'sfcore_project_cat');

        $result = (new SetTermsTool($this->journal))->execute(
            ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['Web Design']],
            $this->context()
        );

        $this->assertSame([301], $result['termIds']);
        $this->assertSame([301], $GLOBALS['sfcore_test_wp']['objectTerms'][10]['sfcore_project_cat']);
    }

    public function test_an_unknown_term_is_reported_rather_than_invented()
    {
        // A model guessing at a taxonomy tree is how a portfolio ends up with
        // three spellings of one category and nobody notices.
        $this->givenPost(10, ['post_type' => 'sfcore_project']);

        try {
            (new SetTermsTool($this->journal))->execute(
                ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['Brand New']],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
            $this->assertSame(['Brand New'], $e->getError()->getDetails()['unknown']);
        }

        $this->assertSame([], $GLOBALS['sfcore_test_wp']['objectTerms']);
    }

    public function test_a_missing_term_is_created_only_when_explicitly_asked_for()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);

        $result = (new SetTermsTool($this->journal))->execute(
            [
                'postId'        => 10,
                'taxonomy'      => 'sfcore_project_cat',
                'terms'         => ['Brand New'],
                'createMissing' => true,
            ],
            $this->context()
        );

        $this->assertSame(['Brand New'], $result['created']);
        $this->assertCount(1, $result['termIds']);
    }

    public function test_creating_terms_needs_a_higher_capability_than_assigning_them()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts', 'edit_post:10'];

        $tool = new SetTermsTool($this->journal);

        $tool->authorize(
            ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['x']],
            $this->context()
        );

        $this->expectException(ToolException::class);

        $tool->authorize(
            ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['x'], 'createMissing' => true],
            $this->context()
        );
    }

    public function test_a_taxonomy_that_does_not_exist_is_refused()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);

        try {
            (new SetTermsTool($this->journal))->authorize(
                ['postId' => 10, 'taxonomy' => 'invented_tax', 'terms' => ['x']],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame('taxonomy', $e->getError()->getDetails()['field']);
        }
    }

    public function test_a_taxonomy_the_post_type_does_not_use_is_refused()
    {
        $this->givenPost(10, ['post_type' => 'page']);

        try {
            (new SetTermsTool($this->journal))->execute(
                ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['x']],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
        }
    }

    public function test_the_prior_term_set_is_restored_on_rollback()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);
        $this->givenTerm(301, 'Web Design', 'sfcore_project_cat');
        $this->givenTerm(302, 'Branding', 'sfcore_project_cat');

        $GLOBALS['sfcore_test_wp']['objectTerms'][10]['sfcore_project_cat'] = [302];

        $context = $this->context();

        (new SetTermsTool($this->journal))->execute(
            ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['Web Design']],
            $context
        );

        $this->assertSame([301], $GLOBALS['sfcore_test_wp']['objectTerms'][10]['sfcore_project_cat']);

        (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertSame(
            [302],
            $GLOBALS['sfcore_test_wp']['objectTerms'][10]['sfcore_project_cat'],
            'Prior terms are stored as IDs so a renamed term still restores.'
        );
    }

    public function test_terms_get_their_own_journal_row_not_the_posts()
    {
        $this->givenPost(10, ['post_type' => 'sfcore_project']);
        $this->givenTerm(301, 'Web Design', 'sfcore_project_cat');

        (new SetTermsTool($this->journal))->execute(
            ['postId' => 10, 'taxonomy' => 'sfcore_project_cat', 'terms' => ['Web Design']],
            $this->context()
        );

        $this->assertCount(1, $this->repository->ofType(JournalEntry::TARGET_TERMS));
        $this->assertCount(0, $this->repository->ofType(JournalEntry::TARGET_POST));
    }

    /* Site settings ----------------------------------------------------- */

    public function test_the_title_and_tagline_are_written_and_reversible()
    {
        $GLOBALS['sfcore_test_options']['blogname']        = 'Old name';
        $GLOBALS['sfcore_test_options']['blogdescription'] = 'Old tagline';

        $context = $this->context(true);

        (new UpdateSiteSettingsTool($this->journal))->execute(
            ['title' => 'Acme Studio', 'tagline' => 'Design and build'],
            $context
        );

        $this->assertSame('Acme Studio', $GLOBALS['sfcore_test_options']['blogname']);

        (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertSame('Old name', $GLOBALS['sfcore_test_options']['blogname']);
        $this->assertSame('Old tagline', $GLOBALS['sfcore_test_options']['blogdescription']);
    }

    public function test_only_the_two_allowlisted_settings_can_be_named()
    {
        // The options table holds the home URL, the admin email and the active
        // theme. A free-text option name here would be privilege escalation.
        $schema = (new UpdateSiteSettingsTool($this->journal))->getInputSchema();

        $this->assertSame(['title', 'tagline'], array_keys($schema['properties']));
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_an_omitted_field_is_left_alone_rather_than_blanked()
    {
        $GLOBALS['sfcore_test_options']['blogdescription'] = 'Keep me';

        (new UpdateSiteSettingsTool($this->journal))->execute(
            ['title' => 'Acme'],
            $this->context(true)
        );

        $this->assertSame('Keep me', $GLOBALS['sfcore_test_options']['blogdescription']);
    }

    public function test_site_settings_need_manage_options_and_confirmation()
    {
        $tool = new UpdateSiteSettingsTool($this->journal);

        $this->assertSame('write_published', $tool->getSideEffect());

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_pages'];

        $this->expectException(ToolException::class);
        $tool->authorize(['title' => 'Acme'], $this->context());
    }

    /* Navigation -------------------------------------------------------- */

    public function test_an_existing_menu_can_be_read_back_as_labels_and_urls()
    {
        $this->givenMenu(80, [
            ['label' => 'Home', 'url' => 'https://example.test/', 'postId' => 10],
            ['label' => 'About', 'url' => 'https://example.test/about/'],
        ]);

        $result = (new ListNavigationTool())->execute([], $this->context());

        $this->assertSame(80, $result['menus'][0]['menuId']);
        $this->assertSame('Home', $result['menus'][0]['items'][0]['label']);
        $this->assertSame(10, $result['menus'][0]['items'][0]['postId']);
        $this->assertSame('About', $result['menus'][0]['items'][1]['label']);
        $this->assertArrayNotHasKey('postId', $result['menus'][0]['items'][1]);
    }

    public function test_an_existing_menu_is_rewritten_rather_than_duplicated()
    {
        $this->givenMenu(80, [['label' => 'Home', 'url' => 'https://example.test/']]);

        $before = count($GLOBALS['sfcore_test_wp']['posts']);

        (new UpdateNavigationTool($this->journal))->execute(
            [
                'menuId' => 80,
                'items'  => [
                    ['label' => 'Home', 'url' => 'https://example.test/'],
                    ['label' => 'Work', 'url' => 'https://example.test/work/', 'postId' => 11],
                ],
            ],
            $this->context(true)
        );

        $this->assertSame($before, count($GLOBALS['sfcore_test_wp']['posts']), 'No second menu.');
        $this->assertStringContainsString('Work', $this->post(80)['post_content']);
        $this->assertStringContainsString('"kind":"post-type"', $this->post(80)['post_content']);
    }

    public function test_rewriting_a_menu_is_reversible_from_its_revision()
    {
        $this->givenMenu(80, [['label' => 'Home', 'url' => 'https://example.test/']]);

        $original = $this->post(80)['post_content'];
        $context  = $this->context(true);

        (new UpdateNavigationTool($this->journal))->execute(
            ['menuId' => 80, 'items' => [['label' => 'Different', 'url' => 'https://example.test/x/']]],
            $context
        );

        (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertSame($original, $this->post(80)['post_content']);
    }

    public function test_updating_something_that_is_not_a_menu_is_refused()
    {
        $this->givenPost(10);

        try {
            (new UpdateNavigationTool($this->journal))->authorize(
                ['menuId' => 10, 'items' => []],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
            $this->assertSame('menuId', $e->getError()->getDetails()['field']);
        }
    }

    public function test_an_item_without_a_label_or_url_is_dropped_by_both_navigation_tools()
    {
        $this->givenMenu(80, [['label' => 'Home', 'url' => 'https://example.test/']]);

        $result = (new UpdateNavigationTool($this->journal))->execute(
            [
                'menuId' => 80,
                'items'  => [
                    ['label' => 'Keep', 'url' => 'https://example.test/keep/'],
                    ['label' => '', 'url' => 'https://example.test/drop/'],
                ],
            ],
            $this->context(true)
        );

        $this->assertSame(1, $result['items']);
        $this->assertStringNotContainsString('drop', $this->post(80)['post_content']);
    }
}
