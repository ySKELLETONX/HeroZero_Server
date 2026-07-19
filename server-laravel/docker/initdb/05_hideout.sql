-- Salas do hideout por personagem (build/upgrade/producao real).
-- Ausente das capturas oficiais (sem tabela equivalente conhecida); schema proprio
-- para persistir o que buildHideoutRoom/upgradeHideoutRoom/startHideoutRoomProduction
-- /instantFinishHideoutRoomActivity mutam. getHideout.php cria as 6 salas iniciais
-- (main_building, generator, glue_production, stone_production, attacker_production,
-- defender_production) na primeira visita de cada personagem.

CREATE TABLE IF NOT EXISTS `hideout_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(64) NOT NULL,
  `slot` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `level` int(11) NOT NULL DEFAULT 1,
  `current_resource_amount` int(11) NOT NULL DEFAULT 0,
  `max_resource_amount` int(11) NOT NULL DEFAULT 100,
  `ts_last_resource_change` int(11) NOT NULL DEFAULT 0,
  `ts_activity_end` int(11) NOT NULL DEFAULT 0,
  `current_generator_level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
