<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php'; // Добавляем подключение functions.php

$stmt = $pdo->query("SELECT username, tournament_points FROM users ORDER BY tournament_points DESC LIMIT 5");
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

$match_id = isLoggedIn() ? isUserInMatch($_SESSION['user_id']) : false;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Киберспорт</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="index">
    <header>
        <a href="index.php" class="logo"><h1><?php echo SITE_TITLE; ?></h1></a>
        <nav>
            <a href="index.php" class="nav-btn">Главная</a>
            <a href="tournaments.php" class="nav-btn">Турниры</a>
            <a href="profile.php" class="nav-btn">Профиль</a>
            <a href="rewards.php" class="nav-btn">Призы</a>
            <a href="rules.php" class="nav-btn">Правила</a>
            <?php if ($match_id): ?>
                <a href="lobby.php?match_id=<?php echo $match_id; ?>" class="nav-btn active">Лобби</a>
            <?php endif; ?>
            <?php if (isLoggedIn()): ?>
                <a href="?logout" class="nav-btn">Выйти</a>
            <?php else: ?>
                <a href="profile.php" class="nav-btn">Войти</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h2>Добро пожаловать в <?php echo SITE_TITLE; ?></h2>
            <p>Соревнуйся с лучшими и выигрывай призы!</p>
            <a href="tournaments.php" class="action-btn">Начать</a>
        </section>

        <section class="leaderboard">
            <h3>Топ игроков</h3>
            <div class="leaderboard-grid">
                <?php foreach ($leaderboard as $player): ?>
                    <div class="player"><?php echo htmlspecialchars($player['username']); ?> - <?php echo $player['tournament_points']; ?> TP</div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script src="assets/js/scripts.js"></script>
</body>
</html>