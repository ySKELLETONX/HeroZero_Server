<?php

namespace App\Http\Controllers;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API beta simples de conta (fora do protocolo do jogo) — port de server/beta_api.php.
 * POST /beta-api com JSON { action, email, password, name }.
 */
class BetaApiController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (app()->isProduction() && getenv('HZ_ENABLE_BETA_API') !== '1') {
            return response()->json(['ok' => false, 'error' => 'Not Found'], 404);
        }

        $in     = $request->json()->all() ?: [];
        $action = (string)($in['action'] ?? '');
        $email  = trim((string)($in['email'] ?? ''));
        $pass   = (string)($in['password'] ?? '');
        $name   = trim((string)($in['name'] ?? ''));

        try {
            if ($action === 'register') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->out(['ok' => false, 'error' => 'E-mail invalido.'], 400);
                }
                if (strlen($pass) < 4) {
                    return $this->out(['ok' => false, 'error' => 'Senha muito curta (min. 4).'], 400);
                }
                if ($name === '') {
                    $name = strstr($email, '@', true) ?: 'Heroi';
                }
                if (Db::value('SELECT id FROM `user` WHERE email = ?', [$email])) {
                    return $this->out(['ok' => false, 'error' => 'E-mail ja registrado.'], 409);
                }
                $session = bin2hex(random_bytes(15));
                Db::exec(
                    "INSERT INTO `user` (email, password_hash, session_id, premium_currency, locale, confirmed, trusted, network, ts_creation, registration_source)
                     VALUES (?, ?, ?, 30, 'pt_BR', 1, 1, '', UNIX_TIMESTAMP(), 'beta')",
                    [$email, password_hash($pass, PASSWORD_DEFAULT), $session]
                );
                $userId = (int)Db::pdo()->lastInsertId();
                $char   = Character::createNew($userId, $name);

                return $this->out(['ok' => true, 'user_id' => $userId, 'character_id' => $char->id(),
                                   'session_id' => $session, 'name' => $name]);
            }

            if ($action === 'login') {
                $u = Db::row('SELECT id, password_hash, session_id FROM `user` WHERE email = ?', [$email]);
                if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
                    return $this->out(['ok' => false, 'error' => 'E-mail ou senha incorretos.'], 401);
                }
                $session = bin2hex(random_bytes(15));
                Db::exec(
                    'UPDATE `user`
                        SET session_id_cache5 = session_id_cache4,
                            session_id_cache4 = session_id_cache3,
                            session_id_cache3 = session_id_cache2,
                            session_id_cache2 = session_id_cache1,
                            session_id_cache1 = session_id,
                            session_id = ?,
                            ts_last_login = UNIX_TIMESTAMP(),
                            login_count = login_count + 1
                      WHERE id = ?',
                    [$session, (int)$u['id']]
                );
                $char = Character::loadByUser((int)$u['id']);

                return $this->out(['ok' => true, 'user_id' => (int)$u['id'], 'character_id' => $char->id(),
                                   'session_id' => $session, 'name' => (string)($char->name() ?? '')]);
            }

            return $this->out(['ok' => false, 'error' => 'Acao desconhecida.'], 400);
        } catch (GameError $e) {
            return $this->out(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->out(['ok' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    private function out(array $data, int $code = 200): JsonResponse
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
