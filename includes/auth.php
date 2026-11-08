<?php
// Shared bootstrap: session, DB, helpers. Include at the top of every page.
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../config/db.php';

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function require_login()
{
    if (!current_user_id()) {
        header('Location: login.php');
        exit;
    }
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

// ---------- CSRF ----------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check()
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request (CSRF).');
    }
}

// ---------- Flash message (one-time, survives a redirect) ----------
function flash($msg, $type = 'ok')
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function take_flash()
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- DB helpers (always prepared statements) ----------
function db_rows($sql, $types = '', ...$params)
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_row($sql, $types = '', ...$params)
{
    $rows = db_rows($sql, $types, ...$params);
    return $rows[0] ?? null;
}

// Returns affected rows (INSERT/UPDATE/DELETE)
function db_exec($sql, $types = '', ...$params)
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// ---------- Dates ----------
function today()
{
    return date('Y-m-d');
}

function valid_date($s)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $s);
    return $d && $d->format('Y-m-d') === $s;
}

const TH_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const TH_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

// 2026-11-09 -> "จันทร์ 9 พฤศจิกายน 2569"
function th_date($ymd)
{
    $t = strtotime($ymd);
    return TH_DAYS[(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' '
        . TH_MONTHS[(int) date('n', $t)] . ' ' . ((int) date('Y', $t) + 543);
}
