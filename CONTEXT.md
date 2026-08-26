# KOS Cookie Consent: Development Context

This file is the quickest technical handoff for engineers or AI assistants working on `kos_cookie_consent`.

## Package Basics

- Handle: `kos_cookie_consent`
- Namespace root: `Concrete\Package\KosCookieConsent`
- Main controller: `controller.php`
- Current package version: `0.2.9`

## Key Paths

- Package controller: `controller.php`
- Schema: `db.xml`
- Service layer: `src/Service/CookieConsentService.php`
- Frontend helper: `src/Frontend/CookieConsentHelper.php`
- API controller: `src/Controller/Route/CookieConsentApiController.php`
- Frontend element: `elements/cookie_popup.php`
- Frontend assets:
  - `assets/css/cookie-popup.css`
  - `assets/js/cookie-popup.js`
- Dashboard controllers:
  - `controllers/single_page/dashboard/cookie_consent.php`
  - `controllers/single_page/dashboard/cookie_consent/logs.php`
  - `controllers/single_page/dashboard/cookie_consent/setting.php`
  - `controllers/single_page/dashboard/cookie_consent/popup_content.php`
  - `controllers/single_page/dashboard/cookie_consent/export.php`
- Dashboard views:
  - `single_pages/dashboard/cookie_consent/logs.php`
  - `single_pages/dashboard/cookie_consent/setting.php`
  - `single_pages/dashboard/cookie_consent/popup_content.php`
  - `single_pages/dashboard/cookie_consent/export.php`

## Current Runtime Model

The package now uses a first-party browser-level consent identity.

- Main identity: `kos_cookie_consent_id`
- Main lookup key: `consent_id + site_id + consent_version`
- Audit-only field: `IP`
- State source of truth: database logs, not browser marker cookies alone

Important implications:

- Same browser/profile reuses the same consent state.
- Different browsers/devices/private windows act as separate visitors.
- Deleting `kos_cookie_consent_id` creates a new visitor identity.
- IP is stored for inspection only and must not be treated as the active consent key.

## Routes

Legacy-compatible routes:

- `POST /cookieConsentSave`
- `POST /cookieConsentSaveV2`
- `GET /cookieConsentStateV2`

Current frontend routes:

- `POST /kos-cookie-consent/save`
- `GET /kos-cookie-consent/state`

`/kos-cookie-consent/state` returns `no-store` headers to reduce cache-related false negatives.

## End-To-End Flow

### 1. Page Load

1. `controller.php::on_start()` registers services, routes, and frontend hooks.
2. The package injects popup markup and assets on frontend pages.
3. `assets/js/cookie-popup.js` checks for `kos_cookie_consent_id`.
4. If missing, JS generates a random 32-char hex ID and stores it as a first-party cookie.
5. JS requests `GET /kos-cookie-consent/state?site_id=...&consent_id=...`.

### 2. State Lookup

1. `CookieConsentApiController::stateV2()` reads request data.
2. It resolves `site_id`.
3. It reads the incoming `consent_id` from query/request/cookie.
4. It computes the current `consent_version`.
5. It asks `CookieConsentService::DBSelectLogByConsentIdV2()` for the latest saved row matching:
   - `consent_id`
   - `site_id`
   - `consent_version`
6. If found, response returns `saved: true` and approved code buckets.
7. If not found, response returns `saved: false`.

### 3. Save

1. `Accept All`, `Reject All`, and `Save Preferences` all post to `/kos-cookie-consent/save`.
2. `CookieConsentApiController::buildPayload()` sends:
   - `consent_id`
   - `ip`
   - `site_id`
   - selected cookie IDs
3. `CookieConsentService::insertLog()` inserts a new identity row into `_KosIP`.
4. It inserts one `_KosLog` row per selected cookie ID.
5. If nothing optional is selected, it stores a sentinel `cid = 0` row so "all off" still counts as saved consent.
6. Response sets:
   - `kos_cookie_consent_id`
   - `consent=true`
   - `consent_version=<current version>` when save is valid
7. If previously granted categories are now denied, frontend cleanup removes the configured first-party cookies for those categories and reloads the page.

### 4. Later Loads

- JS keeps sending the same `consent_id`.
- Backend keeps resolving the same saved state unless:
  - `consent_id` changes
  - `site_id` changes
  - `consent_version` changes

## Visitor-Facing Behavior

### Banner and Modal

- Banner is the first layer for unanswered visitors.
- Modal is opened by `Customize` or `Cookie Settings`.
- Closing the modal is not consent by itself.

### Button Behavior

- `Accept All`: turns all optional toggles on, saves consent, hides banner
- `Reject All`: turns all optional toggles off, saves consent, hides banner
- `Save Preferences`: saves current toggle state, including all-off
- Withdrawing previously granted categories also triggers managed cookie cleanup for those categories

### Toggle Defaults

- Optional toggles default to `false`
- Always-active cookies are shown as always on and are not controlled by optional toggles

### Cookie Settings Trigger

- After a successful save, the floating `Cookie Settings` trigger becomes available
- It reopens the modal without re-showing the banner as long as state is already saved

## Browser Cookies

Current cookies used by the package:

- `kos_cookie_consent_id`
- `consent`
- `consent_version`

Notes:

- Cookie retention is configurable from `/dashboard/cookie_consent/setting`.
- Default retention is `30 days`, with an allowed range of `1-400 days`.
- The package writes them as first-party cookies with `Path=/`, `SameSite=Lax`, and `Secure` on HTTPS pages.
- They help browser continuity, but DB logs remain the real persistence layer.
- There is no separate server-side "expire after X days" rule yet beyond consent-version changes.

