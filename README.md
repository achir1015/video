# video https://ipmos.ngrok.app/video.php
規劃會員影片串流系統架構與播放器實現方案。
<img width="1882" height="733" alt="image" src="https://github.com/user-attachments/assets/04cd2086-88ea-44a6-8f1f-5024ffe33a4a" />
<img width="1916" height="744" alt="image" src="https://github.com/user-attachments/assets/f278f6c4-8387-4659-9881-c757d36adf13" />
<img width="952" height="513" alt="image" src="https://github.com/user-attachments/assets/a5a86b44-a472-49ce-983e-f67552948ed4" />

📦 安裝步驟
① 上傳 2 個檔案到 /volume3/web/：
檔案說明video.php影片瀏覽播放主頁video_stream.php安全串流（不讓人直接下載）
② 開啟網址測試：
https://ipmos.ngrok.app/video.php
③ 在 index.html 導覽列加入：
html<a href="video.php">🎬 影片庫</a>

✅ 功能說明
功能說明🔐 權限控制guest 無法進入，需要 user 以上📁 資料夾瀏覽左側點選資料夾，支援多層目錄▶️ 線上播放點影片卡片即播，不需下載🔍 搜尋左上角即時搜尋影片名稱⛶ 全螢幕播放器右下角或按 F 鍵⌨️ 鍵盤快捷鍵空白鍵播放暫停、← → 快退快進10秒、↑↓ 音量📱 手機支援自動切換直排布局🛡️ 防盜連結video_stream.php 驗證登入才串流

⚠️ 解析度選擇（1080P/720P/480P）需要預先用 FFmpeg 轉檔成不同畫質版本。目前播放原始檔案，若影片本身是 1080P 就是 1080P 畫質。
