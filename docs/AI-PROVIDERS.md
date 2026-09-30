# AI providers

How to get an API key for each provider Swishfolio Core supports, how to save
it, and how to check it works.

You bring your own key. There is no hosted Swishfolio service, no account, no
credits and no subscription — your provider bills you directly, and the plugin
never sees a payment detail.

**Before you start:** read the **External services** section of `readme.txt`
and `docs/PRIVACY.md` §3.1. They list exactly what is transmitted on every AI
request. Until you save a key, the plugin makes no AI requests at all.

---

## 1. Which providers are supported

| Provider slug | What it is | Credentials needed |
|---|---|---|
| `openai` | OpenAI's own API, at `https://api.openai.com/v1`. | `api_key` |
| `openai-compatible` | Any endpoint that speaks OpenAI's chat-completions protocol — OpenRouter, Groq, Together, a local Ollama, and others. | `api_key` **and** `base_url` |

Both are implemented over `wp_remote_post()`; no SDK is vendored. The
compatible provider is the same adapter with a configurable endpoint, so
anything that accepts an OpenAI-shaped `POST /chat/completions` with a
`response_format` of `json_schema` should work.

A third-party plugin can register another provider through the
`swishfolio_ai_providers` filter — see §8.

> **Not yet supported:** Anthropic and Google have no provider class in this
> release. An Anthropic provider is the next one planned.

---

## 2. There is no settings screen in 1.0.3

The AI settings page and the in-editor "Ask AI" interface are not part of this
release. The REST routes are live and working, so configuration is done through
`GET`/`POST swishfolio/v1/ai/settings` or through WP-CLI. Both are described in
§5.

This is worth saying plainly: if you are looking for **Settings → AI**, it is
not there yet.

---

## 3. OpenAI

### Getting a key

1. Create an account at <https://platform.openai.com/>. This is the developer
   platform, which is billed separately from a ChatGPT Plus subscription — a
   ChatGPT subscription does not include API access.
2. Add a payment method and, strongly recommended, set a **monthly budget
   limit** under Settings → Limits. This is your real protection against a
   runaway bill; the plugin's per-user rate limit is a second line, not a
   first.
3. Create a key under **API keys → Create new secret key**. Give it a name you
   will recognise later, such as the site's domain.
4. Copy it immediately. OpenAI shows a key once; if you lose it you must create
   another.

A key looks like `sk-proj-…`. Treat it as a password — anyone holding it can
spend against your account.

### Models

The provider class offers two models in its list:

| Model ID | Notes |
|---|---|
| `gpt-4o` | The default. Handles the structured-output schema this plugin sends. |
| `gpt-4o-mini` | Cheaper and faster; a reasonable choice for straightforward copy edits. |

The stored `model` value is a free-form string and is not checked against that
list, so you can save any model identifier your account has access to. Two
requirements, though: the model must support OpenAI's **structured outputs**
(`response_format: { type: "json_schema", strict: true }`), because the plugin
relies on schema enforcement, and it must accept a `max_tokens` parameter. A
model that does not will return an error rather than silently misbehaving.

### Cost, roughly

A hero-block edit costs on the order of 1,500 input tokens, most of which is
the byte-stable prefix (rules, manifests, token slugs) that providers can serve
from a prompt cache on repeat requests. Output is a few hundred tokens. At
`gpt-4o-mini` prices that is a fraction of a cent per edit. Check your
provider's current pricing rather than trusting this paragraph — and watch the
real numbers via `GET /ai/usage`, which records input and output tokens from
the very first request.

---

## 4. OpenAI-compatible endpoints

Select provider `openai-compatible` and supply both an `api_key` and a
`base_url`. The plugin appends `/chat/completions` to the base URL, so give the
API root — not the full endpoint path.

| Service | `base_url` | Where to get a key |
|---|---|---|
| OpenRouter | `https://openrouter.ai/api/v1` | <https://openrouter.ai/keys> |
| Groq | `https://api.groq.com/openai/v1` | <https://console.groq.com/keys> |
| Together AI | `https://api.together.xyz/v1` | <https://api.together.ai/settings/api-keys> |
| Ollama (local) | `http://localhost:11434/v1` | Any non-empty string; Ollama ignores the key |

With `openai-compatible` you must type the model identifier yourself — the
provider class returns an empty model list on purpose, because the endpoint
decides what it serves. Use whatever that service calls the model, for example
`openai/gpt-4o-mini` on OpenRouter or `llama-3.3-70b-versatile` on Groq.

Two things to check before choosing an endpoint:

- **Structured outputs.** The plugin sends a strict JSON schema and expects the
  reply to honour it. Endpoints that ignore `response_format` will usually
  return prose, which fails as "the AI returned a reply that was not valid
  JSON". Small local models are the common disappointment here.
- **Whose terms apply.** Your data goes to the host you name. This plugin
  cannot vouch for it and cannot link you to its policies. Read them yourself
  before entering the URL.

