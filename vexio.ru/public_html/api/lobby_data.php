<?php
session_start();
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/functions.php';
require_once '../inc/steam_api.php';

header('Content-Type: application/json');

if (!isset($_GET['match_id'])) {
    echo json_encode(['error' => 'Missing match_id']);
    exit;
}

$match_id = (int)$_GET['match_id'];
$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT m.*, t.name, t.type, t.commission, t.prize_pool FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ? AND m.lobby_active = TRUE");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    echo json_encode(['error' => 'Match not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT mp.user_id, mp.team, u.username, u.steam_id FROM match_players mp JOIN users u ON mp.user_id = u.id WHERE mp.match_id = ?");
$stmt->execute([$match_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$team1 = array_values(array_filter($players, fn($p) => $p['team'] == 'team1')); // Спецназ
$team2 = array_values(array_filter($players, fn($p) => $p['team'] == 'team2')); // Террористы
$player_count = count($players);
$max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);

$stmt = $pdo->prepare("SELECT lc.message, u.username, lc.timestamp FROM lobby_chat lc JOIN users u ON lc.user_id = u.id WHERE lc.match_id = ? ORDER BY lc.timestamp");
$stmt->execute([$match_id]);
$chat = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [
    'match' => [
        'id' => $match['id'],
        'name' => $match['name'],
        'status' => $match['status'],
        'mode' => $match['mode'],
        'map' => $match['map'] ?? 'de_dust2',
        'bet_amount' => $match['bet_amount'],
        'type' => $match['type'],
        'prize' => $match['type'] == 'quick' ? $match['bet_amount'] * $max_players : $match['prize_pool'],
        'end_time' => $match['end_time'] ? round((strtotime($match['end_time']) - time()) / 60) : 'Не начат',
        'players_count' => $player_count,
        'max_players' => $max_players,
        'team1' => array_map(fn($p) => [
            'username' => $p['username'],
            'avatar' => getSteamProfile($p['steam_id'])['avatar'],
            'steam_id' => $p['steam_id']
        ], $team1),
        'team2' => array_map(fn($p) => [
            'username' => $p['username'],
            'avatar' => getSteamProfile($p['steam_id'])['avatar'],
            'steam_id' => $p['steam_id']
        ], $team2)
    ],
    'chat' => array_map(fn($c) => ['username' => $c['username'], 'message' => $c['message'], 'timestamp' => $c['timestamp']], $chat)
];

echo json_encode($result);
?>