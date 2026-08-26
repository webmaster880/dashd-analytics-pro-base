# Changelog

All notable changes to this project are documented in this file.

## [Unreleased] - 2026-05-27

### Changed
- Admin operations layout restructured:
  - `Global Synchronization`, `Auto-Synchronization (WP Cron)`, and `Danger Zone: Wipe Data` moved from main dashboard to `Settings → Data Sources & Raw Data` (placed above `Connected Sources`).
  - `Recent Sync Logs` moved to dedicated `Settings → Logs` tab.
- Main `Analytics Pro Dashboard` simplified to overview cards plus quick links to new `Settings` locations.
- Action redirects updated to the new structure:
  - manual sync now returns to `Settings → Data Sources & Raw Data` with `status=synced`;
  - auto-sync schedule save now returns to `Settings → Data Sources & Raw Data` with `status=cron_saved`;
  - wipe action now returns to `Settings → Data Sources & Raw Data` with `status=wiped`.
- Constructor button styling aligned:
  - dashicons in constructor action buttons now use unified vertical centering and spacing.
- Admin settings header polish:
  - `Update cache` control rebuilt as compact TTL dropdown (`12h..24h`) with unified styling next to `Check updates now`;
  - improved spacing/alignment and responsive behavior for header action controls.
- Admin action buttons unified:
  - toolbar buttons now use consistent spacing, height, and alignment;
  - colored action/danger buttons now force white dashicons for better contrast.
  - plugin action submit buttons now override third-party admin CSS margins (for example WPML button rules) to prevent vertical jumping.
  - header action buttons now explicitly preserve plugin hover/focus/active colors against third-party button rules.
- Raw data period interpretation updated:
  - years with `Q4` are treated as completed annual periods;
  - completed years aggregate all available quarters into one annual chart value;
  - years without `Q4` remain quarter-by-quarter in charts and trend tables.
- Negative raw values are now treated as soft data quality warnings instead of being blocked:
  - admin Raw Data rows with negative values are highlighted and marked as `Needs review`;
  - frontend chart points/bars and detailed table cells show warning markers/tooltips;
  - `Donut` view falls back to `Bar` and `Log` scale falls back to `Linear` when negative values are present.
- Frontend data quality warnings can now be controlled per widget via `show_data_warnings`.
- Frontend/API widgets can now limit chart output with optional `period_start` / `period_end` shortcode attributes (`YYYY-QN`).
- Negative stacked bar segments now receive rounded outer edges on the negative side of the axis.
- Country flag legend now renders as rounded color pills matching each chart series color.

### Added
- Admin Constructor period range dropdowns for selecting the visible chart window.
- Period range controls added to YOOtheme Pro, Elementor, and Gutenberg integrations.
- Country `Flag URL` field in `Countries Translation`, including CSV import/export support.
- Optional frontend HTML legend with circular country flags; falls back to circular color markers when no flag URL is set.
- New `Settings → Logs` management actions:
  - export sync logs to CSV;
  - clear sync logs (with confirmation).
- New settings notice for logs cleanup status (`status=logs_cleared`).
- Documentation update for `build.sh` options:
  - `--comment`;
  - `--yes`;
  - auto commit/push and final status line behavior.
- `get_dashd_periods_split` response extended with period availability metadata:
  - `year_quarters` map of available quarters per year;
  - `latest` period object (`year` + `quarter`) for deterministic default selection.
- GitHub-based plugin updater module:
  - WordPress admin now can show new-version notifications from GitHub Releases;
  - supports private repositories via token (`DASHD_GITHUB_TOKEN`);
  - update metadata and plugin info are injected through native WP update hooks.
- `Settings` header now includes `Check updates now` action to force refresh plugin update metadata.
- Release automation in `build.sh`:
  - new `--publish-release` option creates/pushes tag and publishes (or updates) GitHub Release ZIP asset via `gh` CLI.
- GitHub updater cache TTL control:
  - new admin setting in `Analytics Pro → Settings` (`Update cache (hours)`);
  - saved option `dashd_github_updater_cache_ttl_hours`;
  - bounded cache window `12..24` hours with instant cache reset after save.
- New data quality warning toggle added to shortcode generation and builder integrations:
  - Admin Constructor;
  - Elementor;
  - Gutenberg;
  - YOOtheme Pro.

