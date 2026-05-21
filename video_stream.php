<?php
// ============================================================
// video_stream.php  安全影片串流
// 支援 Range Request（拖曳進度條）
// 只有 member 以上才能播放
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();
require_once __DIR__ . '/fan_members/config/config.php';
ob_end_clean();
require_once __DIR__ . '/fan_members/includes/Auth.php';

// ── 權限檢查：guest 以上不含 guest，至少要 user ─────────────
$user = Auth::currentUser();
if (!Auth::isLoggedIn() || $user['role'] === 'guest') {
    http_response_code(403);
    die('403 Forbidden: 需要會員身份才能播放');
}

// ── 安全路徑驗證 ─────────────────────────────────────────────
define('VIDEO_ROOT', '/volume3/video');

$reqFile = isset($_GET['file']) ? $_GET['file'] : '';
if (!$reqFile) { http_response_code(400); die('缺少檔案參數'); }

// 解碼並清理路徑
$reqFile  = urldecode($reqFile);
$fullPath = realpath(VIDEO_ROOT . '/' . $reqFile);

// 確保路徑在 VIDEO_ROOT 內（防止路徑穿越攻擊）
if (!$fullPath || strpos($fullPath, VIDEO_ROOT) !== 0) {
    http_response_code(403);
    die('403 Forbidden: 非法路徑');
}

// 確認檔案存在且為影片
if (!is_file($fullPath)) {
    http_response_code(404);
    die('404 Not Found');
}

$allowedExt = ['mp4','mkv','avi','mov','wmv','flv','webm','m4v','ts','m3u8'];
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt)) {
    http_response_code(403);
    die('不支援的檔案類型');
}

// ── MIME 類型對應 ─────────────────────────────────────────────
$mimeMap = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mkv'  => 'video/x-matroska',
    'avi'  => 'video/x-msvideo',
    'mov'  => 'video/quicktime',
    'wmv'  => 'video/x-ms-wmv',
    'flv'  => 'video/x-flv',
    'm4v'  => 'video/mp4',
    'ts'   => 'video/mp2t',
    'm3u8' => 'application/x-mpegURL',
];
$mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'video/mp4';

// ── Range Request 串流（支援拖曳進度）────────────────────────
$fileSize = filesize($fullPath);
$start    = 0;
$end      = $fileSize - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// 處理 Range 標頭
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    $start = $matches[1] !== '' ? (int)$matches[1] : 0;
    $end   = $matches[2] !== '' ? (int)$matches[2] : $fileSize - 1;

    if ($end >= $fileSize) $end = $fileSize - 1;
    if ($start > $end) { http_response_code(416); die(); }

    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
} else {
    http_response_code(200);
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

// 串流輸出
$fp = fopen($fullPath, 'rb');
fseek($fp, $start);

$bufSize = 1024 * 256; // 256KB 緩衝
$sent    = 0;
while (!feof($fp) && $sent < $length) {
    $chunk = min($bufSize, $length - $sent);
    echo fread($fp, $chunk);
    $sent += $chunk;
    flush();
}
fclose($fp);
exit;
