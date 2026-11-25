# InforWijzers module (mod_inforwijzers)

### History
- 2026-11-25: page created when the package moved from jacc.nl to its own public repo

Last updated: 2026-11-25
Verified against: VERIFIED_SHA
Covers:
- Src/modules/mod_inforwijzers/

## Purpose

Shows Informanagement's InforWijzers (advice guides) for the office. Replaces the WordPress plugin *Informanagement InforWijzers*, which loaded its scripts render-blocking on every page.

## Settings

- `office_id` (required, integer) and `code` (required, a GUID): both from Informanagement.
- `office_styles` (default on): loads the office's own `SiteStyles/Settings.css` from the CDN.
- Standard `layout` and `moduleclass_sfx`.

## Behaviour

- `Dispatcher::getLayoutData()` sets `configured` only when `office_id > 0` and `code` is a GUID. Not configured: the layout shows `MOD_INFORWIJZERS_NOT_CONFIGURED` to users who may manage modules, and nothing to visitors.
- Configured: registers on the web asset manager `CommonModals.css`, optionally the office stylesheet, and `Common/JS/Integrationjs.js` with `defer`, all from `https://cdn.informanagement.com/Content/ePrint/Assets/Accountants/`. Assets are only added on pages that render the module.
- An inline script before the integration script sets `window.inforwijzers_url` to `https://eprint.informanagement.com/Int/AccountantOnlineTemplates?ui=<code>`.
- An inline script after it turns `alt=" "` into `alt=""` on images in `#id01`, also for images added later (MutationObserver), so screen readers skip the decorative thumbnails (WCAG 1.1.1).
- `tmpl/default.php` renders `<div id="id01">` and `<div class="pubLdBXInfor">`: the ids and class Informanagement's script expects. A `<noscript>` text explains the module needs JavaScript.

## Content Security Policy

`style-src` and `script-src https://cdn.informanagement.com`, `connect-src https://eprint.informanagement.com`, and the inline scripts (a nonce or hash if the site's CSP has no `'unsafe-inline'`).
