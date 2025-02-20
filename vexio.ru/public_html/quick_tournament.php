<?php
// /vexio.ru/public_html/quick_tournament.php

ob_start();
require_once 'partials/header.php';

$user = isLoggedIn() ? getCurrentUser($pdo) : null;

// Проверяем, участвует ли пользователь в любом матче (Created/Live)
$activeMatch = null;
if ($user) {
    $stmtActive = $pdo->prepare("
        SELECT m.*
          FROM match_players mp
          JOIN matches m ON m.id = mp.match_id
         WHERE mp.user_id = ?
           AND m.status IN ('Created','Live')
         LIMIT 1
    ");
    $stmtActive->execute([$user['id']]);
    $activeMatch = $stmtActive->fetch(PDO::FETCH_ASSOC);
}

$msg = $_GET['msg'] ?? '';

// Обработка создания быстрого турнира
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create_quick')) {
    if (!$user) {
        $msg = "Вы не авторизованы!";
    } elseif ($activeMatch) {
        $msg = "Вы уже участвуете в матче (#{$activeMatch['id']}, статус={$activeMatch['status']}).";
    } else {
        $mapName = trim($_POST['map_name'] ?? 'Dust II');
        $region = trim($_POST['region'] ?? 'Москва');
        $mode = trim($_POST['mode'] ?? '1v1');
        $entryFee = intval($_POST['entry_fee'] ?? 0);
        if ($entryFee < 0) { $entryFee = 0; }
        $startIn = intval($_POST['start_in'] ?? 5);
        if ($startIn < 1) { $startIn = 5; }

        // Определяем лимит участников по режиму
        if ($mode === '1v1') {
            $participantsLimit = 2;
        } elseif ($mode === '2v2') {
            $participantsLimit = 4;
        } elseif ($mode === '5v5') {
            $participantsLimit = 10;
        } else {
            $participantsLimit = 10;
        }

        if ($user['tickets'] < $entryFee) {
            $msg = "Недостаточно билетов (у вас {$user['tickets']}, нужно {$entryFee}).";
        } else {
            $tournamentName = "Быстрый турнир от " . $user['personaname'];
            $start_time = date('Y-m-d H:i:s', strtotime("+$startIn minutes"));

            $stmtIns = $pdo->prepare("
                INSERT INTO matches (name, map_name, region, mode, status, created_by, tournament_type, entry_fee, start_time, participants_limit)
                VALUES (?,?,?,?,?,'quick',?,?,?,?)
            ");
            $stmtIns->execute([
                $tournamentName,
                $mapName,
                $region,
                $mode,
                'Created',
                $user['id'],
                $entryFee,
                $start_time,
                $participantsLimit
            ]);
            $matchId = $pdo->lastInsertId();

            if ($entryFee > 0) {
                $pdo->prepare("UPDATE users SET tickets = tickets - ? WHERE id=?")
                    ->execute([$entryFee, $user['id']]);
            }

            $pdo->prepare("
                INSERT INTO match_players (match_id, user_id, team, entry_fee)
                VALUES (?,?,1,?)
            ")->execute([$matchId, $user['id'], $entryFee]);

            header("Location: match_lobby.php?match_id=" . $matchId);
            exit;
        }
    }
}

// Выбираем все матчи типа 'quick' со статусом Created/Live
$stmtQuick = $pdo->query("
    SELECT *
      FROM matches
     WHERE tournament_type = '1'
       AND status IN ('Created','Live')
     ORDER BY id DESC
");
$quickTournaments = $stmtQuick->fetchAll(PDO::FETCH_ASSOC);
var_dump($quickTournaments);
ob_end_flush();
?>
<div class="container">
  <h2>Быстрые турниры (одна игра)</h2>
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
        <th>Взнос</th>
        <th>Статус</th>
        <th>Начало</th>
        <th>Команды (T1/T2)</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($quickTournaments as $t):
          $teamCountsStmt = $pdo->prepare("SELECT team, COUNT(*) as cnt FROM match_players WHERE match_id=? GROUP BY team");
          $teamCountsStmt->execute([$t['id']]);
          $teamCounts = $teamCountsStmt->fetchAll(PDO::FETCH_ASSOC);

          $t1 = 0;
          $t2 = 0;
          foreach ($teamCounts as $tc) {
              if ($tc['team'] == 1) $t1 = $tc['cnt'];
              if ($tc['team'] == 2) $t2 = $tc['cnt'];
          }
      ?>
      <tr>
        <td>#<?= htmlspecialchars($t['id']) ?></td>
        <td><?= htmlspecialchars($t['name'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($t['map_name'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($t['mode'], ENT_QUOTES) ?></td>
        <td><?= (int)$t['entry_fee'] ?></td>
        <td><?= htmlspecialchars($t['status'], ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($t['start_time'], ENT_QUOTES) ?></td>
        <td>T1: <?= $t1 ?> / T2: <?= $t2 ?></td>
        <td><a href="match_lobby.php?match_id=<?= $t['id'] ?>" class="play-button">Лобби</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3 style="margin-top:20px;">Создать быстрый турнир</h3>
  <?php if ($user): ?>
    <?php if (!$activeMatch): ?>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="action" value="create_quick">
        <label>Карта:
          <select name="map_name">
            <option>Dust II</option>
            <option>Mirage</option>
            <option>Inferno</option>
            <option>Nuke</option>
            <option>Ancient</option>
            <option>Overpass</option>
          </select>
        </label><br><br>
        <label>Регион:
          <select name="region">
            <option>Москва</option>
            <option>Екатеринбург</option>
            <option>Europe</option>
            <option>Франкфурт-на-Майне</option>
            <option>Хельсинки</option>
          </select>
        </label><br><br>
        <label>Режим:
          <select name="mode">
            <option value="1v1">1v1</option>
            <option value="2v2">2v2</option>
            <option value="5v5">5v5</option>
          </select>
        </label><br><br>
        <label>Вступительный взнос (билеты):
          <input type="number" name="entry_fee" step="1" min="0" value="0">
        </label><br><br>
        <label>Начало через (минут):
          <input type="number" name="start_in" step="1" min="1" value="5">
        </label><br><br>
        <button type="submit" class="play-button">Создать</button>
      </form>
    <?php else: ?>
      <p>Вы уже участвуете в матче (#<?= $activeMatch['id'] ?>, статус <?= htmlspecialchars($activeMatch['status']) ?>).
         <a href="match_lobby.php?match_id=<?= $activeMatch['id'] ?>">Перейти в лобби</a>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p>Авторизуйтесь через Steam, чтобы создавать турниры.</p>
  <?php endif; ?>
</div>
<?php require_once 'partials/footer.php'; ?>
