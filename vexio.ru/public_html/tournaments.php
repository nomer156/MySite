<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';
require_once 'inc/steam_api.php';

if (!isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->query("SELECT id, name, type, entry_cost, prize_pool, commission, status, mode, max_players FROM tournaments WHERE status = 'open'");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quick'])) {
    $bet_amount = (int)$_POST['bet_amount'];
    $mode = $_POST['mode'];
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    if ($bet_amount >= 10 && !isUserInMatch($user_id)) {
        $stmt = $pdo->prepare("INSERT INTO tournaments (name, type, start_date, status, entry_cost, prize_pool, commission, is_private, mode, max_players) VALUES (?, 'quick', NOW(), 'open', ?, 0, 10, ?, ?, ?)");
        $stmt->execute(["Custom $mode ($bet_amount TP)", $bet_amount, $is_private, $mode, $mode == '1v1' ? 2 : ($mode == '2v2' ? 4 : 10)]);
        $tournament_id = $pdo->lastInsertId();
        
        $match_id = joinQuickMatch($user_id, $tournament_id, $bet_amount, $mode);
        if ($match_id) {
            header("Location: lobby.php?match_id=$match_id");
            exit;
        }
    } else {
        $error = "Недостаточно TP или вы уже в матче.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_match'])) {
    $match_id = (int)$_POST['match_id'];
    $current_match = isUserInMatch($user_id);
    if ($current_match == $match_id) {
        header("Location: lobby.php?match_id=$match_id");
        exit;
    }
    $stmt = $pdo->prepare("SELECT m.*, t.entry_cost FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ? AND m.status = 'pending'");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    if ($match) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ?");
        $stmt->execute([$match_id]);
        $player_count = $stmt->fetchColumn();
        $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
        if ($player_count < $max_players) {
            if (joinMatch($user_id, $match_id, $match['entry_cost'], $player_count % 2 == 0 ? 'team1' : 'team2')) {
                if ($player_count + 1 == $max_players) {
                    $stmt = $pdo->prepare("UPDATE matches SET status = 'ongoing' WHERE id = ?");
                    $stmt->execute([$match_id]);
                }
                header("Location: lobby.php?match_id=$match_id");
                exit;
            } else {
                $error = "Недостаточно TP.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tournament_id'])) {
    $tournament_id = (int)$_POST['tournament_id'];
    $stmt = $pdo->prepare("SELECT type, entry_cost, mode FROM tournaments WHERE id = ? AND status = 'open'");
    $stmt->execute([$tournament_id]);
    $tournament = $stmt->fetch();

    if ($tournament && !isUserInMatch($user_id)) {
        if ($tournament['type'] == 'quick') {
            $match_id = joinQuickMatch($user_id, $tournament_id, $tournament['entry_cost'], $tournament['mode']);
        } else {
            $match_id = joinBracketTournament($user_id, $tournament_id, $tournament['entry_cost'], $tournament['mode']);
        }
        if ($match_id) {
            header("Location: lobby.php?match_id=$match_id");
            exit;
        } else {
            $error = "Не удалось присоединиться.";
        }
    } else {
        $error = "Недостаточно TP или вы уже в матче.";
    }
}

$match_id = isUserInMatch($user_id);
$current_match = null;
if ($match_id) {
    $stmt = $pdo->prepare("SELECT m.*, t.name as tournament_name, t.type, t.entry_cost FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ?");
    $stmt->execute([$match_id]);
    $current_match = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Турниры</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="tournaments">
    <header>
        <a href="index.php" class="logo"><h1><?php echo SITE_TITLE; ?></h1></a>
        <nav>
            <a href="index.php" class="nav-btn">Главная</a>
            <a href="tournaments.php" class="nav-btn">Турниры</a>
            <a href="profile.php" class="nav-btn">Профиль</a>
            <a href="rewards.php" class="nav-btn">Призы</a>
            <a href="rules.php" class="nav-btn">Правила</a>
            <?php if ($match_id): ?>
                <a href="lobby.php?match_id=<?php echo $match_id; ?>" class="nav-btn active">Лобби</a>
            <?php endif; ?>
            <a href="?logout" class="nav-btn">Выйти</a>
        </nav>
    </header>

    <main>
        <section class="tournaments">
            <h2>Создать быстрый матч</h2>
            <form method="POST" class="create-match">
                <div class="form-group">
                    <label>Ставка (TP):</label>
                    <input type="number" name="bet_amount" min="10" required>
                </div>
                <div class="form-group">
                    <label>Режим:</label>
                    <select name="mode" required>
                        <option value="1v1">1v1</option>
                        <option value="2v2">2v2</option>
                        <option value="5v5">5v5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Карта:</label>
                    <select name="map" disabled>
                        <option value="de_dust2">de_dust2</option>
                    </select>
                </div>
                <div class="form-group checkbox">
                    <label><input type="checkbox" name="is_private"> Приватный</label>
                </div>
                <button type="submit" name="create_quick" class="action-btn">Создать</button>
            </form>

            <h2>Активные матчи</h2>
            <?php if (isset($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <div class="tournament-list" id="active-matches">
                <!-- Здесь будут динамически обновляться матчи -->
            </div>

            <h2>Доступные турниры</h2>
            <div class="tournament-list">
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="tournament-card">
                        <h3><?php echo htmlspecialchars($tournament['name']); ?></h3>
                        <p>Тип: <?php echo $tournament['type'] == 'quick' ? 'Быстрый' : 'Сетка'; ?></p>
                        <p>Режим: <?php echo htmlspecialchars($tournament['mode']); ?></p>
                        <p><?php echo $tournament['type'] == 'quick' ? 'Ставка' : 'Вход'; ?>: <?php echo $tournament['entry_cost']; ?> TP</p>
                        <?php if ($tournament['type'] == 'quick'): ?>
                            <p>Комиссия: <?php echo $tournament['commission']; ?>%</p>
                        <?php else: ?>
                            <p>Призовой фонд: <?php echo $tournament['prize_pool']; ?> TP</p>
                            <p>Игроков: <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mp.user_id) FROM match_players mp JOIN matches m ON mp.match_id = m.id WHERE m.tournament_id = ? AND m.stage = 'round_16'");
                                $stmt->execute([$tournament['id']]);
                                echo $stmt->fetchColumn() . '/' . $tournament['max_players'];
                            ?></p>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="tournament_id" value="<?php echo $tournament['id']; ?>">
                            <button type="submit" class="action-btn">Присоединиться</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script>
    function updateActiveMatches() {
        fetch('api/active_matches.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('active-matches');
                container.innerHTML = '';
                data.forEach(match => {
                    const isCurrentMatch = <?php echo $match_id ? "match.id == $match_id" : 'false'; ?>;
                    let html = `<div class="tournament-card${isCurrentMatch ? ' current-match' : ''}">`;
                    html += `<h3>${match.tournament_name} (${match.mode})</h3>`;
                    html += `<p>Статус: ${match.status == 'pending' ? 'Ожидание' : 'В процессе'}${isCurrentMatch ? ' (Ваш матч)' : ''}</p>`;
                    html += `<div class="match-players">`;
                    html += `<div class="team"><p>Спецназ</p>`;
                    match.team1.forEach(player => {
                        html += `<div class="player-info"><img src="${player.avatar}" alt="Avatar" class="avatar small"><span>${player.username}</span></div>`;
                    });
                    html += `</div>`;
                    html += `<div class="vs">VS</div>`;
                    html += `<div class="team"><p>Террористы</p>`;
                    match.team2.forEach(player => {
                        html += `<div class="player-info"><img src="${player.avatar}" alt="Avatar" class="avatar small"><span>${player.username}</span></div>`;
                    });
                    html += `</div>`;
                    html += `</div>`;
                    html += `<p>Карта: ${match.map}</p>`; // Добавляем карту
                    html += `<p>Игроков: <span id="player-count-${match.id}">${match.players_count}</span>/${match.max_players}</p>`;
                    html += `<p>Ставка: ${match.bet_amount} TP</p>`;
                    html += `<p>Приз: ${match.bet_amount * match.max_players} TP</p>`;
                    html += `<p>До конца: <span id="timer-${match.id}">${match.end_time}</span> мин</p>`;
                    if (!isCurrentMatch && match.players_count < match.max_players && match.status === 'pending') {
                        html += `<form method="POST"><input type="hidden" name="match_id" value="${match.id}"><button type="submit" name="join_match" class="action-btn">Присоединиться</button></form>`;
                    } else {
                        html += `<a href="lobby.php?match_id=${match.id}" class="action-btn">${isCurrentMatch ? 'Вернуться в лобби' : 'Наблюдать'}</a>`;
                    }
                    html += `</div>`;
                    container.insertAdjacentHTML('beforeend', html);
                });
            })
            .catch(error => console.error('Error updating matches:', error));
    }

    setInterval(updateActiveMatches, 5000);
    updateActiveMatches();
    </script>
</body>
</html>