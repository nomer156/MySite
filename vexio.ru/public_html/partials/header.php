<?php
// /vexio.ru/public_html/partials/header.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'vexio.ru',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Для отладки ошибок (раскомментируйте при необходимости):
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title><?= SITE_TITLE ?></title>
  <link rel="stylesheet" href="css/style.css?v=2025-02-20">
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const userBtn = document.getElementById('user-menu-btn');
      const userMenu = document.getElementById('user-menu-dropdown');
      if (userBtn && userMenu) {
        userBtn.addEventListener('click', (ev) => {
          ev.stopPropagation();
          userMenu.classList.toggle('open');
        });
        document.addEventListener('click', () => {
          userMenu.classList.remove('open');
        });
      }
    });
  </script>
</head>
<body>
<header class="site-header">
  <div class="header-brand">
    <a href="index.php" class="logo">
      <img src="img/logo.png" alt="logo">
      <span class="site-name"><?= SITE_TITLE ?></span>
    </a>
  </div>
  <nav class="main-nav">
    <a href="quick_tournament.php">Быстрые турниры</a>
    <a href="tournaments.php">Турниры</a>
    <a href="#">Блог</a>
    <a href="#">Античит</a>
  </nav>
  <div class="header-right">
    <?php if (isLoggedIn()): ?>
      <?php $u = getCurrentUser($pdo); ?>
      <?php if ($u): ?>
        <div class="user-menu-container">
          <button id="user-menu-btn" class="user-menu-button">
            <img src="<?= htmlspecialchars($u['avatar'] ?? '') ?>" alt="avatar" class="user-avatar">
            <span><?= htmlspecialchars($u['personaname'] ?? 'Unknown') ?></span>
          </button>
          <div id="user-menu-dropdown" class="user-menu-dropdown">
            <a href="profile.php">Профиль</a>
            <a href="auth.php?action=logout">Выйти</a>
          </div>
        </div>
      <?php else: ?>
        <a href="auth.php?action=login" class="login-link">Войти (Steam)</a>
      <?php endif; ?>
    <?php else: ?>
      <a href="auth.php?action=login" class="login-link">Войти (Steam)</a>
    <?php endif; ?>
  </div>
</header>
<main>
