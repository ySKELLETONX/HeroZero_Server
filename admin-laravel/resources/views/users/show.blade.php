@extends('layout')
@section('title', "Conta {$user->id}")
@section('content')
<h2>Conta #{{ $user->id }} @if($user->ts_banned)<span class="pill" style="color:#ff9c8a">BANIDA</span>@endif</h2>

<div class="card">
  <form method="post" action="/users/{{ $user->id }}" class="row">
    @csrf
    <input type="hidden" name="do" value="save">
    <div><label>E-mail</label><input name="email" value="{{ $user->email }}"></div>
    <div><label>Donuts (premium)</label><input name="premium_currency" type="number" value="{{ $user->premium_currency }}"></div>
    <div><label>Locale</label><input name="locale" value="{{ $user->locale }}"></div>
    <div style="flex:0"><button>Salvar</button></div>
  </form>
  <p style="color:var(--mut); margin-top:8px">
    Criada: {{ $user->ts_creation ? date('d/m/Y H:i', $user->ts_creation) : '—' }} ·
    Último login: {{ $user->ts_last_login ? date('d/m/Y H:i', $user->ts_last_login) : '—' }} ·
    Logins: {{ $user->login_count }}
  </p>
</div>

<div class="card row">
  <form method="post" action="/users/{{ $user->id }}" class="inline">@csrf<input type="hidden" name="do" value="reset_session"><button class="ghost">Resetar sessão (kick)</button></form>
  <form method="post" action="/users/{{ $user->id }}" class="inline row" style="flex:2">
    @csrf<input type="hidden" name="do" value="set_password">
    <div><input name="new_password" placeholder="nova senha"></div>
    <div style="flex:0"><button class="ghost">Redefinir senha</button></div>
  </form>
  @if($user->ts_banned)
    <form method="post" action="/users/{{ $user->id }}" class="inline">@csrf<input type="hidden" name="do" value="unban"><button class="ghost">Desbanir</button></form>
  @else
    <form method="post" action="/users/{{ $user->id }}" class="inline">@csrf<input type="hidden" name="do" value="ban"><button class="danger">Banir</button></form>
  @endif
  <form method="post" action="/users/{{ $user->id }}/delete" class="inline" onsubmit="return confirm('Apagar a conta {{ $user->id }} e TODOS os dados? Não tem volta.')">
    @csrf<button class="danger">Apagar conta</button>
  </form>
</div>

<h3>Personagens</h3>
<table>
  <tr><th>Id</th><th>Nome</th><th>Nível</th><th>Moedas</th><th>Honra</th><th>Guilda</th><th></th></tr>
  @foreach($chars as $c)
  <tr>
    <td>{{ $c->id }}</td><td>{{ $c->name }}</td><td>{{ $c->level }}</td>
    <td>{{ number_format($c->game_currency, 0, ',', '.') }}</td><td>{{ $c->honor }}</td>
    <td>{{ $c->guild_id ? $c->guild_id : '—' }}</td>
    <td><a class="btn" href="/chars/{{ $c->id }}">Gerenciar</a></td>
  </tr>
  @endforeach
</table>
@endsection
