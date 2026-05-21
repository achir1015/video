<?php
// ============================================================
// video.php  影片瀏覽與播放
// 需要 user 以上（guest 不可看）
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();
require_once __DIR__ . '/fan_members/config/config.php';
ob_end_clean();
require_once __DIR__ . '/fan_members/includes/Auth.php';

// ── 權限：需要至少 user ───────────────────────────────────────
if (!Auth::isLoggedIn() || Auth::currentUser()['role'] === 'guest') {
    header('Location: /fan_members/login.php?redirect=' . urlencode('/video.php'));
    exit;
}
$user    = Auth::currentUser();
$isAdmin = $user['role'] === 'admin';

// ── 影片根目錄 ────────────────────────────────────────────────
define('VIDEO_ROOT',  '/volume3/video');
define('VIDEO_EXTS',  ['mp4','mkv','avi','mov','wmv','flv','webm','m4v']);

// ── 目前瀏覽路徑 ──────────────────────────────────────────────
$reqDir  = $_GET['dir'] ?? '';
$reqDir  = ltrim(str_replace(['..','./'], '', $reqDir), '/');
$curDir  = VIDEO_ROOT . ($reqDir ? '/' . $reqDir : '');
$curDir  = realpath($curDir);

if (!$curDir || strpos($curDir, VIDEO_ROOT) !== 0 || !is_dir($curDir)) {
    $curDir = VIDEO_ROOT;
    $reqDir = '';
}

// ── 讀取目錄內容 ──────────────────────────────────────────────
$folders = $files = [];
if (is_dir($curDir)) {
    foreach (scandir($curDir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $curDir . '/' . $item;
        if (is_dir($path)) {
            $folders[] = ['name' => $item, 'path' => $reqDir ? $reqDir.'/'.$item : $item, 'count' => count(glob($path.'/*.{'.implode(',', VIDEO_EXTS).'}', GLOB_BRACE))];
        } elseif (in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), VIDEO_EXTS)) {
            $files[] = ['name' => $item, 'path' => ($reqDir ? $reqDir.'/' : '') . $item, 'size' => filesize($path), 'mtime' => filemtime($path), 'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))];
        }
    }
    usort($folders, function($a,$b){ return strnatcmp($a['name'], $b['name']); });
    usort($files,   function($a,$b){ return strnatcmp($a['name'], $b['name']); });
}

// ── 播放的影片 ───────────────────────────────────────────────
$playFile = $_GET['play'] ?? '';
$playFile = ltrim(str_replace(['..','./'], '', $playFile), '/');
$playTitle = '';
$playStream = '';
if ($playFile) {
    $fp = realpath(VIDEO_ROOT . '/' . $playFile);
    if ($fp && strpos($fp, VIDEO_ROOT) === 0 && is_file($fp)) {
        $playTitle  = pathinfo($fp, PATHINFO_FILENAME);
        $playStream = '/video_stream.php?file=' . urlencode($playFile);
    }
}

// ── 麵包屑 ───────────────────────────────────────────────────
function buildCrumbs($path) {
    $crumbs = [];
    if (!$path) return $crumbs;
    $parts = explode('/', trim($path, '/'));
    $acc   = '';
    foreach ($parts as $p) {
        $acc .= ($acc ? '/' : '') . $p;
        $crumbs[] = ['name' => $p, 'path' => $acc];
    }
    return $crumbs;
}
$crumbs = buildCrumbs($reqDir);

function fmtSize($b) {
    if ($b >= 1073741824) return round($b/1073741824, 1) . ' GB';
    if ($b >= 1048576)    return round($b/1048576, 1)    . ' MB';
    return round($b/1024, 1) . ' KB';
}

