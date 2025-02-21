<?php

function getSteamProfile($steam_id) {
    if (empty($steam_id)) {
        error_log("Steam API: Empty steam_id provided");
        return ['avatar' => 'https://via.placeholder.com/40', 'personaname' => 'Unknown'];
    }

    $api_key = defined('STEAM_API_KEY') ? STEAM_API_KEY : '';
    if (empty($api_key)) {
        error_log("Steam API: Missing API key");
        return ['avatar' => 'https://via.placeholder.com/40', 'personaname' => 'Unknown'];
    }

    $url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$api_key&steamids=$steam_id";
    $response = @file_get_contents($url);
    if ($response === false) {
        error_log("Steam API error for steam_id: $steam_id - Failed to fetch data");
        return ['avatar' => 'https://via.placeholder.com/40', 'personaname' => 'Unknown'];
    }

    $data = json_decode($response, true);
    if (isset($data['response']['players'][0])) {
        $player = $data['response']['players'][0];
        return [
            'avatar' => $player['avatar'] ?? 'https://via.placeholder.com/40',
            'personaname' => $player['personaname'] ?? 'Unknown'
        ];
    }

    error_log("Steam API error for steam_id: $steam_id - No player data: " . $response);
    return ['avatar' => 'https://via.placeholder.com/40', 'personaname' => 'Unknown'];
}

?>