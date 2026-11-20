<?php
/**
 * Kinopatia Arcade · pannello di controllo privato
 * Apri: https://kinopatia.com/arcade-api/admin.php?k=CHIAVE
 */
const ADMIN_KEY = 'CAMBIA-QUESTA-CHIAVE';
const DB_FILE = __DIR__ . '/data/arcade.sqlite';
date_default_timezone_set('Europe/Rome');
header('X-Robots-Tag: noindex, nofollow');
if (ADMIN_KEY === 'CAMBIA-QUESTA-CHIAVE' || !hash_equals(ADMIN_KEY, (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('Accesso negato'); }
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$rows = []; $ed = $_GET['ed'] ?? '';
if (is_file(DB_FILE)) {
  $pdo = new PDO('sqlite:' . DB_FILE);
  $rows = $pdo->query('SELECT edition,name,company,email,consent,CAST(record AS INTEGER) record,games,created_at,updated_at FROM players ORDER BY edition, record DESC, updated_at ASC')->fetchAll(PDO::FETCH_ASSOC);
}
$editions = array_values(array_unique(array_column($rows, 'edition')));
if (!$ed && $editions) $ed = in_array('natale', $editions) ? 'natale' : $editions[0];
$list = array_values(array_filter($rows, fn($r) => $r['edition'] === $ed));
$games = array_sum(array_column($list, 'games'));
$companies = count(array_unique(array_map('mb_strtolower', array_column($list, 'company'))));
$best = $list ? $list[0]['record'] : 0;
$prizes = [1 => 'Chiedeteci una cosa', 2 => 'Styleframe', 3 => 'Styleframe'];
$fmt = fn($d) => $d ? date('d/m H:i', strtotime($d)) : '';
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kinopatia Arcade · Pannello</title>
<style>
body{margin:0;background:#0b0b0b;color:#f4f2ee;font:15px/1.4 system-ui,-apple-system,Segoe UI,sans-serif}
.w{max-width:1100px;margin:0 auto;padding:28px 20px 60px}
h1{font-size:26px;margin:0 0 4px}.sub{color:#8a8782;margin-bottom:22px}
.tabs{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}.tabs a{padding:7px 14px;border-radius:999px;background:#1a1a1a;color:#ccc;text-decoration:none}.tabs a.on{background:#E3262F;color:#fff}
.kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px}.kpi div{background:#151515;border:1px solid #232323;border-radius:16px;padding:16px}.kpi b{display:block;font-size:28px}.kpi span{color:#8a8782;font-size:12px;letter-spacing:.08em;text-transform:uppercase}
table{width:100%;border-collapse:collapse;background:#121212;border-radius:16px;overflow:hidden}th,td{padding:11px 12px;text-align:left;border-bottom:1px solid #1f1f1f;font-size:14px}th{color:#8a8782;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.06em;background:#151515}
td.n{font-weight:800;color:#F5C542}tr.top td{background:rgba(245,197,66,.06)}.p{font-size:11px;padding:3px 8px;border-radius:999px;background:#F5C542;color:#000;margin-left:6px;white-space:nowrap}
.btn{display:inline-block;margin-top:18px;padding:11px 18px;border-radius:999px;background:#f4f2ee;color:#000;text-decoration:none;font-weight:700}
.empty{padding:40px;text-align:center;color:#8a8782}a{color:#f4f2ee}
@media(max-width:700px){.kpi{grid-template-columns:1fr 1fr}.hm{display:none}}
</style></head><body><div class="w">
<h1>Kinopatia Arcade · Pannello</h1><div class="sub">Classifica e iscritti in tempo reale · pagina privata, non condividere il link</div>
<div class="tabs"><?php foreach ($editions as $e): ?><a class="<?= $e === $ed ? 'on' : '' ?>" href="?k=<?= $h($_GET['k']) ?>&ed=<?= $h($e) ?>"><?= $h(ucfirst($e)) ?></a><?php endforeach ?></div>
<div class="kpi"><div><b><?= count($list) ?></b><span>Iscritti</span></div><div><b><?= $companies ?></b><span>Aziende</span></div><div><b><?= $games ?></b><span>Partite giocate</span></div><div><b><?= $best ?></b><span>Record</span></div></div>
<?php if (!$list): ?><div class="empty">Ancora nessun iscritto in questa edizione.</div><?php else: ?>
<table><tr><th>#</th><th>Nome</th><th>Azienda</th><th>Email</th><th>Record</th><th class="hm">Partite</th><th class="hm">Iscritto</th><th class="hm">Ultima partita</th></tr>
<?php foreach ($list as $i => $r): $pos = $i + 1; $pz = $prizes[$pos] ?? ($pos <= 10 ? 'Brainstorming' : ''); ?>
<tr class="<?= $pos <= 3 ? 'top' : '' ?>"><td class="n"><?= $pos ?></td><td><?= $h($r['name']) ?><?php if ($pz): ?><span class="p"><?= $h($pz) ?></span><?php endif ?></td><td><?= $h($r['company']) ?></td><td><a href="mailto:<?= $h($r['email']) ?>"><?= $h($r['email']) ?></a></td><td><b><?= (int)$r['record'] ?></b></td><td class="hm"><?= (int)$r['games'] ?></td><td class="hm"><?= $fmt($r['created_at']) ?></td><td class="hm"><?= $fmt($r['updated_at']) ?></td></tr>
<?php endforeach ?></table><?php endif ?>
<a class="btn" href="api.php?export=<?= $h($_GET['k']) ?>">Scarica CSV (per HubSpot / Excel)</a>
</div></body></html>
