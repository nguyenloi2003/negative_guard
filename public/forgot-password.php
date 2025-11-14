<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/email.php';

send_security_headers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        $error = 'Vui lòng nhập tên đăng nhập';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, username, email FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['email'])) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = time() + 3600;

            $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (admin_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))');
            $stmt->execute([$user['id'], $token, $expiresAt]);

            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=$token";
            $subject = "Đặt lại mật khẩu - IUH Admin";
            $body = "  
                <h2>Yêu cầu đặt lại mật khẩu</h2>  
                <p>Xin chào {$user['username']},</p>  
                <p>Nhấn vào link sau để đặt lại mật khẩu:</p>  
                <p><a href='$resetLink'>$resetLink</a></p>  
                <p>Link này có hiệu lực trong 1 giờ.</p>  
            ";

            send_email($user['email'], $subject, $body);
            $success = 'Đã gửi link đặt lại mật khẩu vào email của bạn';
        } else {
            $success = 'Nếu tài khoản tồn tại, link đặt lại mật khẩu đã được gửi';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - IUH Admin</title>
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

        .forgot-container {
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

        .forgot-left {
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

        .forgot-left::before {
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

        .forgot-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .forgot-header {
            margin-bottom: 30px;
        }

        .forgot-header h3 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .forgot-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
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

        .message.success {
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
        }

        .form-group {
            margin-bottom: 25px;
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

        .support-info {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .forgot-container {
                flex-direction: column;
                width: 90%;
            }

            .forgot-left {
                padding: 40px 30px;
            }

            .forgot-right {
                padding: 40px 30px;
            }
        }
    </style>
</head>

<body>
    <div class="background-pattern"></div>

    <div class="forgot-container">
        <div class="forgot-left">
            <div class="logo-container">
                <div class="logo">
                    <div class="logo-icon">🔐</div>
                </div>
                <div class="welcome-text">
                    <h2>Khôi phục tài khoản</h2>
                    <p>Đừng lo lắng! Chúng tôi sẽ giúp bạn lấy lại quyền truy cập vào tài khoản của mình.</p>
                </div>
            </div>
        </div>

        <div class="forgot-right">
            <div class="forgot-header">
                <h3>Quên mật khẩu?</h3>
                <p>Nhập tên đăng nhập của bạn và chúng tôi sẽ gửi link đặt lại mật khẩu đến email đã đăng ký.</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="username">Tên đăng nhập</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Gửi link đặt lại mật khẩu</button>

                <div class="divider">hoặc</div>

                <div class="back-link">
                    <a href="/login.php">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7" />
                        </svg>
                        Quay lại đăng nhập
                    </a>
                </div>

                <div class="support-info">
                    Cần hỗ trợ? Liên hệ IT: <strong>it@iuh.edu.vn</strong>
                </div>
            </form>
        </div>
    </div>
</body>

</html>