<?php
session_start();
require_once('config.php');

// --- SCHUTZ: Nur Admins (Level 10) dürfen diese Seite sehen ---
if (!isset($_SESSION['level_id']) || $_SESSION['level_id'] < 10) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Datenbank-Fehler: " . $conn->connect_error);
}

$view = $_GET['view'] ?? 'buttons';
$current_menu = isset($_GET['menu']) ? intval($_GET['menu']) : 0;

// --- LOGIK: DASHBOARD BUTTONS SPEICHERN ---
if (isset($_POST['save_button'])) {
    $id = intval($_POST['id']);
    $label = $conn->real_escape_string($_POST['label']);
    $io_id = $conn->real_escape_string($_POST['io_id']);
    $status_id = $conn->real_escape_string($_POST['status_id']);
    $dimmer_id = $conn->real_escape_string($_POST['dimmer_id']);
    $color_id = $conn->real_escape_string($_POST['color_id']);
    $type = $conn->real_escape_string($_POST['type']);
    $parent_id = intval($_POST['parent_id']);
    $min_level = intval($_POST['min_level']); // NEU: Level-Logik

    if ($id > 0) {
        $sql = "UPDATE remote_buttons SET label='$label', io_id='$io_id', status_id='$status_id', dimmer_id='$dimmer_id', color_id='$color_id', type='$type', parent_id=$parent_id, min_level=$min_level WHERE id=$id";
    } else {
        $sql = "INSERT INTO remote_buttons (label, io_id, status_id, dimmer_id, color_id, type, parent_id, min_level) VALUES ('$label', '$io_id', '$status_id', '$dimmer_id', '$color_id', '$type', $parent_id, $min_level)";
    }
    $conn->query($sql);
    header("Location: admin.php?view=buttons&menu=$parent_id");
    exit;
}

// --- LOGIK: WASCHPROGRAMME SPEICHERN ---
if (isset($_POST['save_waschprogramm'])) {
    $w_id = intval($_POST['w_id']);
    $label = $conn->real_escape_string($_POST['w_label']);
    $duration = intval($_POST['w_duration']);
    $cat = $conn->real_escape_string($_POST['w_category']);

    if ($w_id > 0) {
        $sql = "UPDATE waschmaschine_programme SET label='$label', duration_seconds=$duration, category='$cat' WHERE id=$w_id";
    } else {
        $sql = "INSERT INTO waschmaschine_programme (label, duration_seconds, category) VALUES ('$label', $duration, '$cat')";
    }
    $conn->query($sql);
    header("Location: admin.php?view=waschmaschine");
    exit;
}

