<?php
require_once 'partials/header.php';

if (!isLoggedIn()) {
  echo "<div class='container'><p>Вы должны войти, чтобы создавать матчи.</p></div>";
  require_once 'partials/footer.php';
  exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Получаем поля
  $mapName = trim($_POST['map_name'] ?? 'Dust II');
  $region = trim($_POST['region'] ?? 'Москва');
  $mode = trim($_POST['mode'] ?? '5v5');

  // Создаём запись в matches
  $stmt = $pdo->prepare("INSERT INTO matches (map_name, region, mode, created_by) VALUES (?,?,?,?)");
  $stmt->execute([$mapName, $region, $mode, $_SESSION['user_id']]);
  $msg = "Матч успешно создан!";
}
?>
<div class="container">
  <h2>Создать матч</h2>
  <?php if ($msg): ?>
    <div class="message"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <form method="POST" style="max-width:300px;">
    <label>Карта:
      <select name="map_name">
        <option value="Dust II">Dust II</option>
        <option value="Mirage">Mirage</option>
        <option value="Inferno">Inferno</option>
        <option value="Nuke">Nuke</option>
        <option value="Ancient">Ancient</option>
        <option value="Overpass">Overpass</option>
      </select>
    </label><br/><br/>

    <label>Регион:
      <select name="region">
        <option value="Москва">Москва</option>
        <option value="Екатеринбург">Екатеринбург</option>
        <option value="Франкфурт-на-Майне">Франкфурт-на-Майне</option>
        <option value="Хельсинки">Хельсинки</option>
      </select>
    </label><br/><br/>

    <label>Режим:
      <select name="mode">
        <option value="5v5">5v5</option>
        <option value="2v2">2v2</option>
        <option value="1v1">1v1</option>
      </select>
    </label><br/><br/>

    <button type="submit" class="play-button">Создать</button>
  </form>
</div>
<?php require_once 'partials/footer.php'; ?>
