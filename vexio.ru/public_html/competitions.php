<?php
// /vexio.ru/public_html/competitions.php
require_once 'partials/header.php';

$user = isLoggedIn() ? getCurrentUser($pdo) : null;
$activeComp = null;
if ($user) {
    $stmtComp = $pdo->prepare("
        SELECT m.*
          FROM match_players mp
          JOIN matches m ON m.id = mp.match_id
         WHERE mp.user_id = ? AND m.tournament_type = 'competition'
           AND m.status IN ('Created','Live')
         LIMIT 1
    ");
    $stmtComp->execute([$user['id']]);
    $activeComp = $stmtComp->fetch(PDO::FETCH_ASSOC);
}

$msg = $_GET['msg'] ?? '';

$stmtList = $pdo->query("SELECT * FROM matches WHERE tournament_type = 'competition' AND status IN ('Created','Live') ORDER BY id DESC");
$competitions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_comp') {
    if (!$user) {
        $msg = "Вы не авторизованы! Войдите через Steam.";
    } elseif ($activeComp) {
        $msg = "Вы уже участвуете в соревновании (#{$activeComp['id']}).";
    } else {
        $name = trim($_POST['name'] ?? 'Новый Турнир');
        $mapName = trim($_POST['map_name'] ?? 'Dust II');
        $region = trim($_POST['region'] ?? 'Москва');
        $mode = trim($_POST['mode'] ?? '1v1');
        $fixedFee = intval($_POST['fixed_fee'] ?? 0);
        if ($fixedFee < 1) $fixedFee = 1;
        // Создаем соревнование с типом 'competition'
        $stmtIns = $pdo->prepare("
            INSERT INTO matches (name, map_name, region, mode, status, created_by, tournament_type, entry_fee)
            VALUES (?,?,?,?,?,?,'competition',?)
        ");
        $stmtIns->execute([$name, $mapName, $region, $mode, 'Created', $user['id'], $fixedFee]);
        $compId = $pdo->lastInsertId();
        // Организатор автоматически участвует, платя фиксированную ставку
        $pdo->prepare("UPDATE users SET tickets = tickets - ? WHERE id=?")
            ->execute([$fixedFee, $user['id']]);
        $pdo->prepare("
            INSERT INTO match_players (match_id, user_id, team, entry_fee)
            VALUES (?,?,1,?)
        ")->execute([$compId, $user['id'], $fixedFee]);
        header("Location: competitions.php?msg=" . urlencode("Соревнование создано. Ожидайте заполнения участников."));
        exit;
    }
}
?>
<div class="container">
  <h2>Соревнования (турнирная сетка)</h2>
  <?php if ($msg): ?>
    <div class="message"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Карта</th>
        <th>Режим</th>
        <th>Фикс. взнос</th>
        <th>Статус</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($competitions as $c): ?>
        <tr>
          <td>#<?= $c['id'] ?></td>
          <td><?= htmlspecialchars($c['name'] ?? 'Без названия', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['map_name'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['mode'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['entry_fee'] ?? 0, ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['status'], ENT_QUOTES) ?></td>
          <td><a href="match_lobby.php?match_id=<?= $c['id'] ?>" class="play-button">Лобби</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3 style="margin-top:20px;">Создать соревнование</h3>
  <?php if ($user): ?>
    <?php if ($activeComp): ?>
      <p>Вы уже участвуете в соревновании #<?= $activeComp['id'] ?> (<?= $activeComp['status'] ?>). 
         <a href="match_lobby.php?match_id=<?= $activeComp['id'] ?>">Перейти в лобби</a></p>
    <?php else: ?>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="action" value="create_comp">
        <label>Название турнира:
          <input type="text" name="name" value="Новый Турнир">
        </label><br/><br/>
        <label>Карта:
          <select name="map_name">
            <option>Dust II</option>
            <option>Mirage</option>
            <option>Inferno</option>
            <option>Nuke</option>
            <option>Ancient</option>
            <option>Overpass</option>
          </select>
        </label><br/><br/>
        <label>Регион:
          <select name="region">
            <option>Москва</option>
            <option>Екатеринбург</option>
            <option>Europe</option>
            <option>Франкфурт-на-Майне</option>
            <option>Хельсинки</option>
          </select>
        </label><br/><br/>
        <label>Режим:
          <select name="mode">
            <option>1v1</option>
            <option>2v2</option>
            <option>5v5</option>
          </select>
        </label><br/><br/>
        <label>Фиксированная ставка (в билетах):
          <input type="number" name="fixed_fee" step="1" min="1" value="1">
        </label><br/><br/>
        <button type="submit" class="play-button">Создать соревнование</button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p>Авторизуйтесь через Steam, чтобы создавать соревнования.</p>
  <?php endif; ?>
</div>
<?php require_once 'partials/footer.php'; ?>
