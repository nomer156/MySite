<?php
// /vexio.ru/public_html/index.php
require_once 'partials/header.php';
?>
<div class="container" style="max-width:800px; margin:0 auto;">
  <h2>Добро пожаловать на <?= SITE_TITLE ?>!</h2>
  <p>Принимайте участие в турнирах, зарабатывайте билеты и получайте призы!</p>
  <a href="quick_tournament.php" class="play-button">Быстрые турниры</a>
  <a href="tournaments.php" class="play-button" style="margin-left:10px;">Турниры</a>
</div>
<?php
require_once 'partials/footer.php';
?>
