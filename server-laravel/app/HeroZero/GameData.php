<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Fábricas dos data-objects que o cliente espera nas respostas.
 * Chaves em snake_case, exatamente como os getters do cliente as leem
 * (ver docs/PROTOCOL.md e docs/DOCharacter.fields.txt, build 252).
 */
final class GameData
{
    /**
     * Objeto `user` do envelope de loginUser/autoLoginUser (DOUser + wrapper User).
     * Lido por rf.refreshUser: id, session_id, email são persistidos em cache.
     */
    public static function user(int $userId, string $sessionId, string $email, string $locale = 'pt_BR'): array
    {
        return [
            'id'                       => $userId,
            'session_id'               => $sessionId,
            'email'                    => $email,
            'network'                  => '',
            'locale'                   => $locale,
            'registration_source'      => 'email',
            'ts_creation'              => time(),
            'confirmed'                => true,
            'premium_currency'         => 0,
            'blocked_premium_currency' => 0,
        ];
    }

    /**
     * Esqueleto de `character` (DOCharacter, 103 campos) para o initGame.
     * Valores default de um herói nível 1 recém-criado. Preencher via banco/RE.
     */
    public static function character(int $characterId, int $userId, string $name, string $gender = 'male'): array
    {
        return [
            'id'                          => $characterId,
            'user_id'                     => $userId,
            'name'                        => $name,
            'gender'                      => $gender,
            'title'                       => '',
            'game_currency'               => 0,
            'xp'                          => 0,
            'level'                       => 1,
            'description'                 => '',
            'note'                        => '',
            'online_status'               => 1,

            // atributos
            'stat_base_stamina'           => 10,
            'stat_base_strength'          => 10,
            'stat_base_critical_rating'   => 0,
            'stat_base_dodge_rating'      => 0,
            'stat_total_stamina'          => 10,
            'stat_total_strength'         => 10,
            'stat_total_critical_rating'  => 0,
            'stat_weapon_damage'          => 1,
            'stat_bought_stamina'         => 0,
            'stat_bought_critical_rating' => 0,
            'stat_bought_dodge_rating'    => 0,

            // missões
            'max_quest_stage'             => 1,
            'current_quest_stage'         => 1,
            'quest_energy'                => 100,
            'max_quest_energy'            => 100,
            'active_quest_id'             => 0,
            'quest_pool'                  => '',

            // duelos
            'honor'                       => 0,
            'active_duel_id'              => 0,
            'duel_stamina'                => 10,
            'max_duel_stamina'            => 10,
            'duel_stamina_cost'           => 1,

            // treino
            'active_training_id'          => 0,
            'training_energy'             => 100,
            'max_training_energy'         => 100,
            'training_count'              => 0,
            'max_training_count'          => 3,

            // guilda / liga
            'guild_id'                    => 0,
            'league_points'               => 0,
            'league_stamina'              => 10,
            'max_league_stamina'          => 10,

            // aparência / tutorial
            'show_mask'                   => true,
            'show_cape'                   => true,
            'tutorial_flags'              => '',
        ];
    }
}
