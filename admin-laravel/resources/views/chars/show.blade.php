@extends('layout')
@section('title', "Herói {$char->name}")
@section('content')
<h2>Herói {{ $char->name }} <span class="pill">char {{ $char->id }} · user <a href="/users/{{ $char->user_id }}">{{ $char->user_id }}</a></span></h2>

<div class="card">
  <h3 style="margin-top:0">Dados</h3>
  <form method="post" action="/chars/{{ $char->id }}">
    @csrf
    <div class="grid">
      @foreach($editable as $f)
        <div><label>{{ $f }}</label><input name="{{ $f }}" value="{{ $char->$f ?? 0 }}"></div>
      @endforeach
    </div>
    <div style="margin-top:12px"><button>Salvar personagem</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Equipado</h3>
  <table>
    <tr><th>Slot</th><th>Item</th><th>Q</th><th>Lv</th><th>Stats</th><th></th></tr>
    @foreach($equipped as $slot => $it)
    <tr>
      <td>{{ $slot }}</td>
      @if($it)
        <td>{{ $it->identifier }} <span class="pill">#{{ $it->id }}</span></td>
        <td>{{ $it->quality }}</td><td>{{ $it->required_level }}</td>
        <td>vig {{ $it->stat_stamina }} · for {{ $it->stat_strength }} · cri {{ $it->stat_critical_rating }} · esq {{ $it->stat_dodge_rating }} · dano {{ $it->stat_weapon_damage }}</td>
        <td><form class="inline" method="post" action="/chars/{{ $char->id }}/items/{{ $it->id }}/delete">@csrf<button class="danger">remover</button></form></td>
      @else
        <td colspan="4" style="color:var(--mut)">vazio</td><td></td>
      @endif
    </tr>
    @endforeach
  </table>

  <h3>Mochila</h3>
  <table>
    <tr><th>#</th><th>Item</th><th>Q</th><th>Lv</th><th>Stats</th><th></th></tr>
    @foreach($bag as $i => $it)
      @if($it)
      <tr>
        <td>{{ $i }}</td>
        <td>{{ $it->identifier }} <span class="pill">#{{ $it->id }}</span></td>
        <td>{{ $it->quality }}</td><td>{{ $it->required_level }}</td>
        <td>vig {{ $it->stat_stamina }} · for {{ $it->stat_strength }} · cri {{ $it->stat_critical_rating }} · esq {{ $it->stat_dodge_rating }} · dano {{ $it->stat_weapon_damage }}</td>
        <td><form class="inline" method="post" action="/chars/{{ $char->id }}/items/{{ $it->id }}/delete">@csrf<button class="danger">remover</button></form></td>
      </tr>
      @endif
    @endforeach
  </table>

  <h3>Dar item</h3>
  <form method="post" action="/chars/{{ $char->id }}/items">
    @csrf
    <div class="row">
      <div><label>Tipo</label>
        <select name="type" id="give-type">
          <option value="1">1 · Máscara</option><option value="2">2 · Capa</option>
          <option value="3">3 · Traje</option><option value="4">4 · Cinto</option>
          <option value="5">5 · Botas</option><option value="6" selected>6 · Arma</option>
          <option value="7">7 · Gadget</option>
        </select>
      </div>
      <div style="flex:2"><label>Identifier (catálogo oficial)</label>
        <input name="identifier" list="idents" placeholder="ex.: weapon_hair_dryer1" required>
        <datalist id="idents">
          @foreach($catalog as $tp => $ids) @foreach(array_slice($ids, 0, 400) as $ident)
            <option value="{{ $ident }}">
          @endforeach @endforeach
        </datalist>
      </div>
      <div><label>Qualidade</label><input name="quality" type="number" value="1" min="1" max="5"></div>
      <div><label>Nível</label><input name="required_level" type="number" value="{{ $char->level }}" min="1"></div>
    </div>
    <div class="row" style="margin-top:8px">
      <div><label>Vigor</label><input name="stat_stamina" type="number" value="0"></div>
      <div><label>Força</label><input name="stat_strength" type="number" value="0"></div>
      <div><label>Crítico</label><input name="stat_critical_rating" type="number" value="0"></div>
      <div><label>Esquiva</label><input name="stat_dodge_rating" type="number" value="0"></div>
      <div><label>Dano arma</label><input name="stat_weapon_damage" type="number" value="0"></div>
      <div><label>Preço compra</label><input name="buy_price" type="number" value="10"></div>
      <div><label>Preço venda</label><input name="sell_price" type="number" value="5"></div>
      <div style="flex:0"><button>Dar item</button></div>
    </div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Missões</h3>
  <table>
    <tr><th>Id</th><th>Identifier</th><th>Tipo</th><th>Stage</th><th>Status</th><th>Duração</th><th>Energia</th><th>Rewards</th><th></th></tr>
    @foreach($quests as $qq)
    <tr>
      <td>{{ $qq->id }}</td><td>{{ $qq->identifier }}</td>
      <td>{{ $qq->type == 2 ? 'luta' : 'tempo' }}</td><td>{{ $qq->stage }}</td>
      <td>{{ ['','disponível','iniciada','?','finalizada'][$qq->status] ?? $qq->status }}</td>
      <td>{{ $qq->duration }}s</td><td>{{ $qq->energy_cost }}</td>
      <td style="font-size:12px">{{ $qq->rewards }}</td>
      <td><form class="inline" method="post" action="/chars/{{ $char->id }}/quests/{{ $qq->id }}/delete">@csrf<button class="danger">apagar</button></form></td>
    </tr>
    @endforeach
  </table>

  <h3>Criar / editar missão</h3>
  <form method="post" action="/chars/{{ $char->id }}/quests">
    @csrf
    <div class="row">
      <div><label>Editar id (vazio = criar)</label><input name="quest_id" type="number" placeholder="novo"></div>
      <div style="flex:2"><label>Identifier</label><input name="identifier" value="quest_stage{{ $char->max_quest_stage ?? 1 }}_time2"></div>
      <div><label>Tipo</label><select name="type"><option value="1">tempo</option><option value="2">luta</option></select></div>
      <div><label>Stage</label><input name="stage" type="number" value="{{ $char->max_quest_stage ?? 1 }}"></div>
      <div><label>Level</label><input name="level" type="number" value="{{ $char->level }}"></div>
    </div>
    <div class="row" style="margin-top:8px">
      <div><label>Status</label><select name="status"><option value="1">disponível</option><option value="2">iniciada</option><option value="4">finalizada</option></select></div>
      <div><label>Duração (s)</label><input name="duration" type="number" value="60"></div>
      <div><label>Energia</label><input name="energy_cost" type="number" value="2"></div>
      <div><label>Moedas</label><input name="reward_coins" type="number" value="50"></div>
      <div><label>XP</label><input name="reward_xp" type="number" value="50"></div>
      <div><label>NPC (se luta)</label><input name="fight_npc_identifier" placeholder="npc_business_man_artless"></div>
      <div style="flex:0"><button>Salvar missão</button></div>
    </div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0">Eventos (event_quests)</h3>
  <table>
    <tr><th>Id</th><th>Identifier</th><th>Status</th><th>Fim</th><th>Rewards</th><th></th></tr>
    @foreach($events as $ev)
    <tr>
      <td>{{ $ev->id }}</td><td>{{ $ev->identifier }}</td><td>{{ $ev->status }}</td>
      <td>{{ $ev->end_date }}</td><td style="font-size:12px">{{ $ev->rewards }}</td>
      <td><form class="inline" method="post" action="/chars/{{ $char->id }}/events/{{ $ev->id }}/delete">@csrf<button class="danger">apagar</button></form></td>
    </tr>
    @endforeach
  </table>
  <form method="post" action="/chars/{{ $char->id }}/events" class="row">
    @csrf
    <div><label>Editar id (vazio = criar)</label><input name="event_id" type="number" placeholder="novo"></div>
    <div style="flex:2"><label>Identifier</label><input name="identifier" placeholder="event_..."></div>
    <div><label>Status</label><input name="status" type="number" value="1"></div>
    <div><label>Fim (Y-m-d)</label><input name="end_date" value="{{ date('Y-m-d', time() + 7*86400) }}"></div>
    <div style="flex:2"><label>Rewards (json)</label><input name="rewards" placeholder='{"coins":100}'></div>
    <div style="flex:0"><button>Salvar evento</button></div>
  </form>
  <p style="color:var(--mut); margin-top:8px">Obs.: o catálogo de eventos que o cliente mostra vem dos fixtures do servidor; esta tabela é o estado por personagem.</p>
</div>
@endsection
