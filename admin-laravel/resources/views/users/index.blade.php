@extends('layout')
@section('title', 'Contas')
@section('content')
<h2>Contas</h2>

<div class="card">
  <form method="get" action="/users" class="row">
    <div style="flex:3"><label>Buscar (id, e-mail ou nome do herói)</label><input name="q" value="{{ $q }}" placeholder="ex.: test2"></div>
    <div style="flex:0"><button>Buscar</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Criar conta nova</h3>
  <form method="post" action="/users" class="row">
    @csrf
    <div><label>E-mail</label><input name="email" type="email" required></div>
    <div><label>Senha</label><input name="password" required></div>
    <div><label>Nome do herói</label><input name="name" required></div>
    <div style="flex:0"><button>Criar</button></div>
  </form>
</div>

<table>
  <tr><th>User</th><th>E-mail</th><th>Herói</th><th>Nível</th><th>Donuts</th><th>Status</th><th></th></tr>
  @foreach($users as $u)
  <tr>
    <td>{{ $u->id }}</td>
    <td>{{ $u->email }}</td>
    <td>@if($u->character_id)<a href="/chars/{{ $u->character_id }}">{{ $u->name }}</a>@else — @endif</td>
    <td>{{ $u->level ?? '—' }}</td>
    <td>{{ $u->premium_currency }}</td>
    <td>@if($u->ts_banned)<span class="pill" style="color:#ff9c8a">banido</span>@else<span class="pill">ativo</span>@endif</td>
    <td><a class="btn" href="/users/{{ $u->id }}">Gerenciar</a></td>
  </tr>
  @endforeach
</table>
@endsection
