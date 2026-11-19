<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\REST\CustomiseController;

/**
 * The owner's description (business.brief) is required on the build routes and
 * has room for a detailed answer.
 */
class BriefRequiredTest extends TestCase
{
    public function testEmptyOrWhitespaceBriefIsRejected()
    {
        foreach (['', '   ', "\n\t "] as $text) {
            $error = CustomiseController::requireBrief(CustomiseController::cleanBrief(['business' => ['name' => 'Acme', 'brief' => $text]]));
            $this->assertInstanceOf(\WP_Error::class, $error);
            $this->assertSame('sfcore_brief_required', $error->get_error_code());
            $this->assertSame(400, $error->get_error_data()['status']);
        }

        $this->assertInstanceOf(\WP_Error::class, CustomiseController::requireBrief(CustomiseController::cleanBrief([])));
    }

    public function testFilledBriefPasses()
    {
        $this->assertNull(CustomiseController::requireBrief(CustomiseController::cleanBrief(['business' => ['brief' => 'A bakery in Leeds.']])));
    }

    public function testLongBriefKeepsItsRoomOtherFieldsStayCapped()
    {
        $long  = str_repeat('a', 7000);
        $clean = CustomiseController::cleanBrief(['business' => ['brief' => $long, 'tagline' => $long]]);

        $this->assertSame(CustomiseController::BRIEF_MAX, mb_strlen($clean['business']['brief']));
        $this->assertGreaterThanOrEqual(4000, CustomiseController::BRIEF_MAX);
        $this->assertSame(3000, mb_strlen($clean['business']['tagline']));
    }
}