$display_name = htmlspecialchars($user['display_name']);
$avatar_url   = isset($user['avatar_url']) ? $user['avatar_url'] : '';
$initial      = mb_substr(isset($user['display_name']) ? $user['display_name'] : 'U', 0, 1);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $playTitle ? esc($playTitle).' | ' : ''; ?>影片庫 | 優力好資訊</title>
<link rel="shortcut icon" href="http://ipmos.tw/files/License-2.png">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<!-- Plyr 播放器 -->
<link  rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.js"></script>
<script src="/member_widget.js" defer></script>
<style>
:root{
  --bg:#0d1117;--bg2:#161b22;--bg3:#1c2330;--card:#1f2937;
  --border:rgba(255,255,255,.08);--border2:rgba(255,255,255,.13);
  --text:#e6edf3;--muted:rgba(230,237,243,.5);
  --teal:#2D7D90;--teal-lt:#4AA3B5;--gold:#E8A54B;--gold-lt:#F5C882;
  --violet:#a78bfa;--red:#f87171;--green:#34d399;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Noto Sans TC',sans-serif;background:var(--bg);color:var(--text);line-height:1.6}

/* ── 頂部 ── */
.topbar{background:linear-gradient(135deg,#1D5A68,#1a2535);border-bottom:1px solid var(--border);padding:0 1.4rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:9999;box-shadow:0 2px 16px rgba(0,0,0,.4)}
.logo{display:flex;align-items:center;gap:9px;text-decoration:none;color:white}
.logo-icon{width:36px;height:36px;border-radius:9px;background:var(--gold);overflow:hidden;display:flex;align-items:center;justify-content:center;border:1.5px solid rgba(255,255,255,.2)}
.logo-icon img{width:100%;height:100%;object-fit:cover}
.logo-txt h1{font-size:.9rem;font-weight:700;line-height:1.2;color:white}
.logo-txt span{font-size:.62rem;color:rgba(255,255,255,.55)}
.top-right{display:flex;align-items:center;gap:8px}
.badge{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:20px;padding:4px 11px 4px 5px}
.av{width:26px;height:26px;border-radius:50%;object-fit:cover;border:1.5px solid var(--gold)}
.av-ph{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--gold));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0}
.uname{font-size:.78rem;font-weight:500}
.urole{font-size:.6rem;background:rgba(232,165,75,.2);color:var(--gold-lt);padding:1px 6px;border-radius:7px;border:1px solid rgba(232,165,75,.3)}
.btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:16px;font-size:.75rem;font-weight:500;text-decoration:none;transition:.2s;cursor:pointer;border:none;font-family:inherit}
.btn-back{background:rgba(45,125,144,.18);border:1px solid rgba(45,125,144,.35);color:var(--teal-lt)}
.btn-back:hover{background:rgba(45,125,144,.3);text-decoration:none;color:var(--teal-lt)}
.btn-danger{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:var(--red)}

/* ── 主體布局 ── */
.layout{display:flex;height:calc(100vh - 56px)}

/* ── 側邊欄 ── */
.sidebar{width:280px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;overflow-y:auto}
.sb-header{padding:.9rem 1rem;border-bottom:1px solid var(--border);font-size:.75rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;display:flex;align-items:center;gap:6px}
.sb-search{padding:.6rem .8rem;border-bottom:1px solid var(--border)}
.sb-search input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:7px;color:var(--text);padding:.45rem .8rem;font-size:.82rem;outline:none;font-family:inherit}
.sb-search input:focus{border-color:var(--teal)}
.sb-search input::placeholder{color:var(--muted)}

/* 麵包屑 */
.sb-crumbs{padding:.5rem .8rem;display:flex;align-items:center;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--border);min-height:38px}
.crumb-link{color:var(--teal-lt);text-decoration:none;font-size:.75rem;padding:2px 6px;border-radius:5px;transition:.15s}
.crumb-link:hover{background:rgba(45,125,144,.15)}
.crumb-sep{color:var(--muted);font-size:.7rem}
.crumb-cur{color:var(--text);font-size:.75rem;font-weight:500}

/* 資料夾列表 */
.folder-list{flex:1;overflow-y:auto;padding:.4rem}
.folder-item{display:flex;align-items:center;gap:8px;padding:.55rem .8rem;border-radius:8px;cursor:pointer;transition:.15s;text-decoration:none;color:var(--text)}
.folder-item:hover{background:rgba(255,255,255,.05)}
.folder-item.active{background:rgba(45,125,144,.15);color:var(--teal-lt)}
.folder-icon{font-size:1.1rem;flex-shrink:0}
.folder-name{flex:1;font-size:.85rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.folder-count{font-size:.65rem;color:var(--muted);background:rgba(255,255,255,.07);padding:1px 6px;border-radius:8px;white-space:nowrap}

/* ── 主內容 ── */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}

