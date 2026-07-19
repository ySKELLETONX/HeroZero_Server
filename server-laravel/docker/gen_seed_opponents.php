<?php
/**
 * Gera 04_seed_opponents.sql: os oponentes de duelo capturados viram linhas NPC na
 * tabela `character` (user_id=0). stat_total_* do capture -> stat_base_* (NPC sem equip).
 * Uso: I:\Xampp\php\php.exe gen_seed_opponents.php  (depois rodar o .sql no container)
 */
declare(strict_types=1);

$cap = json_decode(file_get_contents(__DIR__ . '/../data/getDuelOpponents.json'), true);
$out = __DIR__ . '/initdb/04_seed_opponents.sql';
$opp = $cap['opponents'] ?? [];

function s($x): string { return "'" . str_replace(["\\","'"], ["\\\\","''"], (string)$x) . "'"; }

$sql  = "-- Oponentes NPC (user_id=0) na tabela character. Gerado; nao editar a mao.\n";
$sql .= "USE `herozero`;\n";
$sql .= "DELETE FROM `character` WHERE user_id = 0;\n\n";

foreach ($opp as $o) {
    $id     = (int)$o['id'];
    $name   = s($o['name']);
    $gender = s($o['gender']);
    $level  = (int)$o['level'];
    $honor  = (int)$o['honor'];
    $sta    = (int)$o['stat_total_stamina'];
    $str    = (int)$o['stat_total_strength'];
    $crit   = (int)$o['stat_total_critical_rating'];
    $dodge  = (int)$o['stat_total_dodge_rating'];
    $sql .= "INSERT INTO `character` (id,user_id,name,gender,level,honor,"
          . "stat_base_stamina,stat_base_strength,stat_base_critical_rating,stat_base_dodge_rating,"
          . "quest_energy,max_quest_energy,duel_stamina,max_duel_stamina,tutorial_flags,new_user_voucher_ids) "
          . "VALUES ($id,0,$name,$gender,$level,$honor,$sta,$str,$crit,$dodge,100,100,10,10,'','[]');\n";
}

file_put_contents($out, $sql);
echo "OK -> $out  (" . count($opp) . " oponentes)\n";
