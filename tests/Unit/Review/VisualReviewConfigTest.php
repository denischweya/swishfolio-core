<?php

namespace SwishfolioCore\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Review\VisualReviewConfig;

class VisualReviewConfigTest extends TestCase
{
    public function test_auto_is_on_only_when_chrome_is_found()
    {
        $found = new VisualReviewConfig([], static function ($p) {
            return $p === '/usr/bin/chromium';
        });
        $none  = new VisualReviewConfig([], static function () {
            return false;
        });

        $this->assertTrue($found->enabled());
        $this->assertSame('/usr/bin/chromium', $found->chromePath());
        $this->assertFalse($none->enabled());
    }

    public function test_off_wins_and_a_set_path_must_exist()
    {
        $off = new VisualReviewConfig(['mode' => 'off'], static function () {
            return true;
        });
        $bad = new VisualReviewConfig(['mode' => 'auto', 'chrome' => '/nope/chrome'], static function ($p) {
            return $p === '/usr/bin/chromium';
        });

        $this->assertFalse($off->enabled());
        $this->assertSame('', $bad->chromePath());
        $this->assertFalse($bad->enabled());
    }

    public function test_sanitize_refuses_anything_a_shell_could_read()
    {
        $s = VisualReviewConfig::sanitize(['vr_mode' => 'maybe', 'vr_chrome' => '/usr/bin/chromium; rm -rf /', 'vr_host_map' => '127.0.0.1']);

        $this->assertSame(['mode' => 'auto', 'chrome' => '', 'hostMap' => '127.0.0.1'], $s);
    }
}
