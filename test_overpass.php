<?php
/**
 * Script de test pour l'API Overpass
 * À exécuter via navigateur : http://localhost/projets/geoguess/test_overpass.php
 */

require_once 'config/database.php';
require_once 'api/nominatim.php';
require_once 'api/overpass.php';

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur : Impossible de se connecter à la base de données\n");
}

echo "=== Test de l'API Overpass ===\n\n";

// D'abord, s'assurer qu'on a un pays en base
echo "--- Préparation : Recherche du Portugal ---\n";
$countryResult = findOrCreateCountry('Portugal', $pdo);

if (!$countryResult['success']) {
    die("Erreur : Impossible de récupérer le pays\n");
}

$idCountry = $countryResult['data']['id_country'];
echo "✓ Pays trouvé : " . $countryResult['data']['name'] . " (ID: $idCountry)\n";
echo "Search Area : " . $countryResult['data']['search_area'] . "\n\n";

// Tester la récupération des villes
echo "--- Récupération des villes du Portugal ---\n";
echo "Ceci peut prendre 10-15 secondes...\n\n";

$result = fetchAndSaveCities($idCountry, $pdo, 30);

if ($result['success']) {
    echo "✓ Succès\n";
    echo "Pays : " . $result['country'] . "\n";
    echo "Villes récupérées : " . $result['total'] . "\n";
    echo "Nouvelles villes sauvegardées : " . $result['saved'] . "\n";
    echo "Villes déjà existantes : " . $result['skipped'] . "\n";
} else {
    echo "✗ Erreur : " . $result['error'] . "\n";
}

echo "\n=== Villes dans la base de données ===\n";
$stmt = $pdo->prepare("
    SELECT c.name as city_name, co.name as country_name, c.osm_id
    FROM cities c
    JOIN countries co ON c.id_country = co.id_country
    ORDER BY c.id_city
    LIMIT 20
");
$stmt->execute();
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cities)) {
    echo "Aucune ville dans la base de données\n";
} else {
    echo "Top 20 villes :\n";
    foreach ($cities as $city) {
        echo sprintf(
            "- %s (%s) | OSM ID: %s\n",
            $city['city_name'],
            $city['country_name'],
            $city['osm_id']
        );
    }
}

// Statistiques
echo "\n=== Statistiques ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cities");
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total de villes en base : " . $total['total'] . "\n";

echo "\n✓ Tests terminés\n";
