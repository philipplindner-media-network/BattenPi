<?php
// Unterdrückt Warnungen für sauberen JS-Start
error_reporting(E_ERROR | E_PARSE); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('config.php');

if (!isset($_SESSION['user_id'])) {
    $view_mode = isset($_GET['view']) ? $_GET['view'] : '';
    if ($view_mode === 'portrait') {
        header("Location: login.php?view=portrait");
    } else {
        header("Location: login.php");
    }
    exit;
}

$username = $_SESSION['username'] ?? 'Gast';
$level_id = $_SESSION['level_id'] ?? '0';

// --- MANUELLE HOCHKANT-ERKENNUNG (?view=portrait) ---
$is_portrait = (isset($_GET['view']) && $_GET['view'] === 'portrait');
$view_query = $is_portrait ? '&view=portrait' : '';

// Grid-Einstellungen (wird bei Portrait auf 2 Spalten erzwungen)
if ($is_portrait) {
    $grid_cols = 2;
    $grid_rows = 6;
} else {
    $grid_cols = defined('GRID_COLUMNS') ? GRID_COLUMNS : 3;
    $grid_rows = defined('GRID_ROWS') ? GRID_ROWS : 4;
}
$max_per_page = ($grid_cols * $grid_rows);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Datenbank-Fehler: " . $conn->connect_error);
}

$current_menu = isset($_GET['menu']) ? intval($_GET['menu']) : 0;
$page = isset($_GET['p']) ? max(0, intval($_GET['p'])) : 0;

// Berechne Limit
$limit = $max_per_page - 1; 
$offset = $page * $limit;

// 1. Zähle alle Buttons für dieses Menü
$count_query = "SELECT COUNT(*) as total FROM remote_buttons WHERE parent_id = $current_menu";
$total_res = $conn->query($count_query);
$total_buttons = $total_res->fetch_assoc()['total'];

// 2. Lade nur die Buttons für die aktuelle Seite
$query = "SELECT * FROM remote_buttons 
          WHERE parent_id = $current_menu 
          ORDER BY (type = 'folder') DESC, label ASC 
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

$has_more = ($offset + $limit) < $total_buttons;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .grid { 
            display: grid; 
            grid-template-columns: repeat(<?= $grid_cols ?>, 1fr); 
            grid-template-rows: repeat(<?= $grid_rows ?>, 1fr); 
            gap: 15px; padding: 15px; height: calc(100vh - 80px); box-sizing: border-box; 
        }
        .status-info { font-size: 8px !important; color: #666; line-height: 1.1; word-break: break-all; margin-top: auto; opacity: 0.6; }
        .nav-btn { border: 2px dashed #444 !important; background: rgba(255,255,255,0.05) !important; }
        .button { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 10px !important; position: relative; }
        
        /* Modal Design */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: #1a1a1a; margin: 5% auto; padding: 20px;
            border: 1px solid var(--accent); width: 85%; max-width: 500px; border-radius: 15px; color: white;
        }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #888; }

        /* --- NEU: INTEGRATION DER LADEANIMATION (SPINNER) --- */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top-color: var(--accent, #00d2ff);
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin: 5px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<?php if ($level_id >= 10): ?>
    <div class="admin-top-bar">
        <div class="admin-info">
            <span class="admin-badge">SYSTEM ADMIN</span>
            <span class="admin-status">Studio Control Center</span>
        </div>
        <div class="admin-nav">
            <a href="register.php" class="admin-link">👤 Registrierung</a>
            <a href="admin.php" class="admin-link">⚙️ Admin</a>
        </div>
    </div>
<?php endif; ?>

<header>
    <div class="header-left">
        <div style="font-size: 10px; color: #777;">ANGEMELDET ALS:</div>
        <div class="user-name <?= ($level_id >= 10) ? 'admin-glow' : '' ?>">
            <?= htmlspecialchars($username) ?> (Lvl <?= $level_id ?>)
        </div>
    </div>
    <div class="header-center">
        <div id="clock">00:00:00</div>
        <div id="date">Wird geladen...</div>
    </div>
    <div class="header-right">
        <button onclick="triggerGlobalReload();" class="action-btn">🔄</button>
        <a href="logout.php" class="logout-btn">LOGOUT</a>
    </div>
</header>
<div class="grid">
    <?php if ($page > 0): ?>
        <a href="?menu=<?= $current_menu ?>&p=<?= $page - 1 ?><?= $view_query ?>" class="button nav-btn">
            <div class="icon">⬅️</div><div class="label">Seite <?= $page ?></div>
        </a>
    <?php elseif ($current_menu != 0): ?>
        <a href="?menu=0<?= $view_query ?>" class="button">
            <div class="icon">🔙</div><div class="label">Zurück</div>
        </a>
    <?php endif; ?>

    <?php if ($page == 0 && $current_menu == 0): ?>
        <div class="button" onclick="openCalendar()">
            <div class="icon">📅</div>
            <div class="label">Termine</div>
            <div id="cal-preview" class="live-val" style="font-size: 0.8em; color: var(--accent);"><div class="spinner"></div></div>
        </div>
    <?php endif; ?>

    <?php while($row = $result->fetch_assoc()):
        $lowLabel = strtolower($row['label']);
        $status_id = (!empty($row['status_id']) && $row['status_id'] !== '-') ? $row['status_id'] : '';
        $isWarning = (strpos($lowLabel, 'wetterwarnung') !== false);

        // --- LEVEL LOGIK ---
        $btnLevel = isset($row['min_level']) ? (int)$row['min_level'] : 1;
        $canControl = false;

        if ($level_id >= 10) {
            $canControl = true;
        } elseif ($level_id >= 5) {
            $canControl = ($btnLevel < 10);
        }

        if ($row['type'] === 'link'): ?>
            <a href="waschmaschine/index.php" class="button" style="text-decoration:none; color:inherit; border: 2px solid var(--accent);">
                <div class="icon">🧼</div>
                <div class="label"><?= htmlspecialchars($row['label']) ?></div>
                <div class="live-val" style="font-size: 0.9em; color: var(--accent);">Menü öffnen</div>
            </a>
        <?php continue; endif;

        $unit = '';
        if (strpos($lowLabel, 'luftdruck') !== false) $unit = ' hPa';
        elseif (strpos($lowLabel, 'feuchte') !== false) $unit = ' %';
        elseif (strpos($lowLabel, 'ldr') !== false || strpos($lowLabel, 'helligkeit') !== false) $unit = ' %';
        elseif (strpos($lowLabel, 'temperatur') !== false || strpos($lowLabel, 'stube') !== false || strpos($lowLabel, 'schlafzimmer') !== false || strpos($lowLabel, 'flur') !== false || strpos($lowLabel, 'bad') !== false || strpos($lowLabel, 'küche') !== false) $unit = ' °C';
        elseif (strpos($lowLabel, 'cpu') !== false || strpos($lowLabel, 'ram') !== false) $unit = ' %';
        elseif (strpos($lowLabel, 'speed') !== false) $unit = ' Mbit/s';
        elseif (strpos($lowLabel, 'feinstaub') !== false) $unit = ' µg/m³';
        elseif (strpos($lowLabel, 'stromkreis') !== false || strpos($lowLabel, 'rack') !== false) $unit = ' W';
    ?>

    <div class="button" id="btn_<?= $row['id'] ?>"
         onclick="<?= ($isWarning) ? "showWarning(this)" : "handleBtnClick(this, event)" ?>"
         data-io-id="<?= $row['io_id'] ?>"
         data-status-id="<?= $status_id ?>"
         data-dimmer-id="<?= $row['dimmer_id'] ?>"
         data-type="<?= $row['type'] ?>"
         data-unit="<?= $unit ?>">

        <div class="icon">
            <?php
           if (!$canControl && $level_id < 5) {
                echo '🔒';
            }
            elseif ($isWarning) echo '⚠️';
            elseif($row['type'] == 'folder') echo '📁';
            elseif(strpos($lowLabel, 'wasch') !== false) echo '🧺';
            elseif(strpos($lowLabel, 'luftdruck') !== false) echo '⏲️';
            elseif(strpos($lowLabel, 'feuchte') !== false) echo '💧';
            elseif(strpos($lowLabel, 'ldr') !== false || strpos($lowLabel, 'helligkeit') !== false) echo '☀️';
            elseif($row['type'] == 'toggle' || strpos($lowLabel, 'licht') !== false) echo '💡';
            elseif(strpos($lowLabel, 'bad') !== false) echo '🛁';
            elseif(strpos($lowLabel, 'küche') !== false) echo '🍳';
            elseif(strpos($lowLabel, 'schlafzimmer') !== false || strpos($lowLabel, 'stube') !== false || strpos($lowLabel, 'flur') !== false) echo '🌡️';
            elseif(strpos($lowLabel, 'cpu') !== false) echo '🧠';
            elseif(strpos($lowLabel, 'feinstaub') !== false || strpos($lowLabel, 'pm2.5') !== false) echo '💨';
            else echo '🔌';
            ?>
        </div>

        <div class="label"><?= htmlspecialchars($row['label']) ?></div>

        <div class="live-val" style="font-weight: bold; font-size: 1.2em;">
            <?php
                if ($row['type'] === 'folder') {
                    echo '<span style="font-size: 0.7em; color: var(--accent);">Öffnen</span>';
                } else {
                    echo $isWarning ? 'Details' : '<div class="spinner"></div>';
                }
            ?>
        </div>

        <div class="power-val"></div>
        <div class="status-info">ID: <?= $row['id'] ?> | SID: <?= htmlspecialchars($row['io_id']) ?></div>

        <?php if(!empty($row['dimmer_id']) && $row['type'] !== 'toggle'): ?>
            <div class="dim-btn" onclick="openControl(event, '<?= htmlspecialchars($row['label']) ?>', '<?= $row['dimmer_id'] ?>', '<?= $row['color_id'] ?>')">🔆</div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>

    <?php if ($has_more): ?>
        <a href="?menu=<?= $current_menu ?>&p=<?= $page + 1 ?><?= $view_query ?>" class="button nav-btn">
            <div class="icon">➡️</div><div class="label">Nächste Seite</div>
        </a>
    <?php endif; ?>
</div>

<div id="calendar-modal" class="modal" onclick="closeCalendar(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close" onclick="closeCalendar(event)">&times;</span>
        <h3 style="color: var(--accent); margin-top: 0;">📅 Anstehende Termine</h3>
        <hr style="border: 0; border-top: 1px solid #333; margin-bottom: 15px;">
        <div id="calendar-full-content" style="text-align: left; line-height: 1.6; max-height: 60vh; overflow-y: auto;">
            <div class="spinner"></div>
        </div>
    </div>
</div>

<div id="ctrlModal" class="modal">
    <div class="modal-content" style="position:relative; min-width:350px;">
        <span onclick="closeControl()" class="close">✕</span>
        <h3 id="modalTitle" style="margin-top:0; color: var(--accent);">Steuerung</h3>
        <div id="dimmerSection" style="display:none; margin: 20px 0;">
            <input type="range" id="dimRange" min="0" max="100" style="width:100%;" oninput="setDimmer(this.value)">
        </div>
        <div id="colorSection" style="display:none; margin: 20px 0;">
            <input type="color" id="colorPicker" onchange="setColor(this.value)" style="width:100%; height:45px; cursor:pointer;">
        </div>
        <div id="mediaBox"></div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button onclick="resetDevice()" style="flex:1; padding:12px; background:#441111; color:white; border-radius:10px; border:none;">RESET</button>
            <button onclick="closeControl()" style="flex:2; padding:12px; background:#1a1a1a; border:1px solid var(--accent); color:white; border-radius:10px;">FERTIG</button>
        </div>
    </div>
</div>

<script>var ioURL = "http://xxx:8087";</script>
<script src="assets/js/dashboard.js"></script>
<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').textContent = now.toLocaleTimeString('de-DE');
        document.getElementById('date').textContent = now.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Funktion für den Refresh-Button in der Topbar: Blendet Spinner ein und lädt dann neu
    function triggerGlobalReload() {
        document.querySelectorAll('.live-val').forEach(el => {
            // Wenn es kein Ordner ist, packen wir die Animation rein
            if (!el.innerHTML.includes('Öffnen')) {
                el.innerHTML = '<div class="spinner"></div>';
            }
        });
        setTimeout(() => {
            location.reload();
        }, 300);
    }
</script>
</body>
</html>
