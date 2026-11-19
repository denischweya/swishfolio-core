<?php

namespace SwishfolioCore\Tests\Unit\Tools;

require_once __DIR__ . '/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Tools\Write\TrustedMarkup;

class TrustedMarkupVouchTest extends TestCase
{
    public function test_only_the_exact_vouched_body_is_trusted()
    {
        $body = '<!-- wp:html --><svg viewBox="0 0 1 1"></svg><!-- /wp:html -->';

        TrustedMarkup::vouch($body);

        $this->assertTrue(TrustedMarkup::isVouched($body));
        $this->assertFalse(TrustedMarkup::isVouched($body . ' '));
    }

    public function test_the_insert_filter_keeps_a_vouched_body_unfiltered()
    {
        $body = '<!-- wp:html --><form onsubmit="return false"></form><!-- /wp:html -->';

        TrustedMarkup::vouch($body);

        $data = TrustedMarkup::filter(['post_content' => 'kses output', 'post_type' => 'page'], [], ['post_content' => $body]);

        $this->assertSame($body, $data['post_content']);
    }
}
