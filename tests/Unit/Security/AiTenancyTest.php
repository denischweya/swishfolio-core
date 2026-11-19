<?php

namespace SwishfolioCore\Tests\Unit\Security;

use SwishfolioCore\AI\Providers\AbstractAIProvider;
use SwishfolioCore\Services\SvgSupport;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\ConfirmToken;
use SwishfolioCore\Tools\Journal\JournalEntry;
use SwishfolioCore\Tools\Journal\RunAccess;
use SwishfolioCore\Tools\Read\ListContentTool;

/**
 * Regression tests for the AI security review: one customer, or one user on a
 * customer's site, must not reach what is not theirs through the AI.
 */
class AiTenancyTest extends ToolsTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['sfcore_test_wp']['caps'], $GLOBALS['sfcore_test_user_id']);

        parent::tearDown();
    }

    public function test_list_content_hides_posts_the_user_cannot_read()
    {
        $this->givenPost(1, ['post_title' => 'Mine', 'post_status' => 'draft']);
        $this->givenPost(2, ['post_title' => 'Owner salary notes', 'post_status' => 'private']);

        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts', 'read_post:1'];

        $result = (new ListContentTool())->execute(['status' => 'any'], $this->context());

        $this->assertSame(['Mine'], array_column($result['items'], 'title'));
    }

    public function test_a_non_manager_only_sees_their_own_runs()
    {
        $GLOBALS['sfcore_test_user_id']    = 7;
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $mine   = [new JournalEntry(['run_id' => 'a', 'user_id' => 7])];
        $theirs = [new JournalEntry(['run_id' => 'b', 'user_id' => 1])];

        $this->assertSame(7, RunAccess::scopeUserId());
        $this->assertSame($mine, RunAccess::filter($mine));
        $this->assertSame([], RunAccess::filter($theirs));
    }

    public function test_a_site_manager_sees_every_run()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['manage_options'];

        $theirs = [new JournalEntry(['run_id' => 'b', 'user_id' => 1])];

        $this->assertSame(0, RunAccess::scopeUserId());
        $this->assertSame($theirs, RunAccess::filter($theirs));
    }

    public function test_the_audit_query_filters_by_user()
    {
        $this->repository->append(new JournalEntry(['run_id' => 'a', 'user_id' => 7, 'seq' => 1]));
        $this->repository->append(new JournalEntry(['run_id' => 'b', 'user_id' => 1, 'seq' => 1]));

        $this->assertSame(['a'], array_column($this->repository->recentRuns(20, 0, 7), 'run_id'));
        $this->assertCount(2, $this->repository->recentRuns(20, 0, 0));
    }

    public function test_an_approval_token_from_one_site_is_refused_on_another()
    {
        $calls = [['name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]];

        $GLOBALS['sfcore_test_wp']['blogId'] = 2;
        $token = ConfirmToken::issue($calls, 7);

        $this->assertTrue(ConfirmToken::verify($token, $calls, 7));

        $GLOBALS['sfcore_test_wp']['blogId'] = 3;

        $this->assertFalse(ConfirmToken::verify($token, $calls, 7));
    }

    public function test_provider_errors_never_echo_a_key()
    {
        $message = AbstractAIProvider::redactSecrets(
            'Incorrect API key provided: sk-proj-abc123XYZ****wxyz. You can find your API key at https://platform.openai.com.'
        );

        $this->assertStringNotContainsString('sk-proj', $message);
        $this->assertStringNotContainsString('wxyz', $message);
        $this->assertStringContainsString('Incorrect API key provided', $message);
    }

    /**
     * @dataProvider svgAttacks
     */
    public function test_svg_uploads_lose_anything_that_can_run($svg)
    {
        $clean = $this->sanitizeSvg($svg);

        if (false === $clean) {
            $this->assertFalse($clean);

            return;
        }

        foreach (['onload', 'script', 'javascript', 'foreignObject', 'iframe', 'embed', 'evil'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $clean);
        }
    }

    public function svgAttacks()
    {
        return [
            'no-space handler'  => ['<svg/onload=alert(1)>'],
            'handler attribute' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>'],
            'self-closing script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script href="//evil/x.js"/></svg>'],
            'namespaced script' => ['<svg xmlns="http://www.w3.org/2000/svg"><h:script xmlns:h="http://www.w3.org/1999/xhtml">alert(1)</h:script></svg>'],
            'encoded js href'   => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="&#x6A;avascript:alert(1)"/></svg>'],
            'animated href'     => ['<svg xmlns="http://www.w3.org/2000/svg"><g><animate attributeName="href" values="javascript:alert(1)"/></g></svg>'],
            'foreign object'    => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe xmlns="http://www.w3.org/1999/xhtml"/></foreignObject><embed/></svg>'],
            'external refs'     => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(//evil/x.css);</style><use href="https://evil/x.svg#a"/><image href="https://evil/p.png"/></svg>'],
            'entities'          => ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>'],
        ];
    }

    public function test_an_ordinary_logo_survives_sanitising()
    {
        $logo = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#f00"/></linearGradient></defs><style>.a{fill:url(#g)}</style><path class="a" d="M0 0h10v10z"/><use href="#g"/></svg>';

        $this->assertSame($logo, $this->sanitizeSvg('<?xml version="1.0"?><!-- logo -->' . $logo));
    }

    public function test_svg_sanitising_also_covers_sideloads()
    {
        $source = (string) file_get_contents(SFCORE_PLUGIN_DIR . 'src/Services/SvgSupport.php');

        $this->assertStringContainsString("'wp_handle_sideload_prefilter'", $source, 'AI uploads are sideloads.');
    }

    /**
     * @param string $svg Markup.
     *
     * @return string|false
     */
    private function sanitizeSvg($svg)
    {
        $class  = new \ReflectionClass(SvgSupport::class);
        $method = $class->getMethod('sanitize_svg_content');
        $method->setAccessible(true);

        return $method->invoke($class->newInstanceWithoutConstructor(), $svg);
    }
}
