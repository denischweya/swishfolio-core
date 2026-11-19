<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Onboarding\DemoScan;
use SwishfolioCore\AI\Onboarding\KitBranding;
use SwishfolioCore\AI\Onboarding\KitForms;
use SwishfolioCore\AI\Onboarding\KitImages;
use SwishfolioCore\AI\Onboarding\KitLinks;

/**
 * The pure parts of finishing a kit build: working forms, links, logo,
 * photo placements and the leftover demo scan.
 */
class KitFinishingTest extends TestCase
{
    const FORM = '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->' . "\n"
        . "<!-- wp:html -->\n"
        . '<form class="ll-form ll-card" action="#" method="post" onsubmit="return false">'
        . '<div class="ll-form__head"><p class="ll-form__title">Book a tasting</p></div>'
        . '<div class="ll-form__row"><label>Your name<input type="text" name="name" placeholder="Ada"></label><label>Email<input type="email" name="email"></label></div>'
        . '<fieldset><legend>I am a…</legend><label><input type="radio" name="k" checked><span>Café owner</span></label><label><input type="radio" name="k"><span>Home brewer</span></label></fieldset>'
        . '<label><span>Phone <i>(optional)</i></span><input type="tel" name="tel"></label>'
        . '<label>What can we help with?<textarea name="m"></textarea></label>'
        . '<div class="ll-form__foot"><button type="submit">Send it <svg viewBox="0 0 1 1"><path d="M0 0"/></svg></button><p>This demo form does not send.</p></div>'
        . "</form>\n<!-- /wp:html -->";

    public function test_a_demo_form_becomes_the_working_form_block()
    {
        $this->assertTrue(KitForms::swappable(self::FORM));

        $out = KitForms::swap(self::FORM, ['recipient' => 'owner@cafe.test', 'formId' => 'swish-form-abc-1']);

        $this->assertStringNotContainsString('<form', $out);
        $this->assertStringNotContainsString('does not send', $out);
        $this->assertStringContainsString('<p>Intro</p>', $out);
        $this->assertStringContainsString('class="wp-block-group ll-form ll-card swish-kit-form"', $out);
        $this->assertStringContainsString('Book a tasting', $out);

        preg_match('#<!-- wp:swishfolio-core/swish-form (\{.*\}) /-->#', $out, $m);
        $attrs = json_decode($m[1], true);

        $this->assertSame('swish-form-abc-1', $attrs['formId']);
        $this->assertSame('owner@cafe.test', $attrs['recipientEmail']);
        $this->assertSame('Send it', $attrs['submitButtonText']);

        $byLabel = [];
        foreach ($attrs['fields'] as $field) {
            $byLabel[$field['label']] = $field;
        }

        $this->assertSame(['Your name', 'Email', 'I am a…', 'Phone', 'What can we help with?'], array_keys($byLabel));
        $this->assertSame('select', $byLabel['I am a…']['type']);
        $this->assertSame(['Café owner', 'Home brewer'], array_column($byLabel['I am a…']['options'], 'label'));
        $this->assertTrue($byLabel['Email']['required']);
        $this->assertFalse($byLabel['Phone']['required']);
        $this->assertSame('half', $byLabel['Your name']['width']);
        $this->assertSame('textarea', $byLabel['What can we help with?']['type']);
    }

    public function test_a_form_without_an_email_field_gets_one_and_html_beside_a_form_is_not_swapped()
    {
        $form  = "<!-- wp:html -->\n<form><label>Name<input type=\"text\"></label><button>Go</button></form>\n<!-- /wp:html -->";
        $out   = KitForms::swap($form, []);
        preg_match('#swish-form (\{.*\}) /-->#', $out, $m);
        $types = array_column(json_decode($m[1], true)['fields'], 'type');

        $this->assertSame(['text', 'email'], $types);

        $mixed = "<!-- wp:html -->\n<div>x</div><form><label>Name<input></label></form>\n<!-- /wp:html -->";
        $this->assertFalse(KitForms::swappable($mixed));
        $this->assertSame($mixed, KitForms::swap($mixed, []));
    }

