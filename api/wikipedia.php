<?php

/**
 * Récupère les images d'une page Wikipedia
 * @param string $cityName - Nom de la ville
 * @param string $countryName - Nom du pays (pour gérer les homonymes)
 * @return array - Liste des URLs d'images ou erreur
 */
function getImagesFromWikipedia($cityName, $countryName = '') {
    $countryNameMapping = [
        'España' => 'Spain',
        'Italia' => 'Italy',
        'Deutschland' => 'Germany',
        '日本' => 'Japan',
        'France' => 'France',
        'United Kingdom' => 'United Kingdom',
        'United States' => 'United States',
        'Canada' => 'Canada',
        'Brasil' => 'Brazil',
        'Россия' => 'Russia',
        '中国' => 'China',
        'Österreich' => 'Austria',
        'Schweiz' => 'Switzerland',
        'Nederland' => 'Netherlands',
        'België' => 'Belgium',
        'Portugal' => 'Portugal',
        'Polska' => 'Poland',
        'México' => 'Mexico',
        'Argentina' => 'Argentina'
    ];

    if ($countryName) {
        $countryName = explode(',', $countryName)[0];
        $countryName = trim($countryName);
        if (isset($countryNameMapping[$countryName])) {
            $countryName = $countryNameMapping[$countryName];
        }
    }

    $searchStrategies = [];

    if ($countryName) {
        $searchStrategies[] = $cityName . ', ' . $countryName;
        $searchStrategies[] = $cityName . ' city ' . $countryName;
    }
    $searchStrategies[] = $cityName;

    $pageId = null;
    foreach ($searchStrategies as $searchTerm) {
        $result = searchWikipediaPage($searchTerm);
        if (!isset($result['error'])) {
            if (isValidCityPage($result)) {
                $pageId = $result;
                break;
            }
        }
    }

    if ($pageId === null) {
        return ['error' => 'Page Wikipedia de ville non trouvée'];
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
 * Vérifie si une page Wikipedia est bien une page de ville/lieu géographique
 * @param int $pageId - ID de la page
 * @return bool - True si c'est une page de ville valide
 */
function isValidCityPage($pageId) {
    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'pageids' => $pageId,
        'prop' => 'categories|extracts',
        'cllimit' => 20,
        'exintro' => true,
        'explaintext' => true,
        'exsentences' => 3
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error']) || !isset($response['query']['pages'][$pageId])) {
        return false;
    }

    $page = $response['query']['pages'][$pageId];

    $extract = strtolower($page['extract'] ?? '');
    $locationKeywords = [
        'city', 'town', 'village', 'municipality', 'capital',
        'located', 'population', 'region', 'province', 'county',
        'district', 'commune', 'borough', 'metropolitan'
    ];

    foreach ($locationKeywords as $keyword) {
        if (strpos($extract, $keyword) !== false) {
            return true;
        }
    }

    $categories = $page['categories'] ?? [];
    $validCategoryKeywords = [
        'cities', 'towns', 'villages', 'municipalities',
        'populated places', 'capitals', 'communes'
    ];

    foreach ($categories as $cat) {
        $catTitle = strtolower($cat['title'] ?? '');
        foreach ($validCategoryKeywords as $keyword) {
            if (strpos($catTitle, $keyword) !== false) {
                return true;
            }
        }
    }

    $invalidKeywords = [
        'genus', 'species', 'animal', 'plant', 'fossil',
        'extinct', 'family', 'mammal', 'bird', 'fish',
        'person', 'born', 'died', 'politician', 'actor',
        'singer', 'album', 'song', 'film', 'movie'
    ];

    foreach ($invalidKeywords as $keyword) {
        if (strpos($extract, $keyword) !== false) {
            return false;
        }
    }

    return true;
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
 * Récupère l'image principale (thumbnail) d'une page Wikipedia
 * @param int $pageId - ID de la page
 * @return array - Image principale ou null
 */
function fetchMainImage($pageId) {
    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'pageids' => $pageId,
        'prop' => 'pageimages',
        'piprop' => 'original',
        'pilicense' => 'any'
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error'])) {
        return null;
    }

    if (!isset($response['query']['pages'][$pageId]['original']['source'])) {
        return null;
    }

    return [
        'title' => $response['query']['pages'][$pageId]['pageimage'] ?? 'Main image',
        'url' => $response['query']['pages'][$pageId]['original']['source']
    ];
}

/**
 * Récupère les images d'une page Wikipedia
 * @param int $pageId - ID de la page
 * @return array - Liste des images ou erreur
 */
function fetchPageImages($pageId) {
    $images = [];

    $mainImage = fetchMainImage($pageId);
    if ($mainImage) {
        $images[] = $mainImage;
    }

    $url = "https://en.wikipedia.org/w/api.php?" . http_build_query([
        'action' => 'query',
        'format' => 'json',
        'pageids' => $pageId,
        'prop' => 'images',
        'imlimit' => 20
    ]);

    $response = makeWikipediaRequest($url);

    if (isset($response['error'])) {
        return empty($images) ? $response : $images;
    }

    if (!isset($response['query']['pages'][$pageId]['images'])) {
        return empty($images) ? ['error' => 'Aucune image sur cette page'] : $images;
    }

    $imageNames = $response['query']['pages'][$pageId]['images'];

    foreach ($imageNames as $image) {
        if (count($images) >= 10) {
            break;
        }

        $imageUrl = getImageUrl($image['title']);
        if ($imageUrl && !isset($imageUrl['error'])) {
            $isDuplicate = false;
            foreach ($images as $existingImage) {
                if ($existingImage['url'] === $imageUrl) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $images[] = [
                    'title' => $image['title'],
                    'url' => $imageUrl
                ];
            }
        }
    }

    return $images;
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
 * Filtre les images pour exclure drapeaux, cartes, logos et images non pertinentes
 * @param array $images - Liste des images
 * @param string $cityName - Nom de la ville (optionnel, pour filtrage contextuel)
 * @return array - Images filtrées
 */
function filterImages($images, $cityName = '') {
    $excludePatterns = [
        '/flag/i', '/drapeau/i', '/coat.?of.?arms/i', '/blason/i',
        '/\bmap\b/i', '/\bcarte\b/i', '/\blogo\b/i', '/locator/i',
        '/\bseal\b/i', '/sceau/i', '/emblem/i', '/symbol/i',
        '/diagram/i', '/\.svg$/i', '/\.gif$/i',
        '/\bicon\b/i', '/icône/i', '/button/i', '/arrow/i',
        '/commons-logo/i', '/wikidata/i', '/red.?pencil/i',
        '/increase/i', '/decrease/i', '/steady/i',
        '/question.?mark/i', '/edit-clear/i',
        '/disambig/i', '/folder/i', '/padlock/i',
        '/location.?dot/i', '/marker/i',
        '/percentage/i', '/population/i', '/graph/i', '/chart/i',
        '/portrait/i', '/signature/i', '/autograph/i',
        '/\bwar\b/i', '/battle/i', '/soldier/i', '/military/i',
        '/troop/i', '/army/i', '/regiment/i',
        '/fossil/i', '/skeleton/i', '/skull/i',
        '/specimen/i', '/taxon/i', '/genus/i', '/species/i',
        '/painting/i', '/artwork/i',
        '/screenshot/i', '/album.?cover/i',
        '/\bhead\b/i', '/mugshot/i'
    ];

    $filtered = [];

    foreach ($images as $image) {
        $title = $image['title'];
        $url = $image['url'] ?? '';
        $isValid = true;

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $title)) {
                $isValid = false;
                break;
            }
        }

        if ($isValid && preg_match('/\.(svg|gif)$/i', $url)) {
            $isValid = false;
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'GeoGuess/1.0 (your.email@example.com)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);

    usleep(100000);

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
