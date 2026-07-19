<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuildController extends Controller
{
    public function index()
    {
        $guilds = DB::table('guild')
            ->select('id', 'name', 'honor', 'game_currency', 'premium_currency', 'status',
                     DB::raw('(SELECT COUNT(*) FROM `character` c WHERE c.guild_id = guild.id) as members'))
            ->orderByDesc('honor')->limit(200)->get();
        return view('guilds.index', ['guilds' => $guilds]);
    }

    public function show(int $id)
    {
        $guild = DB::table('guild')->where('id', $id)->first();
        abort_if($guild === null, 404);
        $members = DB::table('character')->where('guild_id', $id)
            ->select('id', 'user_id', 'name', 'level', 'guild_rank', 'honor')
            ->orderBy('guild_rank')->get();
        return view('guilds.show', ['guild' => $guild, 'members' => $members]);
    }

    public function update(Request $request, int $id)
    {
        DB::table('guild')->where('id', $id)->update([
            'name'             => (string)$request->input('name'),
            'description'      => (string)$request->input('description', ''),
            'honor'            => max(0, (int)$request->input('honor')),
            'game_currency'    => max(0, (int)$request->input('game_currency')),
            'premium_currency' => max(0, (int)$request->input('premium_currency')),
            'stat_guild_capacity' => max(10, (int)$request->input('stat_guild_capacity', 10)),
            'stat_character_base_stats_boost' => max(1, (int)$request->input('stat_character_base_stats_boost', 1)),
            'stat_quest_xp_reward_boost' => max(1, (int)$request->input('stat_quest_xp_reward_boost', 1)),
            'stat_quest_game_currency_reward_boost' => max(1, (int)$request->input('stat_quest_game_currency_reward_boost', 1)),
        ]);
        return back()->with('ok', 'Guilda salva.');
    }
}
