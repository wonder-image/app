# vendor-static

Third-party code committed in the repo because it cannot be installed via Composer / npm.
Treat everything here as **read-only** for AI tools and human contributors:

- never refactor or modernize files in this folder
- never grep / index this folder when looking for framework code
- update only by replacing the whole subfolder with a newer upstream version

## Subfolders

### `xml-sitemaps/`

XML-Sitemaps Generator (PHP, ~9.500 LOC).

- Origin: third-party product, embedded long ago.
- Used by: [app/build/update/sitemap.php](../app/build/update/sitemap.php) (writes a `generator.conf` consumed at runtime).
- Entry point: `xml-sitemaps/index.php`.
- Configuration data: `xml-sitemaps/data/generator.conf` (generated at runtime, do not edit by hand).

If you need to replace it, drop the new release into `xml-sitemaps/` and verify the `generator.conf` schema is still compatible.