For a local Ollama, WordPress must be able to reach the address you give. Inside
a container, `localhost` is the container, not your machine — use the host
gateway address your setup provides.

---

## 5. Saving a key

Both methods below require administrator rights (`manage_options`).

### Option A — WP-CLI (simplest, and the key never crosses the network)

```bash
wp eval '
$settings = new SwishfolioCore\AI\Settings( new SwishfolioCore\Services\EncryptionService() );
$settings->save( [
    "provider" => "openai",
    "model"    => "gpt-4o-mini",
    "enabled"  => true,
    "api_key"  => "sk-proj-REPLACE-ME",
] );
echo "saved\n";
'
```

For an OpenAI-compatible endpoint, add the base URL:

```bash
wp eval '
$settings = new SwishfolioCore\AI\Settings( new SwishfolioCore\Services\EncryptionService() );
$settings->save( [
    "provider" => "openai-compatible",
    "model"    => "openai/gpt-4o-mini",
    "base_url" => "https://openrouter.ai/api/v1",
    "enabled"  => true,
    "api_key"  => "sk-or-REPLACE-ME",
] );
echo "saved\n";
'
```

On multisite add `--url=<site>`; AI settings are per-site.

Shell history will contain your key. Clear it afterwards, or prefix the command
with a space if your shell is configured to skip such lines.

### Option B — the REST route

```bash
curl -X POST 'https://example.com/wp-json/swishfolio/v1/ai/settings' \
  -u 'admin_user:xxxx xxxx xxxx xxxx xxxx xxxx' \
  -H 'Content-Type: application/json' \
  -d '{
        "provider": "openai",
        "model": "gpt-4o-mini",
        "enabled": true,
        "api_key": "sk-proj-REPLACE-ME"
      }'
```

The `-u` value is a WordPress **Application Password** (Users → Profile →
Application Passwords), not the account password. From inside wp-admin
JavaScript you would instead send the `X-WP-Nonce` header, as with any
cookie-authenticated REST call.

The response echoes the saved settings **without** the key:

```json
{ "settings": { "provider": "openai", "model": "gpt-4o-mini",
                "base_url": "", "enabled": true, "hasKey": true } }
```

### What the fields mean

| Field | Notes |
|---|---|
| `provider` | `openai` or `openai-compatible`. **Not validated on save** — a typo is stored as given, and `/ai/edit` then fails with HTTP 409 "AI provider … is not available". |
| `model` | Free-form string. Not validated against the provider's model list. |
| `base_url` | Used by `openai-compatible` only. Ignored by the `openai` provider, which always uses `https://api.openai.com/v1`. |
| `enabled` | Stored, but **not currently enforced anywhere** in the code. It does not act as an off switch. To stop AI requests, clear the key (§7). |
| `api_key` | Encrypted with AES-256-CBC before being written. Sending an **empty** `api_key` preserves the stored one, which is what lets a masked form be resubmitted. |

### Reading the settings back

```bash
wp eval '
$s = new SwishfolioCore\AI\Settings( new SwishfolioCore\Services\EncryptionService() );
print_r( $s->get() );
'
```

`get()` returns `hasKey` as a boolean and never the key itself — by design, so
the key cannot leak through the REST layer.

---

## 6. Checking it works

```bash
curl -X POST 'https://example.com/wp-json/swishfolio/v1/ai/test-provider' \
  -u 'admin_user:xxxx xxxx xxxx xxxx xxxx xxxx'
```

A success looks like `{"ok":true,"message":"Connection successful."}`. A failure
returns `ok: false` and the provider's own error message.

This sends a real one-token chat completion to the provider, so it costs a
fraction of a cent and will fail if your account has no credit — which is
usually what you wanted to find out.

> **Known limitation:** the test uses the first entry of the provider's model
> list, and `openai-compatible` deliberately publishes an empty list. The test
> therefore sends no usable model name and will fail against a compatible
> endpoint even when the configuration is correct. Verify a compatible endpoint
> with a real `/ai/edit` request instead.

### Common failures

| Symptom | Likely cause |
|---|---|
| `409` `AI provider "…" is not available` | The `provider` value is not `openai` or `openai-compatible`. |
| `409` `No AI provider API key has been configured` | No key stored, or `AUTH_KEY` changed since it was saved so the ciphertext no longer decrypts. Re-save the key. |
| `401` / `Incorrect API key provided` | Wrong or revoked key. |
| `429` with a provider message | Your provider is rate-limiting you. The plugin retries 429 and 5xx up to three times with exponential backoff before giving up. |
| `429` `Too many AI requests` | The plugin's own per-user limit: 30 per hour. |
| `The AI returned a reply that was not valid JSON` | The endpoint ignored the JSON schema. Common with small local models. |
| `422` `AI could not complete this request` | The reply was well-formed but broke a validation rule. The response body names the `stage` and `path`. |

### Watching what you spend

```bash
curl 'https://example.com/wp-json/swishfolio/v1/ai/usage' \
  -u 'admin_user:xxxx xxxx xxxx xxxx xxxx xxxx'
```

