# ChatProjects shared-state contract

Contract version: **1** (2026-10-09). Applies to: ChatProjects (Free) ≥ 1.3.0 and ChatProjects Pro ≥ 1.3.0.

This file is byte-identical in both repositories and is checked by `bin/contract-check.php`. Change it only by amendment: propose the change to the other product's maintainer, get an acknowledgement, bump the contract version, and commit the same file to both repos.

Free and Pro run on the same WordPress data. Users switch between them, run one after the other, and sometimes have both installed. Everything below must stay compatible in both directions.

## 1. Loading and co-install
- Only one product runs at a time. If Pro is loaded (`CHATPROJECTS_PRO_VERSION` defined), Free's main file returns **before declaring any function or constant**; all of Free's functions live in `includes/bootstrap.php`.
- Pro's top-level functions use the `chatprojects_pro_` prefix. The two main files never declare the same global function name.
- Detection signals:
  - Pro: `CHATPROJECTS_PRO_VERSION`, the plugin file `chatprojects-pro/chatprojects.php`, the plugin Name `ChatProjects Pro`.
  - Free: the plugin file `chatprojects/chatprojects.php`.

## 2. Schema versions
| Option | Owner | Meaning |
|---|---|---|
| `chatprojects_free_db_version` | Free | Free's schema version (1.x) |
| `chatprojects_pro_db_version` | Pro | Pro's schema version (2.x) |
| `chatprojects_db_version` | legacy | Written by Free ≤ 1.2 (1.x values) and Pro ≤ 1.2 (2.x values). Pro still mirrors its version here. |

Each product writes only its own option; Pro also mirrors to the legacy one. Free never writes `chatprojects_db_version`; Pro never touches `chatprojects_free_db_version`. When its own option is missing, a product may adopt the legacy value only if it falls in its own range: Free < 2.0, Pro ≥ 2.0. Anything else counts as a fresh schema.

## 3. Shared tables
Shared: `{prefix}chatprojects_chats`, `{prefix}chatprojects_messages`.

Rule: every column and index that **both** products declare must be identical: name, type, nullability, default, and key columns. A column declared by only one product is allowed; `dbDelta()` never drops columns. Both products create these tables with `dbDelta()` (no `CREATE TABLE IF NOT EXISTS`).

Canonical shared definitions:

```sql
-- {prefix}chatprojects_chats
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
chat_mode varchar(20) DEFAULT 'project',
provider varchar(50) DEFAULT 'openai',
model varchar(100) DEFAULT NULL,
project_id bigint(20) unsigned DEFAULT NULL,
thread_id varchar(255) DEFAULT NULL,
user_id bigint(20) unsigned NOT NULL,
title varchar(255) DEFAULT NULL,
instructions text DEFAULT NULL,
message_count int(11) DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY project_idx (project_id),
KEY user_idx (user_id),
KEY thread_idx (thread_id),
KEY mode_idx (chat_mode),
KEY provider_idx (provider)

-- {prefix}chatprojects_messages
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
chat_id bigint(20) unsigned NOT NULL,
role varchar(20) NOT NULL,
content longtext NOT NULL,
metadata text DEFAULT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY chat_idx (chat_id),
KEY role_idx (role),
KEY created_idx (created_at)
```

- Pro-only columns on chats: `branched_from_chat_id bigint(20) unsigned DEFAULT NULL`, `branched_from_message_id bigint(20) unsigned DEFAULT NULL`, `branch_label varchar(100) DEFAULT NULL`, `KEY branch_idx (branched_from_chat_id)`.
- `chats.model` has no column default (each product's default model differs, so a product-specific default would make the schemas disagree). **Both products MUST set `model` on every insert into `chats`**, including project chats; a new insert path that omits it writes NULL.

Product-only tables (the other product must never create, alter or drop them):
- Free: `chatprojects_indexed_content`, `chatprojects_widget_visitor_sessions`, `chatprojects_widget_visitor_messages`.
- Pro: `chatprojects_widget_chats`, `chatprojects_widget_messages`, and Pro's other tables (subscriptions, usage, credits, teams, prompts, …).

## 4. Encrypted API keys
- Options: `chatprojects_openai_key`, `chatprojects_anthropic_key`, `chatprojects_gemini_key`, `chatprojects_chutes_key`, `chatprojects_openrouter_key`.
- Format: `cpv2:` + base64( 24-byte nonce . `sodium_crypto_secretbox( $plain, $nonce, $key )` ).
- Key: `hash( 'sha256', 'chatprojects_v2|' . $seed, true )`.
- Seed: `CHATPROJECTS_ENCRYPTION_KEY` (if defined and non-empty), else `AUTH_KEY` (unless it is the default phrase), else `SECURE_AUTH_KEY`, else `ABSPATH . DB_NAME`.
- Both products keep the legacy AES-256-CBC reader for values saved before 1.3.0.
- **No code path deletes a stored key it cannot read.** It shows as "not set" until re-entered, and restoring the old salts makes it readable again.

## 5. Destructive operations
- Uninstall removes shared data (shared options, shared tables, `chatpr_project` posts, shared roles/caps) **only when the other product is not installed**.
- Option deletion uses anchored `LIKE` patterns (`chatprojects_%`, never `%chatprojects%`).
- Neither product drops, truncates or deletes rows from the other product's tables.

## 6. Shared options, meta, roles
- Shared options (same name and format in both): the five key options above, `chatprojects_default_model`, `chatprojects_general_chat_provider`, `chatprojects_general_chat_model`, `chatprojects_assistant_instructions`, `chatprojects_allowed_file_types` (array of extensions), `chatprojects_max_file_size` (MB), `chatprojects_keep_data_on_uninstall`.
- Post type `chatpr_project`, role `chatpr_projects_user`, the `*_chatpr_project(s)` capabilities and `manage_chatprojects_settings`.
- Project meta: `_cp_files`, `_cp_instructions`, `_cp_model`, `_cp_vector_store_id`, `_cp_sharing_mode` (`private`|`shared`), `_cp_shared_users`, `_cp_variables`. Free-only: `_cp_widget_enabled`.
- Shared names: filter `chatprojects_trusted_proxy_headers`, constant `CHATPROJECTS_TRUSTED_PROXY_HEADERS`, AJAX nonce `chatpr_ajax_nonce`.
- Open item: the user theme preference meta differs (Free `cp_theme_preference`, Pro `chatpr_theme_preference`). Unify in the next contract version.

## 7. Model catalogue
- Free's `includes/class-model-registry.php` is the source for the chat model catalogue (openai, anthropic, gemini, chutes, openrouter) and the legacy remap table.
- Pro's copy may add keys (e.g. `pricing`) and non-chat catalogues. For every key Free declares, Pro's entries must be identical.
- Stored model ids are migrated conservatively. This covers options, project meta, `chats.model`, and any table that records a past request (usage, transcription or other logs). An id changes only when the legacy remap table maps it to a model that is known at that moment. Everything else is left untouched: ids added through the `chatprojects_models` filter (often not registered yet when upgrades run on `plugins_loaded`), media models, and unknown ids. A migration never substitutes a default.
- `Model_Registry::resolve()` falls back to the provider default, so it is only for the model about to be called at request time. It never rewrites stored data.

## 8. Release order
When a change touches this contract, Pro ships before or together with Free. Old Pro installs don't update automatically unless the store's version metadata is correct.
