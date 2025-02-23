<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';
require_once 'inc/steam_api.php';

if (!isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->query("SELECT id, name, type, entry_cost, prize_pool, commission, status, mode, max_players FROM tournaments WHERE status = 'open'");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quick'])) {
    $bet_amount = (int)$_POST['bet_amount'];
    $mode = $_POST['mode'];
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    if ($bet_amount >= 1 && !isUserInMatch($user_id)) { // Уменьшил условие до 1 для теста
        try {
            $stmt = $pdo->prepare("INSERT INTO tournaments (name, type, start_date, status, entry_cost, prize_pool, commission, is_private, mode, max_players) VALUES (?, 'quick', NOW(), 'open', ?, 0, 10, ?, ?, ?)");
            $stmt->execute(["Custom $mode ($bet_amount Tickets)", $bet_amount, $is_private, $mode, $mode == '1v1' ? 2 : ($mode == '2v2' ? 4 : 10)]);
            $tournament_id = $pdo->lastInsertId();

            $match_id = joinQuickMatch($user_id, $tournament_id, $bet_amount, $mode);
            if ($match_id) {
                header("Location: lobby.php?match_id=$match_id");
                exit;
            } else {
                $error = "Не удалось создать матч.";
            }
        } catch (Exception $e) {
            $error = "Ошибка при создании турнира: " . $e->getMessage();
        }
    } else {
        $error = "Недостаточно билетов или вы уже в матче.";
    }
}

// Остальной код для присоединения к матчам оставляем без изменений
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Турниры</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="tournaments">
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
        <section class="tournaments">
            <h2>Создать быстрый матч</h2>
            <?php if (isset($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST" class="create-match">
                <div class="form-group">
                    <label>Ставка (билеты):</label>
                    <input type="number" name="bet_amount" min="1" required>
                </div>
                <div class="form-group">
                    <label>Режим:</label>
                    <select name="mode" required>
                        <option value="1v1">1v1</option>
                        <option value="2v2">2v2</option>
                        <option value="5v5">5v5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Карта:</label>
                    <select name="map" disabled>
                        <option value="de_dust2">de_dust2</option>
                    </select>
                </div>
                <div class="form-group checkbox">
                    <label><input type="checkbox" name="is_private"> Приватный</label>
                </div>
                <button type="submit" name="create_quick" class="action-btn">Создать</button>
            </form>

            <h2>Доступные турниры</h2>
            <div class="tournament-list">
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="tournament-card">
                        <h3><?php echo htmlspecialchars($tournament['name']); ?></h3>
                        <p>Тип: <?php echo $tournament['type'] == 'quick' ? 'Быстрый' : 'Сетка'; ?></p>
                        <p>Режим: <?php echo htmlspecialchars($tournament['mode']); ?></p>
                        <p>Вход: <?php echo $tournament['entry_cost']; ?> билетов</p>
                        <form method="POST">
                            <input type="hidden" name="tournament_id" value="<?php echo $tournament['id']; ?>">
                            <button type="submit" class="action-btn">Присоединиться</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>
</body>
</html>