@extends('layout')
@section('title', "Guilda {$guild->name}")
@section('content')
<h2>Guilda {{ $guild->name }} <span class="pill">id {{ $guild->id }}</span></h2>

<div class="card">
  <form method="post" action="/guilds/{{ $guild->id }}">
    @csrf
    <div class="grid">
      <div><label>Nome</label><input name="name" value="{{ $guild->name }}"></div>
      <div><label>Honra</label><input name="honor" type="number" value="{{ $guild->honor }}"></div>
      <div><label>Moedas (cofre)</label><input name="game_currency" type="number" value="{{ $guild->game_currency }}"></div>
      <div><label>Donuts (cofre)</label><input name="premium_currency" type="number" value="{{ $guild->premium_currency }}"></div>
      <div><label>Capacidade</label><input name="stat_guild_capacity" type="number" value="{{ $guild->stat_guild_capacity }}"></div>
      <div><label>Boost stats</label><input name="stat_character_base_stats_boost" type="number" value="{{ $guild->stat_character_base_stats_boost }}"></div>
      <div><label>Boost XP</label><input name="stat_quest_xp_reward_boost" type="number" value="{{ $guild->stat_quest_xp_reward_boost }}"></div>
      <div><label>Boost moedas</label><input name="stat_quest_game_currency_reward_boost" type="number" value="{{ $guild->stat_quest_game_currency_reward_boost }}"></div>
    </div>
    <div style="margin-top:10px"><label>Descrição</label><textarea name="description" rows="2">{{ $guild->description }}</textarea></div>
    <div style="margin-top:12px"><button>Salvar guilda</button></div>
  </form>
</div>

<h3>Membros</h3>
<table>
  <tr><th>Char</th><th>Nome</th><th>Nível</th><th>Patente</th><th>Honra</th></tr>
  @foreach($members as $m)
  <tr>
    <td><a href="/chars/{{ $m->id }}">{{ $m->id }}</a></td>
    <td>{{ $m->name }}</td><td>{{ $m->level }}</td>
    <td>{{ [1 => 'líder', 2 => 'oficial'][$m->guild_rank] ?? 'membro' }}</td>
    <td>{{ $m->honor }}</td>
  </tr>
  @endforeach
</table>
@endsection
