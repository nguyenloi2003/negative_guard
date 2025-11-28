<?php
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/fb_graph.php';
require_once __DIR__ . '/../../lib/openai_client.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_admin();

// set thời gian việt nam
function format_vn_time($utcTime)
{
    if (empty($utcTime)) return '';
    try {
        $dt = new DateTime($utcTime);
        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format('d/m/Y H:i:s');
    } catch (Exception $e) {
        return $utcTime; // fallback nếu lỗi    
    }
}

send_security_headers();



$err = '';
$posts = [];
try {
    $posts = fb_get_page_posts(20)['data'] ?? [];
} catch (Exception $e) {
    $err = $e->getMessage();
}
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF UPLOAD</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        body {
            font-family: system-ui, Segoe UI, Roboto, Arial, sans-serif;
            margin: 0;
            background: #ffffffff;
            color: #333;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: #9ca2d9ff;
            border-bottom: 1px solid #20274a
        }

        h1,
        h2 {
            font-size: 20px;
            margin: 0
        }

        h2 {
            font-size: 18px;
            margin: 24px 0 12px;
            color: #1f2937;
        }

        nav a {
            color: #9cc1ff;
            text-decoration: none
        }

        main {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 16px
        }

        form textarea,
        form input[type="password"],
        form input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #2c3566;
            background: white;
            color: black;
            font-size: 14px;
        }

        form input[type="password"],
        form input[type="text"] {
            margin-bottom: 12px;
        }

        button {
            margin-top: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            border: 0;
            background: #3759ff;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #2948dd;
        }

        button:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .section-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .warning {
            border: 1px solid #374151;
            border-left: 6px solid #64748b;
            border-radius: 10px;
            padding: 12px;
            margin: 10px 0;
            background: #fefefeff
        }

        .warning.high {
            border-left-color: #f59e0b
        }

        .warning.critical {
            border-left-color: #ef4444
        }

        .warning.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #1f2a5a;
            color: #9cc1ff;
            margin-right: 6px
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }

        #changePasswordResult {
            margin-top: 12px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            margin: 0;
        }

        .close-modal:hover {
            color: #374151;
        }
    </style>
</head>

<body>
    <header style="display:flex;gap:12px;align-items:center;justify-content:space-between;padding:16px 24px;background:#0f1530;border-bottom:1px solid #20274a">
        <div><strong style="color: red;">Pdf Upload</strong></div>
        <nav>
            <a class="badge" href="/admin/dashboard.php">Bảng điều khiển</a>
            <a href="/admin/moderation.php" class="badge btn-danger">Cảnh báo cao</a>
            <a href="/logout.php" class="badge btn-success">Đăng xuất</a>
        </nav>
    </header>

    <main style="max-width:1100px;margin:24px auto;padding:0 16px">
        <form onsubmit="return uploadPDF(event)">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="scan_document">
            <input type="file" name="document_file" accept=".pdf,.docx,.doc" required>
            <button type="submit">Quét tài liệu</button>
        </form>
    </main>

    <!-- JavaScript handlers -->
    <script>
        async function uploadPDF(e) {
            e.preventDefault();
            const fd = new FormData(e.target);

            try {
                const res = await fetch('/admin/action.php', {
                    method: 'POST',
                    body: fd
                });

                const text = await res.text();
                console.log('Raw response:', text);
                console.log('Response length:', text.length);

                const data = JSON.parse(text);
                // ... xử lý kết quả  
            } catch (e) {
                alert('Lỗi quét PDF: ' + e.message);
            }
        }
    </script>

</body>

</html>