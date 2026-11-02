<?php
/**
 * The AI audit log screen.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Admin;

use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\JournalRepositoryInterface;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolRunner;

/**
 * A submenu under Swish AI listing what the AI changed, grouped by run, with an
 * Undo button per run.
 *
 * The undo goes through the tool, not through Rollback directly: the tool is
 * what checks that this user may reverse these particular writes — a run that
 * touched options needs manage_options, a run that only drafted a page does
 * not. Calling Rollback here would skip that.
 *
 * Confirmation is a nonce rather than a ConfirmToken. The gate exists to prove
 * a human saw the plan; here the human is clicking a button on the page that
 * lists it, and the nonce is what proves the click came from that page.
 */
class AuditLogPage
{
    const SLUG         = 'swish-ai-audit';
    const ACTION       = 'sfcore_ai_rollback';
    const NONCE        = 'sfcore_ai_audit';
    const PRUNE_HOOK   = 'swishfolio_ai_prune_journal';
    const PRUNE_DAYS   = 90;

    /** @var JournalRepositoryInterface */
    private $journal;

    /** @var ToolRunner */
    private $runner;

    /**
     * @param JournalRepositoryInterface $journal Audit storage.
     * @param ToolRunner                 $runner  Pipeline, for the undo.
     */
    public function __construct(JournalRepositoryInterface $journal, ToolRunner $runner)
    {
        $this->journal = $journal;
        $this->runner  = $runner;
    }

    /**
     * @return void
     */
    public function boot()
    {
        add_action('admin_menu', [$this, 'registerMenu'], 11);
        add_action('admin_post_' . self::ACTION, [$this, 'handleRollback']);
        add_action(self::PRUNE_HOOK, [$this, 'prune']);
    }

    /**
     * @return void
     */
    public function registerMenu()
    {
        add_submenu_page(
            SwishAiMenu::MENU_SLUG,
            __('AI Audit Log', 'swishfolio-core'),
            __('Audit Log', 'swishfolio-core'),
            'edit_posts',
            self::SLUG,
            [$this, 'render']
        );
    }

