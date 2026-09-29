# AI Block Editing — Implementation Plan (Part 1: PHP Spine)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the server-side spine for selection-based AI block editing — capability registry, design-token resolution, action schema, five-stage validator, provider abstraction, and REST endpoints — with no editor UI yet.

**Architecture:** A hand-authored `ai.json` manifest per block reduces ~366 raw block attributes to a promptable, semantic surface, and doubles as the validator's allowlist. A provider abstraction modelled on the existing `src/Email/` pattern calls an AI over `wp_remote_post()` with a JSON Schema. Returned actions are validated in five all-or-nothing stages and returned to the caller. **PHP never mutates post content** — application happens client-side in Part 2.

**Tech Stack:** PHP 7.4-compatible source, PHPUnit 9.6, Composer (dev-only deps), WordPress 6.7+, existing `SwishfolioCore\` PSR-4 autoload.

**Spec:** `docs/superpowers/specs/2026-09-29-ai-block-editing-design.md`

**Detail level:** Tasks 1–5 carry full implementation code. Tasks 6–17 carry exact interfaces (names, signatures, return types) and full test requirements, but sketch rather than spell out the implementation bodies. That is sufficient for an implementer working in sequence with the earlier tasks in context; if these are handed to context-free subagents, expand the implementation steps first.

## Global Constraints

- **Source must run on PHP 7.4.** No enums, `match`, constructor promotion, union types, `readonly`, nullsafe `?->`, `str_contains`/`str_starts_with`. Tests execute on PHP 8.2 inside DDEV or PHP 7.4 on the host — the source floor is 7.4 either way (`Requires PHP: 7.4`, `composer.json` `"php": ">=7.4"`).
- **Zero runtime Composer dependencies.** Everything added to `composer.json` goes in `require-dev`. The plugin keeps its `spl_autoload_register` PSR-4 fallback and must function with no `vendor/` directory present.
- **No automated test may perform network I/O.** Providers are exercised through fakes and stubbed `wp_remote_post`.
- **Namespace** `SwishfolioCore\` → `src/`. **Text domain** `swishfolio-core`. **Constants** `SFCORE_VERSION`, `SFCORE_PLUGIN_DIR`, `SFCORE_PLUGIN_URL`, `SFCORE_PLUGIN_FILE`.
- **Prefixes:** PHP filters/actions `sfcore_` or `swishfolio_ai_`; options `swishfolio_ai_*`; REST namespace `swishfolio/v1` (existing, do not create a new one).
- **AI-facing colour, spacing and font-size values are theme.json slugs, never literal CSS values.** A slug absent from the active `theme.json` is a validation failure, not a fallback.
- **Validation is all-or-nothing.** Any stage failure rejects the entire AI response.
- Match existing code style: 4-space indent, `declare(strict_types=1)` is **not** used in this codebase — do not introduce it.

---

## File Structure

| Path | Responsibility |
|---|---|
| `src/Blocks/BlockCapability.php` | Immutable value object for one parsed manifest |
| `src/Blocks/FieldSpec.php` | One editable field's type + constraints |
| `src/Blocks/BlockRegistry.php` | Discovery, core manifests, filter, transient cache |
| `src/AI/Manifests/core/*.json` | The 4 core-block content-tier manifests |
| `src/AI/Context/TokenResolver.php` | theme.json preset sets; slug ↔ storage value |
| `src/AI/Context/ContextAssembler.php` | Builds the AI payload from a selection |
| `src/AI/Prompt/PromptManager.php` | Loads and composes prompt files |
| `src/Actions/ActionSchema.php` | Emits the JSON Schema sent to the provider |
| `src/Actions/Stages/*.php` | The five validation stages |
| `src/Actions/ActionValidator.php` | Runs stages, all-or-nothing |
| `src/AI/Contracts/AIProviderInterface.php` | Provider contract |
| `src/AI/Providers/AbstractAIProvider.php` | `wp_remote_post` wrapper, retry, error mapping |
| `src/AI/Providers/OpenAIProvider.php` | OpenAI + OpenAI-compatible |
| `src/AI/{AIRequest,AIResponse,AIManager}.php` | Request/response objects and orchestration |
| `src/AI/UsageLogger.php` | Rolling usage counters in one option |
| `src/AI/Settings.php` | Encrypted provider settings |
| `src/REST/AIController.php` | `/ai/edit`, `/ai/test-provider` |
| `src/REST/SettingsController.php` | `/ai/settings` GET + POST |
| `src/Admin/AISettingsPage.php` | Admin submenu |
| `blocks/*/ai.json` | 23 hand-authored manifests |

---

### Task 1: Test harness

**Files:**
- Modify: `composer.json`, `.gitignore`, `.distignore`
- Create: `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/stubs/wp-functions.php`, `tests/Unit/SmokeTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `vendor/bin/phpunit` runnable against `tests/Unit/`; WP function stubs `sanitize_text_field`, `esc_url_raw`, `wp_kses_post`, `apply_filters`, `get_transient`, `set_transient`, `__`.

- [ ] **Step 1: Add dev dependencies and ignore rules**

`composer.json` — add alongside the existing `require`:

```json
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "yoast/phpunit-polyfills": "^2.0"
    },
    "scripts": {
        "test": "phpunit --testsuite unit"
    }
```

Append `/vendor` to `.gitignore` (dev-only, must never be committed) and `/vendor` to `.distignore` (must never ship).

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true"
         convertErrorsToExceptions="true" convertNoticesToExceptions="true"
         convertWarningsToExceptions="true">
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `tests/stubs/wp-functions.php`**

Only what unit tests need; each guarded so the real WordPress function wins when present.

```php
<?php
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        $url = trim((string) $url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($url !== '' && $scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }
        return $url;
    }
}
if (! function_exists('wp_kses_post')) {
    function wp_kses_post($html) {
        return strip_tags((string) $html, '<a><b><strong><em><i><br><span><code><mark><s><sub><sup>');
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}
if (! function_exists('get_transient')) {
    function get_transient($key) {
        return false;
    }
}
if (! function_exists('set_transient')) {
    function set_transient($key, $value, $expiry = 0) {
        return true;
    }
}
if (! function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}
```

- [ ] **Step 4: Create `tests/bootstrap.php`**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-functions.php';

