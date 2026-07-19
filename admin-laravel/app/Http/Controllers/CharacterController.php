<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CharacterController extends Controller
{
    /** Campos do personagem editaveis pelo painel (curadoria; o resto e derivado). */
    private const EDITABLE = [
        'name', 'level', 'xp', 'game_currency', 'honor', 'league_points',
        'quest_energy', 'training_energy', 'league_stamina', 'max_quest_stage',
        'stat_base_stamina', 'stat_base_strength', 'stat_base_critical_rating', 'stat_base_dodge_rating',
        'stat_points_available',
    ];

    private const EQUIP_SLOTS = [
        'mask_item_id' => 'Mascara', 'cape_item_id' => 'Capa', 'suit_item_id' => 'Traje',
        'belt_item_id' => 'Cinto', 'boots_item_id' => 'Botas', 'weapon_item_id' => 'Arma',
        'gadget_item_id' => 'Gadget', 'missiles_item_id' => 'Missil',
    ];

    public function show(int $id)
    {
        $char = DB::table('character')->where('id', $id)->first();
        abort_if($char === null, 404);
        $inv = DB::table('inventory')->where('character_id', $id)->first();
        $items = DB::table('items')->where('character_id', $id)->orderBy('id')->get()->keyBy('id');

        $equipped = [];
        $bag = [];
        $shop = [];
        if ($inv) {
            foreach (self::EQUIP_SLOTS as $col => $label) {
                $equipped[$label] = $items[(int)($inv->$col ?? 0)] ?? null;
            }
            for ($i = 1; $i <= 18; $i++) {
                $bag[$i] = $items[(int)($inv->{'bag_item' . $i . '_id'} ?? 0)] ?? null;
            }
            for ($i = 1; $i <= 9; $i++) {
                $shop[$i] = $items[(int)($inv->{'shop_item' . $i . '_id'} ?? 0)] ?? null;
            }
        }

        return view('chars.show', [
            'char' => $char, 'inv' => $inv, 'items' => $items,
            'equipped' => $equipped, 'bag' => $bag, 'shop' => $shop,
            'editable' => self::EDITABLE,
            'quests' => DB::table('quests')->where('character_id', $id)->orderBy('id')->get(),
            'events' => DB::table('event_quests')->where('character_id', $id)->orderBy('id')->get(),
            'catalog' => $this->identifierCatalog(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $set = [];
        foreach (self::EDITABLE as $f) {
            if (!$request->has($f)) continue;
            $v = $request->input($f);
            $set[$f] = $f === 'name' ? (string)$v : max(0, (int)$v);
        }
        if ($set !== []) {
            DB::table('character')->where('id', $id)->update($set);
        }
        return back()->with('ok', 'Personagem salvo.');
    }

    /** Da um item: insere em items e coloca no primeiro slot livre da mochila. */
    public function giveItem(Request $request, int $id)
    {
        $inv = DB::table('inventory')->where('character_id', $id)->first();
        if (!$inv) return back()->with('error', 'Personagem sem inventario.');
        $free = null;
        for ($i = 1; $i <= 18; $i++) {
            if ((int)($inv->{'bag_item' . $i . '_id'} ?? 0) <= 0) { $free = 'bag_item' . $i . '_id'; break; }
        }
        if ($free === null) return back()->with('error', 'Mochila cheia.');

        $itemId = DB::table('items')->insertGetId([
            'character_id' => $id,
            'identifier' => (string)$request->input('identifier'),
            'type' => (int)$request->input('type', 6),
            'quality' => max(1, (int)$request->input('quality', 1)),
            'required_level' => max(1, (int)$request->input('required_level', 1)),
            'item_level' => max(1, (int)$request->input('required_level', 1)),
            'charges' => 0,
            'ts_availability_start' => 0, 'ts_availability_end' => 0,
            'premium_item' => 0,
            'buy_price' => max(0, (int)$request->input('buy_price', 10)),
            'sell_price' => max(0, (int)$request->input('sell_price', 5)),
            'stat_stamina' => max(0, (int)$request->input('stat_stamina', 0)),
            'stat_strength' => max(0, (int)$request->input('stat_strength', 0)),
            'stat_critical_rating' => max(0, (int)$request->input('stat_critical_rating', 0)),
            'stat_dodge_rating' => max(0, (int)$request->input('stat_dodge_rating', 0)),
            'stat_weapon_damage' => max(0, (int)$request->input('stat_weapon_damage', 0)),
        ]);
        DB::table('inventory')->where('character_id', $id)->update([$free => $itemId]);
        return back()->with('ok', "Item {$itemId} adicionado a mochila.");
    }

    public function deleteItem(int $id, int $itemId)
    {
        $inv = DB::table('inventory')->where('character_id', $id)->first();
        if ($inv) {
            foreach ((array)$inv as $col => $v) {
                if ((int)$v === $itemId && str_ends_with($col, '_id')) {
                    DB::table('inventory')->where('character_id', $id)->update([$col => 0]);
                }
            }
        }
        DB::table('items')->where('character_id', $id)->where('id', $itemId)->delete();
        return back()->with('ok', "Item {$itemId} removido.");
    }

    /** Cria/edita missao (tabela quests). */
    public function saveQuest(Request $request, int $id)
    {
        $row = [
            'character_id' => $id,
            'identifier' => (string)$request->input('identifier', 'quest_stage1_time2'),
            'type' => (int)$request->input('type', 1),
            'stage' => max(1, (int)$request->input('stage', 1)),
            'level' => max(1, (int)$request->input('level', 1)),
            'status' => (int)$request->input('status', 1),
            'duration_type' => 1,
            'duration_raw' => max(0, (int)$request->input('duration', 60)),
            'duration' => max(0, (int)$request->input('duration', 60)),
            'ts_complete' => 0,
            'energy_cost' => max(0, (int)$request->input('energy_cost', 2)),
            'fight_difficulty' => (int)$request->input('fight_difficulty', 0),
            'fight_npc_identifier' => (string)$request->input('fight_npc_identifier', ''),
            'fight_battle_id' => 0,
            'used_resources' => 0,
            'rewards' => json_encode([
                'coins' => max(0, (int)$request->input('reward_coins', 10)),
                'xp' => max(0, (int)$request->input('reward_xp', 10)),
            ]),
        ];
        $qid = (int)$request->input('quest_id', 0);
        if ($qid > 0) {
            unset($row['character_id'], $row['ts_complete'], $row['fight_battle_id'], $row['used_resources']);
            DB::table('quests')->where('id', $qid)->where('character_id', $id)->update($row);
            return back()->with('ok', "Missao {$qid} atualizada.");
        }
        $qid = DB::table('quests')->insertGetId($row);
        // quest_pool do personagem e recomputado pelo servidor no overlay; nada a fazer aqui.
        return back()->with('ok', "Missao {$qid} criada (aparece no proximo refresh do jogo).");
    }

    public function deleteQuest(int $id, int $questId)
    {
        DB::table('quests')->where('id', $questId)->where('character_id', $id)->delete();
        DB::table('character')->where('id', $id)->where('active_quest_id', $questId)->update(['active_quest_id' => 0]);
        return back()->with('ok', "Missao {$questId} apagada.");
    }

    /** Cria/edita evento (tabela event_quests). */
    public function saveEvent(Request $request, int $id)
    {
        $row = [
            'character_id' => $id,
            'identifier' => (string)$request->input('identifier', 'event'),
            'status' => (int)$request->input('status', 1),
            'end_date' => (string)$request->input('end_date', date('Y-m-d', time() + 7 * 86400)),
            'rewards' => (string)$request->input('rewards', ''),
            'ts_creation' => time(),
        ];
        $eid = (int)$request->input('event_id', 0);
        if ($eid > 0) {
            unset($row['character_id'], $row['ts_creation']);
            DB::table('event_quests')->where('id', $eid)->where('character_id', $id)->update($row);
            return back()->with('ok', "Evento {$eid} atualizado.");
        }
        $eid = DB::table('event_quests')->insertGetId($row);
        return back()->with('ok', "Evento {$eid} criado.");
    }

    public function deleteEvent(int $id, int $eventId)
    {
        DB::table('event_quests')->where('id', $eventId)->where('character_id', $id)->delete();
        return back()->with('ok', "Evento {$eventId} apagado.");
    }

    /** Catalogo de identifiers validos (gerado do i18n oficial, vive no server do jogo). */
    private function identifierCatalog(): array
    {
        $file = base_path('../server-laravel/data/shop_item_identifiers.php');
        return is_file($file) ? (require $file) : [];
    }
}
