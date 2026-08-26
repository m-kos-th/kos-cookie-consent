# KOS Cookie Consent Package

<img width="800" height="452" alt="ScreenRecording2569-08-26at10 16 29-ezgif com-video-to-gif-converter" src="https://github.com/user-attachments/assets/9770c28b-3d97-4ac0-9506-c2e3450ab5d5" />


Cookie consent package for Concrete CMS with multilingual popup copy, per-site cookie categories, consent logs/export, and gated tracking-code injection.

For a shorter Thai summary for PM/client handoff, see [PACKAGE_DETAILS.md](PACKAGE_DETAILS.md).  
For a deeper engineering map, see [CONTEXT.md](CONTEXT.md).

## Current Scope

- Frontend cookie banner and preference modal are rendered automatically by the package.
- Cookie categories, copy, logs, and export are managed from Dashboard single pages.
- Visitors can use `Accept All`, `Reject All`, or `Save Preferences`.
- Optional cookies default to `false` until the visitor explicitly opts in.
- Tracking code is injected only for consented categories.
- Saved visitors get a `Cookie Settings` entry point to reopen the modal later.
- Cookie categories can define first-party cookies to clear when consent is withdrawn.

## Consent Model

The package now uses a first-party browser identifier, not visitor IP, as the main consent key.

- Primary identity: `kos_cookie_consent_id`
- Scope: per browser/profile, per site, per consent version
- Server-side lookup key: `consent_id + site_id + consent_version`
- Audit-only field: visitor IP is still stored in logs, but it is not used to determine consent state

What this means in practice:

- Same browser profile keeps the same consent state.
- Different browsers, incognito windows, or devices get separate consent states.
- Deleting `kos_cookie_consent_id` makes that browser look like a new visitor.
- Changing cookie definitions or popup copy changes `consent_version`, so the visitor is prompted again.

## Visitor Behavior

### First Visit

- Frontend JS creates or reuses `kos_cookie_consent_id`.
- JS calls `GET /kos-cookie-consent/state`.
- If no saved state exists for the current `consent_id + site_id + consent_version`, the banner is shown.

### Accept All

- All optional toggles are turned on.
- Consent is saved.
- Banner is hidden.
- `Cookie Settings` remains available for later changes.

### Reject All

- All optional toggles are turned off.
- Consent is still saved as an answered state.
- Banner is hidden and should not reappear until consent version changes or the browser gets a new `consent_id`.

### Save Preferences

- Only the currently selected optional toggles are saved.
- Saving with everything off is valid and counts as an answered state.
- Withdrawing previously granted categories clears the configured first-party cookies for those categories and reloads the page so the next execution pass respects the updated consent state.

### Closing The Modal

- Closing the modal is not treated as consent.
- If the visitor has not answered yet, the banner remains the first layer.
- If consent is already saved and the modal was reopened from `Cookie Settings`, closing the modal only closes the modal.

## Browser Cookies

The package uses these browser cookies:

- `kos_cookie_consent_id`: first-party random identifier used as the consent key
- `consent`: marker cookie set after a successful save
- `consent_version`: browser-side marker of the last saved consent version

Important:

- Consent-cookie retention is configurable from `/dashboard/cookie_consent/setting`.
- Default retention is `30 days`.
- Allowed retention is `1-400 days` so dashboard input stays within modern browser persistence caps.
- These cookies are written as first-party cookies with `Path=/`, `SameSite=Lax`, and `Secure` when the site is served over HTTPS.
- The source of truth is still the database log keyed by `consent_id + site_id + consent_version`.
- There is no separate server-side consent-expiry rule yet beyond consent-version changes; the configurable retention applies to browser cookies only.

## Managed Cookie Cleanup

Each optional cookie category can define first-party cookies that should be removed when a visitor withdraws consent.

- Configure this on each cookie category in `/dashboard/cookie_consent/setting`.
- Enter one cookie name per line.
- Use `*` only at the end for prefix matches, for example `_ga_*`.
- Cookie path defaults to `/`.
- Domain override is optional and should be a hostname only.

