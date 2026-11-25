# AIdocs — pkg_informanagement

Verified documentation for AI assistants. Read this index first, then the page for the code you are about to change. `CLAUDE.md` in the repo root holds the working rules; these pages hold the facts about the code.

## Pages

| Page | Covers |
|---|---|
| [pkg_informanagement](pkg_informanagement.md) | The Joomla package: `package.xml`, `package_script.php` (minimum versions, enabling the plugins on first install), the package language and the `package.sh` build. |
| [plg_task_informanagement](plg_task_informanagement.md) | The scheduled task: feed parsing, article mapping, the import into com_content, images, the link table and the failure threshold. |
| [plg_system_informanagement](plg_system_informanagement.md) | The `?im_id=` redirect. |
| [mod_inforwijzers](mod_inforwijzers.md) | The InforWijzers module. |

## Keeping the pages true

- Every content page (all except this `_index.md`) starts with `History`, `Last updated`, `Verified against` (the commit whose code you read) and `Covers:` (the paths it documents; a path ending in `/` covers the whole folder).
- When you change a file a page covers, update that page in the same change: `Last updated`, `Verified against` and a `History` line.
- `bash bin/preflight.sh` checks the rules: check 14 (headers, `Covers:` paths, index, coverage of changed files) and check 7 (at most 200 lines per page).

## Not documented here

`bin/` (shared HKweb tooling, see `hkweb_adventcalendar`), `tests/`, `.github/` and the Dutch workflow docs in `docs/`.
