<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    /** Lista/busca contas (por id, email ou nome de personagem). */
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $users = DB::table('user')
            ->leftJoin('character', 'character.user_id', '=', 'user.id')
            ->select('user.id', 'user.email', 'user.premium_currency', 'user.ts_last_login', 'user.ts_banned',
                     'character.id as character_id', 'character.name', 'character.level')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('user.email', 'like', "%{$q}%")
                      ->orWhere('character.name', 'like', "%{$q}%");
                    if (ctype_digit($q)) {
                        $w->orWhere('user.id', (int)$q)->orWhere('character.id', (int)$q);
                    }
                });
            })
            ->orderByDesc('user.id')->limit(100)->get();

        return view('users.index', ['users' => $users, 'q' => $q]);
    }

    /** Cria conta REAL via beta-api do servidor do jogo (mesmo caminho do beta.html). */
    public function store(Request $request)
    {
        $resp = Http::post(env('HZ_GAME_SERVER', 'http://127.0.0.1:8000') . '/beta-api', [
            'action'   => 'register',
            'email'    => (string)$request->input('email'),
            'password' => (string)$request->input('password'),
            'name'     => (string)$request->input('name'),
        ]);
        $j = $resp->json() ?? [];
        if (!($j['ok'] ?? false)) {
            return back()->with('error', 'Falha ao criar: ' . ($j['error'] ?? 'servidor do jogo offline?'));
        }
        return redirect('/users/' . $j['user_id'])->with('ok', "Conta criada (user {$j['user_id']}, personagem {$j['character_id']}).");
    }

    public function show(int $id)
    {
        $user = DB::table('user')->where('id', $id)->first();
        abort_if($user === null, 404);
        $chars = DB::table('character')->where('user_id', $id)->orderBy('id')->get();
        return view('users.show', ['user' => $user, 'chars' => $chars]);
    }

    public function update(Request $request, int $id)
    {
        $action = (string)$request->input('do');
        if ($action === 'save') {
            DB::table('user')->where('id', $id)->update([
                'email'            => (string)$request->input('email'),
                'premium_currency' => max(0, (int)$request->input('premium_currency')),
                'locale'           => (string)$request->input('locale', 'pt_BR'),
            ]);
            return back()->with('ok', 'Conta salva.');
        }
        if ($action === 'reset_session') {
            DB::table('user')->where('id', $id)->update([
                'session_id' => substr(bin2hex(random_bytes(16)), 0, 30),
                'session_id_cache1' => '', 'session_id_cache2' => '', 'session_id_cache3' => '',
                'session_id_cache4' => '', 'session_id_cache5' => '',
            ]);
            return back()->with('ok', 'Sessao resetada (o jogador precisa relogar).');
        }
        if ($action === 'set_password') {
            $pass = (string)$request->input('new_password');
            if (strlen($pass) < 4) return back()->with('error', 'Senha muito curta (min. 4).');
            DB::table('user')->where('id', $id)->update(['password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
            return back()->with('ok', 'Senha redefinida.');
        }
        if ($action === 'ban') {
            DB::table('user')->where('id', $id)->update(['ts_banned' => time()]);
            return back()->with('ok', 'Conta banida.');
        }
        if ($action === 'unban') {
            DB::table('user')->where('id', $id)->update(['ts_banned' => 0]);
            return back()->with('ok', 'Ban removido.');
        }
        return back()->with('error', 'Acao desconhecida.');
    }

    /** Apaga a conta e TUDO do(s) personagem(ns). */
    public function destroy(int $id)
    {
        $charIds = DB::table('character')->where('user_id', $id)->pluck('id')->all();
        foreach ($charIds as $cid) {
            foreach (['quests', 'items', 'inventory', 'bank_inventory', 'sidekicks',
                      'event_quests', 'season_progress', 'training', 'work'] as $t) {
                DB::table($t)->where('character_id', $cid)->delete();
            }
            DB::table('guild_messages')->where('character_from_id', $cid)->delete();
            DB::table('character')->where('id', $cid)->delete();
        }
        DB::table('user')->where('id', $id)->delete();
        return redirect('/users')->with('ok', "Conta {$id} apagada (personagens: " . implode(',', $charIds) . ').');
    }
}