Lifetime token counters, per-stage rejection counts, and the last 100 requests
(provider, model, tokens, duration, outcome). No prompt text or block content is
recorded — see `docs/PRIVACY.md` §2.1.

---

## 7. Rotating or removing a key

**Rotate:** revoke the old key at the provider, then save the new one exactly as
in §5. `save()` overwrites the stored ciphertext.

**Remove, and stop all AI requests:**

```bash
wp option delete swishfolio_ai_settings
```

With no key stored, `ProviderRegistry::configured()` raises and `/ai/edit`
returns HTTP 409 without contacting anyone. Deleting the key is the reliable off
switch; the `enabled` flag is not (§5).

Also revoke the key at the provider. A key deleted from your database is still
valid at OpenAI until you revoke it there.

---

## 8. Operational controls

```php
// Cap AI requests at 5 per user per hour. Returning 0 REMOVES the cap.
add_filter( 'swishfolio_ai_rate_limit', function () {
    return 5;
} );

// Allow at most 6 changes in one AI response (default 25).
add_filter( 'swishfolio_ai_max_actions', function () {
    return 6;
} );

// Restrict AI editing to Editors and above.
add_filter( 'user_has_cap', function ( $allcaps ) {
    if ( empty( $allcaps['edit_others_posts'] ) ) {
        unset( $allcaps['swishfolio_use_ai'] );
    }
    return $allcaps;
}, 20 );

// Register an additional provider.
add_filter( 'swishfolio_ai_providers', function ( $providers ) {
    $providers['my-provider'] = new My_Provider();   // AIProviderInterface
    return $providers;
} );
```

A custom provider must implement
`SwishfolioCore\AI\Contracts\AIProviderInterface`: `getSlug()`, `getLabel()`,
`getRequiredFields()`, `getModels()`, `setCredentials()`, `testConnection()`,
`generate( AIRequest ): AIResponse`, `getLastError()`. Extending
`AbstractAIProvider` gives you the retry, timeout and error-mapping behaviour
for free; override the single `post()` seam if you need different transport.

### Request behaviour

| | |
|---|---|
| HTTP timeout | 60 seconds |
| Attempts | 3 |
| Retried on | HTTP 429 and 5xx, and WordPress transport errors |
| Not retried | Any other 4xx |
| Backoff | 250 ms, doubling per attempt |
| Output token cap | 4096 |
| Max actions per response | 25 (filterable) |
| Requests per user per hour | 30 (filterable) |

---

## 9. Choosing a provider

- **Starting out, or unsure:** OpenAI with `gpt-4o-mini`. Structured outputs
  are reliable, cost per edit is negligible, and the failure messages are
  legible.
- **Want to compare models without more accounts:** OpenRouter through the
  compatible provider. One key, many models.
- **Want speed:** Groq, with the structured-output caveat in §4.
- **Want nothing to leave your network:** a local Ollama. Be realistic about
  schema compliance — verify with a real edit before committing to it, because
  `/ai/test-provider` cannot check a compatible endpoint (§6).

## Anthropic (Claude)

1. Create a key at <https://console.anthropic.com/settings/keys>.
2. In WordPress: **Swish Forms → AI Settings**.
3. Provider **Anthropic (Claude)**, model one of:

   | Model | When |
   |---|---|
   | `claude-opus-5` | Best judgement. Default. |
   | `claude-sonnet-5` | Faster and cheaper, still strong. |
   | `claude-haiku-4-5` | Cheapest — good for a first smoke test. |

4. Paste the key, tick **both** boxes (enable + acknowledgement), save.

Notes specific to this provider:

- Structured output uses `output_config.format` with a JSON Schema. The older `output_format` key is deprecated and `response_format` is OpenAI's.
- Auth is `x-api-key` plus `anthropic-version`, not a bearer token.
- The system prompt is a top-level field, which is also what keeps the cache prefix stable.
- `output_config.effort` is sent only for `claude-opus-5` and `claude-sonnet-5`; **Haiku 4.5 rejects it**, so it is omitted there. Tune with the `swishfolio_ai_effort` filter (default `medium`, chosen for editor latency).
- Adaptive thinking is on by default, so a thinking block can precede the text block. The JSON is read from the first *text* block.
- HTTP timeout defaults to 120s (`swishfolio_ai_http_timeout`), because reasoning models routinely exceed 60s.

### Testing from the command line

Faster than clicking through the editor, and it prints the validated actions:

```bash
# Show the assembled prompt, make no request:
wp swishfolio ai-test "make this feel more premium" --dry-run

# Make a real, billed request:
wp swishfolio ai-test "make this feel more premium and tighten the headline"

# Cheap smoke test, or a different block:
wp swishfolio ai-test "make it bolder" --model=claude-haiku-4-5
wp swishfolio ai-test "rewrite this for a professional audience" --block=core/paragraph
```

Under DDEV, prefix with `ddev exec -d /var/www/html/wordpress` and add `--url=<your-site>`.
