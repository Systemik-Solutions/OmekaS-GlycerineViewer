# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-03-20

### Fixed
- XSS vulnerabilities in block layout form and view template; all user-supplied values are now escaped.
- Pinned Glycerine Viewer CDN dependency to a specific version instead of `@latest`.
- Added `private` visibility to `isStrictHttpUrl` method.
- PSR-2 compliance: added braces to all single-line control structures.
- Removed trailing whitespace across source files.
- Added missing trailing comma in `module.config.php` form_elements array.
- Fixed inconsistent default height values (standardised to `600px`).
- Removed orphaned `giiif_iframe_width`/`giiif_iframe_height` settings references.

### Added
- Class-level and method-level docblocks on all classes and public methods.
- `DEFAULT_WIDTH`, `DEFAULT_HEIGHT`, and `GLYCERINE_VIEWER_VERSION` constants in `Module.php`.
- Extracted inline JS into `glycerine-iiif-inline.phtml` partial with deduplicated viewer init logic.

## [1.0.0] - 2025-11-10

### Added
- Initial release.
- IIIF manifest rendering on item pages via configurable metadata property.
- Site block layout for manual Glycerine Viewer embedding with configurable width/height.
- Module configuration form using Omeka S PropertySelect.
