# Pressento Shared

Shared primitives plugin for the [WP Lingua](https://github.com/pressento/wp-lingua) family.

## What it provides

| Class | Description |
|---|---|
| `Pressento_Taxonomy` | Registers the `Pressento_group` taxonomy used to link translated posts. Exposes the `Pressento_supported_post_types` filter so any plugin can add its own post types. |
| `Pressento_Post_Meta` | Registers and provides get/set access to the `_Pressento_language` post meta field that stores a post's language code (e.g. `ko`, `en`, `ja`). |

## Installation

### As a standard plugin (WordPress 6.5+)

1. Install and activate **Pressento Shared** before activating any Pressento-family plugin.
2. Pressento-family plugins declare `Requires Plugins: pressento-shared` in their headers — WordPress 6.5+ will warn if the dependency is missing.

### As a must-use plugin (recommended for stable environments)

Copy or symlink the `pressento-shared` directory into `wp-content/mu-plugins/`. Must-use plugins are always loaded before regular plugins, so load-order issues are eliminated.

```
wp-content/
  mu-plugins/
    pressento-shared/
      pressento-shared.php
      includes/
        class-taxonomy.php
        class-post-meta.php
```

> **Note:** WordPress does not auto-load subdirectory mu-plugins. Add a loader stub in `wp-content/mu-plugins/pressento-shared-loader.php`:
>
> ```php
> <?php require __DIR__ . '/pressento-shared/pressento-shared.php';
> ```

## Extending supported post types

Any plugin can register additional post types for Pressento features via the `Pressento_supported_post_types` filter:

```php
add_filter( 'Pressento_supported_post_types', function ( array $types ): array {
    $types[] = 'my_custom_post_type';
    return $types;
} );
```

## License

Apache-2.0 — see [LICENSE](https://www.apache.org/licenses/LICENSE-2.0).
