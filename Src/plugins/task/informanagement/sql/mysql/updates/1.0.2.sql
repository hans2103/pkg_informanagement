-- 1.0.2
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
