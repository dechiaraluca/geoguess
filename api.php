<?php
/**
 * API Endpoint pour le jeu GeoGuessr
 * Gère toutes les actions AJAX depuis le frontend
 */

header('Content-Type: application/json');

require_once 'config/database.php';
require_once 'game/game.php';
require_once 'game/score.php';

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Router les actions
switch ($action) {
    case 'start_session':
        handleStartSession($input, $pdo);
        break;

    case 'get_question':
        handleGetQuestion($pdo);
        break;

    case 'submit_answer':
        handleSubmitAnswer($input, $pdo);
        break;

    case 'save_score':
        handleSaveScore($input, $pdo);
        break;

    case 'get_leaderboard':
        handleGetLeaderboard($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action invalide']);
}

/**
 * Démarre une nouvelle session de jeu
 */
function handleStartSession($input, $pdo) {
    $playerName = $input['player_name'] ?? '';

    if (empty($playerName)) {
        echo json_encode(['success' => false, 'error' => 'Nom du joueur requis']);
        return;
    }

    $result = startGameSession($playerName, $pdo);
    echo json_encode($result);
}

/**
 * Récupère une question (ville + images + choix)
 */
function handleGetQuestion($pdo) {
    // Récupérer une ville aléatoire avec images
    $cityResult = getRandomCityWithImages($pdo);

    if (!$cityResult['success']) {
        echo json_encode($cityResult);
        return;
    }

    $city = $cityResult['city'];
    $images = $cityResult['images'];

    // Récupérer les choix multiples
    $choicesResult = getCountryChoices($city['country_id'], $pdo, 4);

    if (!$choicesResult['success']) {
        echo json_encode($choicesResult);
        return;
    }

    echo json_encode([
        'success' => true,
        'city' => $city,
        'images' => $images,
        'choices' => $choicesResult['choices']
    ]);
}

/**
 * Valide la réponse du joueur
 */
function handleSubmitAnswer($input, $pdo) {
    $sessionId = $input['session_id'] ?? 0;
    $cityId = $input['city_id'] ?? 0;
    $answer = $input['answer'] ?? '';

    if (empty($sessionId) || empty($cityId) || empty($answer)) {
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        return;
    }

    $result = validateAnswer($sessionId, $cityId, $answer, $pdo);
    echo json_encode($result);
}

/**
 * Sauvegarde le score final
 */
function handleSaveScore($input, $pdo) {
    $sessionId = $input['session_id'] ?? 0;

    if (empty($sessionId)) {
        echo json_encode(['success' => false, 'error' => 'Session ID requis']);
        return;
    }

    $result = saveFinalScore($sessionId, $pdo);
    echo json_encode($result);
}

/**
 * Récupère le classement
 */
function handleGetLeaderboard($pdo) {
    $result = getLeaderboard($pdo, 10);
    echo json_encode($result);
}