if (! defined('SFCORE_PLUGIN_DIR')) {
    define('SFCORE_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (! defined('SFCORE_VERSION')) {
    define('SFCORE_VERSION', '1.0.3');
}

spl_autoload_register(function ($class) {
    $prefix = 'SwishfolioCore\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $path = SFCORE_PLUGIN_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});
```

- [ ] **Step 5: Write the smoke test**

`tests/Unit/SmokeTest.php`:

```php
<?php
namespace SwishfolioCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function test_harness_boots_with_constants_and_stubs()
    {
        $this->assertTrue(defined('SFCORE_PLUGIN_DIR'));
        $this->assertSame('hello', sanitize_text_field('  hello  '));
        $this->assertSame('', esc_url_raw('javascript:alert(1)'));
    }
}
```

- [ ] **Step 6: Install and run**

Run: `composer install && vendor/bin/phpunit --testsuite unit`
Expected: `OK (1 test, 3 assertions)`

- [ ] **Step 7: Commit**

```bash
git add composer.json .gitignore .distignore phpunit.xml.dist tests/
git commit -m "test: add PHPUnit harness with WordPress function stubs"
```

---

### Task 2: FieldSpec and BlockCapability

**Files:**
- Create: `src/Blocks/FieldSpec.php`, `src/Blocks/BlockCapability.php`
- Test: `tests/Unit/Blocks/FieldSpecTest.php`, `tests/Unit/Blocks/BlockCapabilityTest.php`

**Interfaces:**
- Consumes: Task 1 harness.
- Produces:
  - `FieldSpec::fromArray(string $name, array $spec): FieldSpec`
  - `FieldSpec->getName(): string`, `->getType(): string` (one of `text|url|richtext|number|enum|token`), `->getGroup(): string`, `->getRole(): ?string`, `->getEnum(): array`, `->getMin(): ?float`, `->getMax(): ?float`, `->getMaxLength(): ?int`, `->getTokenSet(): ?string`
  - `BlockCapability::fromArray(string $blockName, array $manifest): BlockCapability`
  - `BlockCapability->getBlockName(): string`, `->getTier(): string`, `->getPurpose(): string`, `->getSummary(): string`, `->getField(string $attr): ?FieldSpec`, `->getFields(): array` (attr => FieldSpec), `->getAllowedChildren(): array`, `->getChildMin(): ?int`, `->getChildMax(): ?int`

- [ ] **Step 1: Write failing tests**

`tests/Unit/Blocks/FieldSpecTest.php`:

```php
<?php
namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\FieldSpec;

class FieldSpecTest extends TestCase
{
    public function test_enum_spec_is_detected_without_explicit_type()
    {
        $spec = FieldSpec::fromArray('contentAlign', ['enum' => ['left', 'center'], 'group' => 'layout']);
        $this->assertSame('enum', $spec->getType());
        $this->assertSame(['left', 'center'], $spec->getEnum());
    }

    public function test_token_spec_carries_token_set()
    {
        $spec = FieldSpec::fromArray('backgroundColor', ['type' => 'token', 'tokenSet' => 'color']);
        $this->assertSame('token', $spec->getType());
        $this->assertSame('color', $spec->getTokenSet());
    }

    public function test_number_spec_carries_bounds()
    {
        $spec = FieldSpec::fromArray('minHeight', ['type' => 'number', 'min' => 320, 'max' => 1000]);
        $this->assertSame(320.0, $spec->getMin());
        $this->assertSame(1000.0, $spec->getMax());
    }

    public function test_unknown_type_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['type' => 'wormhole']);
    }

    public function test_token_without_token_set_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        FieldSpec::fromArray('x', ['type' => 'token']);
    }
}
```

`tests/Unit/Blocks/BlockCapabilityTest.php`:

```php
<?php
namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockCapability;

class BlockCapabilityTest extends TestCase
{
    private function manifest(array $overrides = [])
    {
        return array_merge([
            'version' => 1,
            'tier' => 'full',
            'purpose' => 'hero',
            'summary' => 'A hero.',
            'capabilities' => [
                'content' => ['heading' => ['type' => 'text', 'maxLength' => 120]],
                'style' => ['backgroundColor' => ['type' => 'token', 'tokenSet' => 'color']],
            ],
        ], $overrides);
    }

