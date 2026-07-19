<?php
// Extrai o payload `data` das capturas de boot para server/data/<action>.json
$cap = __DIR__ . '/capture';
$out = dirname(__DIR__) . '/server/data';
@mkdir($out, 0777, true);
$want = ['initEnvironment','autoLoginUser','initGame','getStandalonePaymentOffers','loginFriendBar','updateGameSession'];
$done = [];
foreach (glob("$cap/*.json") as $f) {
    $j = json_decode(file_get_contents($f), true);
    $a = $j['action'] ?? '';
    if (!in_array($a, $want) || isset($done[$a])) continue;
    if (($j['response']['error'] ?? '') !== '') continue;
    file_put_contents("$out/$a.json", json_encode($j['response']['data'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $done[$a] = filesize("$out/$a.json");
}
foreach ($done as $a=>$sz) echo "$a  ".number_format($sz)." bytes\n";
// captura user/session p/ configurar a pagina
$fb = json_decode(file_get_contents("$cap/05_loginFriendBar.json"), true)['response']['data']['user'] ?? [];
echo "\nuser_id=".($fb['id']??'?')."  session_id=".($fb['session_id']??'?')."\n";
