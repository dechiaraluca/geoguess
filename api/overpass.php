<?php

/**
 * Récupère les villes d'un pays via l'API Overpass
 * @param int $searchArea - L'ID de zone calculé depuis osm_id (3600000000 + osm_id)
 * @param int $limit - Nombre maximum de villes à récupérer
 * @return array - Liste des villes ou erreur
 */
function getCitiesFromOverpass($searchArea, $limit = 50) {
    $query = "[out:json][timeout:60];
    area({$searchArea})->.searchArea;
    (
        node[\"place\"=\"city\"](area.searchArea);
        node[\"place\"=\"town\"](area.searchArea);
    );
    out body {$limit};";

    $servers = [
        "https://overpass-api.de/api/interpreter",
        "https://overpass.kumi.systems/api/interpreter"
    ];

    foreach ($servers as $serverIndex => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GeoGuessrEducationalProject/1.0 (l.dechiara.dev@gmail.com)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 65);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        sleep(2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 504 || $httpCode === 429 || $response === false) {
            if ($serverIndex < count($servers) - 1) {
                sleep(3);
                continue;
            }
        }

        if ($response === false || $error) {
            return ['error' => 'Erreur cURL : ' . $error];
        }

        if ($httpCode === 429) {
            return ['error' => 'Rate limit dépassé. Attendez quelques secondes et réessayez.'];
        }

        if ($httpCode !== 200) {
            return ['error' => 'Code HTTP : ' . $httpCode];
        }

        $data = json_decode($response, true);

        if (!isset($data['elements']) || empty($data['elements'])) {
            return ['error' => 'Aucune ville trouvée'];
        }

        return $data['elements'];
    }

    return ['error' => 'Tous les serveurs Overpass ont échoué'];
}

/**
 * Sauvegarde les villes dans la base de données
 * @param array $cities - Liste des villes depuis Overpass
 * @param int $idCountry - ID du pays dans la BDD
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Statistiques de sauvegarde
 */
function saveCitiesToDatabase($cities, $idCountry, $pdo) {
    $saved = 0;
    $skipped = 0;

    try {
        foreach ($cities as $city) {
            $stmt = $pdo->prepare("SELECT id_city FROM cities WHERE osm_id = :osm_id");
            $stmt->execute(['osm_id' => $city['id']]);

            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }

            $cityName = isset($city['tags']['name']) ? $city['tags']['name'] : 'Unknown';

            $stmt = $pdo->prepare("
                INSERT INTO cities (name, osm_id, id_country)
                VALUES (:name, :osm_id, :id_country)
            ");

            $stmt->execute([
                'name' => $cityName,
                'osm_id' => $city['id'],
                'id_country' => $idCountry
            ]);

            $saved++;
        }

        return [
            'success' => true,
            'saved' => $saved,
            'skipped' => $skipped,
            'total' => count($cities)
        ];

    } catch (PDOException $e) {
        error_log("Erreur sauvegarde villes : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'saved' => $saved,
            'skipped' => $skipped
        ];
    }
}

/**
 * Fonction principale : récupère et sauvegarde les villes d'un pays
 * @param int $idCountry - ID du pays dans la BDD
 * @param PDO $pdo - Connexion à la base de données
 * @param int $limit - Nombre maximum de villes
 * @return array - Résultat de l'opération
 */
function fetchAndSaveCities($idCountry, $pdo, $limit = 50) {
    $stmt = $pdo->prepare("SELECT * FROM countries WHERE id_country = :id");
    $stmt->execute(['id' => $idCountry]);
    $country = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$country) {
        return [
            'success' => false,
            'error' => 'Pays non trouvé dans la base de données'
        ];
    }

    $cities = getCitiesFromOverpass($country['search_area'], $limit);

    if (isset($cities['error'])) {
        return [
            'success' => false,
            'error' => $cities['error']
        ];
    }

    $result = saveCitiesToDatabase($cities, $idCountry, $pdo);

    return array_merge($result, [
        'country' => $country['name']
    ]);
}
