<?php
/**
 * Kinopatia Arcade · classifica condivisa (PHP + SQLite)
 * Va caricato sull'hosting OVH, es. https://kinopatia.com/arcade-api/api.php
 * Il database si crea da solo nella cartella data/ (protetta da .htaccess).
 * Le email non vengono mai restituite al gioco.
 */
const ALLOWED_ORIGINS = ['https://arcade.kinopatia.com', 'https://nicopid-lgtm.github.io'];
const MAX_SCORE = 300;
const TOP_N = 20;
const DB_FILE = __DIR__ . '/data/arcade.sqlite';
const EXPORT_KEY = 'CAMBIA-QUESTA-CHIAVE';   // per scaricare il CSV degli iscritti: api.php?export=CHIAVE

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ALLOWED_ORIGINS, true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); }
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function db() {
  static $pdo = null; if ($pdo) return $pdo;
  if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0700, true);
  $pdo = new PDO('sqlite:' . DB_FILE);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
  $pdo->exec('CREATE TABLE IF NOT EXISTS players(
      id TEXT, edition TEXT, pkey TEXT, name TEXT, company TEXT, email TEXT, consent INTEGER,
      record INTEGER DEFAULT 0, games INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT, hubspot TEXT DEFAULT "",
      PRIMARY KEY(id, edition))');
  return $pdo;
}
function out($o) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; }
function clean($v, $n) { return mb_substr(trim(str_replace(['<','>'], '', (string)($v ?? ''))), 0, $n); }
function short_name($n) { $p = preg_split('/\s+/', trim($n)); return count($p) > 1 ? $p[0] . ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : $p[0]; }

function board($edition, $id) {
  $st = db()->prepare('SELECT id,name,company,record FROM players WHERE edition=? AND CAST(record AS INTEGER)>0 ORDER BY CAST(record AS INTEGER) DESC, updated_at ASC');
  $st->execute([$edition]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $me = 0; foreach ($rows as $i => $r) if ($r['id'] === $id) { $me = $i + 1; break; }
  $list = [];
  foreach (array_slice($rows, 0, TOP_N) as $r) $list[] = ['name' => short_name($r['name']), 'company' => $r['company'], 'score' => (int)$r['record'], 'me' => $r['id'] === $id];
  if ($me > TOP_N) { $r = $rows[$me - 1]; $list[] = ['name' => short_name($r['name']), 'company' => $r['company'], 'score' => (int)$r['record'], 'me' => true, 'pos' => $me]; }
  return ['ok' => true, 'rows' => $list, 'me' => $me, 'total' => count($rows)];
}

// export CSV per Kinopatia (protetto da chiave)
if (isset($_GET['export'])) {
  if (!hash_equals(EXPORT_KEY, (string)$_GET['export']) || EXPORT_KEY === 'CAMBIA-QUESTA-CHIAVE') { http_response_code(403); exit('no'); }
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="arcade-iscritti.csv"');
  $f = fopen('php://output', 'w'); fputcsv($f, ['edizione','nome','azienda','email','consenso','record','partite','iscritto_il','aggiornato_il']);
  foreach (db()->query('SELECT edition,name,company,email,consent,record,games,created_at,updated_at FROM players ORDER BY edition, CAST(record AS INTEGER) DESC') as $r) fputcsv($f, $r);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') out(board(clean($_GET['edition'] ?? 'natale', 20), clean($_GET['id'] ?? '', 40)));

$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) out(['ok' => false, 'error' => 'bad_json']);
$id = clean($b['id'] ?? '', 40); $key = clean($b['key'] ?? '', 60); $edition = clean($b['edition'] ?? 'natale', 20);
$score = (int)($b['score'] ?? 0); if ($score < 0 || $score > MAX_SCORE) $score = 0;
$now = gmdate('c'); $pdo = db();

$any = $pdo->prepare('SELECT * FROM players WHERE id=? LIMIT 1'); $any->execute([$id]); $any = $any->fetch(PDO::FETCH_ASSOC);
$cur = $pdo->prepare('SELECT * FROM players WHERE id=? AND edition=?'); $cur->execute([$id, $edition]); $cur = $cur->fetch(PDO::FETCH_ASSOC);

if (($b['action'] ?? '') === 'register') {
  $name = clean($b['name'] ?? '', 60); $company = clean($b['company'] ?? '', 80); $email = mb_strtolower(clean($b['email'] ?? '', 120));
  if (!$id || !$key || !$name || !$company || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($b['consent'] ?? false) !== true) out(['ok' => false, 'error' => 'invalid']);
  if ($any && !hash_equals($any['pkey'], $key)) out(['ok' => false, 'error' => 'forbidden']);
  if ($cur) {
    $pdo->prepare('UPDATE players SET name=?,company=?,email=?,consent=1,record=MAX(record,CAST(? AS INTEGER)),updated_at=? WHERE id=? AND edition=?')->execute([$name,$company,$email,$score,$now,$id,$edition]);
  } else {
    $pdo->prepare('INSERT INTO players(id,edition,pkey,name,company,email,consent,record,games,created_at,updated_at) VALUES(?,?,?,?,?,?,1,CAST(? AS INTEGER),1,?,?)')->execute([$id,$edition,$key,$name,$company,$email,$score,$now,$now]);
  }
  out(board($edition, $id));
}

if (($b['action'] ?? '') === 'score') {
  if (!$any) out(['ok' => false, 'error' => 'unknown']);
  if (!hash_equals($any['pkey'], $key)) out(['ok' => false, 'error' => 'forbidden']);
  if ($cur) $pdo->prepare('UPDATE players SET record=MAX(record,CAST(? AS INTEGER)),games=games+1,updated_at=? WHERE id=? AND edition=?')->execute([$score,$now,$id,$edition]);
  else $pdo->prepare('INSERT INTO players(id,edition,pkey,name,company,email,consent,record,games,created_at,updated_at) VALUES(?,?,?,?,?,?,?,CAST(? AS INTEGER),1,?,?)')->execute([$id,$edition,$any['pkey'],$any['name'],$any['company'],$any['email'],$any['consent'],$score,$now,$now]);
  out(board($edition, $id));
}
out(['ok' => false, 'error' => 'unknown_action']);
