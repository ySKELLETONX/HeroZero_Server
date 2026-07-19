<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('dashboard', [
            'stats' => [
                'Contas'       => DB::table('user')->count(),
                'Personagens'  => DB::table('character')->whereRaw('user_id > 0')->count(),
                'NPCs'         => DB::table('character')->where('user_id', 0)->count(),
                'Guildas'      => DB::table('guild')->count(),
                'Itens'        => DB::table('items')->count(),
                'Missoes'      => DB::table('quests')->count(),
            ],
            'lastLogins' => DB::table('user')
                ->leftJoin('character', 'character.user_id', '=', 'user.id')
                ->select('user.id', 'user.email', 'character.name', 'character.level', 'user.ts_last_login')
                ->where('user.ts_last_login', '>', 0)
                ->orderByDesc('user.ts_last_login')->limit(10)->get(),
        ]);
    }

    public function showBroadcast()
    {
        $guilds = DB::table('guild')->where('status', '!=', 0)->select('id', 'name')->orderBy('id')->get();
        return view('broadcast', ['guilds' => $guilds]);
    }

    /**
     * Envia mensagem "do sistema" para o chat de TODAS as guildas (ou uma so).
     * Usa a mesma tabela guild_messages que o servidor do jogo serve no getGuildLog,
     * entao aparece no chat de guilda de todo mundo no proximo poll.
     */
    public function sendBroadcast(Request $request)
    {
        $msg = trim((string)$request->input('message', ''));
        if ($msg === '' || mb_strlen($msg) > 500) {
            return back()->with('error', 'Mensagem vazia ou longa demais (max. 500).');
        }
        $only = (int)$request->input('guild_id', 0);
        $guilds = DB::table('guild')->where('status', '!=', 0)
            ->when($only > 0, fn ($q) => $q->where('id', $only))
            ->pluck('id');
        $now = time();
        foreach ($guilds as $gid) {
            DB::table('guild_messages')->insert([
                'guild_id' => $gid,
                'character_from_id' => 0,
                'character_from_name' => '[SISTEMA]',
                'character_to_id' => 0,
                'is_officer' => 0,   // 1 restringiria a visibilidade a oficiais (GuildChat::visibleMessages)
                'is_private' => 0,
                'message' => $msg,
                'timestamp' => $now,
            ]);
        }
        return back()->with('ok', 'Mensagem enviada para ' . count($guilds) . ' guilda(s).');
    }
}
