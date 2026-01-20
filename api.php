<?php

header('Content-Type: application/json');

require_once 'config/database.php';
require_once 'game/game.php';
require_once 'game/score.php';

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

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

function handleStartSession($input, $pdo) {
    $playerName = $input['player_name'] ?? '';

    if (empty($playerName)) {
        echo json_encode(['success' => false, 'error' => 'Nom du joueur requis']);
        return;
    }

    $result = startGameSession($playerName, $pdo);
    echo json_encode($result);
}

function handleGetQuestion($pdo) {
    $cityResult = getRandomCityWithImages($pdo);

    if (!$cityResult['success']) {
        echo json_encode($cityResult);
        return;
    }

    $city = $cityResult['city'];
    $images = $cityResult['images'];
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

function handleSaveScore($input, $pdo) {
    $sessionId = $input['session_id'] ?? 0;

    if (empty($sessionId)) {
        echo json_encode(['success' => false, 'error' => 'Session ID requis']);
        return;
    }

    $result = saveFinalScore($sessionId, $pdo);
    echo json_encode($result);
}

function handleGetLeaderboard($pdo) {
    $result = getLeaderboard($pdo, 10);
    echo json_encode($result);
}