### Fixed
- PDF report export now preserves the custom HTML legend styling:
  - country legend pills keep their chart colors in generated PDFs;
  - circular flag/color markers are normalized before `html2canvas` capture;
  - a temporary PDF-only legend is injected from visible chart datasets when the interactive or canvas legend is hidden;
  - Chart.js canvas legend is temporarily disabled during PDF capture to avoid duplicate legends;
  - export uses safer image capture options to avoid cross-origin flag images breaking PDF generation.
- Multi-indicator default period selection:
  - widget no longer defaults to `Q4` for the latest year when `Q4` data is missing;
  - default now resolves to the latest actually available quarter of the latest year.
- Quarter controls stability:
  - quarter buttons/select are now rebuilt per selected year and guarded against unavailable quarter picks.
- README expanded with step-by-step `DASHD_GITHUB_TOKEN` generation guide (Fine-grained PAT) and public/private repo requirements.

## [11.7.16] - 2026-05-18

### Added
- Bar behavior controls across all builders/integrations:
  - `bar_orientation` (`horizontal` / `vertical`);
  - `bar_stacked` (`stacked` / `normal`).
- New optional widget parameter `country_order` for manual country sorting (comma-separated country names).
- Country order control added to:
  - Admin Constructor;
  - Elementor widget settings;
  - Gutenberg block inspector;
  - YOOtheme widget settings + render helpers.

### Improved
- Frontend chart controls updated:
  - country selector moved to top and centered;
  - legend moved below chart with circular color markers;
  - bar-orientation and stacked/normal controls hidden on frontend UI while logic remains active from saved settings.
- Single-indicator yearly bar mode upgraded:
  - chart now renders by years;
  - yearly value is resolved from the latest available quarter for each year.
  - years direction is now orientation-aware:
    - `horizontal`: ascending left-to-right;
    - `vertical`: ascending bottom-to-top.
- Bar rendering quality:
  - unified corner rounding strategy bound to bar thickness;
  - `normal` bar mode now applies rounding per each bar;
  - grouped `normal` bars now include spacing between country bars.
- Default color palette updated to:
  - `#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7`
  - applied consistently in shortcode defaults, constructor presets, preview fallback, and builder integrations.

### Fixed
- Indicator-based data source rendering:
  - fixed cases where selected indicator(s) still rendered by full table;
  - fixed multiple-indicator rendering regressions in frontend and builder previews.
- Restored reliable chart rendering in constructor/frontend after indicator-mode refactor regressions.

## [11.4.6] - 2026-04-22

### Added
- Sync service layer expanded:
  - `DashD_Sync_Dictionary_Service` for indicator/country ID resolution.
  - `DashD_Sync_Source_Record_Store` for per-source records map, snapshot preparation, and upsert operations.

### Improved
- `dashd_sync_repository()` refactored to delegate dictionary lookup and source record persistence to dedicated services.
- Bootstrap loading order updated to register sync services before `sync-engine` execution.

### Performance
- Snapshot path optimized for large sources:
  - initial existing-record preload no longer fetches `val` field;
  - full snapshot rows with `val` are now loaded lazily only when full snapshot mode is actually used.

## [11.3.2] - 2026-04-22

### Improved
- Frontend mobile UX (portrait smartphones): chart controls are now rendered as compact dropdowns (`View`, `Scale`, `Year`, `Quarter`) to prevent control overflow.
- Chart axis labels now support adaptive multi-line word wrapping (up to 2-3 lines) for long indicator names on both mobile and desktop.

### Performance
- Sync engine (JSON/CSV ingestion) optimized to reduce query pressure:
  - in-memory caches for indicator/country ID resolution;
  - per-source in-memory upsert map for `dashd_data_records`;
  - reduced repeated point lookups in hot loops.
- Snapshot preparation path optimized to reuse already loaded source records instead of extra repetitive reads.
- Calculated indicators engine optimized by replacing per-period value lookups with batched preloading and in-memory maps.
- Added DB indexes for high-frequency lookup paths in `dashd_data_records`:
  - `(source_key, indicator_id, country_id, data_year, data_quarter)`
  - `(indicator_id, country_id, data_year, data_quarter)`

### Security
- Public lead-capture endpoint no longer performs synchronous DNS checks (`checkdnsrr`) in request path; replaced by fast domain syntax validation to avoid latency amplification under load.
- Constructor preview hardening:
  - AJAX preview now accepts only sanitized `dashd_widget` attributes (instead of generic shortcode execution);
  - preview executes only widget bootstrap script marked with `data-dashd-widget-boot="1"`.
