<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Generation\SocialProof;

/**
 * The owner's clients, testimonial and numbers from the wizard, and the
 * marked placeholders that stand in for what they left out.
 */
class SocialProofTest extends TestCase
{
    public function test_wizard_fields_are_split_cleaned_deduplicated_and_capped()
    {
        $names = implode(', ', array_map(static function ($i) {
            return 'Brand ' . $i;
        }, range(1, 20)));

        $proof = SocialProof::fromBusiness([
            'clients'       => "Glow <b>Skincare</b>\nBloom Studio; glow skincare, Client name, " . $names,
            'testimonial'   => '  "She tripled our reach — in three months."  ',
            'testimonialBy' => 'Ada Obi, founder of Glow',
            'numbers'       => "8 years, 1,200 posts\n00+ projects; 2M views",
        ]);

        $this->assertSame(['Glow Skincare', 'Bloom Studio', 'Brand 1'], array_slice($proof['clients'], 0, 3), 'Tags go, duplicates (any case) and placeholders are dropped.');
        $this->assertCount(SocialProof::MAX_CLIENTS, $proof['clients']);
        $this->assertSame([['quote' => 'She tripled our reach, in three months.', 'by' => 'Ada Obi, founder of Glow']], $proof['testimonials'], 'Quote marks trimmed, no long dash.');
        $this->assertSame(['8 years', '1,200 posts', '2M views'], $proof['numbers'], 'A thousands comma stays; the placeholder number goes.');
    }

    public function test_long_values_are_capped_and_empty_fields_give_an_empty_proof()
    {
        $proof = SocialProof::clean(['clients' => [str_repeat('x', 200)], 'testimonials' => [['quote' => str_repeat('y', 2000), 'by' => '']], 'numbers' => '']);

        $this->assertSame(SocialProof::CLIENT_CHARS, mb_strlen($proof['clients'][0]));
        $this->assertSame(SocialProof::QUOTE_CHARS, mb_strlen($proof['testimonials'][0]['quote']));
        $this->assertTrue(SocialProof::isEmpty(SocialProof::fromBusiness(['clients' => ' , ', 'testimonial' => '', 'numbers' => "\n"])));
        $this->assertTrue(SocialProof::isEmpty(SocialProof::clean('junk')));
        $this->assertSame('', SocialProof::asText(SocialProof::clean([])));
    }

    public function test_the_prompt_facts_carry_the_owner_items_and_the_exact_placeholders()
    {
        $facts = SocialProof::forPrompt(['clients' => 'Glow, Bloom']);

        $this->assertSame(['Glow', 'Bloom'], $facts['clients']);
        $this->assertSame([], $facts['testimonials']);
        $this->assertSame(SocialProof::CLIENT, $facts['placeholders']['clientName']);
        $this->assertSame(SocialProof::QUOTE, $facts['placeholders']['quote']);
        $this->assertSame(SocialProof::NUMBER, $facts['placeholders']['number']);
        $this->assertStringContainsString('Glow, Bloom', SocialProof::asText(SocialProof::clean(['clients' => 'Glow, Bloom'])));
    }

    public function test_placeholders_left_on_a_page_are_found_by_kind()
    {
        $text = 'Brands I have grown with Client name Client name Client name Your brand name '
            . 'Your client’s words go here: a sentence about the result you delivered. Client name, role '
            . '00+ projects delivered 00% retention. Open 9:00 to 17:00, since 2000.';

        $this->assertSame(['clients' => 3, 'testimonial' => 1, 'numbers' => 2], SocialProof::find($text));
        $this->assertSame([], SocialProof::find('Glow Skincare said our reach grew 300%. Open 10:00.'), 'Real copy and times are not placeholders.');
        $this->assertStringContainsString('Home', SocialProof::label('clients', 'Home'));
        $this->assertStringNotContainsString("\u{2014}", SocialProof::label('numbers', 'Home'));
    }

    public function test_only_logos_testimonials_and_stats_are_proof_roles()
    {
        $this->assertTrue(SocialProof::isProofRole('logos'));
        $this->assertTrue(SocialProof::isProofRole('stats'));
        $this->assertFalse(SocialProof::isProofRole('portfolio'));
    }
}
