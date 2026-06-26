<?php
session_start();
include('config.php');

// --- KONFIGURATION & IP-ERKENNUNG ---
$UID_FILE_PATH = 'current_nfc_uid_web.txt'; 
$redirect_url = './'; 
$secret = "smarhomes-systems-123456_test"; // MUSS mit 2fa_gen.php übereinstimmen!

// --- VERBESSERTE IP-ERKENNUNG ---
if (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1') {
    $local_ip = $_SERVER['SERVER_ADDR'];
} else {
    // Fallback: Versuche die lokale IP über das Betriebssystem zu finden
    $local_ip = exec("hostname -I | cut -d' ' -f1"); 
}

// Falls alles fehlschlägt, manuelle IP als Sicherheit (dein Pi im Screenshot)
if (empty($local_ip) || $local_ip == '127.0.0.1') {
    $local_ip = "192.168.1.25"; 
}

$two_fa_url = "http://" . $local_ip . ":8888/smarhome/v2/battenPi/2fg_gen.php";
$qr_link_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($two_fa_url);

function setLoginSession($user, $code_used) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['level_id'] = $user['level_id'];
    
    // Bestimmung der Login-Methode
    if (!empty($user['chip_uid']) && $user['chip_uid'] === $code_used) { $method = 'NFC-Chip'; }
    elseif (!empty($user['RFID']) && $user['RFID'] === $code_used) { $method = 'RFID-Karte'; }
    elseif (!empty($user['qr_code']) && $user['qr_code'] === $code_used) { $method = 'QR-Code'; }
    elseif (!empty($user['barcode']) && $user['barcode'] === $code_used) { $method = 'Barcode'; }
    else { $method = '2FA-Backup'; }

    $_SESSION['login_method'] = $method;
}

// 1. NFC/RFID AJAX Check (Hintergrund)
if (isset($_GET['check_nfc']) && $_GET['check_nfc'] === 'true') {
    header('Content-Type: application/json');
    if (!file_exists($UID_FILE_PATH) || filesize($UID_FILE_PATH) === 0) {
        echo json_encode(['status' => 'waiting']);
        exit;
    }
    $scanned_code = trim(file_get_contents($UID_FILE_PATH));
    file_put_contents($UID_FILE_PATH, ''); 

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $stmt = $conn->prepare("SELECT * FROM users WHERE chip_uid = ? OR RFID = ? OR barcode = ?");
    $stmt->bind_param("sss", $scanned_code, $scanned_code, $scanned_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        setLoginSession($user, $scanned_code);
        echo json_encode(['status' => 'success', 'redirect' => $redirect_url]);
    } else {
        echo json_encode(['status' => 'unknown', 'message' => 'Code unbekannt']);
    }
    $stmt->close(); $conn->close();
    exit;
}

// 2. Manueller POST Check (2FA oder Scanner)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_code'])) {
    header('Content-Type: application/json');
    $scanned_code = trim($_POST['access_code']);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // --- NEU: DYNAMISCHER REDIRECT FÜR PORTRAIT-MODUS ---
    $view_mode = isset($_GET['view']) ? $_GET['view'] : '';
    if ($view_mode === 'portrait') {
        $redirect_url = './?view=portrait';
    } else {
        $redirect_url = './';
    }

    // --- 2FA BACKUP LOGIK ---
    $time_slice = floor(time() / 60);
    $check_current = substr(hash('sha256', $secret . $time_slice), 0, 6);
    $check_past    = substr(hash('sha256', $secret . ($time_slice - 1)), 0, 6);

    if (strtoupper($scanned_code) === strtoupper($check_current) || strtoupper($scanned_code) === strtoupper($check_past)) {
        // Suche den ersten Bewohner/Admin (Level >= 5) für Notfall-Login
        $res = $conn->query("SELECT * FROM users WHERE level_id >= 5 ORDER BY level_id DESC LIMIT 1");
        if ($user = $res->fetch_assoc()) {
            setLoginSession($user, $scanned_code);
            echo json_encode(['status' => 'success', 'redirect' => $redirect_url]);
            exit;
        }
    }

    // Normaler DB Check
    $stmt = $conn->prepare("SELECT * FROM users WHERE chip_uid = ? OR RFID = ? OR barcode = ? OR qr_code = ?");
    $stmt->bind_param("ssss", $scanned_code, $scanned_code, $scanned_code, $scanned_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        setLoginSession($user, $scanned_code);
        echo json_encode(['status' => 'success', 'redirect' => $redirect_url]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ungültiger Code']);
    }
    $stmt->close(); $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Smart Home Login</title>
    <style>
        body { background: #050505; color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; overflow: hidden; }
        .login-card { background: #111; padding: 30px; border-radius: 25px; border: 2px solid #222; text-align: center; width: 300px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        .status-icon { font-size: 40px; margin-bottom: 10px; display: block; }
        #status-text { color: #888; font-size: 13px; margin-bottom: 20px; }
        .input-field { background: #1a1a1a; border: 1px solid #333; color: white; padding: 12px; width: 100%; border-radius: 10px; text-align: center; font-size: 20px; letter-spacing: 4px; outline: none; box-sizing: border-box; }
        .input-field:focus { border-color: #00d2ff; }
        .qr-section { margin-top: 25px; border-top: 1px solid #222; padding-top: 20px; }
        .qr-label { font-size: 9px; color: #555; margin-bottom: 10px; letter-spacing: 1px; }
        .qr-code-img { border: 5px solid white; border-radius: 5px; width: 110px; opacity: 0.7; transition: opacity 0.3s; }
        .qr-code-img:hover { opacity: 1; }
    </style>
</head>
<body>

<div class="login-card">
    <span class="status-icon" id="status-icon">📡</span>
    <h2 style="margin: 0 0 5px 0; font-weight: 300; letter-spacing: 2px;">Smart Home</h2>
    <div id="status-text">Warte auf Scan...</div>
    
    <form id="login-form">
        <input type="password" name="access_code" id="access_code" class="input-field" placeholder="••••••" autocomplete="off">
    </form>
    
    <div class="qr-section">
        <div class="qr-label">SMARTPHONE 2FA GENERATOR</div>
        <img src="<?= $qr_link_url ?>" class="qr-code-img" alt="2FA Link">
    </div>

    <div style="margin-top: 15px; font-size: 9px; color: #444; text-transform: uppercase; letter-spacing: 1px;">
        NFC • RFID • 2FA BACKUP
    </div>
</div>

<script>
    const loginForm = document.getElementById('login-form');
    const accessInput = document.getElementById('access_code');

    // Holt den aktuellen View-Parameter aus der URL-Adresszeile
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view') || '';

    document.addEventListener('click', () => accessInput.focus());
    document.addEventListener('DOMContentLoaded', () => {
        accessInput.focus();
        checkExternalScanner();
    });

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Parameter an den POST-Endpunkt anhängen
        fetch('login.php?view=' + encodeURIComponent(viewParam), { 
            method: 'POST', 
            body: new FormData(loginForm) 
        })
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                window.location.href = data.redirect;
            } else {
                document.getElementById('status-text').innerText = data.message;
                document.getElementById('status-text').style.color = "#ff4d4d";
                accessInput.value = '';
            }
        });
    });

    function checkExternalScanner() {
        fetch('login.php?check_nfc=true')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                // Falls wir im Portrait Modus sind, hängen wir den Parameter auch beim NFC Login an
                if (viewParam === 'portrait') {
                    window.location.href = data.redirect + "?view=portrait";
                } else {
                    window.location.href = data.redirect;
                }
            } else {
                setTimeout(checkExternalScanner, 1000);
            }
        }).catch(() => setTimeout(checkExternalScanner, 2000));
    }
</script>
</body>
</html>
