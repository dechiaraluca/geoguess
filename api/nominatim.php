<?php

function searchCountry($countryName) {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        'q' => $countryName,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 1
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($ch, CURLOPT_USERAGENT, 'test geoguess');
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    sleep(1);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $error) {
        return ['error' => 'Erreur cURL : ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'Code HTTP : ' . $httpCode . ' - Vérifier le User-Agent et respecter les limites de l\'API'];
    }
    
    $data = json_decode($response, true);

    if (empty($data)) {
        return ['error' => 'Pays non trouvé'];
    }

    return $data[0];
}

function calculateSearchArea($osmId, $osmType) {
    if ($osmType === 'relation') {
        return 3600000000 + $osmId;
    }
    return $osmId;
}

function saveCountryToDatabase($countryData, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id_country FROM countries WHERE osm_id = :osm_id");
        $stmt->execute(['osm_id' => $countryData['osm_id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing['id_country'];
        }

        $countryCode = isset($countryData['address']['country_code'])
            ? strtoupper($countryData['address']['country_code'])
            : 'XX';

        $searchArea = calculateSearchArea($countryData['osm_id'], $countryData['osm_type']);

        $stmt = $pdo->prepare("
            INSERT INTO countries (name, code, osm_id, search_area)
            VALUES (:name, :code, :osm_id, :search_area)
        ");

        $stmt->execute([
            'name' => $countryData['display_name'],
            'code' => $countryCode,
            'osm_id' => $countryData['osm_id'],
            'search_area' => $searchArea
        ]);

        return $pdo->lastInsertId();

    } catch (PDOException $e) {
        error_log("Erreur sauvegarde pays : " . $e->getMessage());
        return false;
    }
}

function getCountryFromDatabase($searchTerm, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM countries
            WHERE name LIKE :search OR code = :code
            LIMIT 1
        ");

        $stmt->execute([
            'search' => '%' . $searchTerm . '%',
            'code' => strtoupper($searchTerm)
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erreur récupération pays : " . $e->getMessage());
        return false;
    }
}

function findOrCreateCountry($countryName, $pdo) {
    $existingCountry = getCountryFromDatabase($countryName, $pdo);

    if ($existingCountry) {
        return [
            'success' => true,
            'source' => 'database',
            'data' => $existingCountry
        ];
    }

    $nominatimResult = searchCountry($countryName);

    if (isset($nominatimResult['error'])) {
        return [
            'success' => false,
            'error' => $nominatimResult['error']
        ];
    }

    $idCountry = saveCountryToDatabase($nominatimResult, $pdo);

    if (!$idCountry) {
        return [
            'success' => false,
            'error' => 'Erreur lors de la sauvegarde en base de données'
        ];
    }

    return [
        'success' => true,
        'source' => 'nominatim',
        'data' => [
            'id_country' => $idCountry,
            'name' => $nominatimResult['display_name'],
            'code' => isset($nominatimResult['address']['country_code'])
                ? strtoupper($nominatimResult['address']['country_code'])
                : 'XX',
            'osm_id' => $nominatimResult['osm_id'],
            'search_area' => calculateSearchArea($nominatimResult['osm_id'], $nominatimResult['osm_type'])
        ]
    ];
}