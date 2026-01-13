<?php
/**
 * Script de test pour l'API Wikipedia
 * À exécuter via navigateur : http://localhost/projets/geoguess/test_wikipedia.php
 */

require_once 'config/database.php';
require_once 'api/wikipedia.php';

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur : Impossible de se connecter à la base de données\n");
}

echo "=== Test de l'API Wikipedia ===\n\n";

// Récupérer quelques villes de la base
echo "--- Récupération des villes disponibles ---\n";
$stmt = $pdo->query("
    SELECT c.id_city, c.name as city_name, co.name as country_name
    FROM cities c
    JOIN countries co ON c.id_country = co.id_country
    LIMIT 5
");
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cities)) {
    die("Aucune ville en base. Lancez d'abord test_overpass.php\n");
}

echo "Villes trouvées :\n";
foreach ($cities as $city) {
    echo "- " . $city['city_name'] . " (" . $city['country_name'] . ")\n";
}
echo "\n";

// Tester avec la première ville
$testCity = $cities[0];
echo "--- Test avec : " . $testCity['city_name'] . " ---\n";
echo "Recherche d'images... (peut prendre 5-10 secondes)\n\n";

$result = fetchAndSaveImages($testCity['id_city'], $pdo);

if ($result['success']) {
    echo "✓ Succès\n";
    echo "Ville : " . $result['city'] . " (" . $result['country'] . ")\n";
    echo "Images récupérées : " . $result['total'] . "\n";
    echo "Nouvelles images sauvegardées : " . $result['saved'] . "\n";
    echo "Images déjà existantes : " . $result['skipped'] . "\n";
} else {
    echo "✗ Erreur : " . $result['error'] . "\n";
    echo "Ville : " . $result['city'] . "\n";
}

echo "\n=== Images dans la base de données ===\n";
$stmt = $pdo->query("
    SELECT i.title, i.url, c.name as city_name
    FROM images i
    JOIN cities c ON i.id_city = c.id_city
    WHERE i.is_valid = 1
    LIMIT 10
");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($images)) {
    echo "Aucune image dans la base de données\n";
} else {
    echo "Top 10 images :\n";
    foreach ($images as $image) {
        echo sprintf(
            "- %s | Ville: %s\n  URL: %s\n",
            $image['title'],
            $image['city_name'],
            substr($image['url'], 0, 80) . "..."
        );
    }
}

// Statistiques
echo "\n=== Statistiques ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM images WHERE is_valid = 1");
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total d'images valides en base : " . $total['total'] . "\n";

$stmt = $pdo->query("
    SELECT c.name as city_name, COUNT(i.id_image) as image_count
    FROM cities c
    LEFT JOIN images i ON c.id_city = i.id_city AND i.is_valid = 1
    GROUP BY c.id_city, c.name
    HAVING image_count > 0
    ORDER BY image_count DESC
    LIMIT 5
");
$topCities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($topCities)) {
    echo "\nVilles avec le plus d'images :\n";
    foreach ($topCities as $city) {
        echo "- " . $city['city_name'] . " : " . $city['image_count'] . " images\n";
    }
}

echo "\n✓ Tests terminés\n";
