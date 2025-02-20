<?php
// /vexio.ru/public_html/profile.php
require_once 'partials/header.php';

if (!isLoggedIn()) {
    echo "<div class='container'><p>Вы не авторизованы. <a href='auth.php?action=login'>Войти</a></p></div>";
    require_once 'partials/footer.php';
    exit;
}

$user = getCurrentUser($pdo);
?>
<div class="container">
  <h2>Мой профиль</h2>
  <p><strong>Ник:</strong> <?= htmlspecialchars($user['personaname']) ?></p>
  <p><strong>Обычные билеты:</strong> <?= (int)$user['tickets'] ?></p>
  <p><strong>Премиум билеты:</strong> <?= (int)$user['premium_tickets'] ?></p>
  <p><strong>Steam ID:</strong> <?= htmlspecialchars($user['steam_id']) ?></p>
</div>
<?php
require_once 'partials/footer.php';
?>
