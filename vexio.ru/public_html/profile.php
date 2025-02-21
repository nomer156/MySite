<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/steam_api.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isLoggedIn()) {
    steamLogin();
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, steam_id, tournament_points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$steam = getSteamProfile($user['steam_id']);

// Получаем историю матчей
$stmt = $pdo->prepare("
    SELECT m.id, m.mode, m.map, m.bet_amount, m.status, m.end_time, mp.team,
           CASE WHEN mp.team = (SELECT team FROM match_players WHERE match_id = m.id AND user_id = ? LIMIT 1) THEN 'Победа' ELSE 'Поражение' END as result,
           t.amount as tp_change
    FROM matches m
    JOIN match_players mp ON m.id = mp.match_id
    LEFT JOIN transactions t ON t.user_id = ? AND t.description LIKE '%матче' AND t.transaction_date >= m.end_time - INTERVAL 1 MINUTE AND t.transaction_date <= m.end_time + INTERVAL 1 MINUTE
    WHERE mp.user_id = ? AND m.status = 'finished'
    ORDER BY m.end_time DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Профиль</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="profile">
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
        <section class="profile">
            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab('info')">Информация</button>
                <button class="tab-btn" onclick="showTab('history')">История</button>
            </div>

            <div id="info" class="tab-content active">
                <h2>Профиль</h2>
                <img src="<?php echo $steam['avatar'] ?? 'https://via.placeholder.com/80'; ?>" alt="Аватар" class="avatar">
                <p>Имя: <?php echo htmlspecialchars($user['username']); ?></p>
                <p>Steam ID: <?php echo htmlspecialchars($user['steam_id']); ?></p>
                <p>TP: <?php echo $user['tournament_points']; ?></p>
            </div>

            <div id="history" class="tab-content">
                <h2>История матчей</h2>
                <?php if (empty($history)): ?>
                    <p>У вас пока нет завершённых матчей.</p>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Режим</th>
                                <th>Карта</th>
                                <th>Результат</th>
                                <th>Ставка (TP)</th>
                                <th>Изменение TP</th>
                                <th>Время</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['mode']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['map']); ?></td>
                                    <td><?php echo $entry['result']; ?></td>
                                    <td><?php echo $entry['bet_amount']; ?></td>
                                    <td><?php echo $entry['tp_change'] ?? ($entry['result'] == 'Поражение' ? '-' . $entry['bet_amount'] : '0'); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($entry['end_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script>
    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        document.querySelector(`button[onclick="showTab('${tabId}')"]`).classList.add('active');
    }
    </script>
</body>
</html>