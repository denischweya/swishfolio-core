# Privacy

What Swishfolio Core stores, where it stores it, how long it keeps it, what
leaves your site, and how to delete all of it.

Everything below is a statement about the code in this plugin at version 1.0.3.
Where the plugin does *not* do something, that is stated plainly rather than
left to inference.

---

## 1. Summary

| | |
|---|---|
| Analytics or telemetry sent to the plugin author | **None.** No code path contacts a Swishfolio server. |
| Licence or update check | **None.** |
| Remote scripts, styles or fonts loaded at runtime | **None.** |
| Data sent to third parties | Only to services **you** configure: an AI provider, an email service provider, your own SMTP host. All inert until you save credentials. |
| Personal data stored on your site | Form submissions (email address in clear, submitted field values, a salted hash of the submitter's IP). |
| Automatic deletion | **None.** Nothing is pruned or expired except the rolling AI usage window and short-lived transients. |
| `uninstall.php` | **Does not exist.** Deleting the plugin leaves every option and post listed below in your database. |
| WordPress privacy-tool integration | **Not implemented.** The plugin registers no privacy-policy suggestion, no personal-data exporter and no eraser. See §7. |

---

## 2. What is stored on your site

### 2.1 Options (`wp_options`)

**`swishfolio_ai_settings`** — created the first time AI settings are saved.
Stored with autoload off.

| Key | Contents |
|---|---|
| `provider` | Provider slug: `openai` or `openai-compatible`. |
| `model` | Model identifier, e.g. `gpt-4o`. |
| `base_url` | Endpoint base URL, for the OpenAI-compatible provider only. |
| `enabled` | Boolean flag. |
| `api_key` | Your provider API key, **encrypted** with AES-256-CBC before it is written. |

The raw key is never returned over REST. `Settings::get()` exposes only
`hasKey` (a boolean); `Settings::getKey()` is server-side only and is called
solely when building a provider request. Source: `src/AI/Settings.php`.

**`swishfolio_ai_usage`** — created on the first AI request. Stored with
autoload off. Two parts:

*Lifetime counters:* `requests`, `inputTokens`, `outputTokens`,
`actionsProposed`, `actionsAccepted`, and `failures` (a count per validation
stage that rejected a response).

*A rolling window of the most recent 100 requests.* Each entry holds the
provider slug, the model identifier, input and output token counts, the number
of actions proposed, the round-trip duration in milliseconds, an outcome string
(`proposed`, or `rejected:<stage>`), and a Unix timestamp. Older entries fall
off the end automatically.

**No prompt text, no block content, no instruction text and no provider
response body is recorded.** No user ID is recorded against a usage entry.
Source: `src/AI/UsageLogger.php`.

**`swish_forms_email_settings`** — form notification email settings: SMTP
enabled flag, host, port, encryption mode, username, the SMTP password
(encrypted with AES-256-CBC), plus `from_email` and `from_name`. Written by
`templates/admin/email-settings.php`.

**`swish_forms_esp_settings`** — email service provider credentials, keyed by
provider slug. Credential fields are encrypted with AES-256-CBC before being
written. Written by `templates/admin/integrations.php`.

### 2.2 Transients

| Transient | Contents | Lifetime |
|---|---|---|
| `sfcore_ai_manifests_<plugin version>` | Cached `ai.json` capability manifests. No personal data. | 24 hours, and invalidated by a plugin version change. |
| `sfcore_ai_rate_<user ID>` | An integer request count, for the AI rate limit. | 1 hour. |
| `swish_form_rate_<md5 of submitter IP>` | An integer submission count, for the form rate limit. The visitor's IP appears only inside the MD5-hashed transient *name*, never as a stored value. | 1 hour. |

### 2.3 Form entries (`swish_form_entry` post type)

Every form submission creates one post of the private, non-public
`swish_form_entry` type.

| Where | What |
|---|---|
| `post_title` | The submitter's email address if one was submitted, otherwise `Submission on <date>`. |
| `post_content` | The submitted field values, JSON-encoded. |
| `_swish_form_id` | The form's identifier. |
| `_swish_form_type` | `contact` or `subscription`. |
| `_swish_submission_email` | The submitter's email address, **in clear text**. |
| `_swish_submission_ip` | A SHA-256 hash of the submitter's IP address salted with your site's `AUTH_SALT`. The raw IP is never stored. |
| `_swish_form_data` | The submitted field values as an array. |
| `_swish_esp_synced` | Boolean: whether the entry was forwarded to an email service provider. |

Source: `src/Forms/FormEntryPostType.php`.

**Retention: indefinite.** Entries are never expired, archived or purged by
this plugin.

### 2.4 Project content (`sfcore_project` post type)

Projects are ordinary editorial content you author, not collected data. Their
meta keys are `_sfcore_project_status`, `_sfcore_completion_date`,
`_sfcore_skills`, `_sfcore_project_url` and `_sfcore_client_name`. Note that
`_sfcore_client_name` may name a real person or company, so treat it as you
would any other content field.

### 2.5 What is *not* stored

- No AI prompt, instruction, block content or provider response.
- No raw visitor IP address, anywhere.
- No record of which user made which AI request.
- No cookies are set by this plugin.
- No custom database tables are created.

---

## 3. What leaves your site

Nothing leaves your site until you save credentials for a service. With no
credentials saved, this plugin makes no outbound HTTP requests of its own.

### 3.1 To an AI provider

**Trigger.** One request per AI edit, and only when an administrator has saved
an API key *and* a logged-in user with the `edit_posts` capability and edit
rights on the target post invokes the `/ai/edit` route. There is no scheduled
call, no call on activation, no call on page load, and nothing on the front end.

**Destination.** `https://api.openai.com/v1/chat/completions` for the `openai`
provider; for the `openai-compatible` provider, whatever base URL you saved.

**Payload.** Assembled by `src/AI/Context/ContextAssembler.php`:

*System message (stable across requests for the same selection shape, so the
provider's prompt cache can hold it):*

- Fixed instruction text from `src/AI/Prompt/system/block-editing.txt`.
- The capability manifests for the block types involved — the selected block
  type, the types of its inner blocks, and the types it is allowed to accept as
  children. A manifest is a description of *this plugin*: a purpose word, a
  one-sentence summary, and for each AI-editable attribute its name, group,
  type, and permitted values or range. It contains none of your content.
- The colour, spacing and font-size preset slugs declared by your active
  theme's `theme.json` — slug names only, no CSS values.

*User message:*

- The selected block's client ID, its block type name, and its **complete
  stored attribute set**. For most blocks that includes its visible text
  (headings, body copy, button labels) and any image or link URLs held in those
  attributes.
- The post type being edited and the name of the active style variation.
- For the selected block's parent, siblings and inner blocks: the block type
  name and the purpose word only. **Their attributes and their text are not
  sent.**
- The instruction the user typed.

*Also in the request:* the model name, an output token cap (4096 by default),
the JSON schema the reply must conform to, your API key in the `Authorization`
header, and WordPress's standard outbound `User-Agent` header — which contains
your WordPress version and your site's home URL.

**Not sent:** the post title, the rest of the post's content, other posts,
usernames, email addresses, form entries, any other part of your database, and
anything about your site's visitors.

**What the provider does with it** is governed by your own agreement with that
provider, because you use your own API key. For OpenAI, see
<https://openai.com/policies/privacy-policy/>. For an OpenAI-compatible
endpoint, this plugin cannot tell you — you chose the host.

### 3.2 To an email service provider

If you connect Mailchimp, Kit (ConvertKit), Klaviyo, ActiveCampaign or Brevo
under **Swish Forms → Integrations** and point a form at it, each matching
submission sends the submitter's email address and the submitted field values
to that provider's API so the person can be added to your list. Endpoints and
terms links are listed in the plugin's `readme.txt` under **External services**.

### 3.3 To your SMTP host

If you enable SMTP under **Swish Forms → Email Settings**, form notification
email — which contains the submitted field values — is handed to the SMTP host
you configured instead of to PHP's mail function.

---

## 4. Encryption at rest

The AI provider API key, the SMTP password and email service provider
credentials are encrypted with AES-256-CBC before being written to the options
table. The encryption key is derived as `sha256(AUTH_KEY)` from your site's
`wp-config.php` salts (`src/Services/EncryptionService.php`).

Two consequences you should know about:

1. **If `AUTH_KEY` is not defined**, the service falls back to a fixed string
   that is published in this plugin's source. On such an install the stored
   ciphertext is not meaningfully protected. Every standard WordPress
   installation defines `AUTH_KEY`; confirm yours does.
2. **If you rotate `AUTH_KEY`**, previously stored credentials can no longer be
   decrypted. Re-enter them after rotating salts.

Encryption at rest protects the database row. It is not a substitute for
restricting who has `manage_options` on your site: anyone with that capability
can use the stored key through the plugin.

---

## 5. Who can see what

| Data | Who can reach it |
|---|---|
| AI settings, including whether a key is stored | `manage_options` (administrators), via `GET swishfolio/v1/ai/settings`. |
| The raw AI API key | Nobody, through any route. It is decrypted only inside PHP when a provider request is built. |
| AI usage counters and the recent-request window | `manage_options`, via `GET swishfolio/v1/ai/usage`. |
| The ability to spend your AI budget | Every logged-in user holding `edit_posts` who can edit the target post — by default that includes Contributors. See §6. |
| Form entries | `manage_options`, via **Swish Forms → Form Entries**. |

---

## 6. Limiting who can use AI

The `swishfolio_use_ai` capability is granted by a `user_has_cap` filter to any
user who already holds `edit_posts`; it is never written to a role, so nothing
is persisted and nothing needs cleaning up. **By default this includes
Contributors**, each of whom may make up to 30 AI requests per hour against
your provider account.

To restrict it, add a higher-priority filter in a site plugin:

```php
// Only Editors and above may use AI editing.
add_filter( 'user_has_cap', function ( $allcaps ) {
    if ( empty( $allcaps['edit_others_posts'] ) ) {
        unset( $allcaps['swishfolio_use_ai'] );
    }
    return $allcaps;
}, 20 );
```

To lower the per-user hourly request cap, or disable AI requests outright:

```php
add_filter( 'swishfolio_ai_rate_limit', function () {
    return 5;   // return 0 to remove the limit entirely, not to disable AI
} );
```

Note that returning `0` **removes** the rate limit rather than blocking
requests. To stop AI requests completely, remove the stored API key.

---

## 7. WordPress privacy tools

This plugin does **not** currently integrate with WordPress's built-in privacy
tooling. Specifically:

- It registers no suggested privacy-policy text
  (`wp_add_privacy_policy_content`).
- It registers no personal-data **exporter**, so form entries will not appear
  in a WordPress "Export Personal Data" request.
- It registers no personal-data **eraser**, so form entries will not be removed
  by a WordPress "Erase Personal Data" request.

If you operate under the GDPR, the UK GDPR, the CCPA or a similar regime, you
must handle subject access and erasure requests for form entries manually, as
described in §8, and you must write your own privacy-policy text covering the
AI provider and any email service provider you have connected.

---

## 8. How to delete everything

The plugin ships no `uninstall.php`, so deactivating or deleting it removes
nothing. Remove data explicitly.

**One person's form submissions.** Find them under **Swish Forms → Form
Entries** (they are titled with the submitter's email address) and delete them
there, or with WP-CLI:

```bash
wp post list --post_type=swish_form_entry --post_status=publish \
  --meta_key=_swish_submission_email --meta_value='person@example.com' \
  --format=ids | xargs -r wp post delete --force
```

`--force` skips the trash. Without it the entry remains in `wp_posts` with
status `trash`.

**All form entries.**

```bash
wp post list --post_type=swish_form_entry --post_status=any --format=ids \
  | xargs -r wp post delete --force
```

**All plugin options.**

```bash
wp option delete swishfolio_ai_settings
wp option delete swishfolio_ai_usage
wp option delete swish_forms_email_settings
wp option delete swish_forms_esp_settings
```

**The AI usage record only** (keeps your provider configuration):

```bash
wp option delete swishfolio_ai_usage
```

**Cached manifests and rate-limit counters.** These are transients and expire
on their own within 24 hours. To clear them immediately:

```bash
wp transient delete --all
```

**Project content.** Projects are your editorial content; delete them as you
would any post type:

```bash
wp post list --post_type=sfcore_project --post_status=any --format=ids \
  | xargs -r wp post delete --force
```

On multisite, add `--url=<site>` to each command and repeat per site — options
and posts are per-site.

**Data already sent to a third party** — to OpenAI, to an email service
provider, or to your SMTP host — is outside this plugin's reach. Delete it
through that provider's own controls.

---

## 9. Where these facts come from

| Claim | Source |
|---|---|
| AI settings shape and encryption on save | `src/AI/Settings.php` |
| Usage record shape; no prompt or content recorded | `src/AI/UsageLogger.php` |
| Exactly what is sent to the provider | `src/AI/Context/ContextAssembler.php` |
| Provider endpoint and headers | `src/AI/Providers/OpenAIProvider.php`, `src/AI/Providers/AbstractAIProvider.php` |
| Form entry storage and IP hashing | `src/Forms/FormEntryPostType.php` |
| Form rate limiting | `src/Forms/FormProcessor.php` |
| Encryption key derivation | `src/Services/EncryptionService.php` |
| AI capability grant and rate limit | `src/Plugin.php`, `src/REST/AIController.php` |
