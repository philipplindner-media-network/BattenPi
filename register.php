<?php
// Fehlerberichterstattung unterdrücken, um das Design sauber zu halten
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();

// config.php einbinden (muss DB_HOST, DB_USER, DB_PASS, DB_NAME enthalten)
require_once('config.php'); 

// --- KONSTANTEN ---
define('NFC_REGISTER_FILE', 'current_nfc_register_uid.txt'); 

// --- INITIALISIERUNG (Verhindert die "Undefined array key" Fehler) ---
if (!isset($_SESSION['reg_step'])) {
    $_SESSION['reg_step'] = 1;
}
if (!isset($_SESSION['reg_user'])) {
    $_SESSION['reg_user'] = ['username' => '', 'level_id' => 5, 'nfc_uid' => ''];
}

$error = '';
$message = '';

// 1. LOGIN-SCHUTZ (Admin-Check)
if (!isset($_SESSION['level_id']) || $_SESSION['level_id'] < 10) {
    header("Location: login.php?error=no_admin");
    exit;
}

// 2. DATENBANK-VERBINDUNG
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Reset-Logik
if (isset($_GET['reset'])) {
    unset($_SESSION['reg_step'], $_SESSION['reg_user']);
    if(file_exists(NFC_REGISTER_FILE)) file_put_contents(NFC_REGISTER_FILE, ""); 
    header("Location: register.php");
    exit;
}

// AJAX Polling für Schritt 2 (NFC Chip)
if (isset($_GET['check_nfc']) && $_SESSION['reg_step'] == 2) {
    header('Content-Type: application/json');
    if (file_exists(NFC_REGISTER_FILE) && filesize(NFC_REGISTER_FILE) > 0) {
        $uid = trim(file_get_contents(NFC_REGISTER_FILE));
        file_put_contents(NFC_REGISTER_FILE, ""); // Datei leeren nach Lesen
        echo json_encode(['status' => 'success', 'uid' => $uid]);
    } else {
        echo json_encode(['status' => 'waiting']);
    }
    exit;
}

// POST-VERARBEITUNG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Schritt 1 -> 2
    if (isset($_POST['go_to_step_2'])) {
        $_SESSION['reg_user']['username'] = htmlspecialchars($_POST['username']);
        $_SESSION['reg_user']['level_id'] = (int)$_POST['level_id'];
        $_SESSION['reg_step'] = 2;
        header("Location: register.php"); exit;
    } 
    // Schritt 2 -> 3
    elseif (isset($_POST['go_to_step_3'])) {
        $_SESSION['reg_user']['nfc_uid'] = htmlspecialchars($_POST['nfc_uid']);
        $_SESSION['reg_step'] = 3;
        header("Location: register.php"); exit;
    }
    // Speichern (Schritt 3)
    elseif (isset($_POST['save_user'])) {
        try {
            // SQL angepasst an deinen Screenshot (chip_uid, name, RFID, etc.)
            $sql = "INSERT INTO users (username, name, level_id, chip_uid, qr_code, barcode, RFID) 
                    VALUES (:username, :name, :level, :chip, :qr, :barcode, :rfid)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':username' => $_SESSION['reg_user']['username'],
                ':name'     => $_SESSION['reg_user']['username'], // 'name' ist Pflichtfeld in deiner DB
                ':level'    => $_SESSION['reg_user']['level_id'],
                ':chip'     => $_SESSION['reg_user']['nfc_uid'], 
                ':qr'       => !empty($_POST['qr_code']) ? $_POST['qr_code'] : null, 
                ':barcode'  => !empty($_POST['barcode']) ? $_POST['barcode'] : null,
                ':rfid'     => !empty($_POST['RFID']) ? $_POST['RFID'] : null 
            ]);
            
            $message = "Benutzer {$_SESSION['reg_user']['username']} wurde erfolgreich angelegt!";
            unset($_SESSION['reg_step'], $_SESSION['reg_user']);
        } catch (PDOException $e) {
            $error = "Fehler beim Speichern: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin Registrierung - Schritt <?= $_SESSION['reg_step'] ?></title>
	<style>
    @import url('https://fonts.googleapis.com/css2?family=Audiowide&display=swap');

    :root { 
        --accent: #00d2ff; 
        --bg: #0a0a0a; 
        --card: #161616; 
        --text: #eee; 
        --error: #ff4d4d;
        --success: #00ff88;
    }

    body { 
        background: var(--bg); 
        color: var(--text); 
        font-family: 'Segoe UI', Roboto, sans-serif; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        min-height: 100vh; 
        margin: 0; 
    }

    .container { 
        width: 100%;
        max-width: 450px; 
        background: var(--card); 
        padding: 40px; 
        border-radius: 20px; 
        border: 1px solid #333; 
        box-shadow: 0 20px 50px rgba(0,0,0,0.8), 0 0 15px rgba(0, 210, 255, 0.05); 
    }

    h2 { 
        font-family: 'Audiowide', cursive; 
        font-size: 1.8em;
        margin-top: 0; 
        text-align: center;
        letter-spacing: 1px;
        color: var(--text);
    }

    /* Fortschrittsbalken */
    .step-bar { 
        display: flex; 
        gap: 10px; 
        margin-bottom: 30px; 
    }
    .step { 
        flex: 1; 
        height: 6px; 
        background: #222; 
        border-radius: 10px; 
        transition: 0.5s;
    }
    .step.active { 
        background: var(--accent); 
        box-shadow: 0 0 10px var(--accent);
    }

    /* Formular Elemente */
    label { 
        display: block; 
        margin: 20px 0 8px; 
        color: var(--accent); 
        font-weight: bold; 
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    input, select { 
        width: 100%; 
        padding: 14px; 
        background: #222; 
        border: 1px solid #444; 
        color: #fff; 
        border-radius: 10px; 
        box-sizing: border-box; 
        font-size: 1em;
        transition: 0.3s;
    }

    input:focus, select:focus {
        border-color: var(--accent);
        outline: none;
        box-shadow: 0 0 8px rgba(0, 210, 255, 0.3);
    }

    /* Buttons */
    .btn { 
        padding: 15px 25px; 
        border: none; 
        border-radius: 10px; 
        cursor: pointer; 
        font-weight: bold; 
        margin-top: 25px; 
        width: 100%; 
        display: block; 
        text-align: center; 
        text-decoration: none; 
        font-size: 1em;
        transition: 0.3s;
    }

    .btn-blue { 
        background: var(--accent); 
        color: #000; 
        box-shadow: 0 4px 15px rgba(0, 210, 255, 0.3);
    }
    .btn-blue:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 210, 255, 0.5);
    }

    .btn-green { 
        background: var(--success); 
        color: #000; 
    }

    .btn-reset { 
    background: transparent; 
    color: #666; 
    font-size: 0.8em; 
    margin: 20px auto 0 auto; /* Zentriert den Button */
    padding: 8px 15px;
    width: auto; /* Nimmt nur so viel Platz ein wie der Text braucht */
    display: table; /* Notwendig für die Zentrierung mit margin auto */
    border: 1px solid #333;
    border-radius: 8px;
    transition: 0.3s;
}

.btn-reset:hover {
    color: #ff4d4d;
    border-color: #ff4d4d;
    background: rgba(255, 77, 77, 0.05);
}

    #status_text {
        font-family: 'Rock Salt', cursive;
        font-size: 0.8em;
    }

    /* Nachrichten */
    .msg {
        text-align: center;
        padding: 10px;
        border-radius: 8px;
        font-weight: bold;
    }
</style>
</head>
<body>

<div class="container">
    <h2>👤 User Registrierung</h2>
    
    <div class="step-bar">
        <div class="step <?= $_SESSION['reg_step'] >= 1 ? 'active' : '' ?>"></div>
        <div class="step <?= $_SESSION['reg_step'] >= 2 ? 'active' : '' ?>"></div>
        <div class="step <?= $_SESSION['reg_step'] >= 3 ? 'active' : '' ?>"></div>
    </div>

    <?php if($message): ?>
        <p style="color: #2ecc71; text-align: center;"><?= $message ?></p>
        <a href="register.php" class="btn btn-blue">Weiteren Benutzer anlegen</a>
    <?php else: ?>

        <form method="POST" id="regForm" onkeydown="return event.key != 'Enter';">

            <?php if ($_SESSION['reg_step'] == 1): ?>
                <label>Benutzername</label>
                <input type="text" name="username" required autofocus placeholder="z.B. Max">
                <label>Berechtigungs-Level</label>
                <select name="level_id">
                    <option value="1">Level 1 (Gast)</option>
                    <option value="5" selected>Level 5 (User)</option>
                    <option value="10">Level 10 (Admin)</option>
                </select>
                <button type="submit" name="go_to_step_2" class="btn btn-blue">Weiter zu NFC ➔</button>

            <?php elseif ($_SESSION['reg_step'] == 2): ?>
                <label>NFC Chip (Warte auf Ubuntu-PC...)</label>
                <input type="text" name="nfc_uid" id="nfc_field" readonly placeholder="Bitte Chip am Reader auflegen">
                <p id="status_text" style="font-size: 0.8em; color: #888; text-align: center;">Suche Chip...</p>
                <button type="submit" name="go_to_step_3" class="btn btn-blue" id="next_btn" style="display:none;">Weiter zu Scanner-Codes ➔</button>
                
                <script>
                function pollNFC() {
                    fetch('register.php?check_nfc=1')
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            document.getElementById('nfc_field').value = data.uid;
                            document.getElementById('status_text').innerText = "✅ Chip erkannt!";
                            document.getElementById('status_text').style.color = "#2ecc71";
                            document.getElementById('next_btn').style.display = "block";
                        } else {
                            setTimeout(pollNFC, 1000);
                        }
                    });
                }
                pollNFC();
                </script>

            <?php elseif ($_SESSION['reg_step'] == 3): ?>
                <label>QR-Code (optional)</label>
                <input type="text" name="qr_code" placeholder="QR-Code scannen...">
                <label>Barcode (optional)</label>
                <input type="text" name="barcode" placeholder="Barcode scannen...">
                <label>RFID Code (optional)</label>
                <input type="text" name="RFID" placeholder="RFID scannen...">
                
                <input type="hidden" name="save_user" value="1">
                <button type="button" onclick="document.getElementById('regForm').submit();" class="btn btn-green">Registrierung abschließen ✓</button>
            <?php endif; ?>

            <a href="register.php?reset=1" class="btn btn-reset">Abbrechen & Neustart</a>
        </form>
    <?php endif; ?>

    <?php if($error): ?>
        <p style="color: #e74c3c; font-size: 0.8em; margin-top: 15px;"><?= $error ?></p>
    <?php endif; ?>
</div>

</body>
</html>
