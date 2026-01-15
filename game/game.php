<?php

/**
 * Démarre une nouvelle session de jeu
 * @param string $playerName - Nom du joueur
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Informations de la session
 */
function startGameSession($playerName, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id_player FROM players WHERE name = :name");
        $stmt->execute(['name' => $playerName]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$player) {
            $stmt = $pdo->prepare("INSERT INTO players (name) VALUES (:name)");
            $stmt->execute(['name' => $playerName]);
            $idPlayer = $pdo->lastInsertId();
        } else {
            $idPlayer = $player['id_player'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO game_sessions (id_player, lives_remaining, current_score, status)
            VALUES (:id_player, 5, 0, 'in_progress')
        ");
        $stmt->execute(['id_player' => $idPlayer]);
        $idSession = $pdo->lastInsertId();

        return [
            'success' => true,
            'session_id' => $idSession,
            'player_id' => $idPlayer,
            'player_name' => $playerName,
            'lives_remaining' => 5,
            'current_score' => 0
        ];

    } catch (PDOException $e) {
        error_log("Erreur démarrage session : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sélectionne une ville aléatoire avec des images
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Ville et ses images
 */
function getRandomCityWithImages($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.id_city, c.name as city_name,
                   co.id_country, co.name as country_name, co.code as country_code
            FROM cities c
            JOIN countries co ON c.id_country = co.id_country
            JOIN images i ON c.id_city = i.id_city AND i.is_valid = 1
            GROUP BY c.id_city
            HAVING COUNT(i.id_image) >= 3
            ORDER BY RAND()
            LIMIT 1
        ");

        $city = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$city) {
            return [
                'success' => false,
                'error' => 'Aucune ville avec images disponible'
            ];
        }

        $stmt = $pdo->prepare("
            SELECT id_image, url, title
            FROM images
            WHERE id_city = :id_city AND is_valid = 1
            LIMIT 6
        ");
        $stmt->execute(['id_city' => $city['id_city']]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'city' => [
                'id' => $city['id_city'],
                'name' => $city['city_name'],
                'country_id' => $city['id_country'],
                'country_name' => $city['country_name'],
                'country_code' => $city['country_code']
            ],
            'images' => $images
        ];

    } catch (PDOException $e) {
        error_log("Erreur sélection ville : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Valide la réponse du joueur
 * @param int $sessionId - ID de la session
 * @param int $cityId - ID de la ville
 * @param string $guessedCountry - Réponse du joueur
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Résultat de la validation
 */
function validateAnswer($sessionId, $cityId, $guessedCountry, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.name as city_name, co.id_country, co.name as country_name, co.code
            FROM cities c
            JOIN countries co ON c.id_country = co.id_country
            WHERE c.id_city = :city_id
        ");
        $stmt->execute(['city_id' => $cityId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$city) {
            return ['success' => false, 'error' => 'Ville introuvable'];
        }

        $isCorrect = (
            strcasecmp($guessedCountry, $city['country_name']) === 0 ||
            strcasecmp($guessedCountry, $city['code']) === 0
        );

        $stmt = $pdo->prepare("
            SELECT lives_remaining, current_score
            FROM game_sessions
            WHERE id_session = :id
        ");
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return ['success' => false, 'error' => 'Session introuvable'];
        }

        $newScore = $session['current_score'];
        $livesRemaining = $session['lives_remaining'];

        if ($isCorrect) {
            $newScore += 10;
        } else {
            $livesRemaining -= 1;
        }

        $stmt = $pdo->prepare("
            INSERT INTO answers (id_session, id_city, guessed_country, is_correct)
            VALUES (:session_id, :city_id, :guessed, :correct)
        ");
        $stmt->execute([
            'session_id' => $sessionId,
            'city_id' => $cityId,
            'guessed' => $guessedCountry,
            'correct' => $isCorrect ? 1 : 0
        ]);

        $gameStatus = $livesRemaining <= 0 ? 'completed' : 'in_progress';

        if ($gameStatus === 'completed') {
            $stmt = $pdo->prepare("
                UPDATE game_sessions
                SET current_score = :score,
                    lives_remaining = :lives,
                    status = :status,
                    ended_at = NOW()
                WHERE id_session = :id
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE game_sessions
                SET current_score = :score,
                    lives_remaining = :lives,
                    status = :status
                WHERE id_session = :id
            ");
        }

        $stmt->execute([
            'score' => $newScore,
            'lives' => $livesRemaining,
            'status' => $gameStatus,
            'id' => $sessionId
        ]);

        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_country' => $city['country_name'],
            'correct_code' => $city['code'],
            'city_name' => $city['city_name'],
            'new_score' => $newScore,
            'lives_remaining' => $livesRemaining,
            'game_over' => $livesRemaining <= 0
        ];

    } catch (PDOException $e) {
        error_log("Erreur validation réponse : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Récupère l'état actuel de la session
 * @param int $sessionId - ID de la session
 * @param PDO $pdo - Connexion à la base de données
 * @return array - État de la session
 */
function getSessionState($sessionId, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT gs.*, p.name as player_name
            FROM game_sessions gs
            JOIN players p ON gs.id_player = p.id_player
            WHERE gs.id_session = :id
        ");
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return ['success' => false, 'error' => 'Session introuvable'];
        }

        return [
            'success' => true,
            'session' => $session
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Récupère une liste de pays pour le choix multiple
 * @param int $correctCountryId - ID du pays correct
 * @param PDO $pdo - Connexion à la base de données
 * @param int $count - Nombre total de choix (incluant le bon)
 * @return array - Liste des pays
 */
function getCountryChoices($correctCountryId, $pdo, $count = 4) {
    try {
        $stmt = $pdo->prepare("SELECT id_country, name, code FROM countries WHERE id_country = :id");
        $stmt->execute(['id' => $correctCountryId]);
        $correctCountry = $stmt->fetch(PDO::FETCH_ASSOC);

        $limit = (int)($count - 1);
        $stmt = $pdo->prepare("
            SELECT id_country, name, code
            FROM countries
            WHERE id_country != :id
            ORDER BY RAND()
            LIMIT {$limit}
        ");
        $stmt->execute([
            'id' => $correctCountryId
        ]);
        $otherCountries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $choices = array_merge([$correctCountry], $otherCountries);
        shuffle($choices);

        return [
            'success' => true,
            'choices' => $choices
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}