    public function test_form_units_tangled_with_a_script_are_not_offered()
    {
        $kit = Kit::fromDir(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits/logistics');

        $this->assertNotNull($kit);
        $this->assertNull($kit->section('home-01-hero'));
        $this->assertArrayNotHasKey('quote-01-page-header', $kit->units('section'));
        $this->assertNotNull($kit->get('home-01-hero'));

        $hvac = Kit::fromDir(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits/hvac');
        $this->assertNotNull($hvac->section('contact-01-page-header'));
    }

    private function links()
    {
        $pages = [
            ['key' => 'home', 'title' => 'Home', 'slug' => 'home', 'postId' => 10, 'url' => 'https://cafe.test/home/'],
            ['key' => 'menu', 'title' => 'Our coffee', 'slug' => 'coffee', 'postId' => 11, 'url' => 'https://cafe.test/coffee/'],
            ['key' => 'contact', 'title' => 'Visit us', 'slug' => 'visit', 'postId' => 12, 'url' => 'https://cafe.test/visit/'],
        ];
        $kitPages = [['key' => 'home', 'slug' => 'home'], ['key' => 'menu', 'slug' => 'menu'], ['key' => 'reservations', 'slug' => 'reservations']];

        return new KitLinks('https://cafe.test/', $pages, 'home', ['menu', 'contact'], $kitPages);
    }

    public function test_template_paths_resolve_to_generated_pages()
    {
        $links = $this->links();

        $this->assertSame('https://cafe.test/', $links->resolve('/')['url']);
        $this->assertSame(11, $links->resolve('/menu/')['postId']);       // Same key.
        $this->assertSame(12, $links->resolve('/reservations/')['postId']); // Dropped page: last in menu.
        $this->assertSame(12, $links->resolve('/visit/')['postId']);      // Generated slug.
        $this->assertSame('https://cafe.test/', $links->resolve('/projects/low-tide/')['url']);

        $this->assertNull($links->internalPath('https://other.test/menu/'));
        $this->assertNull($links->internalPath('mailto:a@b.test'));
        $this->assertNull($links->internalPath('https://cafe.test/wp-content/uploads/a.jpg'));
        $this->assertNull($links->internalPath('#top'));
        $this->assertSame('/menu/', $links->internalPath('/menu/'));
    }

    public function test_parts_and_menus_are_relinked()
    {
        $links  = $this->links();
        $header = '<a href="/">Home</a><a class="btn" href="https://cafe.test/reservations/">Book</a><a href="https://instagram.com/x">IG</a>'
            . '<!-- wp:navigation-link {"label":"Menu","url":"https://cafe.test/menu/","kind":"custom"} /-->';

        $out = $links->remap($header);

        $this->assertStringContainsString('href="https://cafe.test/visit/">Book', $out);
        $this->assertStringContainsString('href="https://instagram.com/x"', $out);
        $this->assertStringContainsString('"url":"https://cafe.test/coffee/"', $out);
        $this->assertStringContainsString('"id":11', $out);

        $menu = $links->menu();
        $this->assertSame(2, substr_count($menu, 'wp:navigation-link'));
        $this->assertStringContainsString('"label":"Our coffee"', $menu);

        $footer = '<!-- wp:navigation-link {"label":"Story","url":"/story/","kind":"custom"} /-->' . "\n"
            . '<!-- wp:navigation-link {"label":"Private","url":"/private/","kind":"custom"} /-->' . "\n"
            . '<!-- wp:navigation-link {"label":"Instagram","url":"https://instagram.com/x","kind":"custom"} /-->';
        $remapped = $links->remapMenu($footer);
        // Both unknown template pages go home, so the second collapses.
        $this->assertSame(2, substr_count($remapped, 'wp:navigation-link'));
        $this->assertStringContainsString('Instagram', $remapped);
    }

    public function test_logo_replaces_the_kit_wordmark()
    {
        $header = '<!-- wp:html --><a class="lu-brand" href="/" aria-label="Lumbre home"><svg></svg><span>Lumbre</span></a><!-- /wp:html -->';

        $this->assertTrue(KitBranding::hasBrand($header));
        $out = KitBranding::withLogo($header, 'https://cafe.test/logo.png', 'Bean & Co');

        $this->assertStringContainsString('aria-label="Bean &amp; Co home"', $out);
        $this->assertStringContainsString('<img class="swish-logo" src="https://cafe.test/logo.png" alt="Bean &amp; Co"', $out);
        $this->assertStringNotContainsString('Lumbre', $out);
        $this->assertStringContainsString('class="lu-brand swish-has-logo"', $out);

        $title = '<!-- wp:site-title {"level":0} /-->';
        $this->assertStringContainsString('wp:site-logo', KitBranding::withLogo($title, 'https://cafe.test/l.png', 'X'));
    }

    public function test_photo_placements_are_checked()
    {
        $slots  = [
            ['ref' => '10:i1', 'postId' => 10, 'slot' => 'i1'],
            ['ref' => '10:i2', 'postId' => 10, 'slot' => 'i2'],
            ['ref' => '11:i1', 'postId' => 11, 'slot' => 'i1'],
            ['ref' => '12:i1', 'postId' => 12, 'slot' => 'i1'],
        ];
        $photos = [['id' => 5, 'note' => 'Our bar'], ['id' => 6, 'note' => '']];

        $placed = KitImages::placements(['placements' => [
            ['slot' => '10:i1', 'attachmentId' => 5, 'alt' => 'The bar'],
            ['slot' => '10:i2', 'attachmentId' => 5, 'alt' => 'Twice on one page'],
            ['slot' => '10:i1', 'attachmentId' => 6, 'alt' => 'Slot taken'],
            ['slot' => '11:i1', 'attachmentId' => 5, 'alt' => 'Second use'],
            ['slot' => '12:i1', 'attachmentId' => 5, 'alt' => 'Third use'],
            ['slot' => '11:i9', 'attachmentId' => 6, 'alt' => 'No such slot'],
            ['slot' => '12:i1', 'attachmentId' => 99, 'alt' => 'Not theirs'],
        ]], $slots, $photos);

        $this->assertSame([['10', 'i1', 5], ['11', 'i1', 5]], array_map(static function ($p) {
            return [(string) $p['postId'], $p['slot'], $p['attachmentId']];
        }, $placed));

        $fallback = KitImages::fallback($slots, $photos);
        $this->assertSame([5, 6], array_column($fallback, 'attachmentId'));
        $this->assertSame(['i1', 'i2'], array_column($fallback, 'slot'));
    }

    public function test_demo_scan_finds_and_fixes_leftovers()
    {
        $scan = new DemoScan([
            'business' => 'Ashcombe Vale LLP',
            'tagline'  => 'Solicitors for business',
            'phones'   => ['+44 117 496 0120'],
            'emails'   => ['enquiries@ashcombevale.example'],
            'places'   => ['Bristol', 'London'],
            'people'   => ['Helen Achebe'],
        ]);
        $scan->exceptOwner("Lagos Physio\nWe are in London");

        $html = '<p>Call Ashcombe Vale on <a href="tel:+441174960120">+44 117 496 0120</a> or '
            . '<a href="mailto:enquiries@ashcombevale.example">enquiries@ashcombevale.example</a>.</p>'
            . '<img src="https://x.test/ashcombe-vale.jpg" alt="Helen Achebe in Bristol"><p class="av-london">Our London clinic</p>';

        $kinds = array_column($scan->scan(DemoScan::visible($html)), 'kind', 'text');
        $this->assertSame('business', $kinds['Ashcombe Vale']);
        $this->assertSame('person', $kinds['Helen Achebe']);
        $this->assertSame('place', $kinds['Bristol']);
        $this->assertArrayNotHasKey('London', $kinds, 'The owner is in London too.');

        $fixed = $scan->fix($html, ['business' => 'Lagos Physio', 'phone' => '+234 801 234 5678', 'email' => 'hi@physio.test']);

        $this->assertStringContainsString('Call Lagos Physio on <a href="tel:+2348012345678">+234 801 234 5678</a>', $fixed);
        $this->assertStringContainsString('href="mailto:hi@physio.test">hi@physio.test</a>', $fixed);
        $this->assertStringContainsString('src="https://x.test/ashcombe-vale.jpg"', $fixed, 'URLs are never touched.');

        $left = array_column($scan->scan(DemoScan::visible($fixed)), 'text');
        $this->assertSame(['Bristol', 'Helen Achebe'], array_values(array_intersect(['Bristol', 'Helen Achebe'], $left)));
        $this->assertNotContains('Ashcombe Vale', $left);

        $this->assertNotEmpty($scan->scan(DemoScan::visible('<p>This demo form does not send.</p>')));
    }

    public function test_the_owners_own_email_is_not_a_leftover_even_at_example_com()
    {
        $scan = (new DemoScan(['business' => 'Lumbre']))->exceptOwner("Kofi House\nhello@example.com");

        $this->assertSame([], $scan->scan('Write to hello@example.com'));
        $this->assertSame('phrase', $scan->scan('Write to someone@example.com')[0]['kind']);
    }

    public function test_a_role_form_mock_up_becomes_a_working_form()
    {
        $kit    = Kit::fromDir(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits/restaurant');
        $markup = $kit->markup('reservations-01-page-header');

        $this->assertTrue(KitForms::swappable($markup));
        $this->assertNotNull($kit->section('reservations-01-page-header'));

        $out = KitForms::swap($markup, ['recipient' => 'owner@kofi.test', 'formId' => 'swish-form-abc']);
        $this->assertStringNotContainsString('role="form"', $out);
        $this->assertStringContainsString('<!-- wp:swishfolio-core/swish-form', $out);

        preg_match('#<!-- wp:swishfolio-core/swish-form (\{.*?\}) /-->#s', $out, $m);
        $attrs  = json_decode($m[1], true);
        $labels = array_column($attrs['fields'], 'label');
        $this->assertContains('Email', $labels);
        $this->assertSame(['Date', 'Party size', 'Time', 'Where', 'Name', 'Email', 'Notes'], $labels);
        $this->assertSame('5.30', $attrs['fields'][2]['options'][0]['label']);
        $this->assertSame('half', $attrs['fields'][4]['width']);
        $this->assertSame('textarea', $attrs['fields'][6]['type']);
        $this->assertSame('Request this table', $attrs['submitButtonText']);
    }

    public function test_a_newsletter_sign_up_that_sends_nothing_is_removed_from_a_part()
    {
        $whole = "<!-- wp:paragraph -->\n<p>Keep</p>\n<!-- /wp:paragraph -->\n<!-- wp:html -->\n"
            . '<form class="ll-remind" action="#" onsubmit="return false"><p class="ll-remind__t">Get reminders</p>'
            . '<div class="ll-remind__row"><input type="email" aria-label="Email address"><button type="submit">Subscribe</button></div></form>'
            . "\n<!-- /wp:html -->\n<!-- wp:paragraph -->\n<p>After</p>\n<!-- /wp:paragraph -->";
        $out = KitForms::dropNewsletters($whole);
        $this->assertStringNotContainsString('<form', $out);
        $this->assertStringNotContainsString('wp:html', $out);
        $this->assertStringContainsString('<p>Keep</p>', $out);
        $this->assertStringContainsString('<p>After</p>', $out);

        $nested = "<!-- wp:html -->\n<div class=\"od-foot__grid\"><div class=\"od-foot__col\"><p>Havenstraat 41</p></div>"
            . '<div class="od-foot__col od-foot__news"><p class="od-foot__label">Odd notes</p><form action="#"><input type="email"><button>Sign up</button></form></div></div>'
            . "\n<!-- /wp:html -->";
        $out = KitForms::dropNewsletters($nested);
        $this->assertStringContainsString('Havenstraat 41', $out);
        $this->assertStringNotContainsString('Odd notes', $out);
        $this->assertStringNotContainsString('<form', $out);

        // A real contact form (more than one field) is not a newsletter.
        $contact = "<!-- wp:html -->\n<form><input type=\"text\"><input type=\"email\"><button>Send</button></form>\n<!-- /wp:html -->";
        $this->assertSame($contact, KitForms::dropNewsletters($contact));
    }

    public function test_a_choice_built_from_nested_elements_keeps_its_words_apart()
    {
        $form = KitForms::parse('<form><fieldset><legend>When suits you?</legend>'
            . '<label><input type="radio" name="w"><span>Morning<small>before 9am</small></span></label></fieldset>'
            . '<label>Email<input type="email"></label><button>Send</button></form>');

        $this->assertSame('Morning before 9am', $form['fields'][0]['options'][0]['label']);
    }
}
