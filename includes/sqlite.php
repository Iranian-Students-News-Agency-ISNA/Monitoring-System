<?php
// لایه SQLite فقط برای داده‌های ارزیابی (excel_files و excel_rows)؛ بقیه داده‌ها همچنان روی JSON هستند.
require_once __DIR__ . '/jsondb.php';

const EX_FILE_COLS = ['id','original_name','stored_path','report_date','detected_date','date_from','date_to','status','uploaded_at','updated_at'];
const EX_ROW_COLS  = ['id','file_id','code','site','publisher','title','news_type','reporter','service_main','service_sub','date','pub_time','views','news_link','source'];

function exPubMin(string $t): int
{
    return preg_match('/^(\\d{1,2}):(\\d{1,2})/', $t, $m) ? (int)$m[1] * 60 + (int)$m[2] : -1;
}

function exDb(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = appConfig()['data_dir'];
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $dir . '/nezarat_eval.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA temp_store=MEMORY; PRAGMA cache_size=-20000; PRAGMA busy_timeout=8000;');
    $pdo->exec("CREATE TABLE IF NOT EXISTS excel_files(
        id INTEGER PRIMARY KEY AUTOINCREMENT, original_name TEXT, stored_path TEXT, report_date TEXT, detected_date TEXT,
        date_from TEXT, date_to TEXT, status TEXT DEFAULT 'active', uploaded_at TEXT, updated_at TEXT, extra TEXT);
    CREATE TABLE IF NOT EXISTS excel_rows(
        id INTEGER PRIMARY KEY AUTOINCREMENT, file_id INTEGER NOT NULL,
        code TEXT DEFAULT '', site TEXT DEFAULT '', publisher TEXT DEFAULT '', title TEXT DEFAULT '',
        news_type TEXT DEFAULT '', reporter TEXT DEFAULT '', service_main TEXT DEFAULT '', service_sub TEXT DEFAULT '',
        date TEXT DEFAULT '', pub_time TEXT DEFAULT '', pub_min INTEGER DEFAULT -1, views INTEGER DEFAULT 0,
        news_link TEXT DEFAULT '', source TEXT DEFAULT '', extra TEXT);
    CREATE INDEX IF NOT EXISTS ix_rows_date ON excel_rows(date);
    CREATE INDEX IF NOT EXISTS ix_rows_file ON excel_rows(file_id);
    CREATE INDEX IF NOT EXISTS ix_rows_code ON excel_rows(date, code);
    CREATE INDEX IF NOT EXISTS ix_rows_site_date ON excel_rows(site, date);
    CREATE INDEX IF NOT EXISTS ix_rows_svc_date ON excel_rows(service_main, date);");
    // دیتابیس‌های ساخته‌شده قبل از این نسخه: افزودن ستون pub_min (دقیقه از نیمه‌شب) و پر کردن یک‌باره
    $cols = array_column($pdo->query('PRAGMA table_info(excel_rows)')->fetchAll(), 'name');
    if (!in_array('pub_min', $cols, true)) {
        $pdo->exec('ALTER TABLE excel_rows ADD COLUMN pub_min INTEGER DEFAULT -1');
        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE excel_rows SET pub_min=? WHERE pub_time=?');
        foreach ($pdo->query("SELECT DISTINCT pub_time FROM excel_rows WHERE pub_time<>''")->fetchAll(PDO::FETCH_COLUMN) as $pt) {
            $up->execute([exPubMin((string)$pt), $pt]);
        }
        $pdo->commit();
    }
    return $pdo;
}

// ردیف آرایه‌ای (با کلیدهای دلخواه) -> [مقادیر ستون‌ها, extra]
function exSplit(array $r, array $cols): array
{
    $vals = []; 
    foreach ($cols as $c) { $vals[$c] = $r[$c] ?? null; }
    $extra = array_diff_key($r, array_flip($cols));
    return [$vals, $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null];
}

// ردیف دیتابیس -> آرایه با همان ساختار JSON قبلی
function exOut(array $r): array
{
    if (!empty($r['extra'])) {
        $x = json_decode($r['extra'], true);
        if (is_array($x)) $r = array_merge($x, $r);
    }
    unset($r['extra']);
    if (array_key_exists('views', $r)) $r['views'] = (int)$r['views'];
    $r['id'] = (int)$r['id'];
    if (isset($r['file_id'])) $r['file_id'] = (int)$r['file_id'];
    return $r;
}

function exInsertRow(PDOStatement $st, array $r): void
{
    [$v, $extra] = exSplit($r, EX_ROW_COLS);
    $st->execute([$v['id'], $v['file_id'], (string)($v['code'] ?? ''), (string)($v['site'] ?? ''), (string)($v['publisher'] ?? ''),
        (string)($v['title'] ?? ''), (string)($v['news_type'] ?? ''), (string)($v['reporter'] ?? ''), (string)($v['service_main'] ?? ''),
        (string)($v['service_sub'] ?? ''), (string)($v['date'] ?? ''), (string)($v['pub_time'] ?? ''), exPubMin((string)($v['pub_time'] ?? '')), (int)($v['views'] ?? 0),
        (string)($v['news_link'] ?? ''), (string)($v['source'] ?? ''), $extra]);
}
function exRowInsertSql(): string
{
    return 'INSERT INTO excel_rows(id,file_id,code,site,publisher,title,news_type,reporter,service_main,service_sub,date,pub_time,pub_min,views,news_link,source,extra) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
}

function exInsertFile(PDOStatement $st, array $f): void
{
    [$v, $extra] = exSplit($f, EX_FILE_COLS);
    $st->execute([$v['id'], $v['original_name'], $v['stored_path'], $v['report_date'], $v['detected_date'], $v['date_from'],
        $v['date_to'], $v['status'] ?? 'active', $v['uploaded_at'], $v['updated_at'], $extra]);
}
function exFileInsertSql(): string
{
    return 'INSERT INTO excel_files(id,original_name,stored_path,report_date,detected_date,date_from,date_to,status,uploaded_at,updated_at,extra) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
}

// معادل jsonUpdate('excel_rows', ...) برای ابزارهای backfill: همه ردیف‌ها را می‌دهد و فقط ردیف‌های تغییرکرده را ذخیره می‌کند
function excelRowsMutate(callable $mutator): void
{
    $pdo = exDb();
    $before = array_map('exOut', $pdo->query('SELECT * FROM excel_rows ORDER BY id')->fetchAll());
    $after = $mutator($before);
    if (!is_array($after)) return;
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM excel_rows WHERE id=?');
        $ins = $pdo->prepare(exRowInsertSql());
        foreach ($after as $i => $r) {
            if (isset($before[$i]) && $before[$i] == $r) continue;
            $del->execute([$r['id']]);
            exInsertRow($ins, $r);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
