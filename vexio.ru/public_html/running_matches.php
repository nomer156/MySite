<?php
// /vexio.ru/public_html/running_matches.php
require_once 'partials/header.php';

// Для отладки можно включить вывод ошибок:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$stmt = $pdo->query("SELECT * FROM matches WHERE status = 'Live' ORDER BY id DESC");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
  <h2>Запущенные матчи</h2>
  <?php if (!$matches): ?>
    <p>Нет запущенных матчей.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Название</th>
          <th>Карта</th>
          <th>Режим</th>
          <th>Взнос</th>
          <th>Начало</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matches as $m): ?>
          <tr>
            <td>#<?= htmlspecialchars($m['id']) ?></td>
            <td><?= htmlspecialchars($m['name'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['map_name'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($m['mode'], ENT_QUOTES) ?></td>
            <td><?= (int)$m['entry_fee'] ?></td>
            <td><?= htmlspecialchars($m['start_time'], ENT_QUOTES) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require_once 'partials/footer.php'; ?>
