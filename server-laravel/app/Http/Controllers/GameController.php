<?php

namespace App\Http\Controllers;

use HeroZero\GameError;
use HeroZero\Protocol;
use HeroZero\Response as GameResponse;
use HeroZero\Router;
use HeroZero\Session;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ponto de entrada do protocolo do jogo (port do server/request.php).
 *
 * Protocolo (docs/PROTOCOL.md): POST application/x-www-form-urlencoded com
 * action, user_id, user_session_id, auth = md5(action + SALT + user_id)...
 * Resposta: JSON { "data": {...}, "error": "" }.
 */
class GameController extends Controller
{
    public function request(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->withHeaders(response('', 204), $request);
        }

        $params = $request->post();
        $action = (string)($params['action'] ?? '');

        if ($action === '') {
            return $this->json(GameResponse::error('errRequestNoAction'), $request);
        }

        // DEV: captura o payload de gameReportError (o cliente reporta crashes aqui).
        if ($action === 'gameReportError' && !app()->isProduction()) {
            $safeParams = $params;
            unset($safeParams['auth'], $safeParams['user_session_id']);
            if (isset($safeParams['error'])) {
                $safeParams['error'] = substr((string)$safeParams['error'], 0, 20000);
            }
            file_put_contents(
                storage_path('logs/last_error.json'),
                json_encode($safeParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        }

        // DEV: log de toda action (menos polling) p/ diagnosticar fluxos sem captura.
        if (!app()->isProduction()
            && !in_array($action, ['syncOpticalChanges', 'getGuildLog', 'gameReportError'], true)) {
            error_log("[herozero] action={$action} user=" . ($params['user_id'] ?? '?') .
                ' params=' . json_encode(array_diff_key($params, array_flip(
                    ['action', 'user_id', 'user_session_id', 'auth', 'client_version', 'build_number', 'rct', 'keep_active', 'device_id', 'device_type']
                ))));
        }

        if (!Protocol::verifyAuth($params)) {
            error_log("[herozero] auth invalido para action={$action}");
            if (Protocol::strictAuth()) {
                return $this->json(GameResponse::error('errRequestInvalidAuth'), $request);
            }
        }

        try {
            Session::guard($action, $params);
            $data = Router::dispatch($action, $params);
            $body = GameResponse::ok($data);
        } catch (GameError $e) {
            $body = GameResponse::error($e->getMessage());
        } catch (\Throwable $e) {
            error_log("[herozero] excecao em {$action}: " . $e->getMessage());
            $body = GameResponse::error('response_errorIO');
        }
        return $this->json($body, $request);
    }

    /** Boot do cliente DESKTOP (Steam/NW.js) — port do server/steam.php. */
    public function steam(Request $request): Response
    {
        $origin = $request->getSchemeAndHttpHost();
        $appCDNUrl = (string)(getenv('HZ_CDN_URL') ?: 'https://hz-static-2.akamaized.net/');
        $uid = (string)$request->query('uid', '25328');
        $sid = (string)$request->query('sid', 'PrTvkhsQnOJamBJsB3IMMQnXI1JGkG');

        $clientVars = [
            'applicationTitle'     => 'Hero Zero (local/desktop)',
            'urlPublic'            => $origin . '/',
            'urlRequestServer'     => $origin . '/request.php',
            'urlSocketServer'      => '',
            'urlCDN'               => $appCDNUrl,
            'userId'               => $uid,
            'userSessionId'        => $sid,
            'testMode'             => 'false',
            'debugRunTests'        => 'false',
            'registrationSource'   => 'ref=;subid=;lp=;',
            'startupParams'        => '',
            'platform'             => 'standalone',
            'ssoInfo'              => '',
            'uniqueId'             => 'desktop' . time(),
            'server_id'            => 'local',
            'default_locale'       => 'pt_BR',
            'blockRegistration'    => 'false',
            'isFriendbarSupported' => 'false',
        ];

        return $this->json(json_encode([
            'scripts' => [
                $origin . '/js/jquery-3.3.1.min.js',
                $origin . '/js/standalone.js?v=local-desktop-1',
                $origin . '/js/HeroZero.min.js',
            ],
            'clientVars' => $clientVars,
            'urlCDN'     => $appCDNUrl,
            'root'       => rtrim($appCDNUrl, '/') . '/assets/html5',
            'clientName' => 'HeroZero.min',
        ], JSON_UNESCAPED_SLASHES), $request);
    }

    /** Endpoint de logs do cliente (dev) -> storage/logs/client.log. */
    public function clientlog(Request $request): Response
    {
        if (app()->isProduction()) {
            return response('Not Found', 404);
        }
        $body = (string)$request->getContent();
        if ($body !== '') {
            $body = substr($body, 0, 65536);
            $body = preg_replace('/("(?:user_session_id|auth)"\s*:\s*")[^"]+"/i', '$1[redacted]"', $body);
            file_put_contents(storage_path('logs/client.log'), $body . "\n", FILE_APPEND | LOCK_EX);
        }
        return response('', 204);
    }

    private function json(string $body, Request $request): Response
    {
        return $this->withHeaders(
            response($body, 200)->header('Content-Type', 'application/json; charset=utf-8'),
            $request
        );
    }

    private function withHeaders(Response $response, Request $request): Response
    {
        $allowedOrigin = (string)(getenv('HZ_ALLOWED_ORIGIN') ?: '');
        $requestOrigin = (string)$request->headers->get('Origin', '');
        if ($allowedOrigin !== '' && $requestOrigin !== '' && hash_equals($allowedOrigin, $requestOrigin)) {
            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
            $response->header('Vary', 'Origin');
        }
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Cache-Control', 'no-store');
        return $response;
    }
}