/* 影片播放器區 */
.player-section{background:#000;flex-shrink:0;position:relative}
.player-section.hidden{display:none}
.player-wrap{max-width:1280px;margin:0 auto;width:100%}
.player-title{padding:.6rem 1rem;background:rgba(0,0,0,.6);font-size:.9rem;font-weight:500;display:flex;align-items:center;gap:8px;color:white}
.player-title .close-btn{margin-left:auto;background:rgba(255,255,255,.1);border:none;color:white;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:.75rem}
.player-title .close-btn:hover{background:rgba(255,255,255,.2)}

/* Plyr 客製化 */
.plyr{--plyr-color-main:#2D7D90;border-radius:0;width:100%}
.plyr video{max-height:60vh;object-fit:contain;background:#000}

/* 檔案清單 */
.file-section{flex:1;overflow-y:auto;padding:.8rem 1rem}
.section-title{font-size:.75rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;padding:.4rem 0 .6rem;border-bottom:1px solid var(--border);margin-bottom:.6rem;display:flex;align-items:center;gap:6px}
.video-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.8rem;margin-bottom:1.5rem}
.video-card{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow:hidden;cursor:pointer;transition:.2s}
.video-card:hover{border-color:var(--teal);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.video-card.playing{border-color:var(--gold);box-shadow:0 0 0 2px rgba(232,165,75,.3)}
.video-thumb{width:100%;height:110px;background:linear-gradient(135deg,#1a2535,#0d1117);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.video-thumb .play-icon{font-size:2.2rem;opacity:.7;transition:.2s}
.video-card:hover .play-icon{opacity:1;transform:scale(1.1)}
.video-thumb .ext-badge{position:absolute;bottom:5px;right:6px;background:rgba(0,0,0,.7);color:var(--gold-lt);font-size:.6rem;padding:2px 6px;border-radius:5px;font-weight:600;text-transform:uppercase}
.video-thumb .now-playing{position:absolute;top:5px;left:6px;background:var(--gold);color:#000;font-size:.6rem;padding:2px 7px;border-radius:5px;font-weight:700}
.video-info{padding:.6rem .7rem}
.video-name{font-size:.78rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.25rem}
.video-meta{font-size:.65rem;color:var(--muted);display:flex;justify-content:space-between}

/* 空狀態 */
.empty{text-align:center;padding:4rem;color:var(--muted)}
.empty-icon{font-size:3rem;margin-bottom:.8rem}

/* 頁腳 */
.footer{background:var(--bg2);border-top:1px solid var(--border);padding:.45rem 1rem;font-size:.65rem;color:var(--muted);display:flex;justify-content:space-between;flex-shrink:0}
.footer a{color:var(--gold-lt);text-decoration:none}

/* ── RWD 手機版 ── */
@media(max-width:768px){
  .layout{flex-direction:column}
  .sidebar{width:100%;height:auto;max-height:220px;border-right:none;border-bottom:1px solid var(--border)}
  .folder-list{display:flex;flex-direction:row;flex-wrap:wrap;padding:.4rem;gap:.3rem;overflow-x:auto;overflow-y:hidden}
  .folder-item{white-space:nowrap;flex-shrink:0;padding:.4rem .7rem}
  .plyr video{max-height:40vh}
  .video-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem}
}
</style>
</head>
<body>

<!-- 頂部 -->
<div class="topbar">
  <a href="/index.html" class="logo" title="回首頁">
    <div class="logo-icon">
      <img src="http://ipmos.tw/files/License-2.png" alt="優力好資訊"
           onerror="this.style.display='none';this.parentElement.textContent='🌿'">
    </div>
    <div class="logo-txt"><h1>優力好資訊</h1><span>影片庫</span></div>
  </a>
  <div class="top-right">
    <a href="/index.html" class="btn btn-back">← 首頁</a>
    <div class="badge">
      <?php if ($avatar_url): ?>
        <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="av" alt="">
      <?php else: ?>
        <div class="av-ph"><?php echo $initial; ?></div>
      <?php endif; ?>
      <span class="uname"><?php echo $display_name; ?></span>
      <span class="urole">
        <?php echo $isAdmin ? '🔑 管理員' : '⭐ 會員'; ?>
      </span>
    </div>
    <a href="/fan_members/logout.php" class="btn btn-danger">登出</a>
  </div>
</div>

<div class="layout">

  <!-- 左側：資料夾瀏覽 -->
  <aside class="sidebar">
    <div class="sb-header">🎬 影片資料夾</div>

    <!-- 搜尋 -->
    <div class="sb-search">
      <input type="text" id="searchInput" placeholder="🔍 搜尋影片...">
    </div>

    <!-- 麵包屑 -->
    <div class="sb-crumbs">
      <a href="video.php" class="crumb-link">🏠 根目錄</a>
      <?php $lastCrumb = end($crumbs); foreach ($crumbs as $c): ?>
        <span class="crumb-sep">›</span>
        <?php if ($c['path'] === $lastCrumb['path']): ?>
          <span class="crumb-cur"><?php echo htmlspecialchars($c['name']); ?></span>
        <?php else: ?>
          <a href="video.php?dir=<?php echo urlencode($c['path']); ?>" class="crumb-link">
            <?php echo htmlspecialchars($c['name']); ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- 資料夾 -->
    <div class="folder-list" id="folderList">
      <?php if ($reqDir): ?>
        <a href="video.php?dir=<?php echo urlencode(dirname($reqDir)); ?>"
           class="folder-item">
          <span class="folder-icon">⬆️</span>
          <span class="folder-name">上層目錄</span>
        </a>
      <?php endif; ?>

      <?php foreach ($folders as $f): ?>
        <a href="video.php?dir=<?php echo urlencode($f['path']); ?>"
           class="folder-item <?php echo ($reqDir === $f['path']) ? 'active' : ''; ?>">
          <span class="folder-icon">📁</span>
          <span class="folder-name"><?php echo htmlspecialchars($f['name']); ?></span>
          <?php if ($f['count'] > 0): ?>
            <span class="folder-count"><?php echo $f['count']; ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if (!$folders && !$reqDir): ?>
        <div style="padding:1rem;font-size:.78rem;color:var(--muted);text-align:center">
          載入中…
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <!-- 主要內容 -->
  <div class="main">

    <!-- 播放器 -->
    <div class="player-section <?php echo $playFile ? '' : 'hidden'; ?>" id="playerSection">
      <div class="player-wrap">
        <?php if ($playFile): ?>
        <div class="player-title">
          🎬 <?php echo htmlspecialchars($playTitle); ?>
          <button class="close-btn" onclick="closePlayer()">✕ 關閉</button>
        </div>
        <?php endif; ?>
        <video id="videoPlayer" playsinline controls
               <?php if ($playFile): ?>src="<?php echo htmlspecialchars($playStream); ?>"<?php endif; ?>>
        </video>
      </div>
    </div>

    <!-- 影片列表 -->
    <div class="file-section">

      <!-- 路徑標題 -->
      <div class="section-title">
        🎬
        <?php echo $reqDir ? htmlspecialchars(basename($reqDir)) : '全部影片'; ?>
        <span style="color:var(--muted);font-weight:400">(<?php echo count($files); ?> 部)</span>
      </div>

      <?php if ($files): ?>
      <div class="video-grid" id="videoGrid">
        <?php foreach ($files as $f):
          $isPlaying = ($playFile === $f['path']);
          $playUrl   = 'video.php?dir=' . urlencode($reqDir) . '&play=' . urlencode($f['path']);
          $nameNoExt = pathinfo($f['name'], PATHINFO_FILENAME);
          $extUpper  = strtoupper($f['ext']);
          $icon      = in_array($f['ext'], ['mp4','m4v','webm']) ? '▶️' : '🎞️';
        ?>
        <div class="video-card <?php echo $isPlaying ? 'playing' : ''; ?>"
             onclick="playVideo('<?php echo addslashes(htmlspecialchars($f['path'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($nameNoExt)); ?>')">
          <div class="video-thumb">
            <span class="play-icon"><?php echo $icon; ?></span>
            <span class="ext-badge"><?php echo $extUpper; ?></span>
            <?php if ($isPlaying): ?>
              <span class="now-playing">▶ 播放中</span>
            <?php endif; ?>
          </div>
          <div class="video-info">
            <div class="video-name" title="<?php echo htmlspecialchars($f['name']); ?>">
              <?php echo htmlspecialchars($nameNoExt); ?>
            </div>
            <div class="video-meta">
              <span><?php echo fmtSize($f['size']); ?></span>
              <span><?php echo date('Y/m/d', $f['mtime']); ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php else: ?>
      <div class="empty">
        <div class="empty-icon">📂</div>
        <div><?php echo $folders ? '請從左側選擇資料夾' : '此目錄沒有影片'; ?></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="footer">
      <span>© 2024 <a href="/index.html">優力好資訊</a> | 影片庫</span>
      <span>🔐 會員專屬 | <?php echo count($files); ?> 部影片</span>
    </div>

  </div><!-- /.main -->
</div><!-- /.layout -->

<script>
// ── Plyr 播放器初始化 ─────────────────────────────────────────
var player = null;

function initPlayer() {
  if (player) player.destroy();
  player = new Plyr('#videoPlayer', {
    controls: [
      'play-large','play','rewind','fast-forward','progress',
      'current-time','duration','mute','volume',
      'settings','pip','airplay','fullscreen'
    ],
    settings: ['quality','speed'],
    speed: { selected:1, options:[0.5, 0.75, 1, 1.25, 1.5, 2] },
    quality: {
      default: 1080,
      options: [4320, 2880, 2160, 1440, 1080, 720, 576, 480, 360, 240],
      forced: true,
      onChange: function(quality) { console.log('Quality: ' + quality); }
    },
    i18n: {
      speed: '播放速度',
      quality: '畫質',
      normal: '標準',
    },
    keyboard: { focused: true, global: true },
    tooltips: { controls: true, seek: true },
  });
}

// ── 播放影片 ─────────────────────────────────────────────────
function playVideo(filePath, title) {
  var streamUrl = '/video_stream.php?file=' + encodeURIComponent(filePath);
  var ps = document.getElementById('playerSection');

  // 更新播放器標題
  var pt = ps.querySelector('.player-title');
  if (!pt) {
    pt = document.createElement('div');
    pt.className = 'player-title';
    ps.querySelector('.player-wrap').prepend(pt);
  }
  pt.innerHTML = '🎬 ' + title + ' <button class="close-btn" onclick="closePlayer()">✕ 關閉</button>';

  // 顯示播放器
  ps.classList.remove('hidden');

  // 設定影片來源
  var vid = document.getElementById('videoPlayer');
  vid.src = streamUrl;

  // 初始化 Plyr
  initPlayer();
  player.play();

  // 更新卡片狀態
  document.querySelectorAll('.video-card').forEach(function(c){ c.classList.remove('playing'); });
  event.currentTarget && event.currentTarget.classList.add('playing');

  // 滾動到播放器
  ps.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── 關閉播放器 ───────────────────────────────────────────────
function closePlayer() {
  if (player) player.pause();
  document.getElementById('playerSection').classList.add('hidden');
  document.querySelectorAll('.video-card').forEach(function(c){ c.classList.remove('playing'); });
}

// ── 搜尋篩選 ─────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function() {
  var q = this.value.toLowerCase();
  document.querySelectorAll('.video-card').forEach(function(c) {
    var name = c.querySelector('.video-name').textContent.toLowerCase();
    c.style.display = name.includes(q) ? '' : 'none';
  });
});

// ── 初始化（若已有播放影片）─────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  var vid = document.getElementById('videoPlayer');
  if (vid && vid.src) initPlayer();
});

// ── 鍵盤快捷鍵 ───────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (!player || e.target.tagName === 'INPUT') return;
  switch(e.key) {
    case ' ':     e.preventDefault(); player.togglePlay(); break;
    case 'f':     player.fullscreen.toggle(); break;
    case 'm':     player.toggleMute(); break;
    case 'Escape': closePlayer(); break;
    case 'ArrowRight': player.forward(10); break;
    case 'ArrowLeft':  player.rewind(10); break;
    case 'ArrowUp':    player.increaseVolume(0.1); break;
    case 'ArrowDown':  player.decreaseVolume(0.1); break;
  }
});
</script>

</body>
</html>
<?php
function esc($s) { return htmlspecialchars(isset($s) ? $s : '', ENT_QUOTES, 'UTF-8'); }
?>
