# WP Lingua

[![Tests](https://github.com/pressento/wp-lingua/actions/workflows/test.yml/badge.svg)](https://github.com/pressento/wp-lingua/actions/workflows/test.yml)
[![Release](https://img.shields.io/github/v/release/pressento/wp-lingua)](https://github.com/pressento/wp-lingua/releases/latest)
[![License](https://img.shields.io/github/license/pressento/wp-lingua)](LICENSE.txt)

A lightweight, minimally invasive multilingual plugin for WordPress. WP Lingua links translated posts through a custom taxonomy without modifying WordPress core or theme files — everything runs as a self-contained plugin.

## Requirements

| Item | Version |
| --- | --- |
| WordPress | 6.1 or higher (tested up to 6.9) |
| PHP | 7.4 or higher |

Block editor features (Gutenberg block) require WordPress 6.1+ for Block API v3 and `get_block_wrapper_attributes()` support.

## Installation

### From GitHub Releases

1. Download the latest `wp-lingua.zip` from the [Releases](https://github.com/pressento/wp-lingua/releases) page.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin.

### From Source

```bash
cd wp-content/plugins
git clone https://github.com/pressento/wp-lingua.git wp-lingua
```

Then activate via **Plugins** in the admin dashboard.

## Features

### Translation Architecture

- **Custom taxonomy** (`lingua_group`) silently groups translated posts together. Each translation group is a single non-public term shared by all posts that are translations of each other.
- **Post meta** (`_lingua_language`) stores a short language code (e.g. `ko`, `en`, `ja`) per post.
- Supports `post` and `page` post types by default, extensible via the `lingua_supported_post_types` filter.

### Site-Wide Locale Switching

- Dynamically switches WordPress locale via the `locale` filter based on the visitor's language.
- Automatic language detection from the browser's `Accept-Language` header.
- Language priority: `?lang=` query parameter → cookie → browser detection → site default.
- The `<html lang="...">` attribute updates to match the active language.

### Frontend Language Switcher

- **Per-post switcher**: automatically appended before post content on singular views, linking to available translations.
- **Global switcher**: site-wide dropdown or button-style switcher.
- Available as:
  - **Gutenberg block** (`lingua/language-switcher`) — works in the site editor for block themes.
  - **Shortcodes** — `[lingua_switcher]` (per-post) and `[lingua_global_switcher style="dropdown|buttons"]` (global).
  - **Classic widget** — for themes with widget areas.

### Admin Interface

- **Translations meta box** on the post editor sidebar: set language, view linked translations, create new translation drafts with one click.
- **Language column** on post/page list tables with translation count.
- **Language filter dropdown** on list tables to filter posts by language.
- **Settings page** (Settings → Lingua Languages): configure enabled languages and default language.
- **Plugin action link** for quick access to settings from the Plugins page.

### Supported Languages (default)

한국어, English, 日本語, 中文, Español, Français, Deutsch, Português, Tiếng Việt, ไทย

Customizable via the `lingua_available_languages` and `lingua_locale_map` filters.

## Design Principles

- **Plugin-only**: zero modifications to WordPress core, database schema, or theme files. Deactivating the plugin restores the site to its original state.
- **Minimal footprint**: one custom taxonomy + one post meta field. No custom database tables.
- **Standard APIs only**: Taxonomy API, Post Meta API, Settings API, Widget API, Block API, Shortcode API, and filter/action hooks.
- **No build step**: the Gutenberg block uses plain JavaScript with `wp` globals — no Node.js, webpack, or build tooling required.
- **No external dependencies**: no third-party services, no remote API calls.

## Plugin Structure

```text
plugins/wp-lingua/
├── wp-lingua.php                         # Main entry point & bootstrapper
├── includes/
│   ├── class-languages.php               # Language definitions & locale mapping
│   ├── class-settings.php                # Admin settings page (Settings API)
│   ├── class-taxonomy.php                # Custom taxonomy registration
│   ├── class-post-meta.php               # Post meta registration & helpers
│   ├── class-translation-group.php       # Translation group CRUD logic
│   ├── class-rest-controller.php         # REST API controller (lingua/v1)
│   ├── class-frontend.php                # Frontend switcher, query filtering, shortcodes
│   ├── class-locale-switcher.php         # Dynamic locale switching
│   ├── class-widget-switcher.php         # Classic widget
│   └── class-admin.php                   # Admin meta box, columns, filters
├── assets/
│   └── css/switcher.css                  # Switcher styles
└── blocks/
    └── language-switcher/
        ├── block.json                    # Block definition (apiVersion 3)
        ├── editor.js                     # Block editor script
        ├── editor.asset.php              # Script dependencies
        └── render.php                    # Server-side block rendering
```

## Development Environment

This repository includes a Docker Compose testbed for local development and testing. Compatible with both Docker and Podman (rootless).

### Services

| Service | Description | URL |
| --- | --- | --- |
| **WordPress** | Latest WordPress with debug mode | <http://localhost:8080> |
| **MariaDB 11** | Database server | `localhost:3306` |
| **phpMyAdmin** | Database management UI | <http://localhost:8081> |
| **Mailpit** | SMTP catch-all for email testing | <http://localhost:8025> |
| **WP-CLI** | Command-line WordPress management | `docker compose run wpcli ...` |

### Quick Start

```bash
# Start the environment (auto-installs WordPress on first run)
docker compose up -d
```

The `setup` service automatically:

- Installs WordPress core with admin credentials
- Installs language packs (ko_KR, ja, zh_CN, es_ES, fr_FR, de_DE, pt_BR, vi, th)
- Sets locale, timezone, and permalink structure
- Activates the WP Lingua plugin

### Default Admin Account

| Item | Value |
| --- | --- |
| URL | <http://localhost:8080/wp-admin> |
| Username | `admin` |
| Password | `admin` |

Configurable in [.env](.env):

```env
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
WP_ADMIN_EMAIL=admin@example.com
WP_LOCALE=ko_KR
WP_TIMEZONE=Asia/Seoul
WP_ACTIVATE_PLUGINS=wp-lingua
WP_LANGUAGE_PACKS=ko_KR,ja,zh_CN,es_ES,fr_FR,de_DE,pt_BR,vi,th
```

### Common Commands

```bash
# Stop the environment
docker compose down

# Reset everything (delete volumes, fresh install)
docker compose down -v

# WP-CLI examples
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli theme list
docker compose run --rm wpcli db export - > backup.sql
```

### Repository Layout

```text
.
├── docker-compose.yml          # Service orchestration
├── docker-compose.test.yml     # Test environment (PHPUnit + isolated DB)
├── Dockerfile                  # Custom WordPress image (dev PHP config, Mailpit SMTP)
├── Dockerfile.test             # PHPUnit test runner image
├── wp-setup.sh                 # Auto-setup script
├── .env                        # Environment configuration
├── .github/workflows/
│   ├── release.yml             # Build & release on tag push
│   └── test.yml                # PHPUnit CI on push/PR
├── plugins/                    # → wp-content/plugins (bind mount)
│   └── wp-lingua/              # The plugin
├── themes/                     # → wp-content/themes (bind mount)
├── tests/                      # PHPUnit test suite
│   ├── bootstrap.php
│   ├── phpunit.xml
│   ├── test-rest-controller.php
│   ├── test-translation-group.php
│   └── bin/
│       ├── install-wp-tests.sh
│       └── run-tests.sh
└── uploads/                    # → wp-content/uploads (bind mount)
```

## REST API

WP Lingua provides a dedicated REST API under the `lingua/v1` namespace for managing translation groups programmatically — useful for external publishing scripts (e.g. Hugo → WordPress) without exposing the internal taxonomy.

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/wp-json/lingua/v1/link` | Link posts into a translation group |
| `DELETE` | `/wp-json/lingua/v1/unlink/{post_id}` | Remove a post from its translation group |
| `GET` | `/wp-json/lingua/v1/translations/{post_id}` | Get all translations for a post |

### POST /lingua/v1/link

Accepts two formats:

**Language map** (sets `_Lingua_language` meta automatically):

```json
{ "post_ids": { "ko": 10, "en": 20, "ja": 30 } }
```

**Plain array** (posts must already have `_Lingua_language` meta set):

```json
{ "post_ids": [10, 20, 30] }
```

Requires `edit_posts` capability. Returns the `group_term_id` and linked post mapping.

### GET /lingua/v1/translations/{post_id}

Returns all translations in the same group:

```json
{
  "post_id": 10,
  "translations": {
    "ko": { "post_id": 10, "title": "한국어 포스트", "status": "publish", "link": "..." },
    "en": { "post_id": 20, "title": "English Post", "status": "publish", "link": "..." }
  }
}
```

Requires `read` capability.

### DELETE /lingua/v1/unlink/{post_id}

Removes the specified post from its translation group. Requires `edit_posts` capability.

## Testing

The project includes a PHPUnit integration test suite that runs against the WordPress test library inside Docker.

### Running Tests

```bash
# Run the full test suite
docker compose -f docker-compose.test.yml up --build --abort-on-container-exit

# Clean up after tests
docker compose -f docker-compose.test.yml down -v
```

### Test Infrastructure

| File | Purpose |
| --- | --- |
| `docker-compose.test.yml` | Test-only Compose (isolated DB with tmpfs, PHPUnit container) |
| `Dockerfile.test` | PHP 8.2 CLI + PHPUnit 9 + MySQL client + Polyfills |
| `tests/bootstrap.php` | PHPUnit bootstrap (loads WP test lib + plugin) |
| `tests/phpunit.xml` | PHPUnit configuration |
| `tests/test-rest-controller.php` | REST API endpoint tests (13 cases) |
| `tests/test-translation-group.php` | Translation group unit tests (8 cases) |
| `tests/bin/install-wp-tests.sh` | Downloads WordPress test library |
| `tests/bin/run-tests.sh` | Container entrypoint (install + run) |

The test database uses `tmpfs` and is automatically cleaned up after each run. Each test method is isolated via database transaction rollback.

### CI/CD

Tests run automatically on every push to `main` and on pull requests via GitHub Actions (`.github/workflows/test.yml`).

## Hooks & Filters

| Hook | Type | Description |
| --- | --- | --- |
| `lingua_supported_post_types` | filter | Post types that support translations (default: `post`, `page`) |
| `lingua_available_languages` | filter | Available language list (code → label) |
| `lingua_locale_map` | filter | Short code → WP locale mapping |
| `lingua_default_language` | filter | Default language code |
| `lingua_translation_created` | action | Fired after a new translation draft is created |

## License

Copyright 2026 Jung Hyun, Nam

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

> <http://www.apache.org/licenses/LICENSE-2.0>

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
