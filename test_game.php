<?php
/**
 * Script de test pour la logique du jeu
 * À exécuter via navigateur : http://localhost/projets/geoguess/test_game.php
 */

require_once 'config/database.php';
require_once 'game/game.php';
require_once 'game/score.php';

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur : Impossible de se connecter à la base de données\n");
}

echo "=== Test de la logique du jeu GeoGuessr ===\n\n";

// Étape 1: Démarrer une session de jeu
echo "--- Étape 1: Démarrage d'une session ---\n";
$playerName = "TestPlayer_" . rand(1000, 9999);
$sessionResult = startGameSession($playerName, $pdo);

if (!$sessionResult['success']) {
    die("✗ Erreur démarrage session : " . $sessionResult['error'] . "\n");
}

echo "✓ Session créée\n";
echo "Joueur : " . $sessionResult['player_name'] . "\n";
echo "Session ID : " . $sessionResult['session_id'] . "\n";
echo "Vies : " . $sessionResult['lives_remaining'] . "\n";
echo "Score : " . $sessionResult['current_score'] . "\n\n";

$sessionId = $sessionResult['session_id'];
$playerId = $sessionResult['player_id'];

// Étape 2: Simuler quelques questions
echo "--- Étape 2: Simulation de 5 questions ---\n\n";

for ($i = 1; $i <= 5; $i++) {
    echo "Question $i:\n";

    // Récupérer une ville aléatoire
    $cityResult = getRandomCityWithImages($pdo);

    if (!$cityResult['success']) {
        echo "✗ Erreur : " . $cityResult['error'] . "\n";
        echo "Assurez-vous d'avoir des villes avec images en base (lancez test_wikipedia.php)\n\n";
        break;
    }

    $city = $cityResult['city'];
    $images = $cityResult['images'];

    echo "Ville : " . $city['name'] . "\n";
    echo "Pays correct : " . $city['country_name'] . " (" . $city['country_code'] . ")\n";
    echo "Nombre d'images : " . count($images) . "\n";

    // Récupérer les choix multiples
    $choicesResult = getCountryChoices($city['country_id'], $pdo, 4);

    if ($choicesResult['success']) {
        echo "Choix proposés : ";
        $choiceNames = array_map(function($c) { return $c['name']; }, $choicesResult['choices']);
        echo implode(", ", $choiceNames) . "\n";
    }

    // Simuler une réponse (alternance bonne/mauvaise pour tester)
    if ($i % 2 == 1) {
        // Bonne réponse
        $guess = $city['country_name'];
        echo "Réponse du joueur : " . $guess . " (CORRECT)\n";
    } else {
        // Mauvaise réponse (prendre un autre pays)
        if ($choicesResult['success'] && count($choicesResult['choices']) > 1) {
            $wrongChoice = array_values(array_filter($choicesResult['choices'], function($c) use ($city) {
                return $c['id_country'] != $city['country_id'];
            }))[0];
            $guess = $wrongChoice['name'];
        } else {
            $guess = "WrongCountry";
        }
        echo "Réponse du joueur : " . $guess . " (INCORRECT)\n";
    }

    // Valider la réponse
    $validationResult = validateAnswer($sessionId, $city['id'], $guess, $pdo);

    if ($validationResult['success']) {
        echo "Résultat : " . ($validationResult['is_correct'] ? "✓ CORRECT" : "✗ INCORRECT") . "\n";
        echo "Score actuel : " . $validationResult['new_score'] . "\n";
        echo "Vies restantes : " . $validationResult['lives_remaining'] . "\n";

        if ($validationResult['game_over']) {
            echo "\n⚠️ GAME OVER - Plus de vies!\n";
            break;
        }
    } else {
        echo "✗ Erreur validation : " . $validationResult['error'] . "\n";
    }

    echo "\n";
}

// Étape 3: Vérifier l'état de la session
echo "--- Étape 3: État final de la session ---\n";
$stateResult = getSessionState($sessionId, $pdo);

if ($stateResult['success']) {
    $session = $stateResult['session'];
    echo "Joueur : " . $session['player_name'] . "\n";
    echo "Score final : " . $session['current_score'] . "\n";
    echo "Vies restantes : " . $session['lives_remaining'] . "\n";
    echo "Statut : " . $session['status'] . "\n";
} else {
    echo "✗ Erreur : " . $stateResult['error'] . "\n";
}
echo "\n";

// Étape 4: Enregistrer le score si la partie est terminée
if ($session['status'] === 'completed') {
    echo "--- Étape 4: Enregistrement du score ---\n";
    $scoreResult = saveFinalScore($sessionId, $pdo);

    if ($scoreResult['success']) {
        echo "✓ Score enregistré\n";
        echo "Score final : " . $scoreResult['final_score'] . "\n";
        echo "Questions totales : " . $scoreResult['total_questions'] . "\n";
        echo "Réponses correctes : " . $scoreResult['correct_answers'] . "\n";
        echo "Vies utilisées : " . $scoreResult['lives_used'] . "\n";
    } else {
        echo "✗ Erreur : " . $scoreResult['error'] . "\n";
    }
    echo "\n";
}

// Étape 5: Afficher le classement
echo "--- Étape 5: Classement (Top 10) ---\n";
$leaderboardResult = getLeaderboard($pdo, 10);

if ($leaderboardResult['success']) {
    $leaderboard = $leaderboardResult['leaderboard'];

    if (empty($leaderboard)) {
        echo "Aucun score enregistré\n";
    } else {
        echo sprintf("%-20s | %-10s | %-15s | %s\n", "Joueur", "Score", "Précision", "Date");
        echo str_repeat("-", 80) . "\n";

        foreach ($leaderboard as $index => $score) {
            $accuracy = $score['total_questions'] > 0
                ? round(($score['correct_answers'] / $score['total_questions']) * 100)
                : 0;

            echo sprintf(
                "%-20s | %-10d | %d/%d (%d%%)    | %s\n",
                $score['player_name'],
                $score['final_score'],
                $score['correct_answers'],
                $score['total_questions'],
                $accuracy,
                date('Y-m-d H:i', strtotime($score['created_at']))
            );
        }
    }
} else {
    echo "✗ Erreur : " . $leaderboardResult['error'] . "\n";
}
echo "\n";

// Étape 6: Statistiques du joueur
echo "--- Étape 6: Statistiques du joueur ---\n";
$statsResult = getPlayerStats($playerId, $pdo);

if ($statsResult['success']) {
    echo "Joueur : " . $statsResult['player_name'] . "\n";
    echo "Total de parties : " . $statsResult['stats']['total_games'] . "\n";
    echo "Meilleur score : " . $statsResult['stats']['best_score'] . "\n";
    echo "Score moyen : " . $statsResult['stats']['avg_score'] . "\n";
    echo "Précision globale : " . $statsResult['stats']['accuracy'] . "%\n";

    if (!empty($statsResult['recent_games'])) {
        echo "\nDernières parties :\n";
        foreach ($statsResult['recent_games'] as $game) {
            echo sprintf(
                "  - Score: %d | Précision: %d/%d | Date: %s\n",
                $game['final_score'],
                $game['correct_answers'],
                $game['total_questions'],
                date('Y-m-d H:i', strtotime($game['created_at']))
            );
        }
    }
} else {
    echo "✗ Erreur : " . $statsResult['error'] . "\n";
}

echo "\n✓ Tests terminés\n";
