@extends('layout')
@section('title', 'Guildas')
@section('content')
<h2>Guildas</h2>
<table>
  <tr><th>Id</th><th>Nome</th><th>Membros</th><th>Honra</th><th>Moedas</th><th>Donuts</th><th>Status</th><th></th></tr>
  @foreach($guilds as $g)
  <tr>
    <td>{{ $g->id }}</td><td>{{ $g->name }}</td><td>{{ $g->members }}</td>
    <td>{{ $g->honor }}</td><td>{{ number_format($g->game_currency, 0, ',', '.') }}</td>
    <td>{{ $g->premium_currency }}</td>
    <td>{{ $g->status ? 'ativa' : 'desativada' }}</td>
    <td><a class="btn" href="/guilds/{{ $g->id }}">Gerenciar</a></td>
  </tr>
  @endforeach
</table>
@endsection
