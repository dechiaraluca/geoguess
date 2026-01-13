<?php

/**
 * Récupère les images d'une page Wikipedia
 * @param string $cityName - Nom de la ville
 * @param string $countryName - Nom du pays (pour gérer les homonymes)
 * @return array - Liste des URLs d'images ou erreur
 */
function getImagesFromWikipedia($cityName, $countryName = '') {
    $searchTerm = $cityName;
    if ($countryName) {
        $searchTerm .= ' ' . $countryName;
    }

    $pageId = searchWikipediaPage($searchTerm);

    if (isset($pageId['error'])) {
        return $pageId;
    }

    $images = fetchPageImages($pageId);

    if (isset($images['error'])) {
        return $images;
    }

    $filteredImages = filterImages($images);

    if (empty($filteredImages)) {
        return ['error' => 'Aucune image valide trouvée'];
    }

    return $filteredImages;
}

/**
 * Recherche une page Wikipedia et retourne son page ID
 * @param string $searchTerm - Terme de recherche
 * @return int|array - Page ID ou erreur
 */
function searchWikipediaPage($searchTerm) {
    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'list' => 'search',
        'srsearch' => $searchTerm,
        'srlimit' => 1
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error'])) {
        return $response;
    }

    if (empty($response['query']['search'])) {
        return ['error' => 'Page Wikipedia non trouvée'];
    }

    return $response['query']['search'][0]['pageid'];
}

/**
 * Récupère les images d'une page Wikipedia
 * @param int $pageId - ID de la page
 * @return array - Liste des images ou erreur
 */
function fetchPageImages($pageId) {
    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'pageids' => $pageId,
        'prop' => 'images',
        'imlimit' => 50
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error'])) {
        return $response;
    }

    if (!isset($response['query']['pages'][$pageId]['images'])) {
        return ['error' => 'Aucune image sur cette page'];
    }

    $imageNames = $response['query']['pages'][$pageId]['images'];

    $imageUrls = [];
    foreach ($imageNames as $image) {
        $url = getImageUrl($image['title']);
        if ($url && !isset($url['error'])) {
            $imageUrls[] = [
                'title' => $image['title'],
                'url' => $url
            ];
        }
    }

    return $imageUrls;
}

/**
 * Récupère l'URL complète d'une image depuis son titre
 * @param string $imageTitle - Titre de l'image (ex: "File:Image.jpg")
 * @return string|array - URL de l'image ou erreur
 */
function getImageUrl($imageTitle) {
    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'titles' => $imageTitle,
        'prop' => 'imageinfo',
        'iiprop' => 'url'
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error'])) {
        return $response;
    }

    $pages = $response['query']['pages'];
    $page = reset($pages);

    if (!isset($page['imageinfo'][0]['url'])) {
        return ['error' => 'URL image non trouvée'];
    }

    return $page['imageinfo'][0]['url'];
}

/**
 * Filtre les images pour exclure drapeaux, cartes, logos
 * @param array $images - Liste des images
 * @return array - Images filtrées
 */
function filterImages($images) {
    $excludeKeywords = [
        'flag', 'drapeau', 'coat_of_arms', 'blason',
        'map', 'carte', 'logo', 'locator',
        'seal', 'sceau', 'emblem', 'symbol',
        'diagram', 'diagramme', '.svg'
    ];

    $filtered = [];

    foreach ($images as $image) {
        $title = strtolower($image['title']);
        $isValid = true;

        foreach ($excludeKeywords as $keyword) {
            if (strpos($title, $keyword) !== false) {
                $isValid = false;
                break;
            }
        }

        if ($isValid) {
            $filtered[] = $image;
        }
    }

    return $filtered;
}

/**
 * Effectue une requête à l'API Wikipedia
 * @param string $url - URL de la requête
 * @return array - Réponse JSON décodée ou erreur
 */
function makeWikipediaRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GeoGuessrEducationalProject/1.0 (l.dechiara.dev@gmail.com)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Rate limiting
    usleep(100000); // 100ms entre chaque requête

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['error' => 'Erreur cURL'];
    }

    if ($httpCode !== 200) {
        return ['error' => 'Code HTTP : ' . $httpCode];
    }

    return json_decode($response, true);
}

/**
 * Sauvegarde les images dans la base de données
 * @param array $images - Liste des images
 * @param int $idCity - ID de la ville
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Statistiques de sauvegarde
 */
function saveImagesToDatabse($images, $idCity, $pdo) {
    $saved = 0;
    $skipped = 0;

    try {
        foreach ($images as $image) {
            $stmt = $pdo->prepare("SELECT id_image FROM images WHERE url = :url");
            $stmt->execute(['url' => $image['url']]);

            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO images (url, title, id_city, is_valid)
                VALUES (:url, :title, :id_city, 1)
            ");

            $stmt->execute([
                'url' => $image['url'],
                'title' => $image['title'],
                'id_city' => $idCity
            ]);

            $saved++;
        }

        return [
            'success' => true,
            'saved' => $saved,
            'skipped' => $skipped,
            'total' => count($images)
        ];

    } catch (PDOException $e) {
        error_log("Erreur sauvegarde images : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'saved' => $saved,
            'skipped' => $skipped
        ];
    }
}

/**
 * Fonction principale : récupère et sauvegarde les images d'une ville
 * @param int $idCity - ID de la ville
 * @param PDO $pdo - Connexion à la base de données
 * @return array - Résultat de l'opération
 */
function fetchAndSaveImages($idCity, $pdo) {
    $stmt = $pdo->prepare("
        SELECT c.name as city_name, co.name as country_name
        FROM cities c
        JOIN countries co ON c.id_country = co.id_country
        WHERE c.id_city = :id
    ");
    $stmt->execute(['id' => $idCity]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$city) {
        return [
            'success' => false,
            'error' => 'Ville non trouvée dans la base de données'
        ];
    }

    $images = getImagesFromWikipedia($city['city_name'], $city['country_name']);

    if (isset($images['error'])) {
        return [
            'success' => false,
            'error' => $images['error'],
            'city' => $city['city_name']
        ];
    }

    $result = saveImagesToDatabse($images, $idCity, $pdo);

    return array_merge($result, [
        'city' => $city['city_name'],
        'country' => $city['country_name']
    ]);
}
