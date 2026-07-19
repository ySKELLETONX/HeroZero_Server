<?php
/**
 * Gera 03_seed_state.sql: inventory + items(owned_items) + quests do skelletonx,
 * extraidos de server/data/autoLoginUser.json, casando com as colunas das tabelas.
 * Uso: I:\Xampp\php\php.exe gen_seed_state.php  (depois rodar o .sql no container)
 */
declare(strict_types=1);

$cap = json_decode(file_get_contents(__DIR__ . '/../data/autoLoginUser.json'), true);
$out = __DIR__ . '/initdb/03_seed_state.sql';

$invCols = ['id','character_id','mask_item_id','cape_item_id','suit_item_id','belt_item_id','boots_item_id','weapon_item_id','gadget_item_id','missiles_item_id','missiles1_item_id','missiles2_item_id','missiles3_item_id','missiles4_item_id','sidekick_id','bag_item1_id','bag_item2_id','bag_item3_id','bag_item4_id','bag_item5_id','bag_item6_id','bag_item7_id','bag_item8_id','bag_item9_id','bag_item10_id','bag_item11_id','bag_item12_id','bag_item13_id','bag_item14_id','bag_item15_id','bag_item16_id','bag_item17_id','bag_item18_id','shop_item1_id','shop_item2_id','shop_item3_id','shop_item4_id','shop_item5_id','shop_item6_id','shop_item7_id','shop_item8_id','shop_item9_id','shop2_item1_id','shop2_item2_id','shop2_item3_id','shop2_item4_id','shop2_item5_id','shop2_item6_id','shop2_item7_id','shop2_item8_id','shop2_item9_id','item_set_data','sidekick_data'];
$itemCols = ['id','character_id','identifier','type','quality','required_level','charges','item_level','ts_availability_start','ts_availability_end','premium_item','buy_price','sell_price','stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating','stat_weapon_damage'];
$questCols = ['id','character_id','identifier','type','stage','level','status','duration_type','duration_raw','duration','ts_complete','energy_cost','fight_difficulty','fight_npc_identifier','fight_battle_id','used_resources','rewards'];

function v($x): string {
    if ($x === null) return 'NULL';
    if (is_bool($x)) return $x ? '1' : '0';
    if (is_int($x) || is_float($x)) return (string)$x;
    if (is_array($x)) return "'" . str_replace("'", "''", json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) . "'";
    return "'" . str_replace(["\\","'"], ["\\\\","''"], (string)$x) . "'";
}
function ins(string $table, array $cols, array $obj): string {
    $use = [];
    foreach ($cols as $c) if (array_key_exists($c, $obj)) $use[$c] = v($obj[$c]);
    if (!$use) return '';
    return "INSERT INTO `$table` (`" . implode('`,`', array_keys($use)) . "`) VALUES (" . implode(',', array_values($use)) . ");\n";
}

$cid = (int)$cap['character']['id'];
$sql  = "-- Estado do skelletonx (char $cid): inventory + items + quests. Gerado; nao editar a mao.\n";
$sql .= "USE `herozero`;\n";
$sql .= "DELETE FROM `items` WHERE character_id=$cid;\n";
$sql .= "DELETE FROM `quests` WHERE character_id=$cid;\n";
$sql .= "DELETE FROM `inventory` WHERE character_id=$cid;\n\n";

$sql .= ins('inventory', $invCols, $cap['inventory'] ?? []);
// itens completos (instancias) vem da chave `items` do topo, nao de owned_items (resumo).
foreach (($cap['items'] ?? []) as $it) $sql .= ins('items', $itemCols, $it);
foreach (($cap['quests'] ?? []) as $q) $sql .= ins('quests', $questCols, $q);

file_put_contents($out, $sql);
echo "OK -> $out (" . strlen($sql) . " bytes)\n";
echo "inventory=1  items=" . count($cap['items'] ?? []) . "  quests=" . count($cap['quests'] ?? []) . "\n";