Important limitations:

- This cleanup is for first-party cookies on your own site domain.
- Browser rules do not allow this package to delete third-party cookies stored on another vendor domain.
- `HttpOnly` cookies cannot be removed from frontend JavaScript.
- Do not add `kos_cookie_consent_id`, `consent`, or `consent_version` to cleanup rules because they are required to remember the visitor choice.

## Database Model

Core tables:

- `_KosCookie`: cookie categories and tracking snippets
- `_KosCookieContent`: translated cookie names and descriptions
- `_KosIP`: legacy table name, now used for consent identity rows
- `_KosLog`: consent log rows
- `_KosCookiePopupCopy`: multilingual popup copy per site/language

Important details:

- `_KosIP.consent_id` stores the browser-level consent identity.
- `_KosIP.IP` stores visitor IP for audit/history only.
- `_KosLog` stores one row per selected cookie ID.
- If a visitor saves with no optional cookie selected, the package stores a sentinel `cid = 0` row so `Reject All` and all-off preferences still count as saved consent.

## Dashboard Pages

- `/dashboard/cookie_consent`
- `/dashboard/cookie_consent/logs`
- `/dashboard/cookie_consent/setting`
- `/dashboard/cookie_consent/popup_content`
- `/dashboard/cookie_consent/export`

### Logs / Export

Logs and CSV export currently show:

- `Consent ID`
- `IP Address`
- per-cookie YES/NO columns
- `Consent Version`
- `Created` timestamp

Timestamps are stored in UTC and displayed/exported in ICT through the existing helper methods.

## Popup Copy Fields

Popup copy is stored in `_KosCookiePopupCopy` and managed per site-language row.

Current fields:

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

Behavior notes:

- `modal_intro_two` is optional. If left blank, the second paragraph is not rendered on the frontend.
- `settings_label` is reused for both the floating `Cookie Settings` trigger and the banner `Customize` action label.

## Frontend UI Notes

Stable BEM-style classes live under `kos-cookie-popup__*`.

Useful button classes for overrides include:

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

## Routes

Legacy-compatible routes:

- `POST /cookieConsentSave`
- `POST /cookieConsentSaveV2`
- `GET /cookieConsentStateV2`

Current frontend routes:

- `POST /kos-cookie-consent/save`
- `GET /kos-cookie-consent/state`

The `state` response is sent with `no-store` headers to reduce cache-related consent bugs.

## Upgrade Notes

- Current package version: `0.2.9`
- Run package upgrade after deploying package code changes.
- Clear Concrete CMS cache after upgrade.
- Existing legacy IP-based rows can be adopted into the new `consent_id` model on first fresh lookup, but new saves should always use `consent_id`.

## Developer Notes

- Keep `_Kos*` table names for backwards compatibility.
- Do not change route paths unless you update both PHP and JS consumers.
- Keep `kos-cookie-popup__*` class names stable unless you also update theme overrides.
- Optional cookies should remain default-off unless product/legal requirements explicitly change.
- Managed cookie cleanup rules should only target first-party cookies that belong to the same consent category.
- If you add popup copy fields, update:
  - table creation/migration in `controller.php`
  - dashboard form/controller
  - service read/write mapping
  - frontend rendering
  - consent version expectations

## Quick Validation Checklist

- Banner appears for a new browser/profile.
- `Reject All` hides the banner and keeps `saved: true` on later page loads.
- `Accept All` restores saved toggles on reload.
- `Save Preferences` persists mixed selections correctly.
- `Cookie Settings` reopens the modal after consent is saved.
- Changing popup copy or cookie definitions produces a new consent version and re-prompts visitors.
- Changing cookie retention updates the consent-cookie max-age used by later state/save responses.
- Withdrawing previously granted consent clears the configured first-party cookies for the revoked categories.
- Logs and export show `Consent ID`, `IP Address`, and consent version correctly.
