<?php
// Resume a estrutura (chaves + tipo + tamanho) do campo data de um capture/*.json
function shape($v, $depth=0, $maxDepth=2) {
    if (is_array($v)) {
        $isList = array_keys($v) === range(0, count($v)-1);
        if ($isList) return 'array['.count($v).']' . (count($v)&&$depth<$maxDepth ? ' of '.shape($v[0],$depth+1,$maxDepth) : '');
        if ($depth >= $maxDepth) return 'object{'.count($v).' keys}';
        $out=[];
        foreach ($v as $k=>$vv) $out[] = $k.': '.shape($vv,$depth+1,$maxDepth);
        return "{\n".str_repeat('  ',$depth+1).implode("\n".str_repeat('  ',$depth+1),$out)."\n".str_repeat('  ',$depth)."}";
    }
    if (is_bool($v)) return 'bool';
    if (is_int($v)) return 'int('.$v.')';
    if (is_float($v)) return 'float';
    if (is_string($v)) return 'string('.(strlen($v)>40?strlen($v).'ch':'"'.$v.'"').')';
    if (is_null($v)) return 'null';
    return gettype($v);
}
$f = $argv[1];
$maxDepth = (int)($argv[2] ?? 1);
$j = json_decode(file_get_contents($f), true);
echo "== ".basename($f)." | action=".$j['action']." err=".($j['response']['error']??'?')." ==\n";
echo shape($j['response']['data'] ?? [], 0, $maxDepth)."\n";
