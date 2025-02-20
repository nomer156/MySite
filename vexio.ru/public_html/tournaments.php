<?php
// /vexio.ru/public_html/tournaments.php

ob_start();
require_once 'partials/header.php';

$user = isLoggedIn() ? getCurrentUser($pdo) : null;

// Проверяем, участвует ли пользователь в каком-либо матче
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

// Обработка создания нового турнира (tournament)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create_tourn')) {
    if (!$user) {
        $msg = "Вы не авторизованы!";
    } elseif ($activeMatch) {
        // Если пользователь уже участвует в матче любого типа
        $msg = "Вы уже участвуете в матче (#{$activeMatch['id']}, статус={$activeMatch['status']}).";
    } else {
        $mapName = trim($_POST['map_name'] ?? 'Dust II');
        $region = trim($_POST['region'] ?? 'Москва');
        $mode = trim($_POST['mode'] ?? '1v1');
        $fixedFee = intval($_POST['fixed_fee'] ?? 1);
        if ($fixedFee < 1) $fixedFee = 1;

        $startIn = intval($_POST['start_in'] ?? 60);
        $participantsLimit = intval($_POST['participants_limit'] ?? 100);
        if ($participantsLimit < 2) $participantsLimit = 2;

        if ($user['tickets'] < $fixedFee) {
            $msg = "Недостаточно билетов (у вас {$user['tickets']}, нужно {$fixedFee}).";
        } else {
            $tournamentName = "Турнир от " . $user['personaname'];
            $start_time = date('Y-m-d H:i:s', strtotime("+$startIn minutes"));

            // Создаём запись в matches
            $stmtIns = $pdo->prepare("
                INSERT INTO matches 
                  (name, map_name, region, mode, status, created_by, tournament_type, entry_fee, start_time, participants_limit)
                VALUES 
                  (?,?,?,?,?,'tournament',?,?,?,?)
            ");
            $stmtIns->execute([
                $tournamentName,
                $mapName,
                $region,
                $mode,
                'Created',
                $user['id'],
                $fixedFee,
                $start_time,
                $participantsLimit
            ]);
            $tournId = $pdo->lastInsertId();

            // Списываем билеты
            $pdo->prepare("UPDATE users SET tickets = tickets - ? WHERE id=?")
                ->execute([$fixedFee, $user['id']]);

            // Добавляем создателя в match_players
            $pdo->prepare("
                INSERT INTO match_players (match_id, user_id, team, entry_fee)
                VALUES (?,?,1,?)
            ")->execute([$tournId, $user['id'], $fixedFee]);

            header("Location: match_lobby.php?match_id=" . $tournId);
            exit;
        }
    }
}

// Выбираем все турниры (tournament) со статусом Created или Live
$stmtList = $pdo->query("
    SELECT * 
      FROM matches
     WHERE tournament_type = '2'
       AND status IN ('Created','Live')
     ORDER BY id DESC
");
$tournaments = $stmtList->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>
<div class="container">
  <h2>Турниры</h2>
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
      <?php foreach ($tournaments as $t): 
          // Считаем кол-во игроков в командах
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

  <h3 style="margin-top:20px;">Создать турнир</h3>
  <?php if ($user): ?>
    <!-- Если у пользователя нет активного матча - показываем форму -->
    <?php if (!$activeMatch): ?>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="action" value="create_tourn">
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
            <option value="1v1">1v1</option>
            <option value="2v2">2v2</option>
            <option value="5v5">5v5</option>
          </select>
        </label><br/><br/>
        <label>Вступительный взнос (билеты):
          <input type="number" name="fixed_fee" step="1" min="1" value="1">
        </label><br/><br/>
        <label>Начало через (минут):
          <input type="number" name="start_in" step="1" min="5" value="60">
        </label><br/><br/>
        <label>Максимальное число участников:
          <input type="number" name="participants_limit" step="1" min="2" value="100">
        </label><br/><br/>
        <button type="submit" class="play-button">Создать турнир</button>
      </form>
    <?php else: ?>
      <p>Вы уже участвуете в матче (#<?= $activeMatch['id'] ?>, статус <?= htmlspecialchars($activeMatch['status']) ?>). 
         <a href="match_lobby.php?match_id=<?= $activeMatch['id'] ?>">Перейти в лобби</a></p>
    <?php endif; ?>
  <?php else: ?>
    <p>Авторизуйтесь через Steam, чтобы создавать турниры.</p>
  <?php endif; ?>
</div>
<?php require_once 'partials/footer.php'; ?>