// --- LÖSCH-LOGIK ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM remote_buttons WHERE id=$id");
    header("Location: admin.php?view=buttons&menu=$current_menu"); exit;
}
if (isset($_GET['delete_w'])) {
    $id = intval($_GET['delete_w']);
    $conn->query("DELETE FROM waschmaschine_programme WHERE id=$id");
    header("Location: admin.php?view=waschmaschine"); exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Systemsteuerung - Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
<style>
body { background: #121212; color: #eee; padding: 20px; font-family: 'Segoe UI', sans-serif; }
.card { background: #1e1e1e; border: 1px solid #333; margin-bottom: 20px; color: #fff; border-radius: 10px; }
.card-header { background: #252525; border-bottom: 1px solid #444; font-weight: bold; color: #00d4ff; }
.table { color: #ddd; }
.nav-tabs { border-bottom: 1px solid #444; }
.nav-tabs .nav-link { color: #aaa; border: none; }
.nav-tabs .nav-link.active { background: #007bff; color: white; border-radius: 5px 5px 0 0; }
.id-badge { font-family: monospace; font-size: 0.8rem; background: #222; padding: 2px 5px; color: #00d4ff; border-radius: 3px; }
.form-control { background: #2a2a2a; border: 1px solid #444; color: #fff; }
.form-control:focus { background: #333; color: #fff; border-color: #00d4ff; box-shadow: none; }
label { font-weight: 600; font-size: 0.85rem; color: #888; text-transform: uppercase; }
</style>
</head>
<body>
<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4">
<h2><span style="color: #00d4ff;">⚙️</span> Systemsteuerung</h2>
<a href="index.php" class="btn btn-outline-info">ZURÜCK ZUM DASHBOARD</a>
</div>

<ul class="nav nav-tabs mb-4">
<li class="nav-item"><a class="nav-link <?= $view == 'buttons' ? 'active' : '' ?>" href="admin.php?view=buttons&menu=<?= $current_menu ?>">🔘 Dashboard Buttons</a></li>
<li class="nav-item"><a class="nav-link <?= $view == 'waschmaschine' ? 'active' : '' ?>" href="admin.php?view=waschmaschine">🧼 Waschprogramme</a></li>
</ul>

<?php if ($view == 'buttons'): ?>
<?php
$edit_btn = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_btn = $conn->query("SELECT * FROM remote_buttons WHERE id=$edit_id")->fetch_assoc();
}
?>
<div class="card">
<div class="card-header"><?= $edit_btn ? '✏️ Button bearbeiten' : '➕ Neuen Button anlegen' ?></div>
<div class="card-body">
<form method="post">
<input type="hidden" name="id" value="<?= $edit_btn['id'] ?? 0 ?>">
<input type="hidden" name="parent_id" value="<?= $current_menu ?>">
<div class="row">
<div class="col-md-3"><label>Beschriftung</label><input type="text" name="label" class="form-control" value="<?= $edit_btn['label'] ?? '' ?>" required></div>
<div class="col-md-2">
<label>Typ</label>
<select name="type" class="form-control">
<option value="toggle" <?= ($edit_btn['type'] ?? '') == 'toggle' ? 'selected' : '' ?>>Licht / Schalter</option>
<option value="value" <?= ($edit_btn['type'] ?? '') == 'value' ? 'selected' : '' ?>>Steckdose / Info</option>
<option value="sensor" <?= ($edit_btn['type'] ?? '') == 'sensor' ? 'selected' : '' ?>>Sensor</option>
<option value="folder" <?= ($edit_btn['type'] ?? '') == 'folder' ? 'selected' : '' ?>>Ordner (Untermenü)</option>
<option value="camera" <?= ($edit_btn['type'] ?? '') == 'camera' ? 'selected' : '' ?>>Kamera</option>
<option value="link" <?= ($edit_btn['type'] ?? '') == 'link' ? 'selected' : '' ?>>Interner Link</option>
</select>
</div>
<div class="col-md-2">
<label>Min. Level</label>
<select name="min_level" class="form-control" style="border: 1px solid #00d4ff;">
<option value="1" <?= ($edit_btn['min_level'] ?? 10) == 1 ? 'selected' : '' ?>>1 (Gast)</option>
<option value="5" <?= ($edit_btn['min_level'] ?? 10) == 5 ? 'selected' : '' ?>>5 (User)</option>
<option value="10" <?= ($edit_btn['min_level'] ?? 10) == 10 ? 'selected' : '' ?>>10 (Admin)</option>
</select>
</div>
<div class="col-md-2"><label>Haupt ID</label><input type="text" name="io_id" class="form-control" value="<?= $edit_btn['io_id'] ?? '' ?>"></div>
<div class="col-md-3"><label>Status ID</label><input type="text" name="status_id" class="form-control" value="<?= $edit_btn['status_id'] ?? '' ?>"></div>
</div>
<div class="row mt-3">
<div class="col-md-3"><label>Dimmer ID</label><input type="text" name="dimmer_id" class="form-control" value="<?= $edit_btn['dimmer_id'] ?? '' ?>"></div>
<div class="col-md-3"><label>Farb ID</label><input type="text" name="color_id" class="form-control" value="<?= $edit_btn['color_id'] ?? '' ?>"></div>
<div class="col-md-6 text-right"><br><button type="submit" name="save_button" class="btn btn-primary px-5">Speichern</button></div>
</div>
</form>
</div>
</div>

<div class="mb-3">
<a href="?view=buttons&menu=0" class="btn btn-sm btn-outline-secondary">Hauptmenü</a>
<span class="ml-3 text-muted">Aktueller Ordner: ID <?= $current_menu ?></span>
</div>

<table class="table table-dark table-striped mt-4">
<thead>
<tr>
<th>Label</th>
<th>Typ</th>
<th>Lvl</th>
<th>ioBroker IDs</th>
<th style="width: 150px;">Aktionen</th>
</tr>
</thead>
<tbody>
<?php
$result = $conn->query("SELECT * FROM remote_buttons WHERE parent_id = $current_menu ORDER BY (type='folder') DESC, label ASC");
while($row = $result->fetch_assoc()): ?>
    <tr>
    <td><strong><?= htmlspecialchars($row['label']) ?></strong></td>
    <td><span class="badge badge-info"><?= $row['type'] ?></span></td>
    <td><span class="badge badge-<?= $row['min_level'] >= 10 ? 'danger' : ($row['min_level'] >= 5 ? 'primary' : 'secondary') ?>"><?= $row['min_level'] ?></span></td>
    <td>
    <div class="id-badge"><?= htmlspecialchars($row['io_id'] ?: '-') ?></div>
    <div class="id-badge text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($row['status_id'] ?: '-') ?></div>
    </td>
    <td>
    <div class="btn-group">
    <a href="?view=buttons&menu=<?= $current_menu ?>&edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
    <?php if($row['type'] == 'folder'): ?>
    <a href="?view=buttons&menu=<?= $row['id'] ?>" class="btn btn-sm btn-info">Öffnen</a>
    <?php endif; ?>
    <a href="?view=buttons&menu=<?= $current_menu ?>&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Löschen?')">X</a>
    </div>
    </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
    </table>

    <?php elseif ($view == 'waschmaschine'): ?>
    <?php
    $edit_w = (isset($_GET['edit_w'])) ? $conn->query("SELECT * FROM waschmaschine_programme WHERE id=".intval($_GET['edit_w']))->fetch_assoc() : null;
    ?>
    <div class="card">
    <div class="card-header"><?= $edit_w ? '✏️ Programm bearbeiten' : '➕ Neues Waschprogramm' ?></div>
    <div class="card-body">
    <form method="post" class="row">
    <input type="hidden" name="w_id" value="<?= $edit_w['id'] ?? 0 ?>">
    <div class="col-md-4"><label>Name</label><input type="text" name="w_label" class="form-control" value="<?= $edit_w['label'] ?? '' ?>" required></div>
    <div class="col-md-3"><label>Dauer (Sekunden)</label><input type="number" name="w_duration" class="form-control" value="<?= $edit_w['duration_seconds'] ?? '' ?>" required></div>
    <div class="col-md-3"><label>Kategorie</label><input type="text" name="w_category" class="form-control" value="<?= $edit_w['category'] ?? 'Standard' ?>"></div>
    <div class="col-md-2"><br><button type="submit" name="save_waschprogramm" class="btn btn-success btn-block">Speichern</button></div>
    </form>
    </div>
    </div>

    <table class="table table-dark table-hover">
    <thead>
    <tr><th>Programm</th><th>Kategorie</th><th>Zeit</th><th>Aktion</th></tr>
    </thead>
    <tbody>
    <?php
    $res = $conn->query("SELECT * FROM waschmaschine_programme ORDER BY category, label");
    while($w = $res->fetch_assoc()): ?>
        <tr>
        <td><span class="text-info font-weight-bold"><?= htmlspecialchars($w['label']) ?></span></td>
        <td><?= htmlspecialchars($w['category']) ?></td>
        <td><?= round($w['duration_seconds']/60) ?> Min <small class="text-muted">(<?= $w['duration_seconds'] ?>s)</small></td>
        <td>
        <a href="?view=waschmaschine&edit_w=<?= $w['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
        <a href="?view=waschmaschine&delete_w=<?= $w['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Löschen?')">X</a>
        </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
        </table>
        <?php endif; ?>
        </div>
        </body>
        </html>
