<?php
/**
 * Script de test pour l'API Nominatim
 * À exécuter via : php test_nominatim.php
 * Ou via navigateur : http://localhost/projets/geoguess/test_nominatim.php
 */

require_once 'config/database.php';
require_once 'api/nominatim.php';

// Connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur : Impossible de se connecter à la base de données\n");
}

echo "=== Test de l'API Nominatim ===\n\n";

// Liste de pays à tester
$testCountries = ['France', 'Portugal', 'Japan', 'Canada'];

foreach ($testCountries as $countryName) {
    echo "--- Test : $countryName ---\n";

    $result = findOrCreateCountry($countryName, $pdo);

    if ($result['success']) {
        echo "✓ Succès\n";
        echo "Source : " . $result['source'] . "\n";
        echo "ID : " . $result['data']['id_country'] . "\n";
        echo "Nom : " . $result['data']['name'] . "\n";
        echo "Code : " . $result['data']['code'] . "\n";
        echo "OSM ID : " . $result['data']['osm_id'] . "\n";
        echo "Search Area : " . $result['data']['search_area'] . "\n";
    } else {
        echo "✗ Erreur : " . $result['error'] . "\n";
    }

    echo "\n";

    // Pause pour respecter le rate limiting (1 seconde entre chaque requête)
    if ($result['source'] === 'nominatim') {
        sleep(1);
    }
}

// Vérifier les pays dans la base de données
echo "=== Pays dans la base de données ===\n";
$stmt = $pdo->query("SELECT * FROM countries ORDER BY id_country");
$countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($countries)) {
    echo "Aucun pays dans la base de données\n";
} else {
    foreach ($countries as $country) {
        echo sprintf(
            "ID: %d | Code: %s | Nom: %s | Search Area: %s\n",
            $country['id_country'],
            $country['code'],
            $country['name'],
            $country['search_area']
        );
    }
}

echo "\n✓ Tests terminés\n";
