<?php

// Пример: в match_lobby.php, если tournament_type='competition' и participants_limit=8
// покажем упрощенную сетку
// Предположим, allPlayers - массив из 8 участников (или меньше, если не все набрались)

echo "<h3>Сетка (8 участников, single elimination)</h3>";
echo "<div class='bracket'>";

// Round 1 (четвертьфинал) - 4 пары
// Если у нас < 8 участников, часть слотов пусты
for ($i = 0; $i < 8; $i += 2) {
   $playerA = $allPlayers[$i]['personaname'] ?? '---';
   $playerB = $allPlayers[$i+1]['personaname'] ?? '---';
   echo "<div class='match-pair'>"
       . "<div class='slot'>$playerA</div>"
       . "<div class='slot'>$playerB</div>"
       . "</div>";
}
// Round 2 (полуфинал) - 2 пары
echo "<div class='next-round'>Полуфинал: 2 матча</div>";
echo "<div class='match-pair'><div class='slot'>Winners of Match1</div><div class='slot'>Winners of Match2</div></div>";
echo "<div class='match-pair'><div class='slot'>Winners of Match3</div><div class='slot'>Winners of Match4</div></div>";

// Round 3 (финал) - 1 матч
echo "<div class='next-round'>Финал</div>";
echo "<div class='match-pair'><div class='slot'>Winners SF1</div><div class='slot'>Winners SF2</div></div>";

echo "</div>"; // bracket

// /vexio.ru/public_html/matches.php
require_once 'partials/header.php';

$user = isLoggedIn() ? getCurrentUser($pdo) : null;
$activeMatch = null;
if ($user) {
  $stmtAm = $pdo->prepare("
    SELECT m.* 
      FROM match_players mp
      JOIN matches m ON m.id = mp.match_id
     WHERE mp.user_id=?
       AND m.status IN ('Created','Live')
     LIMIT 1
  ");
  $stmtAm->execute([$user['id']]);
  $activeMatch = $stmtAm->fetch(PDO::FETCH_ASSOC);
}

$msg = $_GET['msg'] ?? '';

$sql = "SELECT * FROM matches WHERE status IN ('Created','Live') ORDER BY id DESC";
$matches = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_match') {
  if (!$user) {
    $msg = "Вы не авторизованы! Войдите через Steam.";
  } elseif ($activeMatch) {
    $msg = "Вы уже участвуете в турнире (#{$activeMatch['id']}).";
  } else {
    $mapName = trim($_POST['map_name'] ?? 'Dust II');
    $region = trim($_POST['region'] ?? 'Москва');
    $mode   = trim($_POST['mode'] ?? '5v5');
    $entryFee = intval($_POST['entry_fee'] ?? 0);
    if ($entryFee < 0) $entryFee = 0;
    if ($user['tickets'] < $entryFee) {
      $msg = "Недостаточно билетов!";
    } else {
      $pdo->prepare("
        INSERT INTO matches (map_name, region, mode, status, created_by)
        VALUES (?,?,?,?,?)
      ")->execute([$mapName, $region, $mode, 'Created', $user['id']]);
      $matchId = $pdo->lastInsertId();
      $pdo->prepare("UPDATE users SET tickets = tickets - ? WHERE id=?")
          ->execute([$entryFee, $user['id']]);
      $pdo->prepare("
        INSERT INTO match_players (match_id, user_id, team, entry_fee)
        VALUES (?,?,1,?)
      ")->execute([$matchId, $user['id'], $entryFee]);
      header("Location: match_lobby.php?match_id=" . $matchId);
      exit;
    }
  }
}
?>
<div class="container">
  <h2>Турниры</h2>
  <?php if($msg): ?>
    <div class="message"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Карта</th>
        <th>Регион</th>
        <th>Режим</th>
        <th>Статус</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($matches as $m): ?>
        <tr>
          <td>#<?= $m['id'] ?></td>
          <td><?= htmlspecialchars($m['map_name'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($m['region'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($m['mode'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($m['status'], ENT_QUOTES) ?></td>
          <td><a href="match_lobby.php?match_id=<?= $m['id'] ?>" class="play-button">Лобби</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <h3 style="margin-top:20px;">Создать турнир</h3>
  <?php if ($user): ?>
    <?php if ($activeMatch): ?>
      <p>Вы уже участвуете в турнире #<?= $activeMatch['id'] ?> (<?= $activeMatch['status'] ?>). 
         <a href="match_lobby.php?match_id=<?= $activeMatch['id'] ?>">Перейти в лобби</a></p>
    <?php else: ?>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="action" value="create_match">
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
        <label>Вступительный взнос (у вас: <?= htmlspecialchars($user['tickets'], ENT_QUOTES) ?> билетов):
          <input type="number" name="entry_fee" step="1" min="0" value="0">
        </label><br/><br/>
        <button type="submit" class="play-button">Создать</button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p>Авторизуйтесь через Steam, чтобы создавать турниры.</p>
  <?php endif; ?>
</div>
<?php require_once 'partials/footer.php'; ?>
