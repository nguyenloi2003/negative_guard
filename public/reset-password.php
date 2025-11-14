<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

send_security_headers();

$token = $_GET['token'] ?? '';
$pdo = db();

// Kiểm tra token  
$stmt = $pdo->prepare('  
    SELECT t.*, u.username   
    FROM password_reset_tokens t  
    JOIN admin_users u ON t.admin_id = u.id  
    WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()  
');
$stmt->execute([$token]);
$resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resetToken) {
    die('Link không hợp lệ hoặc đã hết hạn');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự';
    } else {
        // Cập nhật mật khẩu  
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $resetToken['admin_id']]);

        // Đánh dấu token đã dùng  
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = ?');
        $stmt->execute([$token]);

        header('Location: /login.php?reset=success');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - IUH Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0066cc 0%, #003d7a 100%);
            overflow: hidden;
            position: relative;
        }

        .background-pattern {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-image:
                radial-gradient(circle at 20% 50%, white 2px, transparent 2px),
                radial-gradient(circle at 80% 80%, white 2px, transparent 2px);
            background-size: 100px 100px;
            animation: float 20s linear infinite;
            pointer-events: none;
        }

        @keyframes float {
            0% {
                transform: translateY(0);
            }

            100% {
                transform: translateY(-100px);
            }
        }

        .reset-container {
            display: flex;
            width: 900px;
            max-width: 95%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out;
            z-index: 1;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-left {
            flex: 1;
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .reset-left::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .logo-container {
            text-align: center;
            z-index: 1;
        }

        .logo {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .logo-icon {
            font-size: 60px;
        }

        .welcome-text h2 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-text p {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .reset-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .reset-header {
            margin-bottom: 30px;
        }

        .reset-header h3 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .reset-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .user-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info svg {
            color: #0066cc;
        }

        .user-info span {
            color: #0066cc;
            font-weight: 600;
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.error {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1);
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            padding-left: 45px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            margin-bottom: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #e0e0e0;
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: #0066cc;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-link a:hover {
            color: #0052a3;
            text-decoration: underline;
        }

        .support-info {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .reset-container {
                flex-direction: column;
                width: 90%;
            }

            .reset-left {
                padding: 40px 30px;
            }

            .reset-right {
                padding: 40px 30px;
            }
        }
    </style>
</head>

<body>
    <div class="background-pattern"></div>

    <div class="reset-container">
        <div class="reset-left">
            <div class="logo-container">
                <div class="logo">
                    <div class="logo-icon">🔑</div>
                </div>
                <div class="welcome-text">
                    <h2>Tạo mật khẩu mới</h2>
                    <p>Hãy chọn một mật khẩu mạnh để bảo vệ tài khoản của bạn.</p>
                </div>
            </div>
        </div>

        <div class="reset-right">
            <div class="reset-header">
                <h3>Đặt lại mật khẩu</h3>
                <p>Nhập mật khẩu mới cho tài khoản của bạn.</p>
            </div>

            <div class="user-info">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span><?= htmlspecialchars($resetToken['username']) ?></span>
            </div>

            <?php if (isset($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="new_password" name="new_password" placeholder="Nhập mật khẩu mới" required>
                    </div>

                    <div class="input-wrapper" style="margin-top: 1rem;">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                    </div>
                    <button type="submit" class="submit-btn" style="margin-top: 1rem;">Đặt lại mật khẩu</button>
                </div>

            </form>
        </div>
    </div>
</body>

</html>