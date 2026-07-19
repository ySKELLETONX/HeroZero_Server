@extends('layout')
@section('title', 'Mensagem global')
@section('content')
<h2>Mensagem para o servidor</h2>
<div class="card">
  <p style="color:var(--mut); margin-bottom:12px">
    A mensagem aparece como <b>[SISTEMA]</b> no chat de guilda dos jogadores no próximo poll do jogo (getGuildLog).
  </p>
  <form method="post" action="/broadcast">
    @csrf
    <div class="row">
      <div><label>Destino</label>
        <select name="guild_id">
          <option value="0">Todas as guildas</option>
          @foreach($guilds as $g)<option value="{{ $g->id }}">{{ $g->name }} (#{{ $g->id }})</option>@endforeach
        </select>
      </div>
    </div>
    <div style="margin-top:10px"><label>Mensagem (máx. 500)</label><textarea name="message" rows="3" maxlength="500" required placeholder="ex.: Manutenção às 22h — o servidor vai reiniciar."></textarea></div>
    <div style="margin-top:12px"><button>Enviar</button></div>
  </form>
</div>
@endsection
