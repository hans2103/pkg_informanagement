--
-- Koppeling tussen Informanagement-artikelen en Joomla-artikelen.
--
-- im_id         huidige id bij Informanagement (verandert als een artikel herzien wordt)
-- source_id     id van het oorspronkelijke artikel (sourceArticleId)
-- hash          vingerafdruk van de lijstvelden, om wijzigingen te zien zonder detailverzoek
-- image         afbeeldings-URL zonder query, om te zien of de afbeelding veranderd is
-- modified      `modified` van het Joomla-artikel na de laatste import; wijkt hij af, dan heeft
--               de redactie het artikel zelf aangepast
--
CREATE TABLE IF NOT EXISTS `#__informanagement_articles` (
  `im_id` int unsigned NOT NULL,
  `source_id` int unsigned NOT NULL,
  `article_id` int unsigned NOT NULL,
  `hash` char(64) NOT NULL DEFAULT '',
  `image` varchar(400) NOT NULL DEFAULT '',
  `modified` datetime DEFAULT NULL,
  `imported` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`im_id`),
  UNIQUE KEY `idx_article` (`article_id`),
  KEY `idx_source` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Mislukte runs op rij per geplande taak, zodat een korte storing bij Informanagement geen foutmail geeft.
--
-- task_id   id van de geplande taak (#__scheduler_tasks)
-- failures  aantal runs op rij waarin Informanagement niet bereikbaar was
-- since     eerste mislukte run van deze reeks (UTC)
--
CREATE TABLE IF NOT EXISTS `#__informanagement_failures` (
  `task_id` int unsigned NOT NULL,
  `failures` smallint unsigned NOT NULL DEFAULT 0,
  `since` datetime NOT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