    public function test_fields_are_flattened_across_groups_and_tagged_with_group()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());
        $this->assertSame(['heading', 'backgroundColor'], array_keys($cap->getFields()));
        $this->assertSame('content', $cap->getField('heading')->getGroup());
        $this->assertSame('style', $cap->getField('backgroundColor')->getGroup());
    }

    public function test_unlisted_attribute_returns_null()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/hero', $this->manifest());
        $this->assertNull($cap->getField('decoShape1Color'));
    }

    public function test_unsupported_manifest_version_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        BlockCapability::fromArray('x/y', $this->manifest(['version' => 99]));
    }

    public function test_invalid_tier_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        BlockCapability::fromArray('x/y', $this->manifest(['tier' => 'partial']));
    }

    public function test_children_constraints_are_exposed()
    {
        $cap = BlockCapability::fromArray('swishfolio-core/testimonials', $this->manifest([
            'children' => ['allowed' => ['swishfolio-core/testimonial-card'], 'min' => 1, 'max' => 12],
        ]));
        $this->assertSame(['swishfolio-core/testimonial-card'], $cap->getAllowedChildren());
        $this->assertSame(1, $cap->getChildMin());
        $this->assertSame(12, $cap->getChildMax());
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "SwishfolioCore\Blocks\FieldSpec" not found`

- [ ] **Step 3: Implement `FieldSpec`**

```php
<?php
namespace SwishfolioCore\Blocks;

class FieldSpec
{
    const TYPES = ['text', 'url', 'richtext', 'number', 'enum', 'token'];

    private $name;
    private $type;
    private $group;
    private $role;
    private $enum = [];
    private $min;
    private $max;
    private $maxLength;
    private $tokenSet;

    private function __construct($name)
    {
        $this->name = $name;
    }

    public static function fromArray($name, array $spec)
    {
        $field = new self($name);
        $field->type = isset($spec['type']) ? $spec['type'] : (isset($spec['enum']) ? 'enum' : null);

        if ($field->type === null || ! in_array($field->type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Field "%s": unknown type.', $name));
        }
        if ($field->type === 'enum') {
            if (empty($spec['enum']) || ! is_array($spec['enum'])) {
                throw new \InvalidArgumentException(sprintf('Field "%s": enum requires a non-empty list.', $name));
            }
            $field->enum = array_values($spec['enum']);
        }
        if ($field->type === 'token') {
            if (empty($spec['tokenSet'])) {
                throw new \InvalidArgumentException(sprintf('Field "%s": token requires tokenSet.', $name));
            }
            $field->tokenSet = $spec['tokenSet'];
        }

        $field->group     = isset($spec['group']) ? $spec['group'] : 'style';
        $field->role      = isset($spec['role']) ? $spec['role'] : null;
        $field->min       = isset($spec['min']) ? (float) $spec['min'] : null;
        $field->max       = isset($spec['max']) ? (float) $spec['max'] : null;
        $field->maxLength = isset($spec['maxLength']) ? (int) $spec['maxLength'] : null;

        return $field;
    }

    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getGroup() { return $this->group; }
    public function getRole() { return $this->role; }
    public function getEnum() { return $this->enum; }
    public function getMin() { return $this->min; }
    public function getMax() { return $this->max; }
    public function getMaxLength() { return $this->maxLength; }
    public function getTokenSet() { return $this->tokenSet; }
}
```

- [ ] **Step 4: Implement `BlockCapability`**

```php
<?php
namespace SwishfolioCore\Blocks;

class BlockCapability
{
    const SUPPORTED_VERSION = 1;
    const TIERS = ['full', 'content'];

    private $blockName;
    private $tier;
    private $purpose;
    private $summary;
    private $fields = [];
    private $allowedChildren = [];
    private $childMin;
    private $childMax;

    private function __construct($blockName)
    {
        $this->blockName = $blockName;
    }

    public static function fromArray($blockName, array $manifest)
    {
        $version = isset($manifest['version']) ? (int) $manifest['version'] : 0;
        if ($version !== self::SUPPORTED_VERSION) {
            throw new \InvalidArgumentException(sprintf('%s: unsupported manifest version.', $blockName));
        }

        $cap = new self($blockName);
        $cap->tier = isset($manifest['tier']) ? $manifest['tier'] : '';
        if (! in_array($cap->tier, self::TIERS, true)) {
            throw new \InvalidArgumentException(sprintf('%s: invalid tier.', $blockName));
        }

        $cap->purpose = isset($manifest['purpose']) ? $manifest['purpose'] : '';
        $cap->summary = isset($manifest['summary']) ? $manifest['summary'] : '';

        $groups = isset($manifest['capabilities']) ? $manifest['capabilities'] : [];
        foreach ($groups as $group => $attrs) {
            foreach ((array) $attrs as $attr => $spec) {
                $spec['group'] = $group;
                $cap->fields[$attr] = FieldSpec::fromArray($attr, $spec);
            }
        }

        if (isset($manifest['children'])) {
            $children = $manifest['children'];
            $cap->allowedChildren = isset($children['allowed']) ? (array) $children['allowed'] : [];
            $cap->childMin = isset($children['min']) ? (int) $children['min'] : null;
            $cap->childMax = isset($children['max']) ? (int) $children['max'] : null;
        }

        return $cap;
    }

    public function getBlockName() { return $this->blockName; }
    public function getTier() { return $this->tier; }
    public function getPurpose() { return $this->purpose; }
    public function getSummary() { return $this->summary; }
    public function getFields() { return $this->fields; }
    public function getAllowedChildren() { return $this->allowedChildren; }
    public function getChildMin() { return $this->childMin; }
    public function getChildMax() { return $this->childMax; }

    public function getField($attr)
    {
        return isset($this->fields[$attr]) ? $this->fields[$attr] : null;
    }
}
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 10 tests.

- [ ] **Step 6: Commit**

```bash
git add src/Blocks/FieldSpec.php src/Blocks/BlockCapability.php tests/Unit/Blocks/
git commit -m "feat(ai): add FieldSpec and BlockCapability manifest value objects"
```

---

### Task 3: BlockRegistry

**Files:**
- Create: `src/Blocks/BlockRegistry.php`
- Test: `tests/Unit/Blocks/BlockRegistryTest.php`, `tests/fixtures/blocks/{alpha,beta}/ai.json`

**Interfaces:**
- Consumes: `BlockCapability` (Task 2).
- Produces:
  - `BlockRegistry::__construct(array $manifestDirs)` — each dir contains `*/ai.json`
  - `->register(string $blockName, array $manifest): void`
  - `->get(string $blockName): ?BlockCapability`
  - `->all(): array` (blockName => BlockCapability)
  - `->has(string $blockName): bool`
  - Filter `swishfolio_ai_block_manifests` applied to the raw manifest array before parsing.

- [ ] **Step 1: Write failing tests**

Fixtures: `tests/fixtures/blocks/alpha/ai.json` with block name `test/alpha`, tier `full`, one text field `title`; `tests/fixtures/blocks/beta/ai.json` similarly for `test/beta`. Manifests declare their own `blockName` key so fixtures do not depend on directory naming.

```php
<?php
namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;

class BlockRegistryTest extends TestCase
{
    private function registry()
    {
        return new BlockRegistry([__DIR__ . '/../../fixtures/blocks']);
    }

    public function test_discovers_manifests_from_directory()
    {
        $registry = $this->registry();
        $this->assertTrue($registry->has('test/alpha'));
        $this->assertTrue($registry->has('test/beta'));
        $this->assertSame('full', $registry->get('test/alpha')->getTier());
    }

    public function test_unknown_block_returns_null()
    {
        $this->assertNull($this->registry()->get('test/nope'));
    }

    public function test_manual_registration_overrides_discovery()
    {
        $registry = $this->registry();
        $registry->register('test/alpha', [
            'version' => 1, 'tier' => 'content', 'purpose' => 'text', 'summary' => 'Overridden.',
            'capabilities' => ['content' => ['content' => ['type' => 'richtext']]],
        ]);
        $this->assertSame('content', $registry->get('test/alpha')->getTier());
    }

    public function test_malformed_manifest_is_skipped_not_fatal()
    {
        $registry = new BlockRegistry([__DIR__ . '/../../fixtures/blocks-broken']);
        $this->assertSame([], $registry->all());
    }
}
```

Also create `tests/fixtures/blocks-broken/gamma/ai.json` containing `{ "version": 99 }`.

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `BlockRegistry` not found.

- [ ] **Step 3: Implement**

```php
<?php
namespace SwishfolioCore\Blocks;

class BlockRegistry
{
    private $manifestDirs;
    private $capabilities = [];
    private $discovered = false;

    public function __construct(array $manifestDirs)
    {
        $this->manifestDirs = $manifestDirs;
    }

    public function register($blockName, array $manifest)
    {
        try {
            $this->capabilities[$blockName] = BlockCapability::fromArray($blockName, $manifest);
        } catch (\InvalidArgumentException $e) {
            // A malformed manifest disables AI for that block; it must not break the editor.
        }
    }

    public function has($blockName)
    {
        $this->discover();
        return isset($this->capabilities[$blockName]);
    }

    public function get($blockName)
    {
        $this->discover();
        return isset($this->capabilities[$blockName]) ? $this->capabilities[$blockName] : null;
    }

    public function all()
    {
        $this->discover();
        return $this->capabilities;
    }

    private function discover()
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        $raw = [];
        foreach ($this->manifestDirs as $dir) {
            foreach ((array) glob(rtrim($dir, '/') . '/*/ai.json') as $file) {
                $manifest = json_decode((string) file_get_contents($file), true);
                if (! is_array($manifest)) {
                    continue;
                }
                $name = isset($manifest['blockName'])
                    ? $manifest['blockName']
                    : 'swishfolio-core/' . basename(dirname($file));
                $raw[$name] = $manifest;
            }
            foreach ((array) glob(rtrim($dir, '/') . '/*.json') as $file) {
                $manifest = json_decode((string) file_get_contents($file), true);
                if (is_array($manifest) && isset($manifest['blockName'])) {
                    $raw[$manifest['blockName']] = $manifest;
                }
            }
        }

        $raw = apply_filters('swishfolio_ai_block_manifests', $raw);

        foreach ($raw as $name => $manifest) {
            if (! isset($this->capabilities[$name])) {
                $this->register($name, $manifest);
            }
        }
    }
}
```

Note the two globs: `*/ai.json` finds block-directory manifests, `*.json` finds the flat core manifests added in Task 5.

- [ ] **Step 4: Add transient caching (spec §4.1)**

Write the test first:

```php
public function test_parsed_manifests_are_cached_under_a_version_keyed_transient()
{
    $GLOBALS['sfcore_test_transients'] = [];
    $registry = $this->registry();
    $registry->all();
    $this->assertArrayHasKey('sfcore_ai_manifests_' . SFCORE_VERSION, $GLOBALS['sfcore_test_transients']);
}

public function test_cache_is_bypassed_when_a_manifest_is_registered_manually()
{
    $registry = $this->registry();
    $registry->register('test/alpha', [
        'version' => 1, 'tier' => 'content', 'purpose' => 'text', 'summary' => 'Live.',
        'capabilities' => ['content' => ['content' => ['type' => 'richtext']]],
    ]);
    $this->assertSame('content', $registry->get('test/alpha')->getTier());
}
```

Upgrade the `get_transient`/`set_transient` stubs in `tests/stubs/wp-functions.php` to read and write `$GLOBALS['sfcore_test_transients']` so caching is observable.

In `discover()`, read the raw manifest array from transient `sfcore_ai_manifests_{SFCORE_VERSION}` before globbing, and write it after. Cache the **raw arrays**, not `BlockCapability` objects — serialised objects break across plugin updates. The key includes `SFCORE_VERSION`, so a plugin update invalidates it without an explicit flush.

- [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Blocks/BlockRegistry.php tests/Unit/Blocks/BlockRegistryTest.php tests/fixtures/ tests/stubs/
git commit -m "feat(ai): add BlockRegistry with manifest discovery, filter and cache"
```

---

### Task 4: TokenResolver

**Files:**
- Create: `src/AI/Context/TokenResolver.php`
- Test: `tests/Unit/AI/TokenResolverTest.php`, `tests/fixtures/theme.json`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `TokenResolver::fromArray(array $themeJson): TokenResolver`
  - `->getSlugs(string $tokenSet): array` — `tokenSet` in `color|spacing|fontSize`
  - `->isValid(string $tokenSet, string $slug): bool`
  - `->toStorageValue(string $tokenSet, string $slug): string` — e.g. `color`/`accent-1` → `var(--wp--preset--color--accent-1)`
  - `->toSlug(string $tokenSet, string $storageValue): ?string` — inverse, for rendering before/after in the preview

- [ ] **Step 1: Write failing tests**

`tests/fixtures/theme.json` contains a trimmed but real-shaped v3 file with `settings.color.palette` slugs `base, contrast, accent-1, accent-2`, `settings.spacing.spacingSizes` slugs `50, 60`, and `settings.typography.fontSizes` slugs `medium, large, display`.

```php
<?php
namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\TokenResolver;

class TokenResolverTest extends TestCase
{
    private function resolver()
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../fixtures/theme.json'), true);
        return TokenResolver::fromArray($json);
    }

    public function test_reads_colour_slugs_from_palette()
    {
        $this->assertSame(['base', 'contrast', 'accent-1', 'accent-2'], $this->resolver()->getSlugs('color'));
    }

    public function test_expands_slug_to_css_custom_property()
    {
        $this->assertSame(
            'var(--wp--preset--color--accent-1)',
            $this->resolver()->toStorageValue('color', 'accent-1')
        );
    }

    public function test_font_size_uses_hyphenated_preset_path()
    {
        $this->assertSame(
            'var(--wp--preset--font-size--large)',
            $this->resolver()->toStorageValue('fontSize', 'large')
        );
    }

    public function test_inverts_storage_value_back_to_slug()
    {
        $this->assertSame('accent-2', $this->resolver()->toSlug('color', 'var(--wp--preset--color--accent-2)'));
    }

    public function test_unknown_slug_is_invalid()
    {
        $this->assertFalse($this->resolver()->isValid('color', 'accent-9'));
        $this->assertTrue($this->resolver()->isValid('color', 'accent-1'));
    }

    public function test_literal_hex_is_never_a_valid_slug()
    {
        $this->assertFalse($this->resolver()->isValid('color', '#FF6B6B'));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `TokenResolver` not found.

- [ ] **Step 3: Implement**

```php
<?php
namespace SwishfolioCore\AI\Context;

class TokenResolver
{
    /** tokenSet => [presetPathSegment, themeJsonPath] */
    const SETS = [
        'color'    => ['color', ['settings', 'color', 'palette']],
        'spacing'  => ['spacing', ['settings', 'spacing', 'spacingSizes']],
        'fontSize' => ['font-size', ['settings', 'typography', 'fontSizes']],
    ];

    private $slugs = [];

    private function __construct(array $slugs)
    {
        $this->slugs = $slugs;
    }

    public static function fromArray(array $themeJson)
    {
        $slugs = [];
        foreach (self::SETS as $set => $config) {
            $node = $themeJson;
            foreach ($config[1] as $segment) {
                $node = isset($node[$segment]) ? $node[$segment] : null;
                if ($node === null) {
                    break;
                }
            }
            $slugs[$set] = [];
            foreach ((array) $node as $preset) {
                if (isset($preset['slug'])) {
                    $slugs[$set][] = (string) $preset['slug'];
                }
            }
        }
        return new self($slugs);
    }

    public function getSlugs($tokenSet)
    {
        return isset($this->slugs[$tokenSet]) ? $this->slugs[$tokenSet] : [];
    }

    public function isValid($tokenSet, $slug)
    {
        return in_array((string) $slug, $this->getSlugs($tokenSet), true);
    }

    public function toStorageValue($tokenSet, $slug)
    {
        if (! isset(self::SETS[$tokenSet])) {
            return (string) $slug;
        }
        return sprintf('var(--wp--preset--%s--%s)', self::SETS[$tokenSet][0], $slug);
    }

    public function toSlug($tokenSet, $storageValue)
    {
        foreach ($this->getSlugs($tokenSet) as $slug) {
            if ($this->toStorageValue($tokenSet, $slug) === $storageValue || $slug === $storageValue) {
                return $slug;
            }
        }
        return null;
    }
}
```

A production factory reading the live theme via `wp_get_global_settings()` is added in Task 13; keeping `fromArray` as the only constructor here is what makes this unit-testable without WordPress.

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/AI/Context/TokenResolver.php tests/Unit/AI/TokenResolverTest.php tests/fixtures/theme.json
git commit -m "feat(ai): add TokenResolver for theme.json design tokens"
```

---

### Task 5: Author the block manifests

**Files:**
- Create: `blocks/*/ai.json` (23 files), `src/AI/Manifests/core/{paragraph,heading,list,button}.json`
- Test: `tests/Unit/Blocks/ManifestConformanceTest.php`

**Interfaces:**
- Consumes: `BlockRegistry`, `BlockCapability`.
- Produces: the full manifest corpus. Core manifests carry an explicit `"blockName"` key (`core/paragraph` etc.) since they live in a flat directory.

**Authoring rule:** expose the attributes a user would plausibly ask AI to change — content, layout, and semantic style. Exclude decorative geometry, image objects, and anything whose value is a media ID. Every colour, spacing and font-size attribute becomes `{"type":"token", …}`, never a free string. Target roughly 8–16 fields per block; `hero` is 15.

- [ ] **Step 1: Write the conformance test first**

This is the highest-value test in the plan: it proves every manifest attribute actually exists in the corresponding `block.json`, so a typo in an attribute name fails CI rather than silently producing an action the editor ignores.

```php
<?php
namespace SwishfolioCore\Tests\Unit\Blocks;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\Blocks\BlockRegistry;

class ManifestConformanceTest extends TestCase
{
    private function registry()
    {
        return new BlockRegistry([
            SFCORE_PLUGIN_DIR . 'blocks',
            SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
        ]);
    }

    public function test_every_plugin_block_directory_has_a_manifest()
    {
        $missing = [];
        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/block.json') as $blockJson) {
            $dir = dirname($blockJson);
            if (! file_exists($dir . '/ai.json')) {
                $missing[] = basename($dir);
            }
        }
        $this->assertSame([], $missing, 'Blocks without an ai.json manifest: ' . implode(', ', $missing));
    }

    public function test_every_manifest_attribute_exists_in_its_block_json()
    {
        $unknown = [];
        foreach ((array) glob(SFCORE_PLUGIN_DIR . 'blocks/*/ai.json') as $manifestFile) {
            $dir = dirname($manifestFile);
            $blockJson = json_decode((string) file_get_contents($dir . '/block.json'), true);
            $manifest  = json_decode((string) file_get_contents($manifestFile), true);
            $declared  = isset($blockJson['attributes']) ? array_keys($blockJson['attributes']) : [];

            foreach ((array) $manifest['capabilities'] as $group => $attrs) {
                foreach (array_keys((array) $attrs) as $attr) {
                    if (! in_array($attr, $declared, true)) {
                        $unknown[] = basename($dir) . '.' . $attr;
                    }
                }
            }
        }
        $this->assertSame([], $unknown, 'Manifest attributes absent from block.json: ' . implode(', ', $unknown));
    }

    public function test_every_manifest_parses_into_a_capability()
    {
        $registry = $this->registry();
        $this->assertGreaterThanOrEqual(27, count($registry->all()));
        $this->assertNotNull($registry->get('swishfolio-core/hero'));
        $this->assertNotNull($registry->get('core/paragraph'));
    }

    public function test_colour_and_size_fields_use_tokens_not_free_strings()
    {
        $offenders = [];
        foreach ($this->registry()->all() as $blockName => $cap) {
            foreach ($cap->getFields() as $attr => $field) {
                $looksLikeToken = preg_match('/(Color|Size|Spacing)$/', $attr);
                if ($looksLikeToken && ! in_array($field->getType(), ['token', 'enum'], true)) {
                    $offenders[] = $blockName . '.' . $attr;
                }
            }
        }
        $this->assertSame([], $offenders, 'Colour/size fields must be token or enum: ' . implode(', ', $offenders));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter ManifestConformance`
Expected: FAIL — every block reported as missing a manifest.

- [ ] **Step 3: Author `blocks/hero/ai.json`**

Copy verbatim from spec §4.3. This is the reference manifest; every other block follows its shape.

- [ ] **Step 4: Author the remaining 22 plugin manifests**

For each block directory, read its `block.json` `attributes` first, then write `ai.json`. Parent blocks (`features`, `bento-cards`, `testimonials`, `pricing`, `swish-counter`, `swish-slider`) additionally get a `children` block whose `allowed` list is taken from the child block's `parent` declaration in its own `block.json`.

Child blocks (`feature-card`, `bento-card`, `testimonial-card`, `pricing-plan`, `swish-counter-item`, `swish-slide`) get manifests too — they are selectable, so "Ask AI" must work on them.

- [ ] **Step 5: Author the 4 core manifests**

`src/AI/Manifests/core/paragraph.json`:

```json
{
  "blockName": "core/paragraph",
  "version": 1,
  "tier": "content",
  "purpose": "body-text",
  "summary": "A paragraph of body text.",
  "capabilities": {
    "content": {
      "content": { "type": "richtext", "maxLength": 5000, "role": "body" }
    }
  }
}
```

`heading.json` (`core/heading`, purpose `heading`, same single `content` richtext field, `maxLength` 300), `list.json` (`core/list`, purpose `list`, `values` richtext `maxLength` 5000 — confirm the attribute name against the installed WordPress version before writing), `button.json` (`core/button`, purpose `cta`, `text` richtext `maxLength` 60 plus `url` of type `url`).

- [ ] **Step 6: Run conformance tests until green**

Run: `vendor/bin/phpunit --filter ManifestConformance`
Expected: PASS. Fix reported attribute-name mismatches by correcting the manifest, never by loosening the test.

- [ ] **Step 7: Commit**

```bash
git add blocks/*/ai.json src/AI/Manifests/ tests/Unit/Blocks/ManifestConformanceTest.php
git commit -m "feat(ai): author capability manifests for 23 blocks and 4 core blocks"
```

---

### Task 6: ActionSchema

**Files:**
- Create: `src/Actions/ActionSchema.php`
- Test: `tests/Unit/Actions/ActionSchemaTest.php`

**Interfaces:**
- Consumes: `BlockCapability`.
- Produces:
  - `ActionSchema::SCHEMA_VERSION` = `'1.0'`
  - `ActionSchema::TYPES` = `['update_block_attributes','update_block_content','insert_block','remove_block']`
  - `ActionSchema::forCapabilities(array $capabilities, TokenResolver $tokens): array` — returns a JSON-Schema array with `additionalProperties: false` throughout, sent verbatim to the provider.

- [ ] **Step 1: Write failing tests**

```php
public function test_schema_requires_version_and_actions()
{
    $schema = ActionSchema::forCapabilities([], $this->tokens());
    $this->assertSame(['schemaVersion', 'summary', 'actions'], $schema['required']);
    $this->assertFalse($schema['additionalProperties']);
}

public function test_action_type_enum_matches_supported_types()
{
    $schema = ActionSchema::forCapabilities([], $this->tokens());
    $typeEnum = $schema['properties']['actions']['items']['properties']['type']['enum'];
    $this->assertSame(ActionSchema::TYPES, $typeEnum);
}

public function test_changes_object_is_open_because_keys_are_block_specific()
{
    $schema = ActionSchema::forCapabilities([], $this->tokens());
    $changes = $schema['properties']['actions']['items']['properties']['changes'];
    $this->assertSame('object', $changes['type']);
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter ActionSchema`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

The schema constrains the envelope and action shape. It deliberately leaves `changes` as an open object: attribute keys differ per block, and the authoritative per-block constraint is the manifest enforced by the Allowlist and Value stages. The manifests themselves go into the prompt (Task 12), not the schema.

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Actions/ActionSchema.php tests/Unit/Actions/ActionSchemaTest.php
git commit -m "feat(ai): add versioned action JSON schema"
```

---

### Task 7: Envelope and Target stages

**Files:**
- Create: `src/Actions/Stages/StageInterface.php`, `src/Actions/ValidationContext.php`, `src/Actions/ValidationException.php`, `src/Actions/Stages/EnvelopeStage.php`, `src/Actions/Stages/TargetStage.php`
- Test: `tests/Unit/Actions/EnvelopeStageTest.php`, `tests/Unit/Actions/TargetStageTest.php`

**Interfaces:**
- Produces:
  - `interface StageInterface { public function process(array $response, ValidationContext $context): array; }` — returns the (possibly transformed) response, throws `ValidationException` on failure.
  - `ValidationException extends \RuntimeException` with `->getStage(): string` and `->getPath(): ?string`.
  - `ValidationContext` carrying `getBlockTree(): array` (clientId => ['name' => string, 'attributes' => array, 'parentClientId' => ?string]), `getRegistry(): BlockRegistry`, `getTokens(): TokenResolver`, `getMaxActions(): int`.

- [ ] **Step 1: Write failing tests**

Envelope rejects: unknown `schemaVersion`; missing `actions`; `actions` not a list; action count above `getMaxActions()` (default 25); unknown action `type`.
Target rejects: `clientId` absent from the submitted block tree; `insert_block` whose `parentClientId` is absent; `remove_block` targeting a block that is not a descendant of the selection root.

Each rejection asserts `ValidationException::getStage()` returns `'envelope'` or `'target'` respectively.

- [ ] **Step 2: Run to verify failure** — Expected: FAIL, classes not found.

- [ ] **Step 3: Implement both stages**

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Actions/ tests/Unit/Actions/
git commit -m "feat(ai): add envelope and target validation stages"
```

---

### Task 8: Allowlist and Value stages

**Files:**
- Create: `src/Actions/Stages/AllowlistStage.php`, `src/Actions/Stages/ValueStage.php`
- Test: `tests/Unit/Actions/AllowlistStageTest.php`, `tests/Unit/Actions/ValueStageTest.php`

**Interfaces:**
- Consumes: `StageInterface`, `ValidationContext`, `BlockCapability`, `TokenResolver`.
- Produces: `ValueStage` converts validated token slugs to storage values via `TokenResolver::toStorageValue()` before returning, so later stages and the response carry the final attribute value.

- [ ] **Step 1: Write failing tests**

Allowlist rejects, with `getStage() === 'allowlist'`:
- an attribute absent from the block's manifest (use `decoShape1Color` on hero — the canonical case)
- `update_block_attributes` against a `content`-tier block
- `update_block_content` against a `full`-tier block with no richtext field
- `insert_block` of a `blockName` not in the parent's `children.allowed`

Value rejects, with `getStage() === 'value'`:
- enum value outside the declared list
- number below `min` or above `max`
- string longer than `maxLength`
- colour slug absent from the fixture `theme.json` (`accent-9`)
- a literal hex (`#FF6B6B`) supplied where a token is required
- URL with a disallowed scheme (`javascript:`)

Value accepts and transforms: `backgroundColor: "accent-2"` becomes `"var(--wp--preset--color--accent-2)"`.

- [ ] **Step 2: Run to verify failure** — Expected: FAIL.

- [ ] **Step 3: Implement both stages**

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Actions/Stages/ tests/Unit/Actions/
git commit -m "feat(ai): add allowlist and value validation stages"
```

---

### Task 9: Sanitize stage and ActionValidator

**Files:**
- Create: `src/Actions/Stages/SanitizeStage.php`, `src/Actions/ActionValidator.php`
- Test: `tests/Unit/Actions/SanitizeStageTest.php`, `tests/Unit/Actions/ActionValidatorTest.php`

**Interfaces:**
- Produces: `ActionValidator::__construct(array $stages)`, `->validate(array $response, ValidationContext $context): array` — returns validated actions, throws `ValidationException`.

- [ ] **Step 1: Write failing tests**

Sanitize: `<script>` stripped from richtext; `sanitize_text_field` applied to text; `esc_url_raw` applied to urls.

ActionValidator, the critical one — **all-or-nothing**:

```php
public function test_one_bad_action_rejects_the_entire_response()
{
    $response = ['schemaVersion' => '1.0', 'summary' => 's', 'actions' => [
        ['type' => 'update_block_attributes', 'clientId' => 'ok', 'changes' => ['heading' => 'Fine']],
        ['type' => 'update_block_attributes', 'clientId' => 'ok', 'changes' => ['decoShape1Color' => 'accent-1']],
    ]];

    $this->expectException(ValidationException::class);
    $this->validator()->validate($response, $this->context());
}

public function test_stages_run_in_declared_order()
{
    // An unknown schemaVersion must fail at 'envelope' even when the action
    // would also fail 'allowlist' — proving order, not just rejection.
    $response = ['schemaVersion' => '9.9', 'summary' => 's', 'actions' => [
        ['type' => 'update_block_attributes', 'clientId' => 'nope', 'changes' => ['bogus' => 1]],
    ]];
    try {
        $this->validator()->validate($response, $this->context());
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $this->assertSame('envelope', $e->getStage());
    }
}
```

- [ ] **Step 2: Run to verify failure** — Expected: FAIL.

- [ ] **Step 3: Implement**

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Actions/ tests/Unit/Actions/
git commit -m "feat(ai): add sanitize stage and all-or-nothing ActionValidator"
```

---

### Task 10: Provider contracts and fake

**Files:**
- Create: `src/AI/Contracts/AIProviderInterface.php`, `src/AI/AIRequest.php`, `src/AI/AIResponse.php`, `src/AI/Providers/AbstractAIProvider.php`
- Test: `tests/Unit/AI/FakeProvider.php`, `tests/Unit/AI/AIResponseTest.php`

**Interfaces:**
- Produces exactly the contract in spec §5:
  - `AIProviderInterface`: `getSlug(): string`, `getLabel(): string`, `getRequiredFields(): array`, `getModels(): array`, `testConnection(): bool`, `generate(AIRequest $request): AIResponse`, `getLastError(): ?string`
  - `AIRequest::__construct(string $systemPrompt, string $userPrompt, array $schema, string $model, int $maxTokens)` plus getters.
  - `AIResponse::__construct(array $decoded, int $inputTokens, int $outputTokens, string $model, string $providerSlug)` plus getters and `->getDecoded(): array`.
  - `AbstractAIProvider::setCredentials(array $credentials): void`, protected `request(string $url, array $body, array $headers): array` with 2 retries on 429/5xx and exponential backoff.
  - **Test seam:** `AbstractAIProvider` calls `wp_remote_post()` through a single overridable `protected function post($url, array $args)`. Task 11's tests subclass the provider and override `post()`. Never redefine WordPress functions in a test.

- [ ] **Step 1: Write the fake and its test** — `FakeProvider` returns a canned decoded response and records the last `AIRequest`, so every later task tests against it with no network.

- [ ] **Step 2: Run to verify failure** — Expected: FAIL.

- [ ] **Step 3: Implement contracts and `AbstractAIProvider`**

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/AI/ tests/Unit/AI/
git commit -m "feat(ai): add provider contract, request/response objects and abstract provider"
```

---

### Task 11: OpenAI provider

**Files:**
- Create: `src/AI/Providers/OpenAIProvider.php`, `src/AI/Providers/OpenAICompatibleProvider.php`
- Test: `tests/Unit/AI/OpenAIProviderTest.php`

**Interfaces:**
- Consumes: `AbstractAIProvider`, `AIRequest`, `AIResponse`.
- Produces: `OpenAIProvider` with `getSlug() === 'openai'`; `OpenAICompatibleProvider extends OpenAIProvider` with `getSlug() === 'openai-compatible'` and a configurable `base_url` required field.

- [ ] **Step 1: Write failing tests**

Stub `wp_remote_post` via a test double injected into `AbstractAIProvider` (add a protected seam in Task 10 rather than redefining WordPress functions). Assert: the request body carries `response_format` with the JSON Schema and `strict: true`; a well-formed response yields correct `inputTokens`/`outputTokens`; a 401 sets `getLastError()` and throws a provider exception; a body that is not valid JSON throws rather than returning a partial `AIResponse`.

- [ ] **Step 2: Run to verify failure** — Expected: FAIL.

- [ ] **Step 3: Implement**

- [ ] **Step 4: Run tests** — Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/AI/Providers/ tests/Unit/AI/OpenAIProviderTest.php
git commit -m "feat(ai): add OpenAI and OpenAI-compatible providers"
```

---

### Task 12: PromptManager

**Files:**
- Create: `src/AI/Prompt/PromptManager.php`, `src/AI/Prompt/system/block-editing.txt`, `src/AI/Prompt/tasks/modify-block.txt`
- Test: `tests/Unit/AI/PromptManagerTest.php`

**Interfaces:**
- Produces: `PromptManager::__construct(string $promptDir)`, `->system(string $name): string`, `->task(string $name, array $vars): string` — `{{placeholder}}` substitution, throws on an unreplaced placeholder.

**Ordering requirement (cache stability):** the composed system prompt is emitted as **system text → capability manifests → design tokens**, with the volatile selection context and user instruction supplied separately as the user message. Test that the first three segments are byte-identical across two calls with different selections.

- [ ] **Step 1: Write failing tests** — including the byte-stability assertion above.
- [ ] **Step 2: Run to verify failure** — Expected: FAIL.
- [ ] **Step 3: Implement**
- [ ] **Step 4: Run tests** — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add src/AI/Prompt/ tests/Unit/AI/PromptManagerTest.php
git commit -m "feat(ai): add PromptManager with file-based prompts"
```

---

### Task 13: ContextAssembler

**Files:**
- Create: `src/AI/Context/ContextAssembler.php`; modify `src/AI/Context/TokenResolver.php` to add `TokenResolver::fromTheme()` using `wp_get_global_settings()`
- Test: `tests/Unit/AI/ContextAssemblerTest.php`

**Interfaces:**
- Consumes: `BlockRegistry` (Task 3), `TokenResolver` (Task 4), `ActionSchema` (Task 6), `PromptManager` (Task 12), `AIRequest` (Task 10).
- Produces: `ContextAssembler::__construct(BlockRegistry $registry, TokenResolver $tokens, PromptManager $prompts, ActionSchema $schema)`, `->forSelection(array $selection, string $instruction): AIRequest` — calls `ActionSchema::forCapabilities()` to populate the request's schema.
- `$selection` shape (sent by the editor, validated by the REST controller in Task 16):
  `['clientId' => string, 'blockName' => string, 'attributes' => array, 'innerBlocks' => [['clientId','blockName'], …], 'parent' => ['clientId','blockName']|null, 'siblings' => [['clientId','blockName'], …], 'postType' => string, 'styleVariation' => string]`

**Context economy:** the selected block is serialised in full; parent, siblings and inner blocks contribute **name + manifest purpose only**. Manifests are included only for block types actually present in the selection, never the whole registry. Assert both in tests — this is the difference between a 2K and a 40K prompt.

- [ ] **Step 1: Write failing tests** — assert a sibling's attributes never appear in the prompt, and that an unrelated block's manifest is absent.
- [ ] **Step 2: Run to verify failure** — Expected: FAIL.
- [ ] **Step 3: Implement**
- [ ] **Step 4: Run tests** — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add src/AI/Context/ tests/Unit/AI/ContextAssemblerTest.php
git commit -m "feat(ai): add ContextAssembler with scoped selection context"
```

---

### Task 14: AIManager and UsageLogger

**Files:**
- Create: `src/AI/AIManager.php`, `src/AI/UsageLogger.php`
- Test: `tests/Unit/AI/AIManagerTest.php`, `tests/Unit/AI/UsageLoggerTest.php`

**Interfaces:**
- Produces:
  - `AIManager::__construct(AIProviderInterface $provider, ContextAssembler $assembler, ActionValidator $validator, UsageLogger $logger)`
  - `->edit(array $selection, string $instruction, ValidationContext $context): array` — returns `['schemaVersion','summary','actions']`; throws `ValidationException` or a provider exception.
  - `UsageLogger::record(AIResponse $response, int $actionCount, int $durationMs, string $outcome): void`, `->getUsage(): array`.

**UsageLogger storage:** option `swishfolio_ai_usage` = `['counters' => [...], 'recent' => [...]]`, `recent` capped at 100 entries, oldest dropped. Test the cap explicitly by recording 105 entries. **No prompt text, block content or response body is stored** — assert this by recording and then scanning the stored option for a sentinel string present in the prompt.

- [ ] **Step 1: Write failing tests** — including the 105-entry cap and the sentinel-absence assertion.
- [ ] **Step 2: Run to verify failure** — Expected: FAIL.
- [ ] **Step 3: Implement** (stub `get_option`/`update_option` in `tests/stubs/wp-functions.php` with an in-memory store).
- [ ] **Step 4: Run tests** — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add src/AI/ tests/Unit/AI/ tests/stubs/
git commit -m "feat(ai): add AIManager orchestration and usage logging"
```

---

### Task 15: Settings storage and admin page

**Files:**
- Create: `src/AI/Settings.php`, `src/Admin/AISettingsPage.php`, `templates/admin/ai-settings.php`
- Modify: `src/Admin/AdminMenu.php` (add the AI submenu)
- Test: `tests/Unit/AI/SettingsTest.php`

**Interfaces:**
- Consumes: existing `SwishfolioCore\Services\EncryptionService`.
- Produces:
  - `Settings::get(): array` — `['provider','model','base_url','enabled']`; **never includes the key**
  - `Settings::hasKey(): bool`
  - `Settings::getKey(): ?string` — decrypted, server-side callers only
  - `Settings::save(array $settings): void` — encrypts `api_key` via `EncryptionService` before writing option `swishfolio_ai_settings`

- [ ] **Step 1: Write failing tests**

Assert: `get()` never returns `api_key` under any input; a saved key round-trips through `getKey()`; saving with an empty `api_key` preserves the previously stored key rather than clearing it (so the masked settings form can be submitted without retyping).

- [ ] **Step 2: Run to verify failure** — Expected: FAIL.
- [ ] **Step 3: Implement** — admin page under the existing `swish-forms` menu, matching `templates/admin/` conventions.
- [ ] **Step 4: Run tests** — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add src/AI/Settings.php src/Admin/ templates/admin/ai-settings.php tests/Unit/AI/SettingsTest.php
git commit -m "feat(ai): add encrypted provider settings and admin screen"
```

---

### Task 16: REST controllers

**Files:**
- Create: `src/REST/AIController.php`, `src/REST/SettingsController.php`
- Test: `tests/Integration/RestAIControllerTest.php`, `tests/Integration/bootstrap.php`, `phpunit-integration.xml.dist`

**Interfaces:**
- Consumes: `AIManager`, `Settings`, `BlockRegistry`.
- Produces the four routes from spec §8 on namespace `swishfolio/v1`.

**This is the first task needing real WordPress.** Integration tests run inside DDEV against the WP test suite:

```bash
ddev start
ddev exec bash bin/install-wp-tests.sh wordpress_test root root db latest
ddev exec vendor/bin/phpunit -c phpunit-integration.xml.dist
```

- [ ] **Step 1: Write failing tests**

Must include, as separate test methods:
- a subscriber receives 403 from `/ai/edit`
- an editor who cannot edit **post 123 specifically** receives 403 even though they can edit other posts — the spec §8 "verified, never trusted" requirement
- a missing or stale nonce receives 403
- a user without `swishfolio_use_ai` receives 403
- exceeding the rate limit receives 429
- `/ai/settings` GET never returns `api_key` for any role
- a `ValidationException` maps to a 422 whose body names the failing stage

- [ ] **Step 2: Run to verify failure** — Expected: FAIL, routes not registered.
- [ ] **Step 3: Implement both controllers**, registering `swishfolio_use_ai` mapped to `edit_posts`, and rate limiting via the transient pattern used by `FormProcessor` (30/hour, filterable through `swishfolio_ai_rate_limit`).
- [ ] **Step 4: Run integration tests** — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add src/REST/ tests/Integration/ phpunit-integration.xml.dist
git commit -m "feat(ai): add REST endpoints with capability and rate-limit enforcement"
```

---

### Task 17: Plugin wiring and version floors

**Files:**
- Modify: `src/Plugin.php`, `swishfolio-core.php`, `readme.txt`, `src/js/project-sidebar.js`
- Test: `tests/Integration/PluginBootTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: services instantiated in `Plugin::__construct` and hooked in `Plugin::boot()` following the existing table in CONTEXT.md §3; REST routes registered on `rest_api_init`.

- [ ] **Step 1: Write failing test** — asserts the four routes appear in `rest_get_server()->get_routes()` after boot.
- [ ] **Step 2: Run to verify failure** — Expected: FAIL.
- [ ] **Step 3: Wire services into `Plugin.php`**
- [ ] **Step 4: Raise version floors**

In `swishfolio-core.php` and `readme.txt`: `Requires at least: 6.4` → `6.7`. Leave `Requires PHP: 7.4` unchanged.

- [ ] **Step 5: Fix the deprecated editor import**

`src/js/project-sidebar.js` line 9: change `from '@wordpress/edit-post'` to `from '@wordpress/editor'`. Run `npm run build` and confirm the project sidebar still renders on a `sfcore_project` edit screen.

- [ ] **Step 6: Run all tests**

Run: `vendor/bin/phpunit --testsuite unit && ddev exec vendor/bin/phpunit -c phpunit-integration.xml.dist`
Expected: all green.

- [ ] **Step 7: Update CONTEXT.md**

Add an AI subsystem section to `CONTEXT.md` §3 and a row per new service, keeping the file regeneration-grade as its header promises.

- [ ] **Step 8: Commit**

```bash
git add src/Plugin.php swishfolio-core.php readme.txt src/js/project-sidebar.js CONTEXT.md tests/Integration/PluginBootTest.php
git commit -m "feat(ai): wire AI services into plugin boot; require WP 6.7"
```

---

## Not in this plan

Part 2 covers milestones 5–7 from the spec — the AI sidebar, block toolbar button, selection context builder in JS, change preview, batched apply with single-step undo, Playwright E2E, and the recorded-response eval suite. It is written once Task 17 lands, because its React state shape depends on the actual response format proven here.

Out of scope entirely: page and site generation, template and global-styles mutation, image and SEO features, the hosted API and credits, the Anthropic provider, the AI history panel, WordPress.org submission, theme generalization.