    /**
     * Time-based expiry of aged rows, not editing of individual records: an
     * unbounded table on shared hosting is a real failure mode. Filter the
     * retention to 0 to keep everything.
     *
     * @return void
     */
    public function prune()
    {
        $days = (int) apply_filters('swishfolio_ai_journal_retention_days', self::PRUNE_DAYS);

        if ($days <= 0) {
            return;
        }

        $this->journal->pruneBefore(gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS)));
    }

    /**
     * @return void
     */
    public function handleRollback()
    {
        check_admin_referer(self::NONCE);

        $runId   = isset($_POST['run_id']) ? sanitize_key(wp_unslash($_POST['run_id'])) : '';
        $context = ToolContext::current();

        // The human clicked Undo on the page listing this run, which is the
        // approval the gate asks for; the nonce above is what proves it.
        $context->setConfirmed(true);

        try {
            $result = $this->runner->run('swishfolio/rollback-run', ['runId' => $runId], $context);
            $notice = $result['complete'] ? 'reverted' : 'partial';
        } catch (ToolException $e) {
            $notice = 'failed';
        }

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::SLUG, 'sfcore_notice' => $notice],
                admin_url('admin.php')
            )
        );

        exit;
    }

    /**
     * @return void
     */
    public function render()
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You cannot view the AI audit log.', 'swishfolio-core'));
        }

        $runs   = $this->journal->recentRuns(25);
        $notice = isset($_GET['sfcore_notice']) ? sanitize_key(wp_unslash($_GET['sfcore_notice'])) : '';

        echo '<div class="wrap"><h1>' . esc_html__('AI Audit Log', 'swishfolio-core') . '</h1>';
        echo '<p class="description">'
            . esc_html__('Every change the AI made to this site, newest first. Undoing a run restores overwritten pages from their revisions, puts settings back, and moves pages the run created to the trash.', 'swishfolio-core')
            . '</p>';

        $this->renderNotice($notice);

        if ($runs === []) {
            echo '<p>' . esc_html__('The AI has not changed anything on this site yet.', 'swishfolio-core') . '</p></div>';

            return;
        }

        foreach ($runs as $run) {
            $this->renderRun(isset($run['run_id']) ? (string) $run['run_id'] : '');
        }

        echo '</div>';
    }

    /**
     * @param string $notice Notice key.
     *
     * @return void
     */
    private function renderNotice($notice)
    {
        $messages = [
            'reverted' => [__('That run has been undone.', 'swishfolio-core'), 'success'],
            'partial'  => [__('Part of that run could not be undone. The details are listed against each change.', 'swishfolio-core'), 'warning'],
            'failed'   => [__('That run could not be undone.', 'swishfolio-core'), 'error'],
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        printf(
            '<div class="notice notice-%s"><p>%s</p></div>',
            esc_attr($messages[$notice][1]),
            esc_html($messages[$notice][0])
        );
    }

    /**
     * @param string $runId Run identifier.
     *
     * @return void
     */
    private function renderRun($runId)
    {
        $entries = $this->journal->findRun($runId);

        if ($entries === []) {
            return;
        }

        $reversible = false;
        $pending    = false;

        foreach ($entries as $entry) {
            if (JournalEntry::TARGET_CALL === $entry->getTargetType()) {
                continue;
            }

            $reversible = true;

            if (! $entry->isReverted()) {
                $pending = true;
            }
        }

        echo '<div class="card" style="max-width:none;margin-bottom:1em">';
        printf(
            '<h2 style="margin-bottom:.2em">%s</h2><p class="description">%s</p>',
            esc_html(sprintf(
                /* translators: %s: date and time. */
                __('Run of %s', 'swishfolio-core'),
                (string) $entries[0]->get('created_at')
            )),
            esc_html($runId)
        );

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('What happened', 'swishfolio-core') . '</th>';
        echo '<th>' . esc_html__('Tool', 'swishfolio-core') . '</th>';
        echo '<th>' . esc_html__('Target', 'swishfolio-core') . '</th>';
        echo '<th>' . esc_html__('Status', 'swishfolio-core') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $this->renderRow($entry);
        }

        echo '</tbody></table>';

        if ($reversible && $pending) {
            $this->renderUndoButton($runId);
        } elseif ($reversible) {
            echo '<p><em>' . esc_html__('This run has been undone.', 'swishfolio-core') . '</em></p>';
        }

        echo '</div>';
    }

    /**
     * @param JournalEntry $entry Row.
     *
     * @return void
     */
    private function renderRow(JournalEntry $entry)
    {
        $status = $entry->getRevertError() !== null
            ? sprintf(
                /* translators: %s: failure reason. */
                __('Could not undo: %s', 'swishfolio-core'),
                (string) $entry->getRevertError()
            )
            : ($entry->isReverted()
                ? __('Undone', 'swishfolio-core')
                : ($entry->getResult() === JournalEntry::RESULT_ERROR
                    ? sprintf(
                        /* translators: %s: error code. */
                        __('Failed (%s)', 'swishfolio-core'),
                        (string) $entry->getErrorCode()
                    )
                    : __('Applied', 'swishfolio-core')));

        $target = JournalEntry::TARGET_CALL === $entry->getTargetType()
            ? '—'
            : $entry->getTargetType() . ' ' . $entry->getTargetId();

        printf(
            '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
            esc_html('' === $entry->getSummary() ? '—' : $entry->getSummary()),
            esc_html($entry->getTool()),
            esc_html($target),
            esc_html($status)
        );
    }

    /**
     * @param string $runId Run identifier.
     *
     * @return void
     */
    private function renderUndoButton($runId)
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:.8em 0">';
        wp_nonce_field(self::NONCE);
        printf('<input type="hidden" name="action" value="%s" />', esc_attr(self::ACTION));
        printf('<input type="hidden" name="run_id" value="%s" />', esc_attr($runId));
        printf(
            '<button type="submit" class="button button-secondary">%s</button>',
            esc_html__('Undo this run', 'swishfolio-core')
        );
        echo '</form>';
    }
}
