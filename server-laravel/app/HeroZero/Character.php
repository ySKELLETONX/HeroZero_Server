<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Modelo do personagem apoiado no banco (tabela `character` + `user`).
 *
 * Estrategia "DB + template": as respostas do jogo tem dezenas de campos, muitos
 * constantes/derivados. Em vez de reconstruir tudo, carregamos o objeto capturado
 * como TEMPLATE e sobrescrevemos ("overlay") apenas os campos que o nosso banco
 * controla e que mudam (moeda, atributos, energias, honra...). Assim a resposta
 * fica completa e ao mesmo tempo reflete o estado real e persistente do banco.
 */
final class Character
{
    /** stat_type do cliente -> sufixo da coluna. */
    public const STAT_MAP = [
        1 => 'stamina',
        2 => 'strength',
        3 => 'critical_rating',
        4 => 'dodge_rating',
    ];
    private const STATS = ['stamina', 'strength', 'critical_rating', 'dodge_rating'];
    private const ITEM_COLUMNS = [
        'identifier', 'type', 'quality', 'required_level', 'charges', 'item_level',
        'ts_availability_start', 'ts_availability_end', 'premium_item', 'buy_price',
        'sell_price', 'stat_stamina', 'stat_strength', 'stat_critical_rating',
        'stat_dodge_rating', 'stat_weapon_damage',
    ];
    private const SHOP_SLOTS = [
        'shop_item1_id', 'shop_item2_id', 'shop_item3_id', 'shop_item4_id', 'shop_item5_id',
        'shop_item6_id', 'shop_item7_id', 'shop_item8_id', 'shop_item9_id',
        'shop2_item1_id', 'shop2_item2_id', 'shop2_item3_id', 'shop2_item4_id', 'shop2_item5_id',
        'shop2_item6_id', 'shop2_item7_id', 'shop2_item8_id', 'shop2_item9_id',
    ];
    private const SHOP1_SLOTS = [
        'shop_item1_id', 'shop_item2_id', 'shop_item3_id', 'shop_item4_id', 'shop_item5_id',
        'shop_item6_id', 'shop_item7_id', 'shop_item8_id', 'shop_item9_id',
    ];
    private const SHOP2_SLOTS = [
        'shop2_item1_id', 'shop2_item2_id', 'shop2_item3_id', 'shop2_item4_id', 'shop2_item5_id',
        'shop2_item6_id', 'shop2_item7_id', 'shop2_item8_id', 'shop2_item9_id',
    ];
    private const EQUIPMENT_SLOT_BY_TYPE = [
        1 => 'mask_item_id',
        2 => 'cape_item_id',
        3 => 'suit_item_id',
        4 => 'belt_item_id',
        5 => 'boots_item_id',
        6 => 'weapon_item_id',
        7 => 'gadget_item_id',
        8 => 'missiles_item_id',
    ];
    private const TRAINING_SETTINGS = [
        1 => 'stamina_indoor',
        2 => 'strength_indoor',
        3 => 'critical_rating_indoor',
        4 => 'dodge_rating_indoor',
    ];
    private const VALID_BOOSTERS = [
        'active_quest_booster_id' => ['booster_quest1', 'booster_quest2', 'booster_quest3'],
        'active_stats_booster_id' => ['booster_stats1', 'booster_stats2', 'booster_stats3'],
        'active_work_booster_id' => ['booster_work1', 'booster_work2', 'booster_work3'],
        'active_league_booster_id' => ['booster_league1', 'booster_league2'],
    ];
    private const BOOSTER_EXPIRES = [
        'active_quest_booster_id' => 'ts_active_quest_boost_expires',
        'active_stats_booster_id' => 'ts_active_stats_boost_expires',
        'active_work_booster_id' => 'ts_active_work_boost_expires',
        'active_league_booster_id' => 'ts_active_league_boost_expires',
    ];
    private const DEFAULT_APPEARANCE = [
        'm' => [
            'appearance_skin_color' => 1,
            'appearance_hair_color' => 1,
            'appearance_hair_type' => 51,
            'appearance_head_type' => 4,
            'appearance_eyes_type' => 8,
            'appearance_eyebrows_type' => 7,
            'appearance_nose_type' => 4,
            'appearance_mouth_type' => 1,
            'appearance_facial_hair_type' => 0,
            'appearance_decoration_type' => 0,
            'show_mask' => 1,
        ],
        'f' => [
            'appearance_skin_color' => 1,
            'appearance_hair_color' => 1,
            'appearance_hair_type' => 25,
            'appearance_head_type' => 5,
            'appearance_eyes_type' => 9,
            'appearance_eyebrows_type' => 5,
            'appearance_nose_type' => 4,
            'appearance_mouth_type' => 24,
            'appearance_facial_hair_type' => 0,
            'appearance_decoration_type' => 0,
            'show_mask' => 1,
        ],
    ];
    private const TUTORIAL_DONE_FLAGS = [
        'ts_prb_start' => -1,
        'shop2_football_generic' => true,
        'itemImprovementsUpdate' => true,
        'first_visit' => true,
        'mission_shown' => true,
        'first_mission_opened' => true,
        'first_mission_started' => true,
        'first_mission' => true,
        'stats_spent' => true,
        'shop_shown' => true,
        'first_item' => true,
        'duel_shown' => true,
        'first_duel' => true,
        'tutorial_finished' => true,
        'training_new' => true,
        'dungeons' => true,
    ];

    private array $row;   // linha da tabela character
    private array $user;  // linha da tabela user

    private function __construct(array $row, array $user)
    {
        $this->row  = $row;
        $this->user = $user;
    }

    /**
     * Cria um personagem NOVO e limpo (nivel 1, zerado) para uma conta recem-criada.
     * Sem boosters, sem itens, sem tutorial, energias cheias. Persiste e retorna.
     * Este e o caminho "conta do zero": nada aqui vem de captura/skelletonx.
     */
    public static function createNew(int $userId, string $name, string $gender = 'm'): self
    {
        Db::exec(
            "INSERT INTO `character`
                (user_id, name, gender, level, xp, honor, game_currency,
                 stat_base_stamina, stat_base_strength, stat_base_critical_rating, stat_base_dodge_rating,
                 quest_energy, max_quest_energy, current_quest_stage, max_quest_stage,
                 duel_stamina, max_duel_stamina,
                 training_count, max_training_count,
                 training_energy, max_training_energy, ts_last_training_energy_change,
                 league_stamina, max_league_stamina, league_stamina_cost,
                 active_quest_booster_id, active_stats_booster_id, active_work_booster_id, active_league_booster_id,
                 tutorial_flags, new_user_voucher_ids)
             VALUES
                (?, ?, ?, 1, 0, 0, 300,
                 10, 10, 0, 0,
                 100, 100, 1, 1,
                 10, 10,
                 0, 3,
                 100, 100, ?,
                 10, 10, 20,
                 '', '', '', '',
                 '', '[]')",
            [$userId, $name, $gender, time()]
        );
        $charId = (int)Db::pdo()->lastInsertId();
        $char = self::load($charId);
        $char->syncAppearanceDefaults();
        // inventario vazio (todos os slots zerados) p/ a conta nova ter shape valida.
        Db::exec("INSERT INTO `inventory` (character_id, item_set_data, sidekick_data) VALUES (?, '', '')", [$charId]);
        // quests iniciais: SEM elas o cliente quebra. Conta com quests=[] estoura no boot
        // (get_quests().iterator() de null) e, se omitirmos a chave, `this._quests` fica null
        // e o painel de Quests estoura em getQuestById (`_quests.h`). A unica shape valida e
        // ter >=1 quest real (como o skelletonx tem 3 de stage 1).
        self::seedStarterQuests($charId, 1);
        return self::load($charId);
    }