## Managed Cookie Cleanup

Each cookie category can define first-party cookie names to clear on withdrawal.

- Configure one cookie name per line in the cookie-category dashboard form.
- A trailing `*` is treated as a prefix rule.
- Path defaults to `/`.
- Optional domain override is available for shared-site hostnames.

Constraints:

- Cleanup only works for first-party cookies that are accessible to frontend JavaScript on the current site.
- Third-party cookies on external vendor domains cannot be removed by this package.
- `HttpOnly` cookies cannot be removed from frontend JavaScript.
- The package protects its own required consent cookies from being added to cleanup rules.

## Database Model

### `_KosCookie`

Stores cookie categories and tracking snippets:

- per-site cookie category rows
- active/remove/always flags
- code buckets for `header`, `body`, `footer`
- managed cookie cleanup rules (`managed_cookie_names`, `managed_cookie_domain`, `managed_cookie_path`)
- `sort_order`

### `_KosCookieContent`

Stores translated cookie names and descriptions keyed by section language.

### `_KosIP`

Legacy table name, current meaning is "consent identity log".

Relevant columns:

- `id`
- `consent_id`
- `IP`
- `created`

Meaning:

- `consent_id` is the active identity key
- `IP` is audit-only

### `_KosLog`

Stores consented cookie IDs per save event.

Relevant columns:

- `pid`
- `cid`
- `site_id`
- `consent_version`
- `created`

Important:

- A single save can create multiple `_KosLog` rows
- A `cid = 0` row represents a saved state with no optional consents selected

### `_KosCookiePopupCopy`

Stores multilingual popup strings per site/language.

Relevant fields:

- `banner_title`
- `banner_summary`
- `modal_title`
- `modal_intro_one`
- `modal_intro_two`
- `preferences_title`
- `save_label`
- `accept_label`
- `decline_label`
- `settings_label`

## Popup Copy Rules

- Defaults are declared in `elements/cookie_popup.php`
- DB values overlay those defaults
- `modal_intro_two` is special:
  - it is optional
  - if stored as blank, it intentionally suppresses the second paragraph
- `settings_label` drives both:
  - the banner customize label
  - the floating `Cookie Settings` trigger label

## Logs and Export

Dashboard logs and CSV export now expose:

- `Consent ID`
- `IP Address`
- per-cookie YES/NO values
- `Consent Version`
- `Created`

Logs are useful for audit and debugging. They are not intended to reconstruct a user account identity.

## Service Methods Worth Reusing

In `CookieConsentService`:

- `getSiteLanguage()`
- `DBSelectCookieConsent()`
- `getActiveCookiesBySite()`
- `DBInsertLogV2()`
- `DBSelectLogByConsentIdV2()`
- `DBSelectPopupCopyBySection()`
- `DBSelectPopupCopyMap()`
- `DBUpdatePopupCopy()`
- `getPopupCopy()`
- `getConsentVersion()`
- `formatLogTimestamp()`

In `CookieConsentHelper`:

- `getConsentId()`
- `getCookieList()`
- `getCurrentSiteId()`
- `getTrackingCodeByPosition()`
- `hasSavedConsent()`
- `getPopupCopy()`

## CSS / Markup Notes

Frontend markup uses stable BEM classes under `kos-cookie-popup__*`.

Useful semantic button modifiers already exist:

- `kos-cookie-popup__button--customize`
- `kos-cookie-popup__button--reject`
- `kos-cookie-popup__button--accept`
- `kos-cookie-popup__button--save`
- `kos-cookie-popup__button--banner-customize`
- `kos-cookie-popup__button--banner-reject`
- `kos-cookie-popup__button--banner-accept`
- `kos-cookie-popup__button--modal-reject`
- `kos-cookie-popup__button--modal-save`
- `kos-cookie-popup__button--modal-accept`

If frontend styling changes in the theme, keep package markup stable where possible.

## Upgrade Notes

- Run package upgrade after deploying package changes
- Clear Concrete cache after upgrade
- `controller.php::ensureConsentLogTables()` handles `consent_id`, `consent_version`, and log-table upgrades
- `controller.php::ensurePopupCopyTable()` handles popup-copy columns such as `decline_label` and `settings_label`
- `controller.php::ensureManagedCookieCleanupColumns()` handles category-level cookie cleanup fields

## Legacy Compatibility

- `_Kos*` table names remain unchanged on purpose
- Legacy IP-based data paths still exist only for controlled migration/fallback scenarios
- New development should treat `consent_id` as the only active consent identity

## Safe Change Guidelines

1. Keep `_Kos*` table names unchanged.
2. Keep route paths stable unless PHP and JS are updated together.
3. Do not reintroduce IP-based consent lookup.
4. Keep optional cookie defaults off unless requirements explicitly change.
5. Keep `kos-cookie-popup__*` selectors stable unless theme overrides are updated too.
6. When adding popup fields, update schema, dashboard form, save logic, service mapping, frontend render, and consent-version expectations together.
7. When changing state/save flow, test across multiple pages and browser profiles.

## Quick Regression Checklist

- New browser shows banner
- `Accept All` persists across page loads
- `Reject All` persists across page loads and keeps `saved: true`
- `Save Preferences` persists mixed selections
- Withdrawing previously granted categories clears the configured first-party cookies for those categories
- `Cookie Settings` reopens modal after save
- Changing popup copy causes a new consent version
- Logs page shows consent ID, IP, version, and correct per-cookie states
- CSV export matches logs page output
