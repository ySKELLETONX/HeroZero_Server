<?php
declare(strict_types=1);
/*
 * Gera server-laravel/app/HeroZero/ResponseSchema.php a partir da tabela de
 * docs/RESPONSE_SCHEMA.md. Fonte unica de verdade dos tipos de nivel raiz que o
 * cliente sabe ler. Re-rode se o schema mudar:
 *   tools/php83/php.exe tools/gen_response_schema.php
 */

$root = dirname(__DIR__);
$md = file_get_contents("$root/docs/RESPONSE_SCHEMA.md");
$arrayKinds = ['ArrayOfDO', 'StringVector', 'Array'];

$arr = [];
$obj = [];
foreach (explode("\n", $md) as $line) {
    if (!preg_match('/^\|\s*`([^`]+)`\s*\|\s*([A-Za-z]+)\s*\|/', $line, $m)) {
        continue;
    }
    [, $field, $kind] = $m;
    if (in_array($kind, $arrayKinds, true)) {
        $arr[] = $field;
    } elseif ($kind === 'TypedObject') {
        $obj[] = $field;
    }
}
sort($arr);
sort($obj);

$fmt = static function (array $xs): string {
    $out = '';
    foreach ($xs as $x) {
        $out .= "        '$x',\n";
    }
    return $out;
};

$php = <<<PHP
<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Fonte unica de verdade dos tipos de nivel raiz que o cliente sabe ler (classe `ta`
 * no HeroZero.min.js). GERADO a partir de docs/RESPONSE_SCHEMA.md por
 * tools/gen_response_schema.php — NAO editar a mao; re-gerar se o schema mudar.
 *
 * Uso: ResponseSchema::normalize(\$data) corrige campos PRESENTES cujo valor veio
 * `null` para o container vazio correto — array JSON `[]` p/ colecoes
 * (ArrayOfDO/StringVector) e objeto `{}` p/ mapas (TypedObject). Isso mata o crash
 * documentado em memory/guild-response-key-null-iterator-pattern: o cliente le o
 * campo por nome exato e, se vier null em vez de colecao vazia, quebra em
 * .iterator().
 *
 * IMPORTANTE: normalize() so age sobre valor === null e NUNCA injeta campos
 * ausentes — presenca de um campo e sinal de feature ativa (missed_duel,
 * worldboss_attack, battle...). Um valor ja preenchido (mesmo assoc) e preservado
 * intacto; corrigir tipo alem de null seria arriscado e fica fora de escopo.
 */
final class ResponseSchema
{
    /** Campos que o cliente le como colecao (ArrayOfDO/StringVector/Array) -> `[]`. */
    private const ARRAY_FIELDS = [
{$fmt($arr)}    ];

    /** Campos que o cliente le como mapa (TypedObject) -> `{}`. */
    private const OBJECT_FIELDS = [
{$fmt($obj)}    ];

    public static function normalize(array \$data): array
    {
        foreach (self::ARRAY_FIELDS as \$f) {
            if (array_key_exists(\$f, \$data) && \$data[\$f] === null) {
                \$data[\$f] = [];
            }
        }
        foreach (self::OBJECT_FIELDS as \$f) {
            if (array_key_exists(\$f, \$data) && \$data[\$f] === null) {
                \$data[\$f] = new \stdClass();
            }
        }
        return \$data;
    }
}

PHP;

file_put_contents("$root/server-laravel/app/HeroZero/ResponseSchema.php", $php);
echo 'ResponseSchema.php gerado: ' . count($arr) . ' array-fields, ' . count($obj) . " object-fields\n";
