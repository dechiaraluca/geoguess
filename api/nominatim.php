<?php

function searchCountry($countryName) {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        'q' => $countryName,
        'format' => 'json',
        'limit' => 1
    ]);
    
    $options = [
        'http' => [
            'header' => "User-Agent: geoguess/1.0 (ton.email@example.com)\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    return json_decode($response, true);
}

// Test
$country = searchCountry("France");
print_r($country);


$searchArea = 3600000000 + $country[0]['osm_id'];