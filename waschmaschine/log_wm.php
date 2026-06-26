<?php
require_once('../config.php');
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) { 
    file_put_contents('wm_debug.txt', date('Y-m-d H:i:s') . " - DB Fehler\n", FILE_APPEND);
    die("DB Fehler"); 
}

$watt = isset($_GET['watt']) ? floatval($_GET['watt']) : 0;
$prog = isset($_GET['prog']) ? $_GET['prog'] : 'Standby';
$volt = isset($_GET['volt']) ? floatval($_GET['volt']) : 230;

// Logge JEDEN Aufruf in eine Textdatei, um zu sehen, ob ioBroker überhaupt anklopft
file_put_contents('wm_debug.txt', date('Y-m-d H:i:s') . " - Aufruf erhalten: Watt=$watt, Prog=$prog\n", FILE_APPEND);

if ($watt > 0.5) {
    $stmt = $conn->prepare("INSERT INTO wm_protocol (program_name, wattage, voltage) VALUES (?, ?, ?)");
    $stmt->bind_param("sdd", $prog, $watt, $volt);
    if(!$stmt->execute()){
        file_put_contents('wm_debug.txt', date('Y-m-d H:i:s') . " - SQL Fehler: " . $stmt->error . "\n", FILE_APPEND);
    }
    $stmt->close();
}
$conn->close();
echo "Logged: $watt W";
?>
