<?php
// /vexio.ru/public_html/inc/functions.php

function isLoggedIn(): bool {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    global $pdo;
    $u = getCurrentUser($pdo);
    return ($u && !empty($u['steam_id']) && !empty($u['personaname']));
}

function getCurrentUser(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getSteamProfile(string $steamId): ?array {
    $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . STEAM_API_KEY . '&steamids=' . $steamId;
    $resp = @file_get_contents($url);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    return $data['response']['players'][0] ?? null;
}
