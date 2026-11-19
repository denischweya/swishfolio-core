<?php
/**
 * Turns a kit's demo form into the plugin's working form block.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Template sections tagged needs:form carry a hand-written demo <form> that
 * submits nowhere ("This demo form does not send"). On a real site that is a
 * broken promise, so generation swaps it for swishfolio-core/swish-form, a
 * dynamic block that posts to /swishfolio/v1/forms/submit and emails the owner.
 *
 * The swap is only done where it is safe: a core/html block whose whole
 * content is one <form>. The fields are read from that form after the plan
 * has rewritten its labels and placeholders for the owner, so the working
 * form asks what the owner's version asked. The form's own heading lines
 * (the paragraphs before the first field) are kept above it, and both sit in
 * a group carrying the form's classes, so the kit's card styling still frames
 * it. Everything else in the section is left byte for byte.
 *
 * Pure: no WordPress calls beyond serialize_block_attributes() when present.
 */
class KitForms
{
    const BLOCK      = 'swishfolio-core/swish-form';
    const MAX_FIELDS = 12;

    /** One core/html block whose whole content is a form. */
    // A real <form>, or a block that is nothing but a role="form" mock-up
    // (the restaurant kit's booking card has a type="button" that sends nothing).
    const PATTERN = '#<!-- wp:html -->\s*(<form\b.*?</form>|<div\b[^>]*\brole="form"[^>]*>.*?)\s*<!-- /wp:html -->#s';

    /**
     * @param string $markup Section markup.
     *
     * @return bool Whether the section holds a demo form that can be swapped.
     */
    public static function swappable($markup)
    {
        return (bool) preg_match(self::PATTERN, (string) $markup);
    }

    /**
     * @param string $markup Section markup (copy already filled in).
     * @param array  $opts   recipient (email), formId (string prefix), subject, success.
     *
     * @return string The same markup with each swappable demo form replaced.
     */
    public static function swap($markup, array $opts = [])
    {
        $n = 0;

        return (string) preg_replace_callback(self::PATTERN, static function ($m) use ($opts, &$n) {
            $form = self::parse($m[1]);

            if ($form === null) {
                return $m[0];
            }

            $n++;
            $id    = (isset($opts['formId']) ? (string) $opts['formId'] : 'swish-form') . ($n > 1 ? '-' . $n : '');
            $attrs = [
                'formId'           => preg_replace('/[^a-z0-9-]/', '', strtolower($id)),
                'formType'         => 'contact',
                'fields'           => $form['fields'],
                'submitButtonText' => $form['submit'] !== '' ? $form['submit'] : 'Send',
                'successMessage'   => isset($opts['success']) ? (string) $opts['success'] : 'Thank you. Your message has been sent and we will reply soon.',
                'recipientEmail'   => isset($opts['recipient']) ? (string) $opts['recipient'] : '',
                'emailSubject'     => isset($opts['subject']) ? (string) $opts['subject'] : 'New enquiry from your website',
            ];

            $classes = trim($form['class'] . ' swish-kit-form');
            $out     = '<!-- wp:group ' . self::json(['className' => $classes, 'layout' => ['type' => 'default']]) . ' -->' . "\n"
                . '<div class="wp-block-group ' . self::attr($classes) . '">';

            if ($form['head'] !== '') {
                $out .= "\n<!-- wp:html -->\n" . $form['head'] . "\n<!-- /wp:html -->\n";
            }

            $out .= "\n<!-- wp:" . self::BLOCK . ' ' . self::json($attrs) . " /-->\n";
            $out .= "</div>\n<!-- /wp:group -->";

            return $out;
        }, (string) $markup);
    }

    /**
     * Template headers and footers carry a newsletter sign-up that sends
     * nothing (action="#"). The owner has no newsletter, so the sign-up goes:
     * the form and the "news" element around it, or the whole html block
     * when nothing else is left in it.
     *
     * @param string $markup Part markup.
     *
     * @return string
     */
    public static function dropNewsletters($markup)
    {
        return (string) preg_replace_callback('#<!-- wp:html -->\s*(.*?)\s*<!-- /wp:html -->\n?#s', static function ($m) {
            if (stripos($m[1], '<form') === false || ! class_exists('\DOMDocument')) {
                return $m[0];
            }

            $doc  = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $ok   = $doc->loadHTML('<?xml encoding="UTF-8"><div id="sfk-root">' . $m[1] . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if (! $ok) {
                return $m[0];
            }

            $xpath   = new \DOMXPath($doc);
            $root    = $xpath->query('//*[@id="sfk-root"]')->item(0);
            $dropped = false;

            foreach (iterator_to_array($xpath->query('//form')) as $form) {
                $fields = $xpath->query('.//input[not(@type="hidden" or @type="submit")]|.//select|.//textarea', $form);
                $email  = $fields->length === 1 && strtolower($fields->item(0)->getAttribute('type')) === 'email';
                if (! $email) {
                    continue;
                }

                $node = $form;
                while ($node->parentNode !== $root && $node->parentNode instanceof \DOMElement
                    && preg_match('/news|remind|subscri|letter|signup/i', $node->parentNode->getAttribute('class'))) {
                    $node = $node->parentNode;
                }
                $node->parentNode->removeChild($node);
                $dropped = true;
            }

            if (! $dropped) {
                return $m[0];
            }
            if (trim($root->textContent) === '' && $xpath->query('.//img|.//svg', $root)->length === 0) {
                return '';
            }

            $html = '';
            foreach ($root->childNodes as $child) {
                $html .= $doc->saveHTML($child);
            }

            return "<!-- wp:html -->\n" . trim($html) . "\n<!-- /wp:html -->\n";
        }, (string) $markup);
    }

    /**
     * Read a demo form: its classes, the heading markup before the first
     * field, the fields and the submit button's words.
     *
     * @param string $html One <form> element.
     *
     * @return array|null {class, head, fields, submit}
     */
    public static function parse($html)
    {
        if (! class_exists('\DOMDocument')) {
            return null;
        }

        $doc  = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok   = $doc->loadHTML('<?xml encoding="UTF-8"><div id="sfk-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            return null;
        }

        $xpath = new \DOMXPath($doc);
        $forms = $doc->getElementsByTagName('form');

        if ($forms->length === 0) {
            $forms = $xpath->query('//*[@role="form"]');
        }

        if ($forms->length !== 1) {
            return null;
        }

        $form   = $forms->item(0);
        $head   = '';
        $fields = [];
        $used   = [];

        // Heading lines: leading children with no form controls in them.
        foreach (iterator_to_array($form->childNodes) as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($xpath->query('.//input|.//select|.//textarea|.//button', $child)->length > 0 || in_array(strtolower($child->tagName), ['input', 'select', 'textarea', 'button', 'label', 'fieldset'], true)) {
                break;
            }
            $head .= $doc->saveHTML($child);
        }

        // In the order the demo shows them: grouped choices (radio / checkbox
        // chips) become one select; every other label with a control is a field.
        foreach ($xpath->query('.//fieldset | .//label[not(ancestor::fieldset)]', $form) as $node) {
            if (strtolower($node->tagName) !== 'fieldset') {
                $field = self::single($node, $form, $xpath, $used);
                if ($field !== null) {
                    $fields[] = $field;
                }
                continue;
            }

            $fieldset = $node;
            $legend  = $xpath->query('./legend', $fieldset)->item(0);
            $label   = $legend ? self::text(self::words($legend)) : '';
            $options = [];

            foreach ($xpath->query('.//label', $fieldset) as $choice) {
                $input = $xpath->query('.//input', $choice)->item(0);
                if (! $input || ! in_array(strtolower($input->getAttribute('type')), ['radio', 'checkbox'], true)) {
                    continue;
                }
                $value = self::text(self::labelText($choice));
                if ($value !== '') {
                    $options[] = ['label' => $value, 'value' => $value];
                }
            }

            if ($options !== [] && $label !== '') {
                $fields[] = self::field($label, 'select', '', false, $used, $options);
            }
        }

        if ($fields === []) {
            return null;
        }


        // A contact form the owner cannot answer is useless: always ask for an email.
        $hasEmail = false;
        foreach ($fields as $field) {
            $hasEmail = $hasEmail || $field['type'] === 'email';
        }
        if (! $hasEmail) {
            $fields[] = self::field('Email', 'email', '', true, $used);
        }

        $button = $xpath->query('.//button', $form)->item(0);
        $submit = '';
        if ($button) {
            foreach ($xpath->query('.//svg', $button) as $svg) {
                $svg->parentNode->removeChild($svg);
            }
            $submit = self::text($button->textContent);
            $submit = trim(preg_replace('/[\x{2192}\x{2197}\x{2794}\x{27F6}>]+$/u', '', $submit));
        }

        return [
            'class'  => self::classes($form->getAttribute('class')),
            'head'   => trim($head),
            'fields' => array_slice($fields, 0, self::MAX_FIELDS),
            'submit' => mb_substr($submit, 0, 60),
        ];
    }

    /**
     * One label and its control: nested in the label, or named by for="".
     *
     * @return array|null Field.
     */
    private static function single(\DOMElement $labelEl, \DOMElement $form, \DOMXPath $xpath, array &$used)
    {
        $control = $xpath->query('.//input|.//select|.//textarea', $labelEl)->item(0);
        $for     = $labelEl->getAttribute('for');

        if (! $control && $for !== '' && preg_match('/^[A-Za-z][\w-]*$/', $for)) {
            $control = $xpath->query('.//*[@id="' . $for . '"][self::input or self::select or self::textarea]', $form)->item(0);
        }
        if (! $control) {
            return null;
        }

        $tag   = strtolower($control->tagName);
        $type  = $tag === 'input' ? strtolower($control->getAttribute('type') ?: 'text') : $tag;
        $label = self::labelText($labelEl);
        $optional = (bool) preg_match('/\(?\boptional\b\)?/i', $label);
        $label = self::text(preg_replace('/\(?\boptional\b\)?/i', '', $label));

        if (in_array($type, ['hidden', 'submit', 'button', 'radio', 'image', 'reset', 'file'], true)) {
            return null;
        }

        $map  = ['email' => 'email', 'tel' => 'tel', 'textarea' => 'textarea', 'select' => 'select', 'checkbox' => 'checkbox'];
        $kind = isset($map[$type]) ? $map[$type] : 'text';
        $options = [];

        if ($kind === 'select') {
            foreach ($xpath->query('.//option', $control) as $option) {
                $value = self::text($option->textContent);
                if ($value !== '') {
                    $options[] = ['label' => $value, 'value' => $value];
                }
            }
        }

        if ($label === '') {
            $label = self::text($control->getAttribute('aria-label') ?: $control->getAttribute('placeholder'));
        }
        if ($label === '') {
            return null;
        }

        $required = ! $optional && ($control->hasAttribute('required') || $kind === 'email');
        $field    = self::field($label, $kind, $kind === 'select' ? '' : self::text($control->getAttribute('placeholder')), $required, $used, $options);

        // Two fields side by side in the demo stay side by side.
        $row      = $labelEl->parentNode;
        $siblings = $row ? $xpath->query('./label', $row)->length : 0;
        // <div class="row"><div><label for>..</div><div><label for>..</div></div>
        if ($siblings < 2 && $for !== '' && $row && $row->parentNode instanceof \DOMElement && $row !== $form) {
            $row      = $row->parentNode;
            $siblings = $xpath->query('./*[label]', $row)->length;
        }
        if ($siblings >= 2 && $row !== $form && $kind !== 'textarea') {
            $field['width'] = 'half';
        }

        return $field;
    }

    /**
     * @param string $label    Visible label.
     * @param string $type     Field type.
     * @param string $ph       Placeholder.
     * @param bool   $required Required.
     * @param array  $used     Ids taken so far (by reference).
     * @param array  $options  Select options.
     *
     * @return array
     */
    private static function field($label, $type, $ph, $required, array &$used, array $options = [])
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(self::ascii($label))), '-');
        $base = $base === '' ? $type : substr($base, 0, 30);
        $id   = $base;
        $i    = 2;

        while (isset($used[$id])) {
            $id = $base . '-' . $i++;
        }
        $used[$id] = true;

        $field = [
            'id'          => $id,
            'type'        => $type,
            'label'       => mb_substr($label, 0, 80),
            'placeholder' => mb_substr($ph, 0, 120),
            'required'    => (bool) $required,
            'width'       => 'full',
        ];

        if ($options !== []) {
            $field['options'] = array_slice($options, 0, 12);
        }

        return $field;
    }

    /**
     * A label's own words, without the text of the control inside it.
     *
     * @param \DOMElement $label Label.
     *
     * @return string
     */
    private static function labelText(\DOMElement $label)
    {
        $out = '';

        foreach ($label->childNodes as $node) {
            if ($node instanceof \DOMElement && in_array(strtolower($node->tagName), ['input', 'select', 'textarea', 'svg'], true)) {
                continue;
            }
            // "Daytime<small>9am to 5pm</small>" reads as two words, not one.
            $out .= ' ' . ($node instanceof \DOMElement ? self::words($node) : $node->textContent);
        }

        return $out;
    }

    /**
     * Text of an element with a space between its child elements' words.
     *
     * @param \DOMElement $el Element.
     *
     * @return string
     */
    private static function words(\DOMElement $el)
    {
        $out = '';
        foreach ($el->childNodes as $node) {
            $out .= $node instanceof \DOMElement ? ' ' . self::words($node) . ' ' : $node->textContent;
        }

        return $out;
    }

    /**
     * Visible words: no step numbering ("01 ·"), no ellipsis tail, one line.
     *
     * @param string $text Raw text.
     *
     * @return string
     */
    private static function text($text)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        // "01 · Name", "<b>02</b>Party size"; never a time such as "5.30".
        $text = preg_replace('/^(?:\d{1,2}\s*[\x{00B7}.:)\-]\s*(?!\d)|\d{2}\s+(?=\p{Lu}))/u', '', $text);

        return trim((string) $text);
    }

    /**
     * @param string $text Text.
     *
     * @return string
     */
    private static function ascii($text)
    {
        if (function_exists('remove_accents')) {
            return remove_accents($text);
        }
        $out = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : $text; // phpcs:ignore WordPress.PHP.NoSilencedErrors

        return $out === false ? $text : $out;
    }

    /**
     * @param string $class Class attribute.
     *
     * @return string Safe class list.
     */
    private static function classes($class)
    {
        $out = [];
        foreach (preg_split('/\s+/', (string) $class) as $name) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,60}$/', $name)) {
                $out[] = $name;
            }
        }

        return implode(' ', $out);
    }

    /**
     * @param string $value Attribute value.
     *
     * @return string
     */
    private static function attr($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Block comment JSON the way the editor writes it.
     *
     * @param array $attrs Attributes.
     *
     * @return string
     */
    private static function json(array $attrs)
    {
        if (function_exists('serialize_block_attributes')) {
            return serialize_block_attributes($attrs);
        }

        $json = (string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str_replace(['--', '<', '>', '&', '\\"'], ['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022'], $json);
    }
}
