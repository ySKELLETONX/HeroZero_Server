<?php
declare(strict_types=1);

namespace HeroZero;

use PDO;

/**
 * Conexao PDO unica com o nosso banco herozero (MySQL em Docker).
 * Uso: Db::pdo()->query(...), Db::row(), Db::rows(), Db::value().
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = config('herozero.db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Uma linha (ou null). */
    public static function row(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    /** Varias linhas. */
    public static function rows(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Um valor escalar da primeira coluna. */
    public static function value(string $sql, array $params = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Vetor plano com a primeira coluna de cada linha. */
    public static function column(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
