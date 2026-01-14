<?php

require_once 'config/database.php';
require_once 'api/nominatim.php';
require_once 'api/overpass.php';
require_once 'api/wikipedia.php';

$countries = [
    'France',
    'Italy',
    'Spain',
    'Germany',
    'United Kingdom',
    'Japan',
    'United States',
    'Canada'
];

echo "=== Peuplement de la base de données GeoGuess ===\n\n";

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur : Impossible de se connecter à la base de données\n");
}

$stats = [
    'countries' => 0,
    'cities' => 0,
    'images' => 0,
    'errors' => []
];

foreach ($countries as $countryName) {
    echo "Traitement de : $countryName\n";
    echo str_repeat('-', 50) . "\n";

    echo "  1. Recherche du pays...\n";
    $countryResult = findOrCreateCountry($countryName, $pdo);

    if (!$countryResult['success']) {
        echo "  Erreur : " . $countryResult['error'] . "\n\n";
        $stats['errors'][] = "$countryName (pays): " . $countryResult['error'];
        continue;
    }

    $country = $countryResult['data'];
    echo "  ✅ Pays : {$country['name']} (ID: {$country['id_country']})\n";
    $stats['countries']++;

    echo "  2. Récupération des villes...\n";
    $citiesResult = fetchAndSaveCities($country['id_country'], $pdo, 10);

    if (!$citiesResult['success']) {
        echo "  Erreur villes : " . $citiesResult['error'] . "\n\n";
        $stats['errors'][] = "$countryName (villes): " . $citiesResult['error'];
        continue;
    }

    echo "  ✅ Villes : {$citiesResult['saved']} nouvelles, {$citiesResult['skipped']} existantes\n";
    $stats['cities'] += $citiesResult['saved'];

    echo "  3. Récupération des images...\n";

    $stmt = $pdo->prepare("
        SELECT id_city, name
        FROM cities
        WHERE id_country = :id_country
        LIMIT 10
    ");
    $stmt->execute(['id_country' => $country['id_country']]);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cities as $city) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM images WHERE id_city = :id_city");
        $stmt->execute(['id_city' => $city['id_city']]);
        $imageCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($imageCount >= 5) {
            echo "    - {$city['name']} : déjà {$imageCount} images\n";
            continue;
        }

        echo "    - {$city['name']} : recherche images...\n";
        $imagesResult = fetchAndSaveImages($city['id_city'], $pdo);

        if (!$imagesResult['success']) {
            echo "      ⚠️  Aucune image trouvée\n";
            $stats['errors'][] = "{$city['name']} (images): " . $imagesResult['error'];
            continue;
        }

        echo "      ✅ {$imagesResult['saved']} images sauvegardées\n";
        $stats['images'] += $imagesResult['saved'];

        sleep(1);
    }

    echo "\n";

    echo "  Pause de 3 secondes...\n\n";
    sleep(3);
}

echo "\n=== Résumé ===\n";
echo "Pays ajoutés : {$stats['countries']}\n";
echo "Villes ajoutées : {$stats['cities']}\n";
echo "Images ajoutées : {$stats['images']}\n";

if (!empty($stats['errors'])) {
    echo "\nErreurs rencontrées :\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n=== Vérification finale ===\n";
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT c.id_city) as count
    FROM cities c
    JOIN images i ON c.id_city = i.id_city
    WHERE i.is_valid = 1
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Villes avec images disponibles : {$result['count']}\n";

if ($result['count'] > 0) {
    echo "\n La base de données est prête ! Vous pouvez maintenant jouer.\n";
} else {
    echo "\n  Aucune ville avec images. Réessayez le script ou ajustez les paramètres.\n";
}
