<?php
require_once 'db.php';
require_once 'rcon.php';

function deductTickets($user_id, $ticket_id, $amount) {
    // Заглушка: всегда возвращаем true, пока нет реальной системы билетов
    return true;
}

function addTickets($user_id, $ticket_id, $amount, $description = 'Победа в матче') {
    // Заглушка: ничего не делаем
}

function isUserInMatch($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT current_match_id FROM users WHERE id = ? AND current_match_id IS NOT NULL");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function joinMatch($user_id, $match_id, $ticket_amount, $team = 'team1') {
    global $pdo;
    if (!isUserInMatch($user_id)) { // Убрали deductTickets для теста
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, user_id, team) VALUES (?, ?, ?)");
        $stmt->execute([$match_id, $user_id, $team]);
        $stmt = $pdo->prepare("UPDATE users SET current_match_id = ? WHERE id = ?");
        $stmt->execute([$match_id, $user_id]);
        return true;
    }
    return false;
}

function leaveMatch($user_id, $match_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT bet_amount FROM matches WHERE id = ? AND lobby_active = TRUE");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();
    if ($match) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND user_id = ?");
        $stmt->execute([$match_id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            addTickets($user_id, 1, $match['bet_amount'], 'Возврат ставки');
            $stmt = $pdo->prepare("DELETE FROM match_players WHERE match_id = ? AND user_id = ?");
            $stmt->execute([$match_id, $user_id]);
            $stmt = $pdo->prepare("UPDATE users SET current_match_id = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ?");
            $stmt->execute([$match_id]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
                $stmt->execute([$match_id]);
            }
        }
    }
}

function joinQuickMatch($user_id, $tournament_id, $ticket_amount, $mode) {
    global $pdo;
    $max_players = $mode == '1v1' ? 2 : ($mode == '2v2' ? 4 : 10);
    $team_size = $max_players / 2;

    $stmt = $pdo->prepare("SELECT m.id FROM matches m WHERE m.tournament_id = ? AND m.status = 'pending' AND m.lobby_active = TRUE");
    $stmt->execute([$tournament_id]);
    $match = $stmt->fetch();

    if ($match) {
        $match_id = $match['id'];
        $stmt = $pdo->prepare("SELECT team, COUNT(user_id) as player_count FROM match_players WHERE match_id = ? GROUP BY team");
        $stmt->execute([$match_id]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($teams as $team) {
            if ($team['player_count'] < $team_size) {
                return joinMatch($user_id, $match_id, $ticket_amount, $team['team']);
            }
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, bet_amount, map, mode, end_time) VALUES (?, ?, 'de_dust2', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        $stmt->execute([$tournament_id, $ticket_amount, $mode]);
        $match_id = $pdo->lastInsertId();
        return joinMatch($user_id, $match_id, $ticket_amount, 'team1');
    }
    return false;
}

function finishMatch($match_id, $winning_team) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT bet_amount, mode FROM matches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
    $total_bet = $match['bet_amount'] * $max_players;
    $commission = $total_bet * 0.1;
    $prize = $total_bet - $commission;

    $stmt = $pdo->prepare("SELECT user_id FROM match_players WHERE match_id = ? AND team = ?");
    $stmt->execute([$match_id, $winning_team]);
    $winners = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($winners as $winner_id) {
        addTickets($winner_id, 1, $prize / count($winners), 'Победа в матче');
    }

    $stmt = $pdo->prepare("UPDATE matches SET status = 'finished', winner_id = ? WHERE id = ?");
    $stmt->execute([$winners[0], $match_id]);
    $stmt = $pdo->prepare("UPDATE users SET current_match_id = NULL WHERE current_match_id = ?");
    $stmt->execute([$match_id]);
}

function checkMatchCompletion($match_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT end_time, mode FROM matches WHERE id = ? AND status = 'ongoing'");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    if ($match && strtotime($match['end_time']) <= time()) {
        $winning_team = 'team1'; // Временная заглушка
        finishMatch($match_id, $winning_team);
        return $winning_team;
    }
    return null;
}
?>