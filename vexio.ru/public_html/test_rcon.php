<?php
require_once 'inc/rcon.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
try {
    $rcon = new Rcon();
    $response = $rcon->send("say Test RCON from site via TCP!");
    echo "RCON response: " . $response;
} catch (Exception $e) {
    echo "RCON error: " . $e->getMessage();
}
?>