    /** Sobrescreve a aparencia (chamado por createCharacter, com os valores escolhidos no cliente). */
    public function setAppearance(array $appearance): void
    {
        $cols = [
            'hair_color' => 'appearance_hair_color', 'skin_color' => 'appearance_skin_color',
            'hair_type' => 'appearance_hair_type', 'head_type' => 'appearance_head_type',
            'eyes_type' => 'appearance_eyes_type', 'eyebrows_type' => 'appearance_eyebrows_type',
            'nose_type' => 'appearance_nose_type', 'mouth_type' => 'appearance_mouth_type',
            'facial_hair_type' => 'appearance_facial_hair_type', 'decoration_type' => 'appearance_decoration_type',
        ];
        $sets = [];
        $values = [];
        foreach ($cols as $param => $col) {
            if (!array_key_exists($param, $appearance)) continue;
            $sets[] = "`$col` = ?";
            $values[] = (int)$appearance[$param];
        }
        if ($sets === []) return;
        $values[] = $this->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);
        foreach ($cols as $param => $col) {
            if (array_key_exists($param, $appearance)) $this->row[$col] = (int)$appearance[$param];
        }
    }

    /** Define o nome do personagem (tela de criacao / troca de nome). */
    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 32) {
            throw new GameError('errRequestInvalidParameter');
        }
        Db::exec('UPDATE `character` SET name = ? WHERE id = ?', [$name, $this->id()]);
        $this->row['name'] = $name;
    }

    /**
     * Semeia as quests de STAGE 1 (as mesmas do skelletonx) para um personagem novo,
     * no nivel informado. Idempotente: nao insere se o personagem ja tiver quests.
     */
    public static function seedStarterQuests(int $charId, int $level = 1): void
    {
        $has = (int)Db::value('SELECT COUNT(*) FROM `quests` WHERE character_id = ?', [$charId]);
        if ($has > 0) return;
        self::insertQuestPool($charId, $level, 3);
    }

    /**
     * Templates de quest com identifier VALIDO na localizacao (quest/<id>/briefing).
     * So conhecemos os do stage 1 (capturados do skelletonx); usar identifier inventado
     * quebra o texto da missao no cliente.
     * [identifier, type, duration_type, duration_raw, duration, energy_cost,
     *  fight_difficulty, fight_npc_identifier, rewards]
     */
    private const QUEST_TEMPLATES = [
        1 => [
            ['quest_stage1_fight1', 2, 1, 360,  60, 1, 1, 'npc_business_man_artless', '{"coins":2,"xp":43}'],
            ['quest_stage1_time2',  1, 2, 660, 120, 2, 0, '',                         '{"coins":3,"xp":76}'],
            ['quest_stage1_time5',  1, 1, 300,  60, 1, 0, '',                         '{"coins":1,"xp":35}'],
        ],
        // Stage 2: definicoes reais capturadas (620_claimQuestRewards, br31).
        2 => [
            ['quest_stage2_time2',  1, 3, 1260, 660, 11, 0, '',                  '{"coins":163,"xp":2330}'],
            ['quest_stage2_fight1', 2, 1, 240,  120,  2, 1, 'npc_hoody_scruffy', '{"coins":31,"xp":489}'],
        ],
    ];

    /** Insere $count quests novas (status=1) para o personagem, no nivel e stage dados. */
    private static function insertQuestPool(int $charId, int $level, int $count, int $stage = 1): void
    {
        // Stage sem templates capturados: reusa os do maior stage conhecido
        // (identifier precisa existir na localizacao, senao o texto da missao quebra).
        $byStage = self::QUEST_TEMPLATES[$stage] ?? self::QUEST_TEMPLATES[max(array_keys(self::QUEST_TEMPLATES))];
        $pool = [];
        while (count($pool) < max(1, $count)) {
            $shuffled = $byStage;
            shuffle($shuffled);
            $pool = array_merge($pool, $shuffled);
        }
        foreach (array_slice($pool, 0, max(0, $count)) as $q) {
            Db::exec(
                "INSERT INTO `quests`
                    (character_id, identifier, type, stage, level, status,
                     duration_type, duration_raw, duration, ts_complete, energy_cost,
                     fight_difficulty, fight_npc_identifier, fight_battle_id, used_resources, rewards)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, 0, ?, ?, ?, 0, 0, ?)",
                [$charId, $q[0], $q[1], $stage, $level, $q[2], $q[3], $q[4], $q[5], $q[6], $q[7], self::scaleQuestRewards($q[8], $level)]
            );
        }
    }

    /**
     * Escala coins/xp da recompensa base (nivel 1) pelo nivel do personagem: no jogo
     * original, missoes de personagens mais altos rendem mais. O fator antigo (+15%/nivel
     * fixo) crescia muito mais devagar que a curva OFICIAL de xp por nivel (data/levels_xp.json,
     * quase exponencial) -> a partir do nivel 2 o xp de missao virava insignificante perto do
     * quanto falta pro proximo nivel (ex.: nivel 2->3 exige 911 xp, mas o ciclo de 3 missoes
     * dava so ~150). Agora o fator acompanha a RAZAO entre o "buraco" de xp do nivel atual
     * pro seguinte e o buraco do nivel 1 pro 2 (baseline dos valores capturados), entao o
     * ritmo de missoes por nivel fica ~constante em vez de cair conforme sobe de nivel.
     */
    private static function scaleQuestRewards(string $baseRewardsJson, int $level): string
    {
        $rewards = json_decode($baseRewardsJson, true);
        if (!is_array($rewards)) return $baseRewardsJson;
        $factor = self::xpRewardFactor($level);
        foreach (['coins', 'xp'] as $key) {
            if (isset($rewards[$key])) {
                $rewards[$key] = max(1, (int)round($rewards[$key] * $factor));
            }
        }
        return json_encode($rewards, JSON_UNESCAPED_SLASHES);
    }

    /** Fator de escala de recompensa p/ o nivel dado, derivado da curva oficial de xp. */
    private static function xpRewardFactor(int $level): float
    {
        $table = self::xpTable();
        if (count($table) < 2) {
            // fallback antigo, so se a tabela oficial sumir
            return 1 + max(0, $level - 1) * 0.15;
        }
        $gap1 = ($table[2] ?? 547) - ($table[1] ?? 0);
        if ($gap1 <= 0) $gap1 = 1;

        $gapCur = $table[$level + 1] ?? null;
        if ($gapCur === null) {
            // fora da tabela conhecida: extrapola pelo ultimo "buraco" registrado
            $maxLvl = max(array_keys($table));
            $lastGap = ($table[$maxLvl] ?? 0) - ($table[$maxLvl - 1] ?? 0);
            return max(1.0, $lastGap / $gap1);
        }
        $gapCur -= $table[$level] ?? 0;
        return max(1.0, $gapCur / $gap1);
    }

    /**
     * action generateNewQuests: troca o pool de missoes. Remove as quests ainda nao
     * iniciadas e insere um pool novo; a quest ativa (em andamento/concluida) e mantida
     * para nao invalidar active_quest_id. Custo premium: 0 por enquanto (as constantes
     * quest_refresh_* vem do asset de constants do CDN, que nao hospedamos).
     */
    public function regenerateQuests(): void
    {
        $activeId = (int)($this->row['active_quest_id'] ?? 0);
        // active_quest_id pode estar pendurado (a linha ja foi deletada por claim);
        // nesse caso zeramos e geramos o pool cheio.
        if ($activeId > 0) {
            $exists = (int)Db::value(
                'SELECT COUNT(*) FROM `quests` WHERE id = ? AND character_id = ?',
                [$activeId, $this->id()]
            );
            if ($exists === 0) {
                Db::exec('UPDATE `character` SET active_quest_id = 0 WHERE id = ?', [$this->id()]);
                $this->row['active_quest_id'] = 0;
                $activeId = 0;
            }
        }
        Db::exec(
            'DELETE FROM `quests` WHERE character_id = ? AND id <> ?',
            [$this->id(), $activeId]
        );
        $kept = $activeId > 0 ? 1 : 0;
        self::insertQuestPool($this->id(), (int)$this->row['level'], 3 - $kept,
            max(1, (int)($this->row['current_quest_stage'] ?? 1)));
    }

    public static function load(int $characterId): self
    {
        $row = Db::row('SELECT * FROM `character` WHERE id = ?', [$characterId]);
        if ($row === null) {
            throw new GameError('errCharacterNotFound');
        }
        $user = Db::row('SELECT * FROM `user` WHERE id = ?', [(int)$row['user_id']]) ?? [];
        return new self($row, $user);
    }

    public static function loadByUser(int $userId): self
    {
        $row = Db::row('SELECT * FROM `character` WHERE user_id = ? ORDER BY id LIMIT 1', [$userId]);
        if ($row === null) {
            throw new GameError('errCharacterNotFound');
        }
        $user = Db::row('SELECT * FROM `user` WHERE id = ?', [$userId]) ?? [];
        return new self($row, $user);
    }

    public function id(): int      { return (int)$this->row['id']; }
    public function userId(): int  { return (int)$this->row['user_id']; }
    public function name(): string { return (string)$this->row['name']; }
    public function gameCurrency(): int { return (int)$this->row['game_currency']; }
    public function premiumCurrency(): int { return (int)($this->user['premium_currency'] ?? 0); }

    /** @var array<int,int>|null tabela oficial nivel => xp total minimo (data/levels_xp.json). */
    private static ?array $xpTable = null;

    /**
     * Tabela OFICIAL de niveis, extraida do constants do CDN do jogo
     * (hz-static .../assets/data/constants_json.data -> constants.levels).
     * O cliente usa exatamente essa tabela p/ desenhar a barra de XP; qualquer
     * outra curva no servidor faz a barra mostrar progresso negativo.
     */
    private static function xpTable(): array
    {
        if (self::$xpTable === null) {
            $raw = json_decode((string)@file_get_contents(__DIR__ . '/../../data/levels_xp.json'), true);
            self::$xpTable = [];
            if (is_array($raw)) {
                foreach ($raw as $lvl => $xp) {
                    self::$xpTable[(int)$lvl] = (int)$xp;
                }
            }
        }
        return self::$xpTable;
    }

    /** XP total minima para cada nivel (tabela oficial; fallback: curva antiga). */
    public static function levelForXp(int $xp): int
    {
        $table = self::xpTable();
        if ($table === []) {
            // fallback antigo, so se o arquivo sumir
            $level = 1;
            while ($level < 400 && $xp >= (int)round(30 * $level ** 1.7)) {
                $level++;
            }
            return $level;
        }
        $level = 1;
        foreach ($table as $lvl => $required) {
            if ($xp >= $required) {
                $level = max($level, $lvl);
            } else {
                break;
            }
        }
        return $level;
    }

    /** Pontos de habilidade concedidos por nivel subido (jogo original: 2 no nivel 2). */
    private const STAT_POINTS_PER_LEVEL = 2;

    /** Recalcula level/score_level pelo XP atual, inclusive para contas antigas. Concede stat points por nivel ganho. */
    public function syncLevelFromXp(): void
    {
        $xp = (int)$this->row['xp'];
        $level = self::levelForXp($xp);
        $oldLevel = (int)$this->row['level'];
        if ($level === $oldLevel) {
            return;
        }
        $gainedPoints = $level > $oldLevel ? ($level - $oldLevel) * self::STAT_POINTS_PER_LEVEL : 0;
        Db::exec(
            'UPDATE `character` SET level = ?, score_level = ?, stat_points_available = stat_points_available + ? WHERE id = ?',
            [$level, ($level * 100000000) + $xp, $gainedPoints, $this->id()]
        );
        $this->row['level'] = $level;
        $this->row['score_level'] = ($level * 100000000) + $xp;
        $this->row['stat_points_available'] = (int)$this->row['stat_points_available'] + $gainedPoints;
    }

    /**
     * Personagem que ja passou do inicio do jogo nao deve ficar preso no tutorial.
     * NAO usar nivel 2: no jogo original, ao chegar no nivel 2 o tutorial da LOJA
     * (brecho) ainda esta por vir (shop_shown/first_item ainda devem ficar false).
     * So a partir do nivel 3 (ja passou da loja) e seguro marcar tudo como visto.
     */
    public function syncTutorialForLevel(): void
    {
        if ((int)$this->row['level'] < 3) {
            return;
        }

        $flags = json_decode((string)($this->row['tutorial_flags'] ?? ''), true);
        if (!is_array($flags)) $flags = [];

        $changed = false;
        foreach (self::TUTORIAL_DONE_FLAGS as $key => $value) {
            if (!array_key_exists($key, $flags) || $flags[$key] !== $value) {
                $flags[$key] = $value;
                $changed = true;
            }
        }
        if (!$changed) {
            return;
        }

        $json = json_encode($flags, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET tutorial_flags = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['tutorial_flags'] = $json;
    }

    /** Corrige contas antigas criadas com partes de rosto zeradas. */
    public function syncAppearanceDefaults(): void
    {
        $gender = (string)($this->row['gender'] ?? 'm');
        $defaults = self::DEFAULT_APPEARANCE[$gender] ?? self::DEFAULT_APPEARANCE['m'];
        $required = [
            'appearance_skin_color',
            'appearance_hair_color',
            'appearance_hair_type',
            'appearance_head_type',
            'appearance_eyes_type',
            'appearance_eyebrows_type',
            'appearance_nose_type',
            'appearance_mouth_type',
        ];

        $needsFix = false;
        foreach ($required as $col) {
            if ((int)($this->row[$col] ?? 0) <= 0) {
                $needsFix = true;
                break;
            }
        }
        if (!$needsFix) {
            return;
        }

        $sets = [];
        $values = [];
        foreach ($defaults as $col => $value) {
            $sets[] = "`$col` = ?";
            $values[] = $value;
            $this->row[$col] = $value;
        }
        $values[] = $this->id();
        Db::exec('UPDATE `character` SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);
    }

    /** Garante sessoes de treino para personagens que ja sairam do tutorial inicial. */
    public function syncTrainingForLevel(): void
    {
        if ((int)$this->row['level'] < 2) {
            return;
        }
        $max = max(3, (int)($this->row['max_training_count'] ?? 0));
        $count = (int)($this->row['training_count'] ?? 0);
        if ($count > 0 && $max === (int)($this->row['max_training_count'] ?? 0)) {
            return;
        }
        if ($count <= 0) {
            $count = $max;
        }
        Db::exec(
            'UPDATE `character` SET training_count = ?, max_training_count = ? WHERE id = ?',
            [$count, $max, $this->id()]
        );
        $this->row['training_count'] = $count;
        $this->row['max_training_count'] = $max;
    }

    /**
     * Bonus de equipamento por atributo (itens equipados), constante enquanto o
     * inventario nao muda. Derivado de UMA fonte completa e autoritativa: o character
     * da fixture de boot autoLoginUser (unico objeto capturado com base E total dos 4).
     *   equip_X = fixture.stat_total_X - fixture.stat_base_X
     * Como a base da fixture == base semeada no banco, total_vivo = base_do_banco + equip
     * continua correto depois de comprar/treinar pontos.
     * TODO(RE): calcular a partir do inventario equipado quando ele vier do banco.
     */
    private static function equipBonus(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = array_fill_keys(self::STATS, 0);
        $file = __DIR__ . '/../../data/autoLoginUser.json';
        if (is_file($file)) {
            $c = json_decode((string)file_get_contents($file), true)['character'] ?? [];
            foreach (self::STATS as $s) {
                if (isset($c["stat_total_$s"], $c["stat_base_$s"])) {
                    $cache[$s] = (int)$c["stat_total_$s"] - (int)$c["stat_base_$s"];
                }
            }
        }
        return $cache;
    }

    /**
     * Sobrescreve no objeto `character` do template TODOS os campos que existem como
     * coluna no banco (identidade inclusa: name, gender, aparencia, boosters,
     * tutorial_flags, quest_pool, ...), preservando o TIPO do template (int/bool/string).
     * Assim skelletonx (semeado no banco) mantem tudo dele e uma conta nova (colunas
     * vazias/zero) nasce limpa, sem caso especial. Os ~26 campos "so-de-resposta" que nao
     * sao coluna (constantes/derivados do sistema) permanecem do template, sem risco de boot.
     * Por cima, recomputa stat_total_* (base + trained + equipamento).
     */
    public function overlayCharacter(array $tplChar): array
    {
        $this->syncLevelFromXp();
        $this->syncTutorialForLevel();
        $this->syncAppearanceDefaults();
        $this->syncTrainingForLevel();
        $out = $tplChar;

        // 1) Overlay de toda coluna do banco presente no template, castando pelo tipo do template.
        foreach ($this->row as $col => $val) {
            if (!array_key_exists($col, $tplChar)) continue;   // preserva a shape do template
            $out[$col] = self::castLike($tplChar[$col], $val);
        }
        $out['id'] = $this->id();

        // league_group_id codifica divisao E grupo: divisao = floor(league_group_id / 100000)
        // (Dd.getLeagueDivisionId no client; Y.get_leagueDivisionId deriva dai). Ou seja,
        // "estar na divisao 1" = league_group_id >= 100000, nunca 1. `leaguedivision` e a
        // divisao ja derivada (lida por xi.getInt("leaguedivision") na visualizacao de batalha).
        $out['leaguedivision'] = intdiv((int)($this->row['league_group_id'] ?? 0), 100000);

        // O cliente so aceita boosters que existem em constants.boosters.
        // Preservamos compras validas e zeramos apenas ids desconhecidos.
        foreach (self::VALID_BOOSTERS as $field => $validIds) {
            $expiresField = self::BOOSTER_EXPIRES[$field] ?? null;
            if (array_key_exists($field, $out) && $out[$field] !== '' && !in_array($out[$field], $validIds, true)) {
                $out[$field] = '';
                if ($expiresField !== null && array_key_exists($expiresField, $out)) {
                    $out[$expiresField] = 0;
                }
            }
        }

        // 2) Totais de atributo computados (nao sao coluna): base + trained + equipamento.
        $equip = self::equipBonus();
        $sumTotal = 0;
        foreach (self::STATS as $s) {
            $total = (int)$this->row["stat_base_$s"] + (int)($this->row["stat_trained_$s"] ?? 0) + ($equip[$s] ?? 0);
            $out["stat_total_$s"] = $total;
            $sumTotal += $total;
        }
        if (array_key_exists('stat_total', $tplChar)) {
            $out['stat_total'] = $sumTotal;
        }

        // 3) quest_pool: NAO e coluna, entao o overlay acima nao o toca e o valor do
        // template (ids do skelletonx) vazaria. O painel de Quests le
        // get_questPool() -> {stage:[quest_id,...]} e faz getQuestById(id) em _quests.
        // Se os ids nao forem os das quests REAIS deste personagem, os botoes ficam
        // vazios (nenhuma missao aparece). Recomputamos do banco.
        if (array_key_exists('quest_pool', $tplChar)) {
            $out['quest_pool'] = $this->questPoolJson();
        }
        // Ids de evento que NAO sao coluna: recomputa do estado real. Se ficar o
        // valor do template (id do jogador da captura), o cliente procura um
        // objeto que nao existe na colecao e crasha em get_id() (painel quests).
        if (array_key_exists('treasure_event_id', $tplChar)) {
            $out['treasure_event_id'] = $this->ensureTreasureEventState()['identifier'] !== '' ? 1 : 0;
        }
        foreach (['draw_event_id', 'hidden_object_event_quest_id'] as $evField) {
            if (array_key_exists($evField, $tplChar)) {
                $out[$evField] = 0;   // eventos ainda nao emulados
            }
        }

        // Sempre presente: pontos gratis de level-up. Sem a chave na resposta o
        // cliente mantem o valor antigo e os pontos "gratis" nunca baixam
        // (upgrade infinito no painel de stats).
        $out['stat_points_available'] = (int)($this->row['stat_points_available'] ?? 0);

        // Sempre presente, mesmo se o template nao tiver a chave (ex.: template de
        // getDailyBonusRewardData): o cliente faz merge por chave e manteria um
        // active_training_id velho apontando para um treino sem quests no mapa.
        $activeTraining = $this->activeTrainingRow();
        $out['active_training_id'] = $activeTraining === null
            ? 0
            : ($this->id() * 100) + (int)$activeTraining['stat_type'];
        if (array_key_exists('training_pool', $tplChar)) {
            $ids = [];
            foreach (array_keys(self::TRAINING_SETTINGS) as $statType) {
                $ids[] = ($this->id() * 100) + $statType;
            }
            $out['training_pool'] = json_encode($ids, JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('ts_last_training_refresh', $tplChar)) {
            $out['ts_last_training_refresh'] = (int)($this->row['ts_last_training'] ?? time());
        }
        if (array_key_exists('ts_last_training_finished', $tplChar)) {
            $out['ts_last_training_finished'] = 0;
        }
        // training_energy/max_training_energy/ts_last_training_energy_change agora sao
        // colunas reais (ver liveTrainingEnergy()) -- ja vem certas do loop de overlay acima.
        // Antes eram fixadas em max_training_energy/agora aqui, entao a barra nunca baixava
        // (startTrainingQuest nunca debitava porque nao existia onde persistir o gasto).
        return $out;
    }

    /**
     * Monta o quest_pool REAL do personagem: JSON string {"<stage>":[quest_id,...]}.
     * E o que o cliente usa p/ decidir quais quests exibir no painel. Vazio -> "{}".
     */
    public function questPoolJson(): string
    {
        $rows = Db::rows('SELECT id, stage FROM `quests` WHERE character_id = ? ORDER BY stage, id', [$this->id()]);
        $pool = [];
        foreach ($rows as $r) {
            $pool[(string)(int)$r['stage']][] = (int)$r['id'];
        }
        return $pool ? json_encode($pool) : '{}';
    }

    /** Converte $val para o mesmo tipo do valor de referencia $like (do template). */
    private static function castLike($like, $val)
    {
        if (is_bool($like)) return (bool)$val;
        if (is_int($like))  return (int)$val;
        if (is_float($like))return (float)$val;
        return $val === null ? '' : (string)$val;
    }

    /**
     * "Esvazia" uma estrutura preservando a shape: lista -> [], objeto -> mesmas chaves
     * com valores zerados (0 / '' / false / recursao). Usado p/ zerar as estruturas de
     * conta (inventario, quests, itens...) numa conta nova, sem vazar dados de outra conta
     * e sem quebrar o formato que o cliente espera.
     */
    public static function emptyLike($v)
    {
        if (is_array($v)) {
            // lista (chaves 0..n) -> vazia; mapa associativo -> zera cada valor.
            if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
                return [];
            }
            $out = [];
            foreach ($v as $k => $val) {
                $out[$k] = self::emptyLike($val);
            }
            return $out;
        }
        if (is_bool($v))  return false;
        if (is_int($v))   return 0;
        if (is_float($v)) return 0.0;
        return '';
    }

    /** Objeto `user` reduzido (id, session_id, email, premium...) usado em varias respostas. */
    public function overlayUser(array $tplUser): array
    {
        $out = $tplUser;
        foreach ($this->user as $key => $value) {
            if (array_key_exists($key, $out)) {
                $out[$key] = self::castLike($out[$key], $value);
            }
        }

        $out['id'] = $this->userId();
        if (array_key_exists('premium_currency', $tplUser)) {
            $out['premium_currency'] = (int)($this->user['premium_currency'] ?? 0);
        }
        // CRITICO: o cliente ADOTA user.session_id da resposta de boot
        // (rf.set_userSessionId(this._user.get_sessionId())) e o usa em TODAS as
        // requisicoes seguintes. Se vazar a sessao do template (skelletonx), a conta
        // real manda a sessao errada e o guard rejeita (errLoginInvalidSession).
        // Por isso devolvemos a sessao REAL da conta (coluna user.session_id).
        if (array_key_exists('session_id', $tplUser)) {
            $out['session_id'] = (string)($this->user['session_id'] ?? '');
        }
        // Identidade coerente (cosmetico, mas evita confundir): email/locale do banco.
        if (array_key_exists('email', $tplUser) && isset($this->user['email'])) {
            $out['email'] = (string)$this->user['email'];
        }
        if (array_key_exists('locale', $tplUser) && isset($this->user['locale'])) {
            $out['locale'] = (string)$this->user['locale'];
        }
        return $out;
    }

    /** Objeto `inventory` (slots de equipamento/loja) do banco, tipado pelo template. */
    public function inventoryData(array $tplInv): array
    {
        $this->ensureShopItems();
        $out = self::emptyLike($tplInv);          // shape completa, tudo zerado
        $row = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($row) {
            foreach ($row as $k => $v) {
                if (array_key_exists($k, $tplInv)) $out[$k] = self::castLike($tplInv[$k], $v);
            }
        }
        return $out;
    }

    /** Lista `items` (instancias de item do personagem) do banco, tipada pelo template. */
    public function itemsData(array $tplItem): array
    {
        $this->ensureShopItems();
        $rows = Db::rows('SELECT * FROM `items` WHERE character_id = ? ORDER BY id', [$this->id()]);
        return array_map(fn(array $r) => self::castRowLike($tplItem, $r), $rows);
    }

    /**
     * Gera uma loja inicial real para personagens sem itens de loja. A fixture fornece
     * apenas o catalogo/base; os IDs novos pertencem ao personagem atual.
     */
    public function ensureShopItems(): void
    {
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($inv === null) {
            Db::exec("INSERT INTO `inventory` (character_id, item_set_data, sidekick_data) VALUES (?, '', '')", [$this->id()]);
            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        }
        if ($inv === null) {
            return;
        }

        $shopIds = [];
        foreach (self::SHOP_SLOTS as $slot) {
            $id = (int)($inv[$slot] ?? 0);
            if ($id > 0) $shopIds[] = $id;
        }
        if ($shopIds !== []) {
            $placeholders = implode(',', array_fill(0, count($shopIds), '?'));
            $count = (int)Db::value(
                "SELECT COUNT(*) FROM `items` WHERE character_id = ? AND id IN ($placeholders)",
                array_merge([$this->id()], $shopIds)
            );
            if ($count > 0) {
                return;
            }
        }

        // Loja inicial GERADA no nivel do personagem (antes vinha do fixture: 22
        // itens nivel 1 fixos -> "brecho sempre igual" mesmo em nivel alto).
        $newSlotIds = [];
        foreach (self::SHOP_SLOTS as $slot) {
            $newSlotIds[$slot] = $this->insertItemFromTemplate($this->generateShopItem());
        }

        $sets = [];
        $values = [];
        foreach ($newSlotIds as $slot => $id) {
            $sets[] = "`$slot` = ?";
            $values[] = $id;
        }
        $values[] = $this->id();
        Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', $values);
    }

    /** Substitui os itens da loja selecionada por novas instancias persistidas. */
    public function refreshShopItems(int $shopIndex, bool $usePremium): void
    {
        $this->ensureShopItems();
        $slots = $shopIndex === 2 ? self::SHOP2_SLOTS : self::SHOP1_SLOTS;

        if ($usePremium) {
            if ($this->premiumCurrency() < 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - 1 WHERE id = ?', [$this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - 1;
        }

        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        if ($inv === null) {
            throw new GameError('errInventoryNotFound');
        }

        $oldIds = [];
        foreach ($slots as $slot) {
            $id = (int)($inv[$slot] ?? 0);
            if ($id > 0) $oldIds[] = $id;
        }
        if ($oldIds !== []) {
            $placeholders = implode(',', array_fill(0, count($oldIds), '?'));
            Db::exec(
                "DELETE FROM `items` WHERE character_id = ? AND id IN ($placeholders)",
                array_merge([$this->id()], $oldIds)
            );
        }

        $sets = [];
        $values = [];
        foreach ($slots as $slot) {
            $newId = $this->insertItemFromTemplate($this->generateShopItem($shopIndex === 2));
            $sets[] = "`$slot` = ?";
            $values[] = $newId;
        }
        $sets[] = '`ts_last_shop_refresh` = ?';
        $values[] = time();
        $sets[] = '`shop_refreshes` = `shop_refreshes` + 1';
        $values[] = $this->id();
        Db::exec('UPDATE `inventory` SET ' . implode(', ', array_slice($sets, 0, count($sets) - 2)) . ' WHERE character_id = ?', array_merge(array_slice($values, 0, count($values) - 2), [$this->id()]));
        Db::exec(
            'UPDATE `character` SET ts_last_shop_refresh = ?, shop_refreshes = shop_refreshes + 1 WHERE id = ?',
            [time(), $this->id()]
        );
        $this->row['ts_last_shop_refresh'] = time();
        $this->row['shop_refreshes'] = (int)($this->row['shop_refreshes'] ?? 0) + 1;
    }

    /** Identifiers validos por tipo (gerado do i18n oficial do CDN; ~3700 itens). */
    private static ?array $shopIdentifierCatalog = null;

    private static function shopIdentifierCatalog(): array
    {
        return self::$shopIdentifierCatalog ??= (require __DIR__ . '/../../data/shop_item_identifiers.php');
    }

    /**
     * Gera um item de loja ALEATORIO escalado ao nivel do personagem (a captura so
     * tinha 22 itens nivel 1 -> brecho sempre igual). Identifier vem do catalogo
     * oficial (o cliente busca a arte por assets/items/{identifier}_i.webp no CDN).
     * $premiumShop (shop_index 2): qualidade melhor e mais itens por donut.
     */
    private function generateShopItem(bool $premiumShop = false): array
    {
        $catalog = self::shopIdentifierCatalog();
        $type = random_int(1, 7);
        $ids = $catalog[$type] ?? [];
        if ($ids === []) {
            $type = 6;
            $ids = $catalog[6];
        }
        $identifier = $ids[random_int(0, count($ids) - 1)];

        $reqLevel = max(1, (int)($this->row['level'] ?? 1) - random_int(0, 4));
        $roll = random_int(1, 100);
        if ($premiumShop) {
            $quality = $roll <= 40 ? 2 : 3;
        } else {
            $quality = $roll <= 70 ? 1 : ($roll <= 95 ? 2 : 3);
        }
        $qualityMult = 1 + 0.25 * ($quality - 1);

        // Orcamento de stats distribuido em 1-2 atributos; arma ganha dano proprio.
        $budget = (int)ceil(($reqLevel * 1.6 + 2) * $qualityMult);
        $stats = ['stat_stamina' => 0, 'stat_strength' => 0, 'stat_critical_rating' => 0, 'stat_dodge_rating' => 0];
        $keys = array_keys($stats);
        shuffle($keys);
        if (random_int(1, 100) <= 45) {
            $first = random_int((int)ceil($budget * 0.4), (int)ceil($budget * 0.7));
            $stats[$keys[0]] = $first;
            $stats[$keys[1]] = max(1, $budget - $first);
        } else {
            $stats[$keys[0]] = $budget;
        }
        $weaponDamage = $type === 6 ? (int)ceil($reqLevel * 0.9) + 2 : 0;

        $isPremium = random_int(1, 100) <= ($premiumShop ? 50 : 12);
        if ($isPremium) {
            $buy = 3 + intdiv($reqLevel, 5) + $quality * 2;              // donuts
            $sell = (int)round(4 * ($reqLevel ** 1.5));                  // vende por moeda
        } else {
            $buy = max(8, (int)round(8 * ($reqLevel ** 1.5) * (1 + 0.3 * ($quality - 1))));
            $sell = max(4, intdiv($buy, 2));
        }

        return [
            'identifier' => $identifier,
            'type' => $type,
            'quality' => $quality,
            'required_level' => $reqLevel,
            'charges' => 0,
            'item_level' => $reqLevel,
            'ts_availability_start' => 0,
            'ts_availability_end' => 0,
            'premium_item' => $isPremium,
            'buy_price' => $buy,
            'sell_price' => $sell,
            'stat_stamina' => $stats['stat_stamina'],
            'stat_strength' => $stats['stat_strength'],
            'stat_critical_rating' => $stats['stat_critical_rating'],
            'stat_dodge_rating' => $stats['stat_dodge_rating'],
            'stat_weapon_damage' => $weaponDamage,
        ];
    }

    private function insertItemFromTemplate(array $item): int
    {
        $cols = ['character_id'];
        $values = [$this->id()];
        foreach (self::ITEM_COLUMNS as $col) {
            $cols[] = "`$col`";
            $values[] = $item[$col] ?? 0;
        }
        $sql = 'INSERT INTO `items` (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
        Db::exec($sql, $values);
        return (int)Db::pdo()->lastInsertId();
    }

    /** Compra item da loja e equipa no slot solicitado pelo cliente. */
    public function buyShopItem(int $itemId, int $targetSlot): void
    {
        $this->ensureShopItems();
        $item = Db::row('SELECT * FROM `items` WHERE id = ? AND character_id = ?', [$itemId, $this->id()]);
        if ($item === null) {
            throw new GameError('errInventoryInvalidItem');
        }

        $slotCol = self::EQUIPMENT_SLOT_BY_TYPE[$targetSlot] ?? self::EQUIPMENT_SLOT_BY_TYPE[(int)$item['type']] ?? null;
        if ($slotCol === null) {
            throw new GameError('errRequestInvalidParameter');
        }
        $isPremium = (int)$item['premium_item'] > 0;
        $price = max(0, (int)$item['buy_price']);
        if ($isPremium && $this->premiumCurrency() < $price) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        if (!$isPremium && $this->gameCurrency() < $price) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }

        $shopSlot = null;
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$this->id()]);
        foreach (self::SHOP_SLOTS as $slot) {
            if ((int)($inv[$slot] ?? 0) === $itemId) {
                $shopSlot = $slot;
                break;
            }
        }
        if ($shopSlot === null) {
            throw new GameError('errInventoryInvalidItem');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::exec("UPDATE `inventory` SET `$slotCol` = ?, `$shopSlot` = 0 WHERE character_id = ?", [$itemId, $this->id()]);
            if ($isPremium) {
                $debited = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$price, $this->userId(), $price]
                );
                if ($price > 0 && $debited !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $price;
            } else {
                $debited = Db::exec(
                    'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                    [$price, $this->id(), $price]
                );
                if ($price > 0 && $debited !== 1) {
                    throw new GameError('errRemoveGameCurrencyNotEnough');
                }
                $this->row['game_currency'] = $this->gameCurrency() - $price;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Lista `quests` do personagem do banco, tipada pelo template. */
    public function questsData(array $tplQuest): array
    {
        $rows = Db::rows('SELECT * FROM `quests` WHERE character_id = ? ORDER BY id', [$this->id()]);
        return array_map(fn(array $r) => self::castRowLike($tplQuest, $r), $rows);
    }

    /** Ofertas de treino vivas para o painel Training. */
    public function trainingsData(array $tplTraining): array
    {
        $this->syncTrainingForLevel();
        if ($tplTraining === []) {
            $boot = Replay::data('autoLoginUser');
            $tplTraining = $boot['trainings'][0] ?? [];
        }
        $active = $this->activeTrainingRow();
        $out = [];
        foreach (self::TRAINING_SETTINGS as $statType => $setting) {
            $training = $tplTraining;
            $id = ($this->id() * 100) + $statType;
            $values = [
                'id' => $id,
                'character_id' => $this->id(),
                'setting' => $setting,
                'status' => 1,
                'training_cost' => 1,
                'energy' => 0,
                'needed_energy' => 100,
                'stat_type' => $statType,
                'duration' => 60,
                'ts_end' => 0,
                'training_quest_id' => 0,
                'training_quest_pool' => '',
                'claimed_stars' => 0,
                'rewards_star_1' => '{"coins":5,"xp":25}',
                'rewards_star_2' => '{"coins":10,"xp":50}',
                'rewards_star_3' => '{"coins":15,"xp":75}',
                'stat_points_star_1' => 1,
                'stat_points_star_2' => 1,
                'stat_points_star_3' => 1,
            ];
            if ($active !== null && (int)$active['stat_type'] === $statType) {
                $values['status'] = (int)$active['status'];
                $values['energy'] = min(100, (int)$active['used_resources']);
                $values['needed_energy'] = 100;
                $values['training_quest_id'] = 0;
                $values['training_quest_pool'] = json_encode([$id * 10 + 1, $id * 10 + 2, $id * 10 + 3]);
                $values['claimed_stars'] = min(3, (int)$active['iterations']);
            }
            $out[] = $tplTraining ? self::castRowLike($training, $values) : $values;
        }
        return $out;
    }

    /** Synthetic quest offers belonging to the currently open training. */
    public function trainingQuestsData(): array
    {
        $active = $this->activeTrainingRow();
        if ($active === null) return [];
        $trainingId = ($this->id() * 100) + (int)$active['stat_type'];
        $out = [];
        foreach ([1, 2, 3] as $index) {
            $out[] = [
                'id' => $trainingId * 10 + $index,
                'identifier' => 'local_training_' . $index,
                'type' => 1,
                'stat' => (int)$active['stat_type'],
                'status' => 1,
                'energy_cost' => 1,
                'fight_difficulty' => 1,
                'fight_battle_id' => 0,
                'fight_npc_identifier' => '',
                'won' => true,
                'rewards' => json_encode(['coins' => 5 * $index, 'xp' => 10 * $index, 'training_progress' => 34]),
            ];
        }
        return $out;
    }

    /**
     * training_energy regenera com o tempo (1/min, ver constants oficiais do CDN
     * `training_energy_refresh_amount_per_minute`), igual o client calcula em
     * `get_trainingEnergy()` (base persistida + minutos desde ts_last_training_energy_change,
     * clampado em max_training_energy). Servidor precisa da MESMA formula pra nao rejeitar
     * energia que o client acha que existe.
     */
    private function liveTrainingEnergy(): int
    {
        $stored = (int)($this->row['training_energy'] ?? 0);
        $max = (int)($this->row['max_training_energy'] ?? 100);
        $last = (int)($this->row['ts_last_training_energy_change'] ?? time());
        $elapsedMinutes = max(0, intdiv(time() - $last, 60));
        return max(0, min($max, $stored + $elapsedMinutes));
    }

    public function startTrainingQuest(int $questId): array
    {
        $active = $this->activeTrainingRow();
        if ($active === null || (int)$active['status'] !== 2) {
            throw new GameError('errStartTrainingQuestNoActiveTraining');
        }
        $quests = $this->trainingQuestsData();
        $quest = null;
        foreach ($quests as $candidate) {
            if ((int)$candidate['id'] === $questId) $quest = $candidate;
        }
        if ($quest === null) throw new GameError('errStartTrainingQuestInvalidQuest');

        // energy_cost sempre 1 nas ofertas sinteticas (trainingQuestsData); mantido
        // dinamico caso isso mude no futuro.
        $cost = max(0, (int)($quest['energy_cost'] ?? 1));
        $energyNow = $this->liveTrainingEnergy();
        if ($energyNow < $cost) {
            throw new GameError('errRemoveTrainingEnergyNotEnough');
        }
        Db::exec(
            'UPDATE `character` SET training_energy = ?, ts_last_training_energy_change = ? WHERE id = ?',
            [$energyNow - $cost, time(), $this->id()]
        );
        $this->row['training_energy'] = $energyNow - $cost;
        $this->row['ts_last_training_energy_change'] = time();

        $progress = min(100, (int)$active['used_resources'] + 34);
        Db::exec('UPDATE `training` SET used_resources = ?, ts_complete = ? WHERE id = ?', [$progress, time(), (int)$active['id']]);
        $quest['status'] = 3;
        return $quest;
    }

    /**
     * Idempotente contra double-submit: o client as vezes dispara essa action 2x seguidas
     * (clique duplo / retry de rede, visto em client.log com 200ms de intervalo). A 1a
     * chamada zera ts_complete; sem essa tolerancia a 2a batia no guard e virava dialog de
     * erro. Seguro porque essa action so devolve o preview da recompensa (nao credita
     * moeda/xp nem stat) -- quem credita de verdade e claimTrainingStar, que tem sua propria
     * trava (iterations) contra reclaim duplo.
     */
    public function claimTrainingQuestRewards(): array
    {
        $active = $this->activeTrainingRow();
        if ($active === null) throw new GameError('errClaimTrainingQuestRewardsNoActiveTraining');
        if ((int)$active['used_resources'] <= 0) throw new GameError('errClaimTrainingQuestRewardsInvalidStatus');
        if ((int)$active['ts_complete'] > 0) {
            Db::exec('UPDATE `training` SET ts_complete = 0 WHERE id = ?', [(int)$active['id']]);
        }
        $quests = $this->trainingQuestsData();
        $index = max(0, min(2, (int)ceil((int)$active['used_resources'] / 34) - 1));
        $quest = $quests[$index] ?? $quests[0];
        $quest['status'] = 4;
        $quest['won'] = true;
        return $quest;
    }

    public function claimTrainingStar(): void
    {
        $active = $this->activeTrainingRow();
        if ($active === null) throw new GameError('errClaimTrainingStarNoActiveTraining');
        $claimed = (int)$active['iterations'];
        if ($claimed >= 3) throw new GameError('errClaimTrainingStarAlreadyClaimed');
        $thresholds = [34, 67, 100];
        if ((int)$active['used_resources'] < $thresholds[$claimed]) {
            throw new GameError('errClaimTrainingStarNotAvailable');
        }
        $newClaimed = $claimed + 1;
        $status = $newClaimed >= 3 ? 4 : 2;
        $statColumn = self::STAT_MAP[(int)$active['stat_type']] ?? null;
        if ($statColumn === null) throw new GameError('errTrainingInvalidTraining');
        Db::exec('UPDATE `training` SET iterations = ?, status = ? WHERE id = ?', [$newClaimed, $status, (int)$active['id']]);
        Db::exec("UPDATE `character` SET stat_trained_$statColumn = stat_trained_$statColumn + 1 WHERE id = ?", [$this->id()]);
        $this->row["stat_trained_$statColumn"] = (int)$this->row["stat_trained_$statColumn"] + 1;

        // Coins/xp da estrela (rewards_star_1/2/3, ver trainingsData()) nunca eram
        // creditados -- so o ponto de stat era. rewards_star_N eh fixo (nao vem do banco),
        // entao usamos os mesmos valores default expostos ao client.
        $rewardsByStar = [
            1 => ['coins' => 5,  'xp' => 25],
            2 => ['coins' => 10, 'xp' => 50],
            3 => ['coins' => 15, 'xp' => 75],
        ];
        $reward = $rewardsByStar[$newClaimed];
        Db::exec(
            'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ? WHERE id = ?',
            [$reward['coins'], $reward['xp'], $this->id()]
        );
        $this->row['game_currency'] = (int)$this->row['game_currency'] + $reward['coins'];
        $this->row['xp'] = (int)$this->row['xp'] + $reward['xp'];
        $this->syncLevelFromXp();
    }

    private function activeTrainingRow(bool $includeComplete = false): ?array
    {
        $statuses = $includeComplete ? '2,4' : '2';
        return Db::row("SELECT * FROM `training` WHERE character_id = ? AND status IN ($statuses) ORDER BY id DESC LIMIT 1", [$this->id()]);
    }

    public function startTraining(int $trainingId): void
    {
        $this->syncTrainingForLevel();
        $statType = $trainingId % 100;
        if (!isset(self::TRAINING_SETTINGS[$statType])) {
            throw new GameError('errTrainingInvalidTraining');
        }
        if ((int)$this->row['training_count'] <= 0) {
            throw new GameError('errRemoveTrainingNotEnough');
        }
        $col = self::STAT_MAP[$statType] ?? null;
        if ($col === null) {
            throw new GameError('errTrainingInvalidTraining');
        }
        if ($this->activeTrainingRow() !== null) {
            throw new GameError('errStartTrainingAlreadyActive');
        }
        Db::exec(
            'INSERT INTO `training` (character_id, status, stat_type, ts_creation, ts_complete, iterations, used_resources) VALUES (?, 2, ?, ?, 0, 0, 0)',
            [$this->id(), $statType, time()]
        );
        Db::exec(
            "UPDATE `character`
                SET training_count = training_count - 1,
                    training_progress_value_$col = 0,
                    training_progress_end_$col = 3,
                    ts_last_training = ?
              WHERE id = ?",
            [time(), $this->id()]
        );
        $this->row['training_count'] = (int)$this->row['training_count'] - 1;
        $this->row['ts_last_training'] = time();
    }

    /** Missao ativa do personagem, ou erro de protocolo se nao houver uma. */
    public function activeQuest(): array
    {
        $questId = (int)($this->row['active_quest_id'] ?? 0);
        if ($questId <= 0) {
            throw new GameError('errNoActiveQuest');
        }
        $q = Db::row('SELECT * FROM `quests` WHERE id = ? AND character_id = ?', [$questId, $this->id()]);
        if ($q === null) {
            throw new GameError('errNoActiveQuest');
        }
        return $q;
    }

    /** Finaliza instantaneamente a missao ativa. O cliente chama sem quest_id. */
    public function instantFinishQuest(int $premiumCost = 0): void
    {
        $q = $this->activeQuest();
        if ((int)$q['status'] !== 2) {
            return;
        }
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        Db::exec('UPDATE `quests` SET ts_complete = 0 WHERE id = ?', [(int)$q['id']]);
        if ($premiumCost > 0) {
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ?', [$premiumCost, $this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
        }
    }

    /**
     * Marca a missao ativa como concluida quando o tempo acabou.
     * Fight quests recebem um battle_id simples para o cliente abrir a tela de luta.
     */
    public function checkActiveQuestComplete(): array
    {
        $q = $this->activeQuest();
        if ((int)$q['status'] >= 3) {
            return $q;
        }
        if ((int)$q['ts_complete'] > time()) {
            return $q;
        }

        $battleId = 0;
        if ((int)$q['type'] === 2) {
            Db::exec(
                "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds)
                 VALUES (?, ?, ?, 'a', ?)",
                [
                    time(),
                    json_encode($this->battleProfile('a'), JSON_UNESCAPED_SLASHES),
                    json_encode($this->battleProfile('b'), JSON_UNESCAPED_SLASHES),
                    json_encode(['rounds' => [['a' => 'a', 'd' => 'b', 'r' => 2, 'v' => 10]]], JSON_UNESCAPED_SLASHES),
                ]
            );
            $battleId = (int)Db::pdo()->lastInsertId();
        }

        Db::exec('UPDATE `quests` SET status = 4, fight_battle_id = ? WHERE id = ?', [$battleId, (int)$q['id']]);
        return Db::row('SELECT * FROM `quests` WHERE id = ?', [(int)$q['id']]) ?? $q;
    }

    public function battleData(int $battleId): ?array
    {
        if ($battleId <= 0) return null;
        return Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]);
    }

    /** Aplica recompensa da missao concluida e gera um novo pool simples para o stage atual. */
    public function claimQuestRewards(): void
    {
        try {
            $q = $this->activeQuest();
        } catch (GameError $e) {
            throw new GameError('errClaimQuestRewardsNoActiveQuest');
        }
        if ((int)$q['status'] < 3) {
            throw new GameError('errClaimQuestRewardsInvalidStatus');
        }
        $rewards = json_decode((string)$q['rewards'], true);
        if (!is_array($rewards)) $rewards = [];
        $coins = (int)($rewards['coins'] ?? 0);
        $xp = (int)($rewards['xp'] ?? 0);

        Db::exec(
            'UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ?, active_quest_id = 0 WHERE id = ?',
            [$coins, $xp, $this->id()]
        );
        $this->row['xp'] = (int)$this->row['xp'] + $xp;
        $this->syncLevelFromXp();   // atualiza level/score_level e concede stat points de nivel
        $newLevel = (int)$this->row['level'];
        $this->syncTutorialForLevel();
        Db::exec('DELETE FROM `quests` WHERE character_id = ?', [$this->id()]);
        // Repoe o pool no STAGE ATUAL (seedStarterQuests e so p/ conta nova/stage 1;
        // usar stage fixo aqui apagava as quests da zona do jogador e o proximo
        // startQuest com id antigo dava errStartQuestInvalidQuest).
        self::insertQuestPool($this->id(), $newLevel, 3, max(1, (int)($this->row['current_quest_stage'] ?? 1)));
    }

    /**
     * Perfil de batalha a partir das stats REAIS do personagem (base + treinado +
     * equipamento -- mesma formula de stat_total_* usada no character overlay, pra
     * bater com o que o proprio perfil do jogador mostra). $profile='b' aplica um
     * penalidade artificial de -1 (usado so quando NAO ha um oponente real, ex.:
     * luta de missao contra NPC -- duelo/liga chamam com 'a' no personagem real,
     * seja ele atacante ou oponente, e so relabelam o campo 'profile' depois).
     */
    public function battleProfile(string $profile): array
    {
        $equip = self::equipBonus();
        $stamina = (int)$this->row['stat_base_stamina'] + (int)($this->row['stat_trained_stamina'] ?? 0) + ($equip['stamina'] ?? 0);
        $strength = (int)$this->row['stat_base_strength'] + (int)($this->row['stat_trained_strength'] ?? 0) + ($equip['strength'] ?? 0);
        $critical = (int)$this->row['stat_base_critical_rating'] + (int)($this->row['stat_trained_critical_rating'] ?? 0) + ($equip['critical_rating'] ?? 0);
        $dodge = (int)$this->row['stat_base_dodge_rating'] + (int)($this->row['stat_trained_dodge_rating'] ?? 0) + ($equip['dodge_rating'] ?? 0);
        if ($profile === 'b') {
            $stamina = max(1, $stamina - 1);
            $strength = max(1, $strength - 1);
            $critical = max(1, $critical - 1);
            $dodge = max(1, $dodge - 1);
        }
        return [
            'profile' => $profile,
            'level' => (int)$this->row['level'],
            'stamina' => $stamina,
            'strength' => $strength,
            'criticalrating' => $critical,
            'dodgerating' => $dodge,
            'weapondamage' => 0,
            'hitpoints' => $stamina * 10,
            'damage_normal' => $strength,
            'damage_bonus' => $strength,
            'chance_critical' => 0.1,
            'chance_dodge' => 0.1,
        ];
    }

    /** Casta cada campo de $row para o tipo do mesmo campo em $tpl (mantendo extras como estao). */
    private static function castRowLike(array $tpl, array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$k] = array_key_exists($k, $tpl) ? self::castLike($tpl[$k], $v) : $v;
        }
        return $out;
    }

    /**
     * Lista de oponentes de duelo a partir das linhas NPC (`character` com user_id=0).
     * Monta o resumo esperado pelo cliente, tipado pelo template $tplOpp.
     */
    public static function duelOpponents(int $excludeId, array $tplOpp, int $limit = 10): array
    {
        $rows = Db::rows(
            'SELECT * FROM `character` WHERE user_id = 0 AND id <> ? ORDER BY RAND() LIMIT ' . (int)$limit,
            [$excludeId]
        );
        $list = [];
        foreach ($rows as $r) {
            $sta = (int)$r['stat_base_stamina'];
            $str = (int)$r['stat_base_strength'];
            $crit= (int)$r['stat_base_critical_rating'];
            $dg  = (int)$r['stat_base_dodge_rating'];
            $opp = [
                'id'                         => (int)$r['id'],
                'server_id'                  => 'br30',
                'name'                       => (string)$r['name'],
                'level'                      => (int)$r['level'],
                'honor'                      => (int)$r['honor'],
                'gender'                     => (string)$r['gender'],
                'has_missile'                => false,
                'stat_total_stamina'         => $sta,
                'stat_total_strength'        => $str,
                'stat_total_critical_rating' => $crit,
                'stat_total_dodge_rating'    => $dg,
                'stat_weapon_damage'         => 0,
                'online_status'              => 2,
                'total_stats'                => $sta + $str + $crit + $dg,
            ];
            // garante exatamente a shape/tipos do template.
            $list[] = $tplOpp ? self::castRowLike($tplOpp, $opp) : $opp;
        }
        return $list;
    }

    /**
     * Inicia uma quest: valida posse/energia, marca a quest como INICIADA (status=2) com
     * ts_complete = agora + duration, aponta active_quest_id no personagem e debita energia.
     * O cliente (Qe): get_isStarted = status==2; get_remainingSeconds = ts_complete - now;
     * o painel quest_progress usa character.get_activeQuest() (=_quests[active_quest_id]).
     */
    public function startQuest(int $questId): void
    {
        $q = Db::row('SELECT * FROM `quests` WHERE id = ? AND character_id = ?', [$questId, $this->id()]);
        if ($q === null) {
            throw new GameError('errStartQuestInvalidQuest');
        }
        if ((int)$q['status'] === 2) {
            return; // ja iniciada -> idempotente
        }
        $cost = (int)$q['energy_cost'];
        if ((int)$this->row['quest_energy'] < $cost) {
            throw new GameError('errRemoveQuestEnergyNotEnough');
        }
        $tsComplete = time() + max(0, (int)$q['duration']);
        Db::exec('UPDATE `quests` SET status = 2, ts_complete = ? WHERE id = ?', [$tsComplete, $questId]);
        Db::exec('UPDATE `character` SET active_quest_id = ?, quest_energy = quest_energy - ? WHERE id = ?',
                 [$questId, $cost, $this->id()]);
        $this->row['active_quest_id'] = $questId;
        $this->row['quest_energy']    = (int)$this->row['quest_energy'] - $cost;
    }

    /** Cancela a missao ativa sem recompensa (a energia gasta ao iniciar nao e devolvida). */
    public function abortQuest(): void
    {
        $q = $this->activeQuest();
        Db::exec('UPDATE `quests` SET status = 1, ts_complete = 0 WHERE id = ?', [(int)$q['id']]);
        Db::exec('UPDATE `character` SET active_quest_id = 0 WHERE id = ?', [$this->id()]);
        $this->row['active_quest_id'] = 0;
    }

    /** Marca a conta como tendo aceito a event quest ativa (catalogo vem do template global). */
    public function assignEventQuest(string $identifier): void
    {
        if ($identifier === '') {
            throw new GameError('errRequestInvalidParameter');
        }
        Db::exec('UPDATE `character` SET event_quest_id = 1 WHERE id = ?', [$this->id()]);
        $this->row['event_quest_id'] = 1;
    }

    /** Recarrega o contador de sessoes de treino (premium). */
    public function buyTrainingEnergy(int $premiumCost): void
    {
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        $max = max(3, (int)($this->row['max_training_count'] ?? 3));
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($premiumCost > 0) {
                $affected = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$premiumCost, $this->userId(), $premiumCost]
                );
                if ($affected !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
            }
            Db::exec('UPDATE `character` SET training_count = ? WHERE id = ?', [$max, $this->id()]);
            $this->row['training_count'] = $max;
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Finaliza instantaneamente o ciclo de treino ativo (premium), completando os 3 stars. */
    public function finishTraining(int $premiumCost): void
    {
        $active = $this->activeTrainingRow();
        if ($active === null) {
            throw new GameError('errStartTrainingQuestNoActiveTraining');
        }
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($premiumCost > 0) {
                $affected = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$premiumCost, $this->userId(), $premiumCost]
                );
                if ($affected !== 1) {
                    throw new GameError('errRemovePremiumCurrencyNotEnough');
                }
                $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
            }
            Db::exec('UPDATE `training` SET used_resources = 100, iterations = 3, status = 4 WHERE id = ?', [(int)$active['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Identifier -> [slot, base duration em segundos p/ producao]. */
    public const HIDEOUT_STARTER_ROOMS = [
        'main_building'       => 0,
        'generator'            => 2,
        'glue_production'      => 4,
        'stone_production'     => 6,
        'attacker_production'  => 8,
        'defender_production'  => 10,
    ];

    /** Garante as 6 salas iniciais do hideout (idempotente) e devolve todas do personagem. */
    public function ensureHideoutRooms(): array
    {
        $existing = Db::rows('SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id', [$this->id()]);
        if ($existing !== []) {
            return $existing;
        }
        $now = time();
        foreach (self::HIDEOUT_STARTER_ROOMS as $identifier => $slot) {
            Db::exec(
                'INSERT INTO `hideout_rooms`
                    (character_id, identifier, slot, status, level, current_resource_amount,
                     max_resource_amount, ts_last_resource_change, ts_activity_end, current_generator_level)
                 VALUES (?, ?, ?, 1, 1, 0, 100, ?, 0, 0)',
                [$this->id(), $identifier, $slot, $now]
            );
        }
        return Db::rows('SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id', [$this->id()]);
    }

    /** Constroi uma sala nova no slot indicado (custa moeda de jogo). */
    public function buildHideoutRoom(string $identifier, int $slot): array
    {
        if ($identifier === '') {
            throw new GameError('errRequestInvalidParameter');
        }
        $this->ensureHideoutRooms();
        $atSlot = Db::row('SELECT id FROM `hideout_rooms` WHERE character_id = ? AND slot = ?', [$this->id(), $slot]);
        if ($atSlot !== null) {
            throw new GameError('errBuildHideoutRoomSlotOccupied');
        }
        $cost = 100;
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$cost, $this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() - $cost;

        $now = time();
        Db::exec(
            'INSERT INTO `hideout_rooms`
                (character_id, identifier, slot, status, level, current_resource_amount,
                 max_resource_amount, ts_last_resource_change, ts_activity_end, current_generator_level)
             VALUES (?, ?, ?, 1, 1, 0, 100, ?, 0, 0)',
            [$this->id(), $identifier, $slot, $now]
        );
        $roomId = (int)Db::pdo()->lastInsertId();
        return Db::row('SELECT * FROM `hideout_rooms` WHERE id = ?', [$roomId]) ?? [];
    }

    private function ownHideoutRoom(int $roomId): array
    {
        $room = Db::row('SELECT * FROM `hideout_rooms` WHERE id = ? AND character_id = ?', [$roomId, $this->id()]);
        if ($room === null) {
            throw new GameError('errRequestInvalidParameter');
        }
        return $room;
    }

    /** Sobe o nivel da sala (custa moeda de jogo, escala com o nivel atual). */
    public function upgradeHideoutRoom(int $roomId): array
    {
        $room = $this->ownHideoutRoom($roomId);
        $cost = 50 * ((int)$room['level'] + 1);
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$cost, $this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() - $cost;
        Db::exec('UPDATE `hideout_rooms` SET level = level + 1 WHERE id = ?', [$roomId]);
        return Db::row('SELECT * FROM `hideout_rooms` WHERE id = ?', [$roomId]) ?? [];
    }

    /** Inicia a producao de recursos da sala (fica ocupada ate ts_activity_end). */
    public function startHideoutRoomProduction(int $roomId, int $productionCount): array
    {
        $room = $this->ownHideoutRoom($roomId);
        if ((int)$room['ts_activity_end'] > time()) {
            throw new GameError('errStartHideoutRoomProductionAlreadyActive');
        }
        $duration = max(1, $productionCount) * 300; // 5min por ciclo de producao
        $endsAt = time() + $duration;
        Db::exec('UPDATE `hideout_rooms` SET ts_activity_end = ? WHERE id = ?', [$endsAt, $roomId]);
        $room['ts_activity_end'] = $endsAt;
        return $room;
    }

    /** Finaliza instantaneamente a atividade da sala (premium). */
    public function instantFinishHideoutRoomActivity(int $roomId, int $premiumCost): array
    {
        $room = $this->ownHideoutRoom($roomId);
        if ((int)$room['ts_activity_end'] <= time()) {
            throw new GameError('errInstantFinishHideoutRoomActivityNotActive');
        }
        if ($premiumCost > 0 && $this->premiumCurrency() < $premiumCost) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
        if ($premiumCost > 0) {
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ?', [$premiumCost, $this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - $premiumCost;
        }
        Db::exec('UPDATE `hideout_rooms` SET ts_activity_end = 0 WHERE id = ?', [$roomId]);
        $room['ts_activity_end'] = 0;
        return $room;
    }

    /** Garante uma linha season_progress (idempotente) e devolve a shape do template autoLoginUser. */
    public function ensureSeasonProgress(): array
    {
        $row = Db::row('SELECT * FROM `season_progress` WHERE character_id = ?', [$this->id()]);
        if ($row !== null) {
            return $row;
        }
        $now = time();
        Db::exec(
            'INSERT INTO `season_progress`
                (character_id, season_id, identifier, status, season_points, premium_unlocked, restarted,
                 claimed_rewards, ts_created, ts_start, ts_end)
             VALUES (?, 1, ?, 2, 0, 0, 0, ?, ?, ?, ?)',
            [$this->id(), 'season_1', '[]', $now, $now, $now + 30 * 86400]
        );
        return Db::row('SELECT * FROM `season_progress` WHERE character_id = ?', [$this->id()]) ?? [];
    }

    /** Reivindica uma recompensa da season pelo id (grava no claimed_rewards, credita moeda). */
    public function claimSeasonReward(int $rewardId): void
    {
        $season = $this->ensureSeasonProgress();
        $claimed = json_decode((string)$season['claimed_rewards'], true);
        if (!is_array($claimed)) $claimed = [];
        if (in_array($rewardId, $claimed, true)) {
            throw new GameError('errClaimSeasonRewardAlreadyClaimed');
        }
        $claimed[] = $rewardId;
        Db::exec('UPDATE `season_progress` SET claimed_rewards = ? WHERE character_id = ?',
            [json_encode($claimed), $this->id()]);
        Db::exec('UPDATE `character` SET game_currency = game_currency + 20 WHERE id = ?', [$this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() + 20;
    }

    /** Garante o blob de estado da treasure event ativa (idempotente). */
    public function ensureTreasureEventState(): array
    {
        $raw = json_decode((string)($this->row['collected_item_pattern'] ?? ''), true);
        if (is_array($raw) && isset($raw['identifier'])) {
            return $raw;
        }
        return ['identifier' => '', 'level' => 1, 'tokens' => 0, 'claimed' => [], 'reveal_claimed' => false];
    }

    private function saveTreasureEventState(array $state): void
    {
        Db::exec('UPDATE `character` SET collected_item_pattern = ? WHERE id = ?',
            [json_encode($state, JSON_UNESCAPED_SLASHES), $this->id()]);
        $this->row['collected_item_pattern'] = json_encode($state, JSON_UNESCAPED_SLASHES);
    }

    public function assignTreasureEvent(string $identifier): void
    {
        if ($identifier === '') {
            throw new GameError('errRequestInvalidParameter');
        }
        $this->saveTreasureEventState(['identifier' => $identifier, 'level' => 1, 'tokens' => 0, 'claimed' => [], 'reveal_claimed' => false]);
        Db::exec('UPDATE `character` SET current_item_pattern_values = ? WHERE id = ?', ['{}', $this->id()]);
        $this->row['current_item_pattern_values'] = '{}';
    }

    /**
     * Estado da dungeon de historia (JSON em character.story_dungeon_state):
     * {"active":{"index":N,"step":N,"status":2|4}|null,"completed":["1_1",...]}.
     * completed usa o formato "{index}_{step}" que o cliente compara em
     * hasStoryDungeonStepCompleted.
     */
    public function storyDungeonState(): array
    {
        $raw = json_decode((string)($this->row['story_dungeon_state'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        return $raw + ['active' => null, 'completed' => []];
    }

    private function saveStoryDungeonState(array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET story_dungeon_state = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['story_dungeon_state'] = $json;
    }

    /** Id sintetico e estavel do passo (o cliente so precisa de consistencia). */
    public function storyDungeonStepId(int $index, int $step): int
    {
        return $this->id() * 100 + $index * 10 + $step;
    }

    public function startStoryDungeonStep(int $index, int $step): void
    {
        if ($index < 1 || $step < 1) {
            throw new GameError('errRequestInvalidParameter');
        }
        $state = $this->storyDungeonState();
        if (in_array($index . '_' . $step, $state['completed'], true)) {
            throw new GameError('errRequestInvalidParameter');
        }
        $state['active'] = ['index' => $index, 'step' => $step, 'status' => 2];
        $this->saveStoryDungeonState($state);
    }

    /** Completa o passo ativo (status 4 = pronto p/ claim). Sem motor de batalha, ataque vence. */
    public function finishActiveStoryDungeonStep(): void
    {
        $state = $this->storyDungeonState();
        if (!is_array($state['active'])) {
            throw new GameError('errRequestInvalidParameter');
        }
        $state['active']['status'] = 4;
        $this->saveStoryDungeonState($state);
    }

    /** Claim do passo completo: registra em completed e destrava a proxima zona. */
    public function claimStoryDungeonStepReward(): void
    {
        $state = $this->storyDungeonState();
        $active = $state['active'] ?? null;
        if (!is_array($active) || (int)($active['status'] ?? 0) !== 4) {
            throw new GameError('errRequestInvalidParameter');
        }
        $key = $active['index'] . '_' . $active['step'];
        if (!in_array($key, $state['completed'], true)) {
            $state['completed'][] = $key;
        }
        $state['active'] = null;
        $this->saveStoryDungeonState($state);

        // Dungeon da zona N concluida -> zona N+1 acessivel (viagem = setCharacterStage).
        $unlocked = (int)$active['index'] + 1;
        Db::exec('UPDATE `character`
                     SET max_quest_stage = GREATEST(max_quest_stage, ?),
                         game_currency = game_currency + 100
                   WHERE id = ?', [$unlocked, $this->id()]);
        $this->row['max_quest_stage'] = max((int)($this->row['max_quest_stage'] ?? 1), $unlocked);
        $this->row['game_currency'] = (int)$this->row['game_currency'] + 100;
    }

    /** Limite/custo oficiais (constants_json do CDN, chave "casino"). */
    private const CASINO_MAX_DAILY_SPINS = 30;
    private const CASINO_PREMIUM_COST_PER_SPIN = 1;
    /** Ids do sistema generico de "resources" (character.unused_resources/used_resources). */
    private const RESOURCE_FREE_CASINO_SPIN = 2;
    private const RESOURCE_CASINO_JETON = 4;
    private const CASINO_FREE_SPIN_COST = 1;   // resource_free_casino_spin_usage_amount
    private const CASINO_JETON_COST = 10;      // resource_casino_jeton_usage_amount

    /**
     * Sistema generico de "resources" do character (unused_resources/used_resources,
     * mapas JSON string->int chaveados pelo id do tipo). O client le via
     * getUnusedResourceCount(type) -- gira o cassino gastando primeiro giro gratis
     * (tipo 2), depois ficha/jeton (tipo 4), so cai pra moeda premium se nao tiver
     * nenhum dos dois -- mesma prioridade usada em dn.refreshMultispinToggle.
     */
    private function unusedResources(): array
    {
        $raw = json_decode((string)($this->row['unused_resources'] ?? ''), true);
        return is_array($raw) ? $raw : [];
    }

    /** Tenta gastar $amount do recurso $type; devolve false se nao tiver o suficiente (nao gasta nada). */
    private function spendResource(int $type, int $amount): bool
    {
        $unused = $this->unusedResources();
        $key = (string)$type;
        $have = (int)($unused[$key] ?? 0);
        if ($have < $amount) {
            return false;
        }
        $unused[$key] = $have - $amount;
        if ($unused[$key] <= 0) {
            unset($unused[$key]);
        }
        $used = json_decode((string)($this->row['used_resources'] ?? ''), true);
        if (!is_array($used)) $used = [];
        $used[$key] = (int)($used[$key] ?? 0) + $amount;

        $unusedJson = json_encode($unused, JSON_UNESCAPED_SLASHES);
        $usedJson = json_encode($used, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET unused_resources = ?, used_resources = ? WHERE id = ?', [$unusedJson, $usedJson, $this->id()]);
        $this->row['unused_resources'] = $unusedJson;
        $this->row['used_resources'] = $usedJson;
        return true;
    }

    /**
     * Estado da maquina de casino (JSON em character.casino_state):
     * {"active":{"slots":[s1,s2,s3],"reward_type":N,"reward_quality":N,"rewards":["{\"coins\":N}"]}|null}.
     * "active" fica pendente ate o cliente chamar applyCasinoReward (fecha o dialog de
     * recompensa) -- mesmo padrao 2 fases do [[zone-progression-story-dungeon]].
     */
    public function casinoState(): array
    {
        $raw = json_decode((string)($this->row['casino_state'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        return $raw + ['active' => null];
    }

    private function saveCasinoState(array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_SLASHES);
        Db::exec('UPDATE `character` SET casino_state = ? WHERE id = ?', [$json, $this->id()]);
        $this->row['casino_state'] = $json;
    }

    /** Sorteia UM giro (3 simbolos 1-8, igual a tabela "casino" dos constants oficiais). */
    private static function rollCasinoSpin(): array
    {
        $slots = [random_int(1, 8), random_int(1, 8), random_int(1, 8)];
        $counts = array_count_values($slots);
        $maxCount = max($counts);
        if ($maxCount >= 3) {
            // Jackpot: os 3 simbolos batem.
            return ['slots' => $slots, 'tier' => 3, 'coins' => 1000, 'xp' => 200];
        }
        if ($maxCount === 2) {
            // Par: 2 dos 3 simbolos batem.
            return ['slots' => $slots, 'tier' => 2, 'coins' => 150, 'xp' => 30];
        }
        // Sem combinacao: premio de consolacao (cassino sempre paga algo).
        return ['slots' => $slots, 'tier' => 1, 'coins' => 20, 'xp' => 0];
    }

    /**
     * Gira a maquina (multi_spin = 1/5/10 giros cobrados de uma vez, resultado agregado).
     * Gasta giro gratis, depois ficha (jeton), so cobra premium_currency se nenhum dos
     * dois cobrir o lote inteiro. Reseta o contador diario a cada 24h corridas desde
     * ts_last_slotmachine_refill (sem calendario, so elapsed time).
     */
    public function spinCasino(int $multiSpin): array
    {
        $multiSpin = in_array($multiSpin, [1, 5, 10], true) ? $multiSpin : 1;

        $state = $this->casinoState();
        if ($state['active'] !== null) {
            throw new GameError('errSpinCasinoMachineCharacterHasActiveSpin');
        }

        $lastRefill = (int)($this->row['ts_last_slotmachine_refill'] ?? 0);
        $spinsToday = (int)($this->row['slotmachine_spin_count'] ?? 0);
        if (time() - $lastRefill >= 86400) {
            $spinsToday = 0;
            $lastRefill = time();
        }
        if ($spinsToday + $multiSpin > self::CASINO_MAX_DAILY_SPINS) {
            throw new GameError('errSpinCasinoMachineDailyLimitReached');
        }

        // Prioridade igual ao client (dn.refreshMultispinToggle): giro gratis > ficha > donut.
        if (!$this->spendResource(self::RESOURCE_FREE_CASINO_SPIN, self::CASINO_FREE_SPIN_COST * $multiSpin)
            && !$this->spendResource(self::RESOURCE_CASINO_JETON, self::CASINO_JETON_COST * $multiSpin)
        ) {
            $cost = self::CASINO_PREMIUM_COST_PER_SPIN * $multiSpin;
            $debited = Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$cost, $this->user['id'], $cost]
            );
            if ($debited !== 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            $this->user['premium_currency'] = $this->premiumCurrency() - $cost;
        }

        $best = null;
        $totalCoins = 0;
        $totalXp = 0;
        for ($i = 0; $i < $multiSpin; $i++) {
            $spin = self::rollCasinoSpin();
            $totalCoins += $spin['coins'];
            $totalXp += $spin['xp'];
            if ($best === null || $spin['tier'] > $best['tier']) {
                $best = $spin;
            }
        }

        $active = [
            'slots' => $best['slots'],
            'reward_type' => $best['tier'],
            'reward_quality' => $best['tier'],
            'rewards' => [json_encode(array_filter(['coins' => $totalCoins, 'xp' => $totalXp]))],
        ];
        $state['active'] = $active;
        $this->saveCasinoState($state);

        Db::exec(
            'UPDATE `character` SET slotmachine_spin_count = ?, ts_last_slotmachine_refill = ? WHERE id = ?',
            [$spinsToday + $multiSpin, $lastRefill, $this->id()]
        );
        $this->row['slotmachine_spin_count'] = $spinsToday + $multiSpin;
        $this->row['ts_last_slotmachine_refill'] = $lastRefill;

        return $active;
    }

    /** Recupera o resultado do giro pendente (reload do cliente antes do claim). */
    public function activeCasinoSpin(): array
    {
        $active = $this->casinoState()['active'];
        if ($active === null) {
            throw new GameError('errGetCasinoRewardCharacterHasNoActiveSpin');
        }
        return $active;
    }

    /** Fecha o dialog de recompensa: credita coins/xp (a nao ser que o jogador descarte). */
    public function applyCasinoReward(bool $discardItem): void
    {
        $state = $this->casinoState();
        $active = $state['active'];
        if ($active === null) {
            throw new GameError('errApplyCasinoRewardCharacterHasNoActiveSpin');
        }
        if (!$discardItem) {
            $rewards = json_decode((string)($active['rewards'][0] ?? '{}'), true);
            if (!is_array($rewards)) $rewards = [];
            $coins = (int)($rewards['coins'] ?? 0);
            $xp = (int)($rewards['xp'] ?? 0);
            Db::exec('UPDATE `character` SET game_currency = game_currency + ?, xp = xp + ? WHERE id = ?', [$coins, $xp, $this->id()]);
            $this->row['game_currency'] = (int)$this->row['game_currency'] + $coins;
            $this->row['xp'] = (int)$this->row['xp'] + $xp;
            $this->syncLevelFromXp();
        }
        $state['active'] = null;
        $this->saveCasinoState($state);
    }

    /**
     * Secoes de "premios possiveis" (DialogCasinoPossibleRewards). Sem captura real do
     * cassino oficial: usa os mesmos 3 tiers de rollCasinoSpin como preview generico.
     * genericRewardType: 1=game_currency, 2=xp (enum DOGenericReward do client).
     */
    public static function casinoPossibleRewardSections(): array
    {
        $tier = static function (string $name, int $colorIndex, int $coins, int $xp): array {
            $rewards = [['genericRewardType' => 1, 'genericRewardAmount' => $coins, 'genericRewardData' => null]];
            if ($xp > 0) {
                $rewards[] = ['genericRewardType' => 2, 'genericRewardAmount' => $xp, 'genericRewardData' => null];
            }
            return ['name' => $name, 'headerColorIndex' => $colorIndex, 'preventDuplicates' => false, 'rewards' => $rewards];
        };
        return [
            $tier('Sem combinacao', 0, 20, 0),
            $tier('Par', 1, 150, 30),
            $tier('Jackpot', 2, 1000, 200),
        ];
    }

    public function createNextTreasureEventLevel(): void
    {
        $state = $this->ensureTreasureEventState();
        if ($state['identifier'] === '') {
            throw new GameError('errCreateNextTreasureEventLevelNoActiveEvent');
        }
        $state['level'] = (int)$state['level'] + 1;
        $this->saveTreasureEventState($state);
        Db::exec('UPDATE `character` SET current_item_pattern_values = ? WHERE id = ?', ['{}', $this->id()]);
        $this->row['current_item_pattern_values'] = '{}';
    }

    /** Abre uma celula do grid (level,x,y); custa moeda e concede tokens da event. */
    public function openTreasureCell(int $level, int $x, int $y, bool $premium): array
    {
        $state = $this->ensureTreasureEventState();
        if ($state['identifier'] === '') {
            throw new GameError('errOpenTreasureCellNoActiveEvent');
        }
        $cells = json_decode((string)($this->row['current_item_pattern_values'] ?? ''), true);
        if (!is_array($cells)) $cells = [];
        $key = "{$level}_{$x}_{$y}";
        if (isset($cells[$key])) {
            throw new GameError('errOpenTreasureCellAlreadyOpen');
        }
        $cost = $premium ? 1 : 20;
        if ($premium) {
            if ($this->premiumCurrency() < $cost) throw new GameError('errRemovePremiumCurrencyNotEnough');
            Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ?', [$cost, $this->userId()]);
            $this->user['premium_currency'] = $this->premiumCurrency() - $cost;
        } else {
            if ($this->gameCurrency() < $cost) throw new GameError('errRemoveGameCurrencyNotEnough');
            Db::exec('UPDATE `character` SET game_currency = game_currency - ? WHERE id = ?', [$cost, $this->id()]);
            $this->row['game_currency'] = $this->gameCurrency() - $cost;
        }
        $cells[$key] = ['opened' => true, 'collected' => false];
        Db::exec('UPDATE `character` SET current_item_pattern_values = ? WHERE id = ?',
            [json_encode($cells, JSON_UNESCAPED_SLASHES), $this->id()]);
        $this->row['current_item_pattern_values'] = json_encode($cells, JSON_UNESCAPED_SLASHES);

        $state['tokens'] = (int)$state['tokens'] + 3;
        $this->saveTreasureEventState($state);
        return ['level' => $level, 'x' => $x, 'y' => $y, 'tokens' => $state['tokens']];
    }

    /** Coleta a recompensa de uma celula ja aberta. */
    public function collectTreasureCellReward(int $level, int $x, int $y): void
    {
        $cells = json_decode((string)($this->row['current_item_pattern_values'] ?? ''), true);
        $key = "{$level}_{$x}_{$y}";
        if (!is_array($cells) || !isset($cells[$key]) || !$cells[$key]['opened']) {
            throw new GameError('errCollectTreasureCellRewardCellNotOpen');
        }
        if (!empty($cells[$key]['collected'])) {
            throw new GameError('errCollectTreasureCellRewardAlreadyCollected');
        }
        $cells[$key]['collected'] = true;
        Db::exec('UPDATE `character` SET current_item_pattern_values = ?, game_currency = game_currency + 10 WHERE id = ?',
            [json_encode($cells, JSON_UNESCAPED_SLASHES), $this->id()]);
        $this->row['current_item_pattern_values'] = json_encode($cells, JSON_UNESCAPED_SLASHES);
        $this->row['game_currency'] = $this->gameCurrency() + 10;
    }

    /** Reivindica uma recompensa de marco (reward_index) da treasure event ativa. */
    public function claimTreasureEventReward(int $rewardIndex): void
    {
        $state = $this->ensureTreasureEventState();
        if ($state['identifier'] === '') {
            throw new GameError('errClaimTreasureEventRewardNoActiveEvent');
        }
        if (in_array($rewardIndex, $state['claimed'], true)) {
            throw new GameError('errClaimTreasureEventRewardAlreadyClaimed');
        }
        $state['claimed'][] = $rewardIndex;
        $this->saveTreasureEventState($state);
        Db::exec('UPDATE `character` SET game_currency = game_currency + 15 WHERE id = ?', [$this->id()]);
        $this->row['game_currency'] = $this->gameCurrency() + 15;
    }

    /** Bonus unico de tokens gratis ao abrir a treasure event pela primeira vez. */
    public function claimFreeTreasureRevealItems(): void
    {
        $state = $this->ensureTreasureEventState();
        if ($state['identifier'] === '') {
            throw new GameError('errClaimFreeTreasureRevealItemsNoActiveEvent');
        }
        if (!empty($state['reveal_claimed'])) {
            throw new GameError('errClaimFreeTreasureRevealItemsAlreadyClaimed');
        }
        $state['reveal_claimed'] = true;
        $state['tokens'] = (int)$state['tokens'] + 5;
        $this->saveTreasureEventState($state);
    }

    /** Custo em game_currency para comprar +1 no proximo ponto de atributo. */
    public function nextStatCost(): int
    {
        // Modelo simples e proximo do oficial (~5/ponto). Servidor e autoritativo.
        return 5;
    }

    /**
     * Sobe +$amount no atributo indicado (stat_type do cliente). Persiste no banco.
     * Pontos gratis (stat_points_available, de level-up) sao consumidos PRIMEIRO
     * e nao contam como stat_bought; o restante e comprado com game_currency.
     * Lanca GameError se nao houver moeda suficiente para a parte paga.
     */
    public function improveStat(int $statType, int $amount = 1): void
    {
        $stat = self::STAT_MAP[$statType] ?? null;
        if ($stat === null || $amount < 1) {
            throw new GameError('errRequestInvalidParameter');
        }
        $free   = (int)($this->row['stat_points_available'] ?? 0);
        $useFree = min($free, $amount);
        $paid    = $amount - $useFree;

        $cost = $paid * $this->nextStatCost();
        if ($this->gameCurrency() < $cost) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        $newCurrency = $this->gameCurrency() - $cost;
        $newFree     = $free - $useFree;
        $newBase     = (int)$this->row["stat_base_$stat"] + $amount;
        $newBought   = (int)$this->row["stat_bought_$stat"] + $paid;

        Db::exec(
            "UPDATE `character`
                SET game_currency = ?, stat_points_available = ?, `stat_base_$stat` = ?, `stat_bought_$stat` = ?
              WHERE id = ?",
            [$newCurrency, $newFree, $newBase, $newBought, $this->id()]
        );
        $this->row['game_currency']         = $newCurrency;
        $this->row['stat_points_available'] = $newFree;
        $this->row["stat_base_$stat"]       = $newBase;
        $this->row["stat_bought_$stat"]     = $newBought;
    }
}
