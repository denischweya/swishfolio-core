<?php

namespace SwishfolioCore\Tests\Unit\Tools\Write;

use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\Rollback;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\Write\SetAltTextTool;
use SwishfolioCore\Tools\Write\UploadMediaTool;

/**
 * Fetching a URL a model supplied is the one genuinely dangerous operation in
 * the tool layer, so most of these tests are about what it refuses.
 */
class MediaToolsTest extends ToolsTestCase
{
    /** @var array[] Every sideload the tool attempted. */
    private $attempts = [];

    private function uploadTool($result = null)
    {
        $this->attempts = [];

        return new UploadMediaTool(
            $this->journal,
            function ($url, $title) use ($result) {
                $this->attempts[] = ['url' => $url, 'title' => $title];

                if (null !== $result) {
                    return $result;
                }

                $id = 500;

                $GLOBALS['sfcore_test_wp']['posts'][$id] = [
                    'ID'             => $id,
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post_title'     => '' !== $title ? $title : 'photo.jpg',
                    'post_mime_type' => 'image/jpeg',
                    'post_content'   => '',
                    'post_name'      => 'photo',
                    'post_author'    => 1,
                ];

                return ['id' => $id];
            }
        );
    }

    private function givenAttachment($id, array $fields = [])
    {
        $GLOBALS['sfcore_test_wp']['posts'][(int) $id] = array_merge(
            [
                'ID'             => (int) $id,
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_title'     => 'Existing image',
                'post_mime_type' => 'image/png',
                'post_content'   => '',
                'post_name'      => 'existing-image',
                'post_author'    => 1,
            ],
            $fields
        );
    }

    public function test_an_image_lands_in_the_library_with_its_alt_text()
    {
        $result = $this->uploadTool()->execute(
            ['url' => 'https://example.test/photo.jpg', 'alt' => 'A studio with north light'],
            $this->context()
        );

        $this->assertSame(500, $result['attachmentId']);
        $this->assertSame(
            'A studio with north light',
            $GLOBALS['sfcore_test_wp']['meta'][500]['_wp_attachment_image_alt']
        );
    }

    public function test_alt_text_is_required_by_the_schema_not_merely_encouraged()
    {
        $schema = $this->uploadTool()->getInputSchema();

        $this->assertContains('alt', $schema['required']);
    }

    public function test_a_non_http_url_is_refused_before_anything_is_fetched()
    {
        foreach (['file:///etc/passwd', 'ftp://example.test/x.jpg', 'javascript:alert(1)', 'notaurl'] as $url) {
            try {
                $this->uploadTool()->execute(['url' => $url, 'alt' => 'x'], $this->context());
                $this->fail('Accepted: ' . $url);
            } catch (ToolException $e) {
                $this->assertSame(ToolError::SCHEMA_INVALID, $e->getError()->getCode());
                $this->assertSame('url', $e->getError()->getDetails()['field']);
            }

            $this->assertSame([], $this->attempts, 'No fetch may be attempted for ' . $url);
        }
    }

    public function test_a_failed_fetch_is_reported_rather_than_producing_a_broken_attachment()
    {
        try {
            $this->uploadTool(['error' => 'Not an allowed file type.'])->execute(
                ['url' => 'https://example.test/evil.svg', 'alt' => 'x'],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::WRITE_FAILED, $e->getError()->getCode());
            $this->assertStringContainsString('allowed file type', $e->getMessage());
        }
    }

    public function test_uploading_needs_the_upload_files_capability()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts', 'edit_pages'];

        $this->expectException(ToolException::class);

        $this->uploadTool()->authorize(
            ['url' => 'https://example.test/photo.jpg', 'alt' => 'x'],
            $this->context()
        );
    }

    public function test_an_upload_needs_no_confirmation_because_visitors_cannot_see_it_yet()
    {
        $this->assertSame('write_draft', $this->uploadTool()->getSideEffect());
    }

    public function test_an_upload_is_reversible_as_both_the_attachment_and_its_alt_text()
    {
        $context = $this->context();

        $this->uploadTool()->execute(
            ['url' => 'https://example.test/photo.jpg', 'alt' => 'A studio'],
            $context
        );

        $targets = $this->repository->ofType(JournalEntry::TARGET_POST);
        $meta    = $this->repository->ofType(JournalEntry::TARGET_META);

        $this->assertSame(['created' => 500], $targets[0]->getPrior());
        $this->assertFalse($meta[0]->getPrior()['existed'], 'There was no prior alt text.');

        $result = (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertTrue($result['complete']);
        $this->assertSame('trash', $this->post(500)['post_status']);
        $this->assertArrayNotHasKey(
            '_wp_attachment_image_alt',
            $GLOBALS['sfcore_test_wp']['meta'][500],
            'Alt text that never existed must be deleted, not blanked.'
        );
    }

    public function test_alt_text_can_be_set_on_an_image_that_already_exists()
    {
        $this->givenAttachment(42);

        $result = (new SetAltTextTool($this->journal))->execute(
            ['attachmentId' => 42, 'alt' => 'Two people at a workbench'],
            $this->context()
        );

        $this->assertSame('Two people at a workbench', $result['alt']);
        $this->assertSame(
            'Two people at a workbench',
            $GLOBALS['sfcore_test_wp']['meta'][42]['_wp_attachment_image_alt']
        );
    }

    public function test_replacing_existing_alt_text_restores_the_old_value_on_rollback()
    {
        $this->givenAttachment(42);

        $GLOBALS['sfcore_test_wp']['meta'][42]['_wp_attachment_image_alt'] = 'Old description';

        $context = $this->context();

        (new SetAltTextTool($this->journal))->execute(
            ['attachmentId' => 42, 'alt' => 'New description'],
            $context
        );

        (new Rollback($this->repository))->revertRun($context->getRunId());

        $this->assertSame(
            'Old description',
            $GLOBALS['sfcore_test_wp']['meta'][42]['_wp_attachment_image_alt']
        );
    }

    public function test_setting_alt_text_on_something_that_is_not_an_attachment_is_refused()
    {
        $this->givenPost(10);

        try {
            (new SetAltTextTool($this->journal))->authorize(
                ['attachmentId' => 10, 'alt' => 'x'],
                $this->context()
            );
            $this->fail('Expected ToolException');
        } catch (ToolException $e) {
            $this->assertSame(ToolError::TARGET_MISSING, $e->getError()->getCode());
        }
    }

    public function test_alt_text_is_checked_against_that_specific_attachment()
    {
        $this->givenAttachment(42);
        $this->givenAttachment(43);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts', 'edit_post:42'];

        $tool = new SetAltTextTool($this->journal);

        $tool->authorize(['attachmentId' => 42, 'alt' => 'x'], $this->context());

        $this->expectException(ToolException::class);
        $tool->authorize(['attachmentId' => 43, 'alt' => 'x'], $this->context());
    }

    public function test_a_meta_row_is_separate_from_the_posts_own_row()
    {
        // A post revision does not carry meta, so restoring a revision would put
        // content back and leave the meta changed. Meta needs its own row.
        $this->givenAttachment(42);

        $context = $this->context();

        (new SetAltTextTool($this->journal))->execute(
            ['attachmentId' => 42, 'alt' => 'Something'],
            $context
        );

        $this->assertCount(1, $this->repository->ofType(JournalEntry::TARGET_META));
        $this->assertCount(0, $this->repository->ofType(JournalEntry::TARGET_POST));
    }
}
