<?php
// Extrai os pares request.php (request+response) de um HAR para tools/capture/.
$har = $argv[1] ?? 'br30.herozerogame.com.har';
$outDir = __DIR__ . '/capture';
@mkdir($outDir, 0777, true);

fwrite(STDERR, "lendo $har ...\n");
$data = json_decode(file_get_contents(__DIR__ . '/' . $har), true);
if (!$data) { fwrite(STDERR, "JSON invalido\n"); exit(1); }

$entries = $data['log']['entries'] ?? [];
fwrite(STDERR, count($entries) . " entradas no HAR\n");

$idx = [];
$n = 0;
foreach ($entries as $e) {
    $url = $e['request']['url'] ?? '';
    if (strpos($url, 'request.php') === false) continue;

    // action a partir do postData (form-urlencoded)
    $post = $e['request']['postData']['text'] ?? '';
    $params = [];
    parse_str($post, $params);
    $action = $params['action'] ?? '?';

    $resText = $e['response']['content']['text'] ?? '';
    // alguns HAR guardam base64
    if (($e['response']['content']['encoding'] ?? '') === 'base64') {
        $resText = base64_decode($resText);
    }

    $n++;
    $file = sprintf('%02d_%s.json', $n, preg_replace('/[^a-zA-Z0-9]/', '', $action));
    $rec = [
        'action'   => $action,
        'url'      => $url,
        'reqParams'=> $params,
        'response' => json_decode($resText, true),
    ];
    file_put_contents("$outDir/$file", json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $resDecoded = json_decode($resText, true);
    $topKeys = is_array($resDecoded['data'] ?? null) ? array_keys($resDecoded['data']) : [];
    $idx[] = sprintf("%2d  %-28s err=%-20s dataKeys=%d  bytes=%d",
        $n, $action, (string)($resDecoded['error'] ?? '?'), count($topKeys), strlen($resText));
}

file_put_contents("$outDir/_index.txt", implode("\n", $idx) . "\n");
fwrite(STDERR, "extraidos $n pares request.php -> $outDir\n");
echo implode("\n", $idx) . "\n";
