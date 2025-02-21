<?php
session_start();
require_once 'inc/auth.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Правила</title>
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
            <?php if (isLoggedIn()): ?>
                <a href="?logout" class="nav-btn">Выйти</a>
            <?php else: ?>
                <a href="profile.php" class="nav-btn">Войти</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="rules">
            <h2>Правила</h2>
            <ul>
                <li>Турниры основаны на навыках.</li>
                <li>TP — валюта для ставок и призов.</li>
                <li>Читы запрещены.</li>
                <li>Администрация может дисквалифицировать нарушителей.</li>
            </ul>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script src="assets/js/scripts.js"></script>
</body>
</html>