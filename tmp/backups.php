<?php
// Страница списка бэкапов конфигов устройства. Файлы лежат вне webroot, отдаются через backup_get.php.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$id  = (int) ($_GET['id'] ?? 0);
$st  = $pdo->prepare("SELECT name, varchar_ip, model FROM network_devices WHERE id = ?");
$st->execute([$id]);
$dev = $st->fetch(PDO::FETCH_ASSOC);

$ip    = $dev['varchar_ip'] ?? '';
$dir   = ($ip && filter_var($ip, FILTER_VALIDATE_IP)) ? "/var/backups/netmon/$ip" : '';
$files = ($dir && is_dir($dir)) ? glob("$dir/*.cfg") : [];
rsort($files);

$months = [1=>'янв',2=>'фев',3=>'мар',4=>'апр',5=>'май',6=>'июн',7=>'июл',8=>'авг',9=>'сен',10=>'окт',11=>'ноя',12=>'дек'];
function fmtSize($b){ return $b >= 1024 ? round($b/1024,1).' КБ' : $b.' б'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Бэкапы конфига — <?= htmlspecialchars($dev['name'] ?? "#$id") ?></title>
<link href="../public/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../public/vendor/bootstrap-icons/1.10.0/font/bootstrap-icons.css">
<style>
  body{background:#f0f2f5;}
  .wrap{max-width:820px;margin:30px auto;}
  .card{box-shadow:0 2px 10px rgba(0,0,0,.08);}
  .mono{font-family:ui-monospace,Menlo,Consolas,monospace;}
  #backupLog {
    display: none;
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: ui-monospace, Menlo, Consolas, monospace;
    font-size: 12px;
    padding: 10px 14px;
    max-height: 220px;
    overflow-y: auto;
    border-top: 1px solid #444;
    white-space: pre-wrap;
    word-break: break-all;
  }
  #backupLog .line-saved { color: #4ec94e; }
  #backupLog .line-same  { color: #888; }
  #backupLog .line-fail  { color: #f47a7a; }
  #backupLog .line-итог  { color: #f0c060; font-weight: bold; }
</style>
</head>
<body>
<div class="wrap">
  <?php if (!$dev): ?>
    <div class="alert alert-danger">Устройство #<?= $id ?> не найдено.</div>
  <?php else: ?>
  <div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <div><i class="bi bi-archive"></i> Бэкапы конфига: <b><?= htmlspecialchars($dev['name']) ?></b></div>
      <div class="d-flex gap-2">
        <button id="backupNowBtn" class="btn btn-sm btn-warning" onclick="createBackup()">
          <i class="bi bi-cloud-download"></i> Снять бэкап
        </button>
        <a href="device_diagnostics.php?id=<?= $id ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Диагностика</a>
      </div>
    </div>
    <div id="backupLog"></div>
    <div class="card-body">
      <div class="text-muted small mb-3">
        IP: <span class="mono"><?= htmlspecialchars($dev['varchar_ip']) ?></span>
        <?= $dev['model'] ? ' &middot; ' . htmlspecialchars($dev['model']) : '' ?>
        &middot; всего копий: <b><?= count($files) ?></b>
      </div>

      <?php if (!$files): ?>
        <div class="alert alert-secondary mb-0">Бэкапов пока нет. Снимаются автоматически (ежемесячно) или вручную через коллектор.</div>
      <?php else: ?>
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th>Дата</th><th>Размер</th><th class="text-end">Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($files as $i => $f):
            $base = basename($f);
            $d    = preg_match('/(\d{4}-\d{2}-\d{2})\.cfg$/', $base, $mm) ? $mm[1] : substr($base, 0, 10);
            $ts   = strtotime($d);
            $human = $ts ? ((int)date('j',$ts)).' '.$months[(int)date('n',$ts)].' '.date('Y',$ts) : $d;
            $size = filesize($f);
        ?>
          <tr>
            <td>
              <i class="bi bi-file-earmark-text text-primary"></i>
              <b><?= htmlspecialchars($human) ?></b>
              <?php if ($i === 0): ?><span class="badge bg-success ms-1">последний</span><?php endif; ?>
            </td>
            <td class="text-muted"><?= fmtSize($size) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" target="_blank"
                 href="backup_get.php?id=<?= $id ?>&f=<?= urlencode($base) ?>&view=1"><i class="bi bi-eye"></i> Просмотр</a>
              <a class="btn btn-sm btn-primary"
                 href="backup_get.php?id=<?= $id ?>&f=<?= urlencode($base) ?>"><i class="bi bi-download"></i> Скачать</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
async function createBackup() {
    const btn = document.getElementById('backupNowBtn');
    const log = document.getElementById('backupLog');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Снимаю...';
    log.innerHTML = '';
    log.style.display = 'block';

    function appendLine(text) {
        const line = text.trimEnd();
        if (!line) return;
        const span = document.createElement('span');
        const up = line.toUpperCase();
        if (up.includes('[SAVED]'))      span.className = 'line-saved';
        else if (up.includes('[SAME ]')) span.className = 'line-same';
        else if (up.includes('[FAIL ]')) span.className = 'line-fail';
        else if (up.startsWith('ИТОГ'))  span.className = 'line-итог';
        span.textContent = line + '\n';
        log.appendChild(span);
        log.scrollTop = log.scrollHeight;
    }

    let saved = false, failed = false;
    try {
        const resp = await fetch('backup_now.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'id=<?= $id ?>'
        });

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buf = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += decoder.decode(value, { stream: true });
            const lines = buf.split('\n');
            buf = lines.pop();
            for (const l of lines) {
                appendLine(l);
                if (l.includes('[SAVED]'))  saved = true;
                if (l.includes('[FAIL ]') || l.startsWith('ERR')) failed = true;
            }
        }
        if (buf) appendLine(buf);

    } catch (e) {
        appendLine('ERR: ошибка связи — ' + e.message);
        failed = true;
    }

    if (saved) {
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Сохранён';
        btn.className = 'btn btn-sm btn-success';
        setTimeout(() => location.reload(), 1800);
    } else if (failed) {
        btn.innerHTML = '<i class="bi bi-x-lg"></i> Ошибка';
        btn.className = 'btn btn-sm btn-danger';
        btn.disabled = false;
    } else {
        btn.innerHTML = '<i class="bi bi-dash"></i> Не изменился';
        btn.className = 'btn btn-sm btn-secondary';
        btn.disabled = false;
    }
}
</script>
</body>
</html>