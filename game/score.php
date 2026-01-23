<?php

/**
 * Enregistre le score final d'une session
 * @param int $sessionId - ID de la session
 * @param PDO $pdo - Connexion à la base de données
 * @param int|null $finalScore - Score final (optionnel, calculé côté client avec bonus)
 * @param int $bestStreak - Meilleure série de bonnes réponses
 * @return array - Résultat de l'enregistrement
 */
function saveFinalScore($sessionId, $pdo, $finalScore = null, $bestStreak = 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_player, current_score, lives_remaining, status
            FROM game_sessions
            WHERE id_session = :id
        ");
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return ['success' => false, 'error' => 'Session introuvable'];
        }

        if ($session['status'] !== 'completed') {
            $stmt = $pdo->prepare("
                UPDATE game_sessions
                SET status = 'completed', ended_at = NOW()
                WHERE id_session = :id
            ");
            $stmt->execute(['id' => $sessionId]);
        }

        $stmt = $pdo->prepare("SELECT id_score FROM scores WHERE id_session = :id");
        $stmt->execute(['id' => $sessionId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Score déjà enregistré'];
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_questions,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
            FROM answers
            WHERE id_session = :id
        ");
        $stmt->execute(['id' => $sessionId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $livesUsed = 5 - $session['lives_remaining'];

        $scoreToSave = $finalScore !== null ? $finalScore : $session['current_score'];

        $stmt = $pdo->prepare("
            INSERT INTO scores (
                id_player, id_session, final_score,
                total_questions, correct_answers, lives_used
            ) VALUES (
                :player, :session, :score,
                :total, :correct, :lives
            )
        ");

        $stmt->execute([
            'player' => $session['id_player'],
            'session' => $sessionId,
            'score' => $scoreToSave,
            'total' => $stats['total_questions'],
            'correct' => $stats['correct_answers'],
            'lives' => $livesUsed
        ]);

        return [
            'success' => true,
            'score_id' => $pdo->lastInsertId(),
            'final_score' => $scoreToSave,
            'total_questions' => $stats['total_questions'],
            'correct_answers' => $stats['correct_answers'],
            'lives_used' => $livesUsed,
            'best_streak' => $bestStreak
        ];

    } catch (PDOException $e) {
        error_log("Erreur sauvegarde score : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Récupère le classement des meilleurs scores
 * @param PDO $pdo - Connexion à la base de données
 * @param int $limit - Nombre de résultats
 * @return array - Classement
 */
function getLeaderboard($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                s.final_score,
                s.correct_answers,
                s.total_questions,
                s.created_at,
                p.name as player_name
            FROM scores s
            JOIN players p ON s.id_player = p.id_player
            ORDER BY s.final_score DESC, s.created_at ASC
            LIMIT :limit
        ");
        $stmt->execute(['limit' => $limit]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'leaderboard' => $scores
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Récupère les statistiques d'un joueur
 * @param int $playerId - ID du joueur
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Statistiques du joueur
 */
function getPlayerStats($playerId, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM players WHERE id_player = :id");
        $stmt->execute(['id' => $playerId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$player) {
            return ['success' => false, 'error' => 'Joueur introuvable'];
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_games,
                MAX(final_score) as best_score,
                AVG(final_score) as avg_score,
                SUM(correct_answers) as total_correct,
                SUM(total_questions) as total_questions
            FROM scores
            WHERE id_player = :id
        ");
        $stmt->execute(['id' => $playerId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT final_score, correct_answers, total_questions, created_at
            FROM scores
            WHERE id_player = :id
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute(['id' => $playerId]);
        $recentGames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'player_name' => $player['name'],
            'stats' => [
                'total_games' => $stats['total_games'] ?? 0,
                'best_score' => $stats['best_score'] ?? 0,
                'avg_score' => round($stats['avg_score'] ?? 0, 2),
                'total_correct' => $stats['total_correct'] ?? 0,
                'total_questions' => $stats['total_questions'] ?? 0,
                'accuracy' => $stats['total_questions'] > 0
                    ? round(($stats['total_correct'] / $stats['total_questions']) * 100, 2)
                    : 0
            ],
            'recent_games' => $recentGames
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
