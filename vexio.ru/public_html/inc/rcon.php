<?php

class Rcon {
    private $host = '46.174.52.43';
    private $port = 27015;
    private $password = 'ciAlweYf28NwXorGsSLs';
    private $socket;

    public function __construct() {
        echo "Connecting to {$this->host}:{$this->port} via TCP...<br>";
        $this->socket = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, 5);
        if (!$this->socket) {
            throw new Exception("RCON connection failed: $errstr ($errno)");
        }
        echo "Socket opened successfully.<br>";
    }

    public function send($command) {
        echo "Sending command: $command<br>";
        $packet = "rcon {$this->password} {$command}\n";
        echo "Sending raw packet: " . bin2hex($packet) . "<br>";
        fwrite($this->socket, $packet);
        stream_set_timeout($this->socket, 5);
        $response = fread($this->socket, 4096);
        echo "Command response: " . bin2hex($response ?: "No data") . "<br>";
        return $response ?: "Command executed: $command";
    }

    public function setPlayerTeam($steam_id, $team) {
        $team_id = $team == 'team1' ? 3 : 2;
        return $this->send("sm_team $steam_id $team_id");
    }

    public function startMatch() {
        $this->send("mp_restartgame 1");
        $this->send("mp_teamlock 1");
    }

    public function endMatch() {
        $this->send("kickall");
    }

    public function getMatchResult() {
        return rand(0, 1) ? 'team1' : 'team2';
    }

    public function __destruct() {
        if ($this->socket) {
            fclose($this->socket);
            echo "Socket closed.<br>";
        }
    }
}
?>