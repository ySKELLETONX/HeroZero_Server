<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><title>Login — HZ Admin</title>
<style>
body { background:#12141a; color:#e6e8ef; font:14px system-ui; display:grid; place-items:center; min-height:100vh; margin:0; }
form { background:#1b1e27; border:1px solid #2a2e3b; border-radius:10px; padding:28px; width:300px; }
h1 { font-size:18px; color:#f5a623; margin:0 0 16px; }
input { width:100%; box-sizing:border-box; background:#12141a; color:#e6e8ef; border:1px solid #2a2e3b; border-radius:5px; padding:9px; margin-bottom:12px; font:inherit; }
button { width:100%; background:#f5a623; border:0; border-radius:5px; padding:9px; font-weight:700; cursor:pointer; }
.err { color:#ff9c8a; margin-bottom:10px; }
</style>
</head>
<body>
<form method="post" action="/login">
  @csrf
  <h1>⚡ Hero Zero Admin</h1>
  @if(session('error'))<div class="err">{{ session('error') }}</div>@endif
  <input type="password" name="password" placeholder="Senha de admin" autofocus>
  <button>Entrar</button>
</form>
</body>
</html>
