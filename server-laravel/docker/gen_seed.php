<?php
/**
 * Gera server/docker/initdb/02_seed.sql com o estado real do personagem skelletonx,
 * extraido da captura server/data/autoLoginUser.json. Casa apenas os campos que
 * existem como coluna na tabela do banco (schema reaproveitado do hz).
 *
 * Uso: I:\Xampp\php\php.exe gen_seed.php
 */
declare(strict_types=1);

$capFile = __DIR__ . '/../data/autoLoginUser.json';
$outFile = __DIR__ . '/initdb/02_seed.sql';

// Colunas reais das tabelas (schema reaproveitado do hz), embutidas p/ nao depender de conexao.
$charCols = ['id','user_id','name','gender','game_currency','xp','level','description','note','ts_last_action','score_honor','score_level','stat_points_available','stat_base_stamina','stat_base_strength','stat_base_critical_rating','stat_base_dodge_rating','stat_bought_stamina','stat_bought_strength','stat_bought_critical_rating','stat_bought_dodge_rating','active_quest_booster_id','ts_active_quest_boost_expires','active_stats_booster_id','ts_active_stats_boost_expires','active_work_booster_id','ts_active_work_boost_expires','ts_active_sense_boost_expires','active_league_booster_id','ts_active_league_boost_expires','ts_active_multitasking_boost_expires','max_quest_stage','current_quest_stage','quest_energy','max_quest_energy','ts_last_quest_energy_refill','quest_energy_refill_amount_today','quest_reward_training_sessions_rewarded_today','honor','ts_last_duel','duel_stamina','max_duel_stamina','ts_last_duel_stamina_change','ts_last_duel_enemies_refresh','current_work_offer_id','stat_trained_stamina','stat_trained_strength','stat_trained_critical_rating','stat_trained_dodge_rating','training_progress_value_stamina','training_progress_value_strength','training_progress_value_critical_rating','training_progress_value_dodge_rating','training_progress_end_stamina','training_progress_end_strength','training_progress_end_critical_rating','training_progress_end_dodge_rating','ts_last_training','training_count','max_training_count','active_worldboss_attack_id','active_dungeon_quest_id','ts_last_dungeon_quest_fail','max_dungeon_index','appearance_skin_color','appearance_hair_color','appearance_hair_type','appearance_head_type','appearance_eyes_type','appearance_eyebrows_type','appearance_nose_type','appearance_mouth_type','appearance_facial_hair_type','appearance_decoration_type','show_mask','tutorial_flags','guild_id','guild_rank','ts_guild_joined','finished_guild_battle_attack_id','finished_guild_battle_defense_id','finished_guild_dungeon_battle_id','guild_donated_game_currency','guild_donated_premium_currency','worldboss_event_id','worldboss_event_attack_count','ts_last_wash_item','ts_last_daily_login_bonus','daily_login_bonus_day','pending_tournament_rewards','ts_last_shop_refresh','shop_refreshes','event_quest_id','friend_data','pending_resource_requests','unused_resources','used_resources','league_points','league_group_id','active_league_fight_id','ts_last_league_fight','league_fight_count','league_opponents','ts_last_league_opponents_refresh','league_stamina','max_league_stamina','ts_last_league_stamina_change','league_stamina_cost','herobook_objectives_renewed_today','slotmachine_spin_count','ts_last_slotmachine_refill','new_user_voucher_ids','current_energy_storage','current_training_storage','received_sidekick','role','herobook_objectives_finished','goal_stats','owned_item_templates','collected_item_pattern','current_item_pattern_values'];
$userCols = ['id','registration_source','registration_ip','ts_creation','email','email_new','password_hash','last_login_ip','login_count','ts_last_login','session_id','session_id_cache1','session_id_cache2','session_id_cache3','session_id_cache4','session_id_cache5','premium_currency','locale','network','geo_country_code','geo_country_code3','geo_country_name','geo_continent_code','settings','ts_banned','trusted','confirmed','email_notifications'];

$cap = json_decode(file_get_contents($capFile), true);
if (!is_array($cap)) { fwrite(STDERR, "captura invalida\n"); exit(1); }

function sqlVal($v): string {
    if ($v === null)            return 'NULL';
    if (is_bool($v))            return $v ? '1' : '0';
    if (is_int($v)||is_float($v)) return (string)$v;
    if (is_array($v))           return "'" . str_replace("'", "''", json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) . "'";
    return "'" . str_replace(["\\","'"], ["\\\\","''"], (string)$v) . "'";
}

/** Monta INSERT com a intersecao (colunas da tabela) x (chaves do objeto). */
function buildInsert(string $table, array $cols, array $obj): string {
    $use = [];
    foreach ($cols as $c) {
        if (array_key_exists($c, $obj)) $use[$c] = sqlVal($obj[$c]);
    }
    if (!$use) return "-- $table: nenhum campo casou\n";
    $names = '`' . implode('`,`', array_keys($use)) . '`';
    $vals  = implode(',', array_values($use));
    return "INSERT INTO `$table` ($names) VALUES ($vals);\n";
}

$char = $cap['character'] ?? [];
$user = $cap['user'] ?? [];

$sql  = "-- Seed gerado de autoLoginUser.json (personagem skelletonx). NAO editar a mao.\n";
$sql .= "USE `herozero`;\n\n";
$sql .= "-- user {$user['id']} / character {$char['id']} ({$char['name']})\n";
$sql .= buildInsert('user', $userCols, $user);
$sql .= buildInsert('character', $charCols, $char);

file_put_contents($outFile, $sql);

$matchedChar = count(array_intersect($charCols, array_keys($char)));
$matchedUser = count(array_intersect($userCols, array_keys($user)));
echo "OK -> $outFile\n";
echo "character: $matchedChar/" . count($charCols) . " colunas casadas\n";
echo "user:      $matchedUser/" . count($userCols) . " colunas casadas\n";
