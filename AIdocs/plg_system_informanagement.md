# System plugin (plg_system_informanagement)

### History
- 2026-11-25: page created when the package moved from jacc.nl to its own public repo

Last updated: 2026-11-25
Verified against: VERIFIED_SHA
Covers:
- Src/plugins/system/informanagement/

## Purpose

Informanagement's newsletters link to `https://<site>/?im_id=<id>` (the WordPress plugin's URL). This plugin sends those visitors to the imported Joomla article.

## Behaviour

- Listens to `onAfterRoute`, site client only, and does nothing without a positive integer `im_id` in the request.
- Looks up `#__informanagement_articles` joined with `#__content` where `im_id = :id OR source_id = :source` (both bound to the same id) and the article is published (`state = 1`), first row only. Matching `source_id` means a link to the original still finds the article after Informanagement published a revision.
- Found: 301 to `Route::_(RouteHelper::getArticleRoute("id:alias", catid, language))`.
- Not found: 302 to the site root, not 301, because the article may arrive with the next import.
- The link table belongs to the task plugin (`sql/mysql/install.sql` there); this plugin has no SQL of its own.
