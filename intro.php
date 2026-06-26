<?php
session_start();
require_once('../config.php'); 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Erfasse Format-Modus (?view=portrait)
$is_portrait = (isset($_GET['view']) && $_GET['view'] === 'portrait');
$target_url = $is_portrait ? 'index.php?view=portrait&intro=done' : 'index.php?intro=done';

// --- SYSTEM CHECKS ---
$system_status = true; // Bleibt true, wenn alles glattläuft

// 1. Netzwerk / Internet (Ping zu einem DNS oder Gateway)
$network_check = false;
$connected = @fsockopen("www.google.com", 80, $errno, $errstr, 2);
if ($connected) {
    $network_check = true;
    fclose($connected);
} else {
    $system_status = false;
}

// 2. Webserver Check (Prüft ob localhost / Apache sauber antwortet)
$webserver_check = ($_SERVER['SERVER_SOFTWARE'] ?? false) ? true : false;
if (!$webserver_check) $system_status = false;

// 3. Datenbank Check
$db_check = false;
$test_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$test_conn->connect_error) {
    $db_check = true;
    $test_conn->close();
} else {
    $system_status = false;
}

// 4. ioBroker API Check (Nutzt deine definierte IP und Port aus der Config)
$iobroker_check = false;
$io_ip = defined('IO_BROKER_IP') ? IO_BROKER_IP : '192.168.1.23';
$io_port = defined('IO_BROKER_PORT') ? IO_BROKER_PORT : '8087';

$io_socket = @fsockopen($io_ip, $io_port, $errno, $errstr, 2);
if ($io_socket) {
    $iobroker_check = true;
    fclose($io_socket);
} else {
    $system_status = false;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Boot & Diagnostic</title>
    
    <?php if ($system_status): ?>
    <!-- NUR weiterleiten, wenn alle Systeme auf OK stehen! -->
    <meta http-equiv="refresh" content="20;url=<?= $target_url ?>">
    <?php endif; ?>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Courier New', Courier, monospace; /* Echter Terminal-Look */
            overflow: hidden;
        }

        .splash-content {
            text-align: center;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .splash-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 30px;
            animation: pulseLogo 2s ease-in-out infinite;
        }

        /* Diagnostic Log-Style */
        .diagnostic-log {
            text-align: left;
            background: #111;
            border: 1px solid #222;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .log-line {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .status-ok { color: #00ff66; font-weight: bold; }
        .status-error { color: #ff3333; font-weight: bold; animation: blinker 1s linear infinite; }

        .splash-loader {
            width: 35px;
            height: 35px;
            border: 3px solid #1a1a1a;
            border-top: 3px solid #00d2ff;
            border-radius: 50%;
            margin: 0 auto 15px auto;
            animation: spinLoader 1s linear infinite;
        }

        .splash-text {
            color: #00d2ff;
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .error-box {
            background: #2a0808;
            border: 1px solid #772222;
            color: #ffcccc;
            padding: 15px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.4;
            margin-top: 15px;
        }

        @keyframes spinLoader {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulseLogo {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }

        @keyframes blinker {
            50% { opacity: 0; }
        }
    </style>
</head>
<body>

<div class="splash-content">
    <!-- System Logo -->
    <img src="pi_SHS.png" alt="System Logo" class="splash-logo">

    <!-- Konsole / Log-Ausgabe -->
    <div class="diagnostic-log">
        <div class="log-line">
            <span>> NETZWERK VERBINDUNG:</span>
            <span class="<?= $network_check ? 'status-ok' : 'status-error' ?>"><?= $network_check ? 'OK' : 'NOT OK' ?></span>
        </div>
        <div class="log-line">
            <span>> WEBSERVER STATUS:</span>
            <span class="<?= $webserver_check ? 'status-ok' : 'status-error' ?>"><?= $webserver_check ? 'OK' : 'NOT OK' ?></span>
        </div>
        <div class="log-line">
            <span>> DATENBANK (MySQL):</span>
            <span class="<?= $db_check ? 'status-ok' : 'status-error' ?>"><?= $db_check ? 'OK' : 'NOT OK' ?></span>
        </div>
        <div class="log-line">
            <span>> IOBROKER API LINK:</span>
            <span class="<?= $iobroker_check ? 'status-ok' : 'status-error' ?>"><?= $iobroker_check ? 'OK' : 'NOT OK' ?></span>
        </div>
    </div>

    <!-- Status-Animation oder Fehlermeldung -->
    <?php if ($system_status): ?>
        <div class="splash-loader"></div>
        <div class="splash-text">System bereit...</div>
    <?php else: ?>
        <div class="status-error" style="font-size: 18px; margin-bottom: 10px;">BOOT_FAILURE</div>
        <div class="error-box">
            <strong>WARNUNG:</strong> Startvorgang angehalten. Ein oder mehrere kritische Systeme antworten nicht. Bitte Netzwerk oder Dienste prüfen.
        </div>
    <?php endif; ?>
</div>

</body>
</html>
