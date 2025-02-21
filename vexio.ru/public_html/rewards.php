<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';

if (!isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT tournament_points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_tp = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT id, name, cost FROM rewards WHERE available = TRUE");
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_id'])) {
    $reward_id = (int)$_POST['reward_id'];
    $stmt = $pdo->prepare("SELECT cost FROM rewards WHERE id = ? AND available = TRUE");
    $stmt->execute([$reward_id]);
    $cost = $stmt->fetchColumn();

    if ($cost && deductTournamentPoints($user_id, $cost)) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description) VALUES (?, ?, 'Обмен на приз')");
        $stmt->execute([$user_id, -$cost]);
        $success = "Приз получен!";
    } else {
        $error = "Недостаточно TP.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Призы</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
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
        <section class="rewards">
            <h2>Призы</h2>
            <p>Ваши TP: <?php echo $user_tp; ?></p>
            <?php if (isset($success)): ?><p class="success"><?php echo $success; ?></p><?php endif; ?>
            <?php if (isset($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <?php foreach ($rewards as $reward): ?>
                <div class="reward-card">
                    <h3><?php echo htmlspecialchars($reward['name']); ?></h3>
                    <p><?php echo $reward['cost']; ?> TP</p>
                    <form method="POST">
                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                        <button type="submit" class="action-btn">Обменять</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script src="assets/js/scripts.js"></script>
</body>
</html>