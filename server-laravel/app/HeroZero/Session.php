<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Guarda de sessão. Fecha o furo de impersonação: o SALT do `auth` é público
 * (extraído do cliente), então a assinatura NÃO prova identidade — qualquer um
 * consegue assinar para qualquer user_id. O único segredo é o `user_session_id`,
 * que o servidor emitiu no login/registro (beta_api) e guardou em `user.session_id`.
 *
 * Regra (dev-friendly, não quebra o que já funciona):
 *   - Resolve o user_id do payload (user_id ou existing_user_id).
 *   - Se não há user_id, ou o usuário não existe no banco, ou o usuário não tem
 *     sessão gravada -> não valida (NPC, template puro, fluxo pré-login).
 *   - Se o usuário REAL tem sessão gravada, o `user_session_id` enviado TEM de bater.
 *     Senão -> errLoginInvalidSession.
 *
 * Actions públicas (pré-login / logging) ficam de fora via ausência de sessão no
 * banco ou pela allowlist PUBLIC_ACTIONS.
 */
final class Session
{
    /** Liga/desliga a validação (env HZ_STRICT_SESSION=0 desliga p/ debug). */
    public static function strict(): bool
    {
        $v = getenv('HZ_STRICT_SESSION');
        return $v === false ? true : ($v !== '0' && strtolower((string)$v) !== 'false');
    }

    /** Actions que nunca exigem sessão (pré-login e logging de dev). */
    private const PUBLIC_ACTIONS = [
        'initEnvironment', 'initGame', 'loginUser', 'autoLoginUser', 'gameReportError', 'registerUser',
        'registerUserSSO',
    ];

    /**
     * Valida a sessão do payload. Lança GameError se um usuário real do banco
     * for acessado com sessão errada. Silenciosa (no-op) nos casos dev acima.
     * OBS: `autoLoginUser` está na allowlist porque é ele quem ESTABELECE a sessão
     * do boot; a validação dele é feita explicitamente dentro do handler.
     */
    public static function guard(string $action, array $params): void
    {
        if (!self::strict() || in_array($action, self::PUBLIC_ACTIONS, true)) {
            return;
        }
        self::assertMatches($params);
    }

    /** Conta convidada compartilhada (fallback do index.html): sessão fixa, nunca rotaciona. */
    public const GUEST_USER_ID = 25328;

    /**
     * Rotaciona a sessão no login (comportamento do jogo oficial): emite uma
     * sessão nova e INVALIDA a anterior (caches limpos) -> qualquer outro
     * navegador logado na conta cai com errLoginInvalidSession no próximo poll.
     * No-op para o convidado e para contas sem sessão gravada (templates/NPC).
     * @return string|null a nova sessão, ou null se não rotacionou.
     */
    public static function rotate(int $userId): ?string
    {
        if ($userId <= 0 || $userId === self::GUEST_USER_ID) {
            return null;
        }
        $current = (string)(Db::value('SELECT session_id FROM `user` WHERE id = ?', [$userId]) ?? '');
        if ($current === '') {
            return null;
        }
        $new = substr(bin2hex(random_bytes(16)), 0, 30);
        Db::exec(
            "UPDATE `user`
                SET session_id = ?, session_id_cache1 = '', session_id_cache2 = '',
                    session_id_cache3 = '', session_id_cache4 = '', session_id_cache5 = ''
              WHERE id = ?",
            [$new, $userId]
        );
        return $new;
    }

    /**
     * Confere user_session_id contra user.session_id do banco.
     * @return int userId validado (0 se não havia nada a validar).
     */
    public static function assertMatches(array $params): int
    {
        // No autoLogin o cliente manda user_id="0" com a identidade real em
        // existing_user_id — "0" presente NAO pode encobrir o existing_*.
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            $userId = (int)($params['existing_user_id'] ?? 0);
        }
        if ($userId <= 0) {
            return 0;
        }

        $row = Db::row(
            'SELECT session_id, session_id_cache1, session_id_cache2, session_id_cache3, session_id_cache4, session_id_cache5 FROM `user` WHERE id = ?',
            [$userId]
        );
        if ($row === null || (string)($row['session_id'] ?? '') === '') {
            return 0;
        }

        $sent = isset($params['existing_user_id'])
            ? (string)($params['existing_session_id'] ?? '')
            : (string)($params['user_session_id'] ?? '');
        foreach ($row as $stored) {
            if ((string)$stored !== '' && hash_equals((string)$stored, $sent)) {
                return $userId;
            }
        }

        throw new GameError('errLoginInvalidSession');
    }
}
