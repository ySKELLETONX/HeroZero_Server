<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Admin') — Hero Zero</title>
<style>
:root { --bg:#12141a; --panel:#1b1e27; --line:#2a2e3b; --tx:#e6e8ef; --mut:#9aa1b5; --acc:#f5a623; --ok:#37c871; --err:#e5533d; }
* { box-sizing:border-box; margin:0; }
body { background:var(--bg); color:var(--tx); font:14px/1.5 system-ui, Segoe UI, sans-serif; min-height:100vh; display:flex; }
aside { width:210px; background:var(--panel); border-right:1px solid var(--line); padding:16px 0; flex-shrink:0; min-height:100vh; }
aside h1 { font-size:16px; padding:0 16px 12px; color:var(--acc); }
aside a { display:block; padding:9px 16px; color:var(--mut); text-decoration:none; }
aside a:hover, aside a.on { color:var(--tx); background:#232734; border-left:3px solid var(--acc); padding-left:13px; }
main { flex:1; padding:22px 26px; max-width:1200px; }
h2 { font-size:19px; margin-bottom:14px; }
h3 { font-size:15px; margin:18px 0 8px; color:var(--acc); }
table { width:100%; border-collapse:collapse; background:var(--panel); border:1px solid var(--line); margin-bottom:14px; }
th, td { padding:7px 10px; text-align:left; border-bottom:1px solid var(--line); }
th { color:var(--mut); font-weight:600; font-size:12px; text-transform:uppercase; }
tr:hover td { background:#20232e; }
a { color:var(--acc); }
.card { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:16px; margin-bottom:16px; }
.grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:10px; }
label { display:block; font-size:12px; color:var(--mut); margin-bottom:2px; }
input, select, textarea { width:100%; background:#12141a; color:var(--tx); border:1px solid var(--line); border-radius:5px; padding:6px 8px; font:inherit; }
button, .btn { background:var(--acc); color:#12141a; font-weight:700; border:0; border-radius:5px; padding:7px 14px; cursor:pointer; text-decoration:none; display:inline-block; }
button.danger { background:var(--err); color:#fff; }
button.ghost { background:transparent; color:var(--mut); border:1px solid var(--line); }
.flash { padding:9px 12px; border-radius:6px; margin-bottom:14px; }
.flash.ok { background:#173524; color:var(--ok); border:1px solid #1f5c3a; }
.flash.err { background:#3a1a15; color:#ff9c8a; border:1px solid #71362a; }
.pill { display:inline-block; padding:1px 8px; border-radius:99px; font-size:12px; background:#242a3a; color:var(--mut); }
.stat { text-align:center; padding:18px 8px; }
.stat b { display:block; font-size:26px; color:var(--acc); }
form.inline { display:inline; }
.row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.row > div { flex:1; min-width:120px; }
</style>
</head>
<body>
<aside>
  <h1>⚡ HZ Admin</h1>
  <a href="/" class="{{ request()->is('/') ? 'on' : '' }}">Painel</a>
  <a href="/users" class="{{ request()->is('users*') ? 'on' : '' }}">Contas</a>
  <a href="/guilds" class="{{ request()->is('guilds*') ? 'on' : '' }}">Guildas</a>
  <a href="/broadcast" class="{{ request()->is('broadcast') ? 'on' : '' }}">Mensagem global</a>
  <form method="post" action="/logout" style="margin-top:20px; padding:0 16px;">@csrf<button class="ghost" style="width:100%">Sair</button></form>
</aside>
<main>
@if(session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
@if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
@yield('content')
</main>
</body>
</html>
