# Task plugin (plg_task_informanagement)

### History
- 2026-11-25: page created when the package moved from jacc.nl to its own public repo; includes the failure threshold (`notify_after`, `FailureCounter`) from 1.0.2
- 2026-11-25: the previous image is only deleted when its resolved path lies inside `images/informanagement` (`ownImage()`); image redirects are followed only to https Informanagement hosts (`downloadImage()`)
- 2026-11-25: curl stops an image download above 8 MB (`imageHttp()`); a failed image download skips the image, not the article

Last updated: 2026-11-25
Verified against: 779881a
Covers:
- Src/plugins/task/informanagement/

## Purpose

Scheduler routine `informanagement.import` (*Import Informanagement news*): imports Informanagement's tax news feed as com_content articles. The site runs it from cron (`cli/joomla.php scheduler:run`) or the lazy scheduler, typically hourly.

## Files

| Path | Role |
|---|---|
| `src/Extension/Informanagement.php` | `TaskPluginTrait` routine; maps exceptions and results to a `TaskStatus` |
| `src/Feed/FeedParser.php`, `FeedItem.php` | Parse the RSS (no network, no entities: `LIBXML_NONET`); readonly item with `hash()` and `isRevision()` |
| `src/Feed/ArticleMapper.php` | Item → title, intro/full text, meta description, created (UTC), source line |
| `src/Feed/FeedException.php`, `FeedUnavailableException.php` | Settings/feed errors; the subclass marks a temporary outage |
| `src/Import/Importer.php` | One run: fetch, compare, detail request, store article, image, link row |
| `src/Import/ImportResult.php` | Counters: created, updated, unchanged, kept, failed |
| `src/Import/FailureCounter.php` | Failed runs in a row per task |
| `forms/import.xml` | Task settings |
| `sql/mysql/` | `#__informanagement_articles`, `#__informanagement_failures`; updates `1.0.0.sql`, `1.0.2.sql` |

`src/Feed/` has no Joomla calls (only `_JEXEC`), so `tests/unit/` runs without Joomla.

## Settings (forms/import.xml)

`template` (required, Informanagement's *sjabloon*, `[A-Za-z0-9_-]+`), `location` (nl, uk, de), `number` (1–500, default 25), `notify_after` (1–24, default 3), `catid` (required), `subcategories` (default on), `state` (published), `created_by` (empty: first Super User), `update`, `keep_edits` (shown when `update` is on), `images`, `image_width` (200–2400, default 1200), `image_jpeg`, `source`. Every `$params->get()` repeats the form default.

## Feed

- List: `https://{location}.informanagement.com/rss/customfeed.aspx?command=rss&nr={number}&length=&forwp=1&sjabloon={template}`. Full text, category, image, `pubDate`, `date`, `id`, `sourceArticleId`.
- Detail: `…?command=viewitem&id={id}&forwp=1&sjabloon={template}`; adds `agency`, `character`, `number` (the source line).
- A revision gets a new `id`; `sourceArticleId` points to the original.
- Items without `id` are skipped; image URLs are forced to https.

## Run (Importer::run)

1. Incomplete `template`, `location` or `catid` → `FeedException` (no request).
2. Fetch the list with the Joomla user agent and a 30 s timeout. No connection or a status other than 200 → `FeedUnavailableException`; so is a body that is not an RSS channel (maintenance page).
3. Items oldest first, so a revision comes after its original. Per item, any exception counts as `failed` and is logged; the run goes on.
4. Per item (`importItem`):
   - Link row found with the same `hash()` and `im_id` → `unchanged`, no further request.
   - Article deleted or trashed by the editors → `kept`; it never comes back.
   - `update` off → `unchanged`. `keep_edits` on and the article's `modified` differs from the stored one → `kept`, link row moves to the new `im_id`.
   - Otherwise fetch the detail feed, map it and store. New articles: category (subcategory per Informanagement section, created when missing), unique alias, `state`, `created`/`publish_up` from the feed, author. Updates keep alias, category and dates, so the URL stays. `note` = `informanagement:{sourceId}`.
5. Articles go through `Table\Content` with a workflow association (else they are missing in the article list) and `onContentBeforeSave`/`onContentAfterSave`.

## HTML and images

- `sanitizer()`: `InputFilter` with a fixed list of tags (`p`, headings, lists, tables, `a`, …) and attributes (`href`, `title`, `scope`, `colspan`, `rowspan`); no inline styles.
- `ArticleMapper` splits after the first paragraph (intro text and meta description, max 160 characters) and appends `<p class="c-im-source">Bron: agency | character | number | date</p>` (label per location; the date is the feed's own calendar date).
- Images: downloaded once to `images/informanagement/YYYY/MM/`, at `image_width`, as JPEG quality 80 when `image_jpeg` is on (`format=webp` gives a 500 at the CDN). Checked: status 200, allowed MIME, at most 8 MB, `getimagesizefromstring()` agrees with the MIME. The link row stores the URL with its options; another width or format means a new download. The previous image is deleted only via `ownImage()`: no `..`, and `realpath()` must lie inside `images/informanagement` (editors can change the path in the article). Image downloads use a client without automatic redirects (Curl/Stream with `follow_location` off; Socket always follows, so it is excluded); `downloadImage()` follows at most 3 redirects itself, each checked with `isInformanagementUrl()` (https, `informanagement.com` or a subdomain, no credentials, port 443). `imageHttp()` (plain options array, since a Registry reads `transport.curl` as a path) sets `CURLOPT_MAXFILESIZE` and a progress callback, so curl stops above 8 MB instead of holding the whole response in memory; with only Stream available the size is checked after the download. Any download error (size, redirect, connection) logs a warning and skips the image; the article is still saved. The feed requests keep the default client.

## Status and the failure threshold

`Extension\Informanagement::import()`:

- `FeedUnavailableException`: `FailureCounter::increment($taskId)`; below `notify_after` → warning `PLG_TASK_INFORMANAGEMENT_LOG_RETRY` and `TaskStatus::OK` (no failure mail); from `notify_after` on → error `PLG_TASK_INFORMANAGEMENT_LOG_FAILED` and `KNOCKOUT` (exit code 5, the scheduler's failure mail if enabled), every run until the feed is back.
- Other `FeedException` (settings) → `KNOCKOUT` at once.
- Success → `FailureCounter::reset()`; a previous count > 0 logs `PLG_TASK_INFORMANAGEMENT_LOG_RECOVERED`. Then the result line; `KNOCKOUT` only when every item failed.
- `#__informanagement_failures` (`task_id` PK, `failures`, `since` UTC): `increment()` is a MySQL upsert (`ON DUPLICATE KEY UPDATE`); `reset()` deletes the row.

## Tables

- `#__informanagement_articles`: `im_id` (PK, current feed id), `source_id`, `article_id` (unique), `hash`, `image`, `modified` (article state after the last import), `imported`, `updated`. Also read by `plg_system_informanagement`.
- `#__informanagement_failures`: see above. Both dropped on uninstall.
