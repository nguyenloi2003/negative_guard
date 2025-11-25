<?php
require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/fb_graph.php';
require_once __DIR__ . '/../../lib/openai_client.php';
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
    <title>Dashboard Fanpage</title>
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
        <div><strong style="color: red;">Admin Dashboard</strong></div>
        <nav>
            <a href="/admin/moderation.php" class="badge btn-danger">Cảnh báo cao</a>
            <a href="#" class="badge btn-success" onclick="openPasswordModal(event)">Đổi mật khẩu</a>
            <a href="/logout.php" class="badge btn-success">Đăng xuất</a>
        </nav>
    </header>

    <main style="max-width:1100px;margin:24px auto;padding:0 16px">
        <?php if ($err): ?><div class="warning critical">Lỗi Graph API: <?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- Modal Đổi mật khẩu -->
        <div id="passwordModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🔐 Đổi mật khẩu</h2>
                    <button class="close-modal" onclick="closePasswordModal()">&times;</button>
                </div>
                <form method="post" action="/admin/action.php" onsubmit="return changePassword(event)">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" required placeholder="Nhập mật khẩu hiện tại">
                    </div>

                    <div class="form-group">
                        <label for="new_password">Mật khẩu mới</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Nhập mật khẩu mới (tối thiểu 8 ký tự)" minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới" minlength="8">
                    </div>

                    <button type="submit">Đổi mật khẩu</button>
                </form>
            </div>
        </div>

        <!-- Form Đăng bài thông báo -->
        <div class="section-card">
            <h2>📢 Đăng bài thông báo</h2>
            <form method="post" action="/admin/action.php" onsubmit="return publishNotice(event)">
                <div class="row" style="display: flex; gap: 10px;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="publish_post">
                    <textarea rows="2" name="message" class="col-md-7" placeholder="Nhập nội dung đăng bài cảnh báo!"></textarea>
                    <button type="submit" class="col-md-5">Đăng bài thông báo</button>
                </div>
            </form>
        </div>

        <!-- Danh sách bài viết -->
        <h2>📝 Bài viết gần đây</h2>
        <?php foreach ($posts as $p): ?>
            <article class="warning" style="border-left-color:#3759ff">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center">
                    <div>
                        <div><a class="badge" target="_blank" href="<?= htmlspecialchars($p['permalink_url'] ?? '#') ?>">Mở Facebook</a> <span class="badge"><?= htmlspecialchars($p['id']) ?></span></div>
                        <div style="margin-top:8px;white-space:pre-wrap;"><?= htmlspecialchars($p['message'] ?? '[Không có nội dung]') ?></div>
                        <div style="opacity:.7;margin-top:6px;">Đăng lúc: <?= htmlspecialchars(format_vn_time($p['created_time'] ?? '')) ?></div>
                    </div>
                    <?php if (!empty($p['full_picture'])): ?>
                        <img src="<?= htmlspecialchars($p['full_picture']) ?>" alt="thumb" style="max-width:200px;border-radius:10px">
                    <?php endif; ?>
                </div>

                <details style="margin-top:10px">
                    <summary>Bình luận (<?= (int)($p['comments']['summary']['total_count'] ?? 0) ?>)</summary>
                    <div>
                        <?php foreach (($p['comments']['data'] ?? []) as $c): ?>
                            <div class="warning">
                                <div style="font-weight:600;"><?= htmlspecialchars(($c['from']['name'] ?? 'Ẩn danh') . ' — ' . format_vn_time($c['created_time'] ?? '')) ?></div>
                                <div style="white-space:pre-wrap;"><?= htmlspecialchars($c['message'] ?? '') ?></div>
                                <!-- <form method="post" action="/admin/action.php" onsubmit="return doComment(event, '<?= htmlspecialchars($c['id']) ?>')"> -->
                                <form method="post" action="/admin/action.php" onsubmit="return doComment(event)">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($c['id']) ?>">
                                    <textarea name="message" placeholder="Phản hồi cảnh báo..." style="width:100%; margin-top: 10px;"></textarea>
                                    <button class="badge" type="submit">Trả lời</button>
                                </form>
                                <div id="res-<?= htmlspecialchars($c['id']) ?>" class="warning" style="display:none"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>

                <div id="ana-<?= htmlspecialchars($p['id']) ?>" class="analysis-box"></div>
            </article>
        <?php endforeach; ?>
    </main>

    <!-- JavaScript handlers -->
    <script>
        // Mở modal đổi mật khẩu  
        function openPasswordModal(e) {
            e.preventDefault();
            document.getElementById('passwordModal').classList.add('show');
        }

        // Đóng modal  
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('show');
            // Reset form khi đóng  
            document.querySelector('#passwordModal form').reset();
        }

        // Đóng modal khi click bên ngoài  
        window.onclick = function(event) {
            const modal = document.getElementById('passwordModal');
            if (event.target === modal) {
                closePasswordModal();
            }
        }

        // Handler cho form đổi mật khẩu  
        async function changePassword(e) {
            e.preventDefault();
            e.stopPropagation(); // Thêm dòng này để chắc chắn  

            const form = e.target;
            const newPass = form.querySelector('#new_password').value;
            const confirmPass = form.querySelector('#confirm_password').value;

            // Kiểm tra mật khẩu khớp  
            if (newPass !== confirmPass) {
                alert('Mật khẩu mới và xác nhận không khớp!');
                return false;
            }

            // Kiểm tra độ dài  
            if (newPass.length < 8) {
                alert('Mật khẩu mới phải có ít nhất 8 ký tự!');
                return false;
            }

            const fd = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang xử lý...';

            try {
                const res = await fetch('/admin/action.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.error) {
                    alert('Lỗi: ' + data.error);
                } else {
                    alert('✓ Đổi mật khẩu thành công!');
                    form.reset();
                }
            } catch (err) {
                alert('Lỗi kết nối: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Đổi mật khẩu';
            }

            return false;
        }

        // Handler cho form đăng bài (giữ nguyên)  
        async function publishNotice(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang đăng...';

            try {
                const res = await fetch('/admin/action.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.error) {
                    alert('Lỗi: ' + data.error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                } else {
                    alert('✓ Đã đăng bài thành công!');
                    // Reload trang để thấy bài mới  
                    location.reload();
                }
            } catch (err) {
                alert('Lỗi kết nối: ' + err.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }

            return false;
        }

        // Handler cho comment (giữ nguyên)  
        async function doComment(e, id) {
            e.preventDefault();
            const fd = new FormData(e.target);
            const res = await fetch('/admin/action.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.error) alert('Lỗi: ' + data.error);
            else alert('Đã bình luận!');
            return false;
        }

        async function toggleHide(id, hide) {
            const fd = new FormData();
            fd.append('csrf', '<?= htmlspecialchars(csrf_token()) ?>');
            fd.append('action', 'hide_comment');
            fd.append('id', id);
            fd.append('hide', hide ? '1' : '0');
            const res = await fetch('/admin/action.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.error) alert('Lỗi: ' + data.error);
            else alert(hide ? 'Đã ẩn' : 'Đã hiện');
        }
    </script>

</body>

</html>