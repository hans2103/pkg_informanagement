# CLAUDE.md

> **Document length monitoring:** check at the start of every session the number of lines with
> `wc -l $(git rev-parse --show-toplevel)/CLAUDE.md`. Report to the user when this is **longer than 200 lines**.

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Informanagement is a Joomla package (`pkg_informanagement`) for accountancy offices that subscribe to [Informanagement](https://www.informanagement.com): their tax news and InforWijzers in Joomla, as a replacement for Informanagement's WordPress plugins.

- `plg_task_informanagement`: scheduled task *Import Informanagement news*, imports the news feed as Joomla articles (one list request; detail only for new or changed items).
- `plg_system_informanagement`: redirects `?im_id=` links (from Informanagement's newsletters) with a 301 to the imported article.
- `mod_inforwijzers`: site module with the InforWijzers; their scripts load only where the module is shown.

Public repo, unofficial (not made by Informanagement). First user: jacc.nl (repo `HKwebnl/jacc.nl`, which installs the package from here).

**Requirements:** Joomla 5.2+ (Joomla 6 ready), PHP 8.3+, MySQL/MariaDB.

Language convention: PHP docblocks in English; commit messages and the workflow docs in `docs/` in Dutch; `README.md` and this file in English. Log and exception messages of the import are Dutch (the feed is Dutch).

## Repository Layout

```
Src/
├── plugins/task/informanagement/     # task plugin (manifest informanagement.xml)
│   ├── src/Extension/Informanagement.php  # routine informanagement.import, failure threshold
│   ├── src/Feed/                     # FeedParser, FeedItem, ArticleMapper, exceptions (Joomla-free)
│   ├── src/Import/                   # Importer (articles, categories, images), ImportResult, FailureCounter
│   ├── forms/import.xml  sql/mysql/{install,uninstall}.sql  sql/mysql/updates/
│   └── language/{en-GB,nl-NL}/
├── plugins/system/informanagement/   # ?im_id= redirect
├── modules/mod_inforwijzers/         # InforWijzers module
└── language/{en-GB,nl-NL}/pkg_informanagement.sys.ini
package.xml  package_script.php  package.sh  .deployignore  cliff.toml  updates.xml  scripts.conf
bin/  docs/  AIdocs/  tests/{unit,shell}/  .githooks/  .github/
```

Never edit `packages/` (build output) or `updates.xml` by hand: `bin/add-update-entry.php` appends the release entries.

## AIdocs

`AIdocs/` holds verified documentation for AI assistants. Read `AIdocs/_index.md` first, then the page for the code you are about to change.

- Every content page starts with `History`, `Last updated`, `Verified against` (a commit) and `Covers:`.
- When you change files a page covers, update that page in the same PR.
- `bash bin/preflight.sh` checks coverage and structure (check 14) and the 200-line limit (check 7, an ERROR here).

## Commands

```bash
bash package.sh [version]          # build packages/{version}/pkg_informanagement-{version}.zip
bash bin/preflight.sh              # lint, manifests, versions, secrets, AIdocs, strict_types (read-only; --all: whole repo)
bash bin/run-release.sh            # full release: merge develop → main, build, commit, tag, push, wait for the release
bash bin/deploy-ddev.sh [-n] [-y]  # rsync the extensions (DEV_LINKS) into the local Joomla site
bash tests/shell/run-all.sh        # tests for bin/
bash bin/ci-local.sh               # the CI checks locally; stamps HEAD for the pre-push hook and gh pr ready (10 min timeout)
for f in tests/unit/*.php; do php "$f" || echo "FAIL $f"; done   # plain-PHP unit tests, no PHPUnit
```

`bin/` is byte-identical in every HKweb Joomla repo (reference: `hkweb_adventcalendar`). Everything that differs per repo is a variable in `scripts.conf`; change a script in one repo and copy it to the others, never fork it.

Each unit test is a standalone script that includes `tests/bootstrap.php` (autoloader, `check()`, `thrown()`, `fixture()`, `finish()`) and exits non-zero on failure. Fixtures in `tests/unit/fixtures/` are made up; never commit real Informanagement articles (their copyright).

## Branch Strategy

- `main` — production; only receives tagged releases
- `develop` — integration branch; all feature PRs merge here first
- Feature branches: `feature/{issue}-{short-description}`
- Never commit directly to `main` or `develop`
- Full workflow: `docs/Ontwikkelworkflow.md`

## Committing

Commit messages in Dutch, conventional and with an issue number: `feat(import): #12 omschrijving` (`#000` when there is no issue). No `Co-Authored-By` line. Activate the hook once per clone: `git config core.hooksPath .githooks`.

## PR's

Do **not** add a 'Generated with Claude Code' line in a PR.

Open a PR as a draft (`gh pr create --draft --base develop`), run `bash bin/ci-local.sh` until green, then `gh pr ready`; the PreToolUse hook in `.claude/settings.json` refuses `gh pr ready` and a non-draft `gh pr create` without a stamp. Details: `docs/Ontwikkelworkflow.md`.

## PR's

Do **not** add a 'Generated with Claude Code' line in a PR.

Open a PR as a draft (`gh pr create --draft --base develop`), run `bash bin/ci-local.sh` until green, then `gh pr ready`; the PreToolUse hook in `.claude/settings.json` refuses `gh pr ready` and a non-draft `gh pr create` without a stamp. Details: `docs/Ontwikkelworkflow.md`.

## Versioning & Release

Calendar versioning `YY.WW.PATCH` (e.g. `26.48.0`; before this repo the package was 1.0.x inside jacc.nl). The single source of truth is `<version>` in `package.xml`; `package.sh {version}` stamps it into the three extension manifests and replaces `__DEPLOY_VERSION__`.

- `bash bin/run-release.sh` runs the release. The tag `v{version}` triggers `.github/workflows/release.yml`: GitHub Release with git-cliff notes, then `updates.xml` on main. GitHub is the update server: `package.xml` points to `https://raw.githubusercontent.com/hans2103/pkg_informanagement/main/updates.xml` and the zip downloads from the GitHub Release (`UPDATE_DOWNLOAD_BASE` empty). No own server, no secrets, no download key.
- Details: `docs/Release en deployment procedure.md`.

## Code Standards

- Joomla coding standard (tabs, braces on their own line); `declare(strict_types=1);` in every class file
- DocBlocks with `@since`; new code gets `@since __DEPLOY_VERSION__`
- Keep `src/Feed/` free of Joomla calls, so the unit tests run without Joomla.
- Database changes: a new `sql/mysql/updates/{version}.sql` (idempotent, `CREATE TABLE IF NOT EXISTS`) plus the same in `install.sql` and a `DROP` in `uninstall.sql`. A file deploy alone does not run SQL: sites must install the package.
- New task settings: field in `forms/import.xml` + en-GB and nl-NL strings + `$params->get()` with the same default; `tests/unit/LanguageContractTest.php` checks the strings.

## Important Implementation Details

- The routine returns `TaskStatus::KNOCKOUT` (exit code 5, the scheduler's failure mail) for incomplete settings at once, but for `FeedUnavailableException` (no connection, HTTP error, no valid feed) only from `notify_after` failed runs in a row (default 3); before that it logs a warning and returns OK. `FailureCounter` keeps the count per task in `#__informanagement_failures` and clears it after a successful run.
- `#__informanagement_articles` links feed ids to articles; a revision gets a new `im_id` and keeps `source_id`, so the article and its URL stay the same.
- Articles are stored through `Joomla\CMS\Table\Content` with workflow association and `onContentBeforeSave`/`onContentAfterSave`, so Smart Search and versions see them.
- An article the editors changed (its `modified` differs from the one stored after import) is not overwritten when `keep_edits` is on.