- `Danger Zone: Wipe Data` updated to perform full plugin data wipe (records, snapshots, leads, sync logs) with clearer UI/confirmation messaging.

## [11.2.15] - 2026-04-21

### Added
- Data Sources admin: edit workflow for existing sources (`type`, `method`, `label`, `url`, `headers`) with immutable `source_key`.
- New formula documentation section in `README.md` with syntax, limits, and valid/invalid examples.

### Improved
- Public API (`get_dashd_modern_data`, `all=true`) performance safety:
  - bounded periods/rows/countries/indicators;
  - SQL `LIMIT` guards;
  - faster period indexing;
  - optional `truncated` flag in response when limits are reached.
- Public API (`get_dashd_periods_split`) now returns a bounded year list.
- Calculated indicators engine hardening:
  - strict formula normalization/validation before execution;
  - safe target-source normalization;
  - guardrails for invalid operands and out-of-range periods;
  - bounded processing scope for indicators/periods/target sources.

### Security
- Sensitive settings handling upgraded:
  - constants-first resolution for runtime secrets (`Telegram`, `CRM webhook`, `Slack webhook`);
  - safer persistence path with `autoload = no` for sensitive options;
  - admin UI masks/disables fields when values are managed via `wp-config.php`.
- CSV import hardening for dictionaries and raw data:
  - upload validation (errors, MIME/extension, size, readability);
  - row/column caps;
  - strict column whitelisting and sanitization on import (prevents mass assignment from arbitrary CSV headers).
- Formula pipeline hardening:
  - invalid calculated formulas are rejected on create/import and auto-disabled on bulk save.
- Admin schedule save hardening:
  - `dashd_auto_sync` now strictly whitelists `enabled|disabled`.

## [11.2.0] - 2026-04-17

### Added
- YOOtheme Pro widget: custom HEX palette override (`Custom Palette`) in addition to preset palettes.
- CSRF nonce protection for public lead-capture AJAX endpoint.

### Improved
- YOOtheme Pro widget settings schema aligned with standard editor behavior; `Advanced` tab structure stabilized.
- PDF export layout for wide `line` datasets optimized to reduce truncation and excessive empty space.
- Admin dashboard action cards visual consistency: action buttons aligned to a shared baseline.

### Fixed
- Restored chart legend toggle behavior (show/hide datasets by legend click).
- Fixed SVG logo handling in PDF export pipeline (admin preview/save + generated report output).
- Fixed YOOtheme integration regressions that could leave widgets in perpetual loading state.
- Removed html2canvas tainted-canvas warnings from hidden/zero-size sparkline canvases during export.

### Security
- Hardened source/webhook input validation and URL sanitization (safer external target handling).
- Strengthened lead-capture abuse controls (validation, throttling/deduplication, request integrity checks).
- Safer client IP resolution defaults for rate limiting; forwarded headers now opt-in via filter.

## [11.0.24] - 2026-04-16

### Added
- Automated version bump flow via `bin/bump-version.php` integrated into release process.

### Improved
- PDF export reliability with SVG logos: image preloading and SVG-to-PNG conversion before `html2canvas`.
- Runtime and API hardening: safer defaults, stricter input validation, and fail-safe guards.
- Admin input safety for source creation and raw value update handlers.
- Documentation and release instructions aligned with current build scripts.

### Fixed
- YOOtheme Pro widget initialization regression that caused infinite loader spinner in builder/frontend contexts.
- Chart legend interaction restored (dataset show/hide toggle on click).
- Language fallback aligned with DB schema (`EN/UK/HY/RO/KA`) to prevent invalid localized SQL column lookups.
- Legacy AJAX compatibility endpoints restored and missing callback wiring fixed.
- Duplicated frontend style enqueue removed; CSV export guarded against empty table state.

## [10.1.x]

### Added
- SVG upload support for administrators.
- PDF logo width control.
- PDF branding improvements.

## [10.0.9]

### Improved
- Custom display sorting (`sort_order`) for indicators and countries.
- Instant recalculation path improvements.

## [10.0.5]

### Added
- Webhook integrations for leads.
- Anomaly alerting integrations.
- Formula engine expansion.
