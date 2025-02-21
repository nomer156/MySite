<?php
session_start();
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/functions.php';
require_once '../inc/steam_api.php';

header('Content-Type: application/json');

$stmt = $pdo->query("SELECT m.*, t.name as tournament_name, t.type, t.entry_cost FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.status IN ('pending', 'ongoing') AND m.lobby_active = TRUE");
$active_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($active_matches as $match) {
    $stmt = $pdo->prepare("SELECT mp.user_id, mp.team, u.username, u.steam_id FROM match_players mp JOIN users u ON mp.user_id = u.id WHERE mp.match_id = ?");
    $stmt->execute([$match['id']]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $team1 = array_filter($players, fn($p) => $p['team'] == 'team1');
    $team2 = array_filter($players, fn($p) => $p['team'] == 'team2');
    $players_count = count($players);
    $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);

    $match_data = [
        'id' => $match['id'],
        'tournament_name' => $match['tournament_name'],
        'mode' => $match['mode'],
        'status' => $match['status'],
        'players_count' => $players_count,
        'max_players' => $max_players,
        'bet_amount' => $match['bet_amount'],
        'map' => $match['map'] ?? 'de_dust2', // Добавляем карту
        'end_time' => $match['end_time'] ? round((strtotime($match['end_time']) - time()) / 60) : 'Не начат',
        'team1' => array_map(fn($p) => ['username' => $p['username'], 'avatar' => getSteamProfile($p['steam_id'])['avatar']], $team1),
        'team2' => array_map(fn($p) => ['username' => $p['username'], 'avatar' => getSteamProfile($p['steam_id'])['avatar']], $team2)
    ];
    $result[] = $match_data;
}

echo json_encode($result);
?>