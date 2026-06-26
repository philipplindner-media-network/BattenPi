<?php
session_start();
require_once('../config.php'); 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { die("Verbindung fehlgeschlagen"); }

// --- MANUELLE HOCHKANT-ERKENNUNG (?view=portrait) ---
$is_portrait = (isset($_GET['view']) && $_GET['view'] === 'portrait');
$view_query = $is_portrait ? '?view=portrait' : '';
$toggle_view_url = $is_portrait ? 'index.php' : 'index.php?view=portrait';
$toggle_view_label = $is_portrait ? '🔄 Querformat' : '🔄 Hochformat';

// Programme laden
$query = "SELECT * FROM waschmaschine_programme ORDER BY category, label ASC";
$result = $conn->query($query);
$programme = [];
$categories = [];
while($row = $result->fetch_assoc()) {
    $programme[] = $row;
    if (!in_array($row['category'], $categories)) $categories[] = $row['category'];
}

// "Service" künstlich als Kategorie hinzufügen, falls nicht vorhanden
if (!in_array('Service', $categories)) {
    $categories[] = 'Service';
}

// Protokoll-Daten für Tabelle und Chart laden (Letzte 50 Werte)
$log_query = "SELECT timestamp, wattage FROM wm_protocol ORDER BY timestamp DESC LIMIT 50";
$log_result = $conn->query($log_query);
$chart_data = [];
$log_table = [];
while($row = $log_result->fetch_assoc()) {
    $log_table[] = $row;
    $chart_data[] = $row;
}
$chart_data = array_reverse($chart_data); // Chronologisch für den Chart
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Waschmaschine - Protokoll & Steuerung</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #0a0a0a;
            --card-bg: #141414;
            --accent: #00d2ff;
            --text: #ffffff;
            --text-muted: #aaaaaa;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        body {
            background: var(--bg);
            color: var(--text);
            padding: 20px;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
        }
        .header h1 { font-size: 24px; font-weight: 400; color: var(--accent); }

        /* Navigation Tabs & Umschalter Reihe */
        .tabs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }
        .category-tabs { display: flex; gap: 10px; }
        .tab-btn {
            background: #222; color: var(--text); border: none; padding: 12px 20px;
            border-radius: 8px; cursor: pointer; font-size: 15px; transition: all 0.2s;
        }
        .tab-btn.active { background: var(--accent); color: #000; font-weight: bold; }
        
        .view-toggle-btn {
            background: #1a1a1a; color: var(--text); border: 1px solid #333; padding: 12px 20px;
            border-radius: 8px; text-decoration: none; font-size: 15px; font-weight: bold; transition: all 0.2s;
        }
        .view-toggle-btn:hover { background: #252525; border-color: var(--accent); }

        /* Haupt-Layout Container */
        .container {
            display: flex;
            gap: 20px;
            flex: 1;
            height: calc(100% - 120px);
        }

        /* Standard Querformat: Panels nebeneinander */
        .left-panel {
            flex: 1.2;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            border: 1px solid #222;
        }
        .chart-container { position: relative; flex: 1; min-height: 200px; }
        
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .wm-grid {
            display: none;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            overflow-y: auto;
            max-height: calc(100vh - 240px);
            padding-right: 5px;
        }
        .wm-grid.active { display: grid; }

        .wm-btn {
            background: #1e1e1e; border: 1px solid #333; border-radius: 10px;
            padding: 20px 15px; color: var(--text); cursor: pointer; text-align: center;
            display: flex; flex-direction: column; gap: 8px; justify-content: center; align-items: center;
            transition: transform 0.1s, border-color 0.2s;
        }
        .wm-btn:active { transform: scale(0.95); }
        .wm-btn .title { font-weight: bold; font-size: 16px; }
        .wm-btn .info { color: var(--text-muted); font-size: 13px; }

        .home-btn {
            background: #222; color: #ff4444; text-decoration: none; padding: 18px;
            border-radius: 10px; text-align: center; font-weight: bold; font-size: 16px;
            border: 1px solid #441111; display: block; margin-top: 15px;
        }

        /* Protokoll-Tabelle */
        .table-wrapper { flex: 1; overflow-y: auto; border: 1px solid #222; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #222; }
        th { background: #1a1a1a; color: var(--accent); position: sticky; top: 0; }
        tr:nth-child(even) { background: #111; }

        /* Overlay für laufendes Programm */
        #wm-status-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(5,5,5,0.98); z-index: 99999;
            display: none; flex-direction: column; justify-content: center; align-items: center; gap: 25px;
        }
        #wm-timer { font-size: 90px; color: var(--accent); font-family: monospace; font-weight: bold; }
        #wm-info { font-size: 26px; color: var(--text); font-weight: bold; }

        /* --- 10 ZOLL HOCHFORMAT ERZWUNGENER FIX --- */
        <?php if ($is_portrait): ?>
        html, body {
            max-width: 100% !important;
            overflow-x: hidden !important; 
            height: auto !important;
            overflow-y: auto !important;
            padding: 10px !important;
        }
        .container { 
            flex-direction: column !important; 
            height: auto !important; 
            width: 100% !important;
            gap: 8px !important;
        }
        /* Wenn Hochformat aktiv ist, verhält sich das linke Panel (Protokoll) dynamisch und wird nur eingeblendet wenn "Service" Tab aktiv ist */
        .left-panel { 
            display: none; 
            width: 100% !important;
            height: 500px !important;
            flex: none !important;
        } 
        /* Spezial-Trigger um das Protokoll im Service-Tab auch im Hochformat anzuzeigen */
        .left-panel.service-active {
            display: flex !important;
        }
        .right-panel { 
            width: 100% !important; 
            flex: none !important;
        }
        .wm-grid {
            display: none;
            grid-template-columns: 1fr !important; /* Kacheln untereinander im Hochformat */
            gap: 12px !important;
            max-height: none !important; 
            overflow-y: visible !important;
            width: 100% !important;
        }
        .wm-grid.active { 
            display: grid !important; 
        }
        .wm-btn { 
            width: 100% !important; 
            padding: 18px 15px !important; 
            box-sizing: border-box !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: center !important;
        }
        .wm-btn .title { font-size: 16px !important; text-align: left !important; }
        .wm-btn .info { font-size: 15px !important; color: var(--accent) !important; margin: 0 !important; }
        .home-btn { padding: 18px !important; margin-top: 20px; font-size: 16px !important; width: 100% !important; }
        <?php endif; ?>
    </style>
</head>
<body>

<div class="header">
    <h1>Waschmaschine Steuerung</h1>
    <div style="font-size: 14px; color: var(--text-muted);">BattenPi Dashboard</div>
</div>

<div class="tabs-container">
    <div class="category-tabs">
        <?php foreach($categories as $index => $cat): ?>
            <button class="tab-btn <?= $index === 0 ? 'active' : '' ?>" onclick="showCategory('cat_<?= md5($cat) ?>', this, '<?= $cat ?>')">
                <?= htmlspecialchars($cat) ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <a href="<?= $toggle_view_url ?>" class="view-toggle-btn"><?= $toggle_view_label ?></a>
</div>

<div class="container">
    <div class="left-panel <?= !$is_portrait ? 'service-active' : '' ?>" id="protocol-panel">
        <h3 style="font-weight: 400; color: var(--text-muted);">Leistungsverlauf (Letzte 50 Messungen)</h3>
        <div class="chart-container">
            <canvas id="wmChart"></canvas>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Zeitstempel</th><th>Verbrauch (Watt)</th></tr>
                </thead>
                <tbody>
                    <?php foreach($log_table as $row): ?>
                    <tr>
                        <td><?= date('d.M H:i:s', strtotime($row['timestamp'])) ?></td>
                        <td style="color: var(--accent); font-weight: bold;"><?= number_format($row['wattage'], 1, ',', '.') ?> W</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="right-panel">
        <div class="programs-wrapper">
            <?php foreach($categories as $cat_index => $cat): ?>
                <div class="wm-grid <?= $cat_index === 0 ? 'active' : '' ?>" id="cat_<?= md5($cat) ?>">
                    <?php if ($cat === 'Service'): ?>
                        <div class="wm-btn" style="cursor: default; border-color: var(--accent); background: #141414;">
                            <div class="title" style="color: var(--accent);">📊 Protokoll-Ansicht</div>
                            <div class="info">Aktiviert (Siehe unten/links)</div>
                        </div>
                    <?php else: ?>
                        <?php foreach($programme as $p): ?>
                            <?php if($p['category'] === $cat): ?>
                                <button class="wm-btn" onclick="startWaschmaschine('<?= htmlspecialchars($p['label']) ?>', <?= (int)$p['duration_seconds'] ?>)">
                                    <div class="title"><?= htmlspecialchars($p['label']) ?></div>
                                    <div class="info"><?= round((int)$p['duration_seconds'] / 60) ?> Min.</div>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="../index.php<?= $view_query ?>" class="home-btn">ZURÜCK ZUM DASHBOARD</a>
    </div>
</div>

<div id="wm-status-overlay">
    <div id="wm-info">Programm läuft...</div>
    <div id="wm-timer">--:--</div>
    <button onclick="stopWaschmaschine()" style="background: #441111; border: 1px solid #772222; color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 20px; font-size: 16px;">ABBRECHEN</button>
</div>

<script>
    window.ioURL = "http://<?= defined('IO_BROKER_IP') ? IO_BROKER_IP : '192.168.1.23' ?>:<?= defined('IO_BROKER_PORT') ? IO_BROKER_PORT : '8087' ?>";

    function showCategory(id, btn, categoryName) {
        document.querySelectorAll('.wm-grid').forEach(g => g.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
        
        const targetGrid = document.getElementById(id);
        if(targetGrid) targetGrid.classList.add('active');
        if(btn) btn.classList.add('active');

        // Hochformat-Steuerung für das Protokoll-Panel
        const protocolPanel = document.getElementById('protocol-panel');
        <?php if ($is_portrait): ?>
        if (categoryName === 'Service') {
            protocolPanel.classList.add('service-active');
        } else {
            protocolPanel.classList.remove('service-active');
        }
        <?php endif; ?>
    }

    // Chart.js Initialisierung
    const chartCanvas = document.getElementById('wmChart');
    if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php foreach($chart_data as $d) echo '"'.date('H:i', strtotime($d['timestamp'])).'",'; ?>],
                datasets: [{
                    label: 'Leistung (Watt)',
                    data: [<?php foreach($chart_data as $d) echo $d['wattage'].','; ?>],
                    borderColor: '#00d2ff',
                    backgroundColor: 'rgba(0, 210, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#222' }, ticks: { color: '#aaa' } }, 
                    x: { grid: { display: false }, ticks: { color: '#aaa' } } 
                },
                plugins: { legend: { labels: { color: '#fff' } } }
            }
        });
    }

    if (localStorage.getItem('wm_endTime')) {
        const overlay = document.getElementById('wm-status-overlay');
        if (overlay) overlay.style.display = 'flex';
        const storedName = localStorage.getItem('wm_name');
        const infoEl = document.getElementById('wm-info');
        if (storedName && infoEl) { infoEl.innerText = storedName + " läuft..."; }
    }
</script>
<script src="js/waschmaschine.js"></script>

</body>
</html>
