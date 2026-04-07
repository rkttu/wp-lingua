# Lingua Shared

Shared primitives plugin for the [WP Lingua](https://github.com/pressento/wp-lingua) family.

## What it provides

| Class | Description |
|---|---|
| `Lingua_Taxonomy` | Registers the `Lingua_group` taxonomy used to link translated posts. Exposes the `Lingua_supported_post_types` filter so any plugin can add its own post types. |
| `Lingua_Post_Meta` | Registers and provides get/set access to the `_Lingua_language` post meta field that stores a post's language code (e.g. `ko`, `en`, `ja`). |

## Installation

### As a standard plugin (WordPress 6.5+)

1. Install and activate **Lingua Shared** before activating any Lingua-family plugin.
2. Lingua-family plugins declare `Requires Plugins: lingua-shared` in their headers — WordPress 6.5+ will warn if the dependency is missing.

### As a must-use plugin (recommended for stable environments)

Copy or symlink the `lingua-shared` directory into `wp-content/mu-plugins/`. Must-use plugins are always loaded before regular plugins, so load-order issues are eliminated.

```
wp-content/
  mu-plugins/
    lingua-shared/
      lingua-shared.php
      includes/
        class-taxonomy.php
        class-post-meta.php
```

> **Note:** WordPress does not auto-load subdirectory mu-plugins. Add a loader stub in `wp-content/mu-plugins/lingua-shared-loader.php`:
>
> ```php
> <?php require __DIR__ . '/lingua-shared/lingua-shared.php';
> ```

## Extending supported post types

Any plugin can register additional post types for Lingua features via the `Lingua_supported_post_types` filter:

```php
add_filter( 'Lingua_supported_post_types', function ( array $types ): array {
    $types[] = 'my_custom_post_type';
    return $types;
} );
```

## License

Apache-2.0 — see [LICENSE](https://www.apache.org/licenses/LICENSE-2.0).
