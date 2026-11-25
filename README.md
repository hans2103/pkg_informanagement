# Informanagement for Joomla

Joomla package for accountancy offices that subscribe to [Informanagement](https://www.informanagement.com): their tax news and InforWijzers in Joomla 5.2+ and 6. It replaces Informanagement's WordPress plugins *Informanagement Import* and *Informanagement InforWijzers*.

> Unofficial. This package is not made or supported by Informanagement. You need your own Informanagement subscription: the feed and the InforWijzers use the template name, office id and code Informanagement gives you.

| Extension | What it does |
|---|---|
| `plg_task_informanagement` | Scheduled task *Import Informanagement news*: imports the news feed as Joomla articles |
| `plg_system_informanagement` | Redirects `?im_id=…` links (used in Informanagement's newsletters) with a 301 to the article |
| `mod_inforwijzers` | Shows the InforWijzers; their scripts load only on pages with the module |

## Requirements

- Joomla 5.2 or newer, or Joomla 6
- PHP 8.3+
- MySQL or MariaDB
- The Joomla Task Scheduler running, preferably from cron: `php cli/joomla.php scheduler:run`

## Installation

1. Download `pkg_informanagement-{version}.zip` from the [Releases](https://github.com/hans2103/pkg_informanagement/releases) page.
2. In the administrator go to **System → Install → Extensions** and upload the zip.
3. The task and system plugins are enabled on the first install. Updates then arrive through Joomla's update manager.

## Importing the news

1. Create a category for the news, for example *News*.
2. **System → Scheduled Tasks → New → Import Informanagement news**.
3. Fill in the **template** (*sjabloon*) Informanagement gave you, the feed (nl, uk or de) and the category, and set the schedule (for example hourly).

| Setting | Default | Meaning |
|---|---|---|
| Number of articles | 25 | How many of the newest items are checked per run (max. 500). Unchanged items cost no extra request. |
| Report after failed runs | 3 | See [Failure mails](#failure-mails). |
| Subcategory per section | Yes | Puts each article in a subcategory named after its Informanagement section (created when missing). |
| Status, Author | Published, first Super User | For new articles. |
| Update articles | Yes | Updates an article when Informanagement changes it or publishes a revision. Alias, category and URL stay the same. |
| Keep own edits | Yes | An article edited in Joomla is no longer overwritten. |
| Fetch images | Yes | Stores the image once in `images/informanagement/YYYY/MM/` as intro and full-text image. |
| Image width, Fetch as JPEG | 1200, Yes | JPEG quality 80 turns a 2.8 MB PNG into about 200 kB. |
| Source line | Yes | Adds e.g. *Bron: Ministerie van Financiën \| wetswijziging \| 12-11-2026* below the article (class `c-im-source`). |

An article the editors delete or trash is not imported again.

### Failure mails

The scheduler can mail on a failed task. Informanagement's server is sometimes unreachable for a moment; one failed run is then followed by a normal one an hour later. So when the feed can't be reached (no connection, an HTTP error or a maintenance page instead of a feed), the task only counts as failed after **Report after failed runs** failed runs in a row. Before that it logs a warning such as `… (run 1 of 3 in a row, not reported yet)` and finishes normally. From that run on it exits with code 5, and the scheduler sends its mail, every run until the feed is back. The first successful run resets the count. Missing task settings are reported at once. Set it to 1 to report every failed run.

## Newsletter links

Informanagement's newsletters link to `https://your-site/?im_id=12345`. `plg_system_informanagement` redirects those with a 301 to the imported article, also when the link points to the original of a revised article. A link to an article that has not been imported yet goes to the home page with a 302.

## InforWijzers

Add a module of type **InforWijzers** and fill in the **office id** and **code** from Informanagement. With *Office styles* on, the office's own stylesheet is loaded too. The scripts load with `defer`, only on pages that show the module.

Content Security Policy: allow `https://cdn.informanagement.com` in `style-src` and `script-src`, `https://eprint.informanagement.com` in `connect-src`, and the module's two small inline scripts.

## Compared with the WordPress plugins

| WordPress | This package |
|---|---|
| Every hour 1 + N requests (list plus every article) | 1 request; the detail request only for new or changed articles |
| `die()` on a feed error, in the middle of WP-cron | A task status and a log line; one failing article doesn't stop the others; a short outage doesn't mail |
| Time zone errors in the source date and publication time | Dates with their own offset; `created` in UTC |
| Overwrites manual edits | Edited articles stay as they are (configurable) |
| A revision becomes a new post, the original goes to the trash (URL gone) | The existing article is updated; alias, category and URL stay |
| About 15 thumbnails per image, PNGs of 3 MB | One checked copy, JPEG by default |
| Inline styles and `border` attributes from the feed | HTML filtered to a fixed list of tags and attributes |
| InforWijzers scripts render-blocking on every page | Only on pages with the module, with `defer` |

## Development

```bash
git config core.hooksPath .githooks
for f in tests/unit/*.php; do php "$f" || echo "FAIL $f"; done   # unit tests, no PHPUnit
bash tests/shell/run-all.sh
bash bin/preflight.sh
bash package.sh                                                  # packages/{version}/pkg_informanagement-{version}.zip
```

Workflow, branching and releases are documented in Dutch in [`docs/`](docs/). Versions follow `YY.WW.PATCH` (year, ISO week, patch).

## License

GNU General Public License version 2 or later, see [LICENSE.txt](LICENSE.txt). © 2026 [HKweb](https://hkweb.nl).
