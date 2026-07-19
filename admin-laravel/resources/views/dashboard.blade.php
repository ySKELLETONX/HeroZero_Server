@extends('layout')
@section('title', 'Painel')
@section('content')
<h2>Painel</h2>
<div class="grid">
  @foreach($stats as $label => $n)
    <div class="card stat"><b>{{ number_format($n, 0, ',', '.') }}</b>{{ $label }}</div>
  @endforeach
</div>

<h3>Últimos logins</h3>
<table>
  <tr><th>User</th><th>E-mail</th><th>Herói</th><th>Nível</th><th>Último login</th></tr>
  @foreach($lastLogins as $u)
  <tr>
    <td><a href="/users/{{ $u->id }}">{{ $u->id }}</a></td>
    <td>{{ $u->email }}</td>
    <td>{{ $u->name ?? '—' }}</td>
    <td>{{ $u->level ?? '—' }}</td>
    <td>{{ $u->ts_last_login ? date('d/m/Y H:i', $u->ts_last_login) : '—' }}</td>
  </tr>
  @endforeach
</table>
@endsection
