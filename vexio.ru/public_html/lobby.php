<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';
require_once 'inc/steam_api.php';

if (!isLoggedIn() || !isset($_GET['match_id'])) {
    header('Location: tournaments.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = (int)$_GET['match_id'];

$stmt = $pdo->prepare("SELECT m.*, t.name, t.type FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ? AND m.lobby_active = TRUE");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: tournaments.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND user_id = ?");
$stmt->execute([$match_id, $user_id]);
$is_participant = $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ready']) && $is_participant) {
    $stmt = $pdo->prepare("UPDATE match_players SET ready = 1 WHERE match_id = ? AND user_id = ?");
    $stmt->execute([$match_id, $user_id]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ?");
    $stmt->execute([$match_id]);
    $player_count = $stmt->fetchColumn();

    $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND ready = 1");
    $stmt->execute([$match_id]);
    $ready_count = $stmt->fetchColumn();

    if ($player_count == $max_players && $ready_count == $max_players) {
        $stmt = $pdo->prepare("UPDATE matches SET status = 'ongoing', end_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
        $stmt->execute([$match_id]);
    }
}

$stmt = $pdo->prepare("SELECT mp.user_id, mp.team, u.username, u.steam_id FROM match_players mp JOIN users u ON mp.user_id = u.id WHERE mp.match_id = ?");
$stmt->execute([$match_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
$team1 = array_filter($players, fn($p) => $p['team'] == 'team1');
$team2 = array_filter($players, fn($p) => $p['team'] == 'team2');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Лобби</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="lobby">
    <header>
        <a href="index.php" class="logo"><h1><?php echo SITE_TITLE; ?></h1></a>
        <nav>
            <a href="index.php" class="nav-btn">Главная</a>
            <a href="tournaments.php" class="nav-btn">Турниры</a>
            <a href="profile.php" class="nav-btn">Профиль</a>
            <a href="rewards.php" class="nav-btn">Призы</a>
            <a href="rules.php" class="nav-btn">Правила</a>
            <a href="?logout" class="nav-btn">Выйти</a>
        </nav>
    </header>

    <main>
        <section class="lobby">
            <div class="lobby-main">
                <h2><?php echo htmlspecialchars($match['name']); ?></h2>
                <div class="match-core" id="match-players">
                    <div class="team" id="team1">
                        <p>Спецназ</p>
                        <?php foreach ($team1 as $player): ?>
                            <?php $steam = getSteamProfile($player['steam_id']); ?>
                            <div class="player-info">
                                <img src="<?php echo $steam['avatar'] ?? 'https://via.placeholder.com/40'; ?>" alt="Avatar" class="avatar small">
                                <span><?php echo htmlspecialchars($player['username']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="vs">VS</div>
                    <div class="team" id="team2">
                        <p>Террористы</p>
                        <?php foreach ($team2 as $player): ?>
                            <?php $steam = getSteamProfile($player['steam_id']); ?>
                            <div class="player-info">
                                <img src="<?php echo $steam['avatar'] ?? 'https://via.placeholder.com/40'; ?>" alt="Avatar" class="avatar small">
                                <span><?php echo htmlspecialchars($player['username']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="map">Карта: <?php echo $match['map']; ?></p>
                <?php if ($is_participant && $match['status'] == 'pending'): ?>
                    <form method="POST">
                        <button type="submit" name="ready" class="action-btn">Готов</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>
</body>
</html>