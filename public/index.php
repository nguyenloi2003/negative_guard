<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
send_security_headers();
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phân tích bài viết MXH</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <!-- favicon-->
    <link rel="shortcut icon" href="./favicon.svg" type="image/svg+xml">

    <!--css-->
    <link rel="stylesheet" href="./assets/style.css">


    <!-- google font link-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Roboto:wght@400;500;600&display=swap"
        rel="stylesheet">
</head>

<body>
    <!-- <header>
        <h1>Kiểm tra an toàn & định hướng thông tin</h1>
        <nav>
            <a href="/login.php">Đăng nhập Admin</a>
        </nav>
    </header> -->

    <!-- HEADER-->
    <header class="header">
        <div class="header-top">
            <div class="container">
                <ul class="contact-list">
                    <li class="contact-item">
                        <ion-icon name="mail-outline"></ion-icon>
                        <a href="mailto:iCheck@gmail.com" class="contact-link">iCheck@gmail.com</a>
                    </li>
                    <li class="contact-item">
                        <ion-icon name="call-outline"></ion-icon>
                        <a href="tel:+917558951351" class="contact-link">+0123456789</a>
                    </li>
                </ul>
                <ul class="social-list">
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-facebook"></ion-icon>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-instagram"></ion-icon>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-twitter"></ion-icon>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-youtube"></ion-icon>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="header-bottom" data-header>
            <div class="container">
                <a href="#" class="logo">iCheck</a>
                <nav class="navbar container" data-navbar>
                    <ul class="navbar-list">
                        <li>
                            <a href="/index.php" class="navbar-link" data-nav-link>Trang chủ</a>
                        </li>
                        <li>
                            <a href="/index.php#analyze" class="navbar-link" data-nav-link>Phân tích nhãn rủi ro</a>
                        </li>
                        <li>
                            <a href="/answer.html" class="navbar-link" data-nav-link>Trả lời IUH</a>
                        </li>
                        <li>
                            <a href="/index.php#contact" class="navbar-link" data-nav-link>Liên hệ</a>
                        </li>
                    </ul>
                </nav>
                <a href="/login.php" class="btn">Admin Login</a>
                <button class="nav-toggle-btn" aria-label="Toggle menu" data-nav-toggler>
                    <ion-icon name="menu-sharp" aria-hidden="true" class="menu-icon"></ion-icon>
                    <ion-icon name="close-sharp" aria-hidden="true" class="close-icon"></ion-icon>
                </button>
            </div>
        </div>
    </header>
    <main>
        <article>
            <!--HERO-->
            <section class="section hero" id="home" style="background-image: url('./assets/images/hero-bg.png')"
                aria-label="hero">
                <div class="container">
                    <div class="hero-content">
                        <img src="assets/images/iCheck_logo.png" alt="ICON" width="70" height="70">
                        <p class="section-subtitle">Welcome To iCheck</p>
                        <h1 class="h1 hero-title"></h1>
                        <p class="hero-text">

                        </p>
                    </div>
                    <figure class="hero-banner">
                        <img src="./assets/images/iuh.jpg" width="587" height="839" alt="hero banner" class="w-100">
                    </figure>
                </div>
            </section>

            <!--ABOUT-->
            <section class="section about" id="about" aria-label="about">
                <div class="container">
                    <figure class="about-banner">
                        <img src="/assets/images/trust_new.jpg" width="470" height="538" loading="lazy"
                            alt="about banner" class="w-100">
                    </figure>
                    <div class="about-content">
                        <p class="section-subtitle">About Us</p>
                        <h3 class="h3 section-title">
                            We Care About You</h3>
                        <p class="section-text section-text-1">
                            iCheck là trợ lý kiểm duyệt sử dụng AI và luật tiếng Việt giúp nhà trường, doanh nghiệp và cộng đồng
                            <strong>phát hiện sớm nội dung tiêu cực</strong>: tục tĩu, miệt thị, kích động, lừa đảo, đường link độc hại…
                            trên fanpage và bình luận. Chúng tôi muốn bạn <strong>an tâm truyền thông</strong>, còn việc “soi rủi ro” cứ để iCheck lo.
                        </p>
                        <p class="section-text">
                            Hệ thống <strong>tự động thu thập bài viết & bình luận</strong>, <strong>chấm điểm rủi ro theo thời gian thực</strong>,
                            cảnh báo tức thì và cung cấp bảng điều khiển trực quan để duyệt/gỡ/chỉnh chỉ với <strong>1 lần bấm</strong>.
                            Mọi thao tác đều được lưu vết, dữ liệu thuộc về bạn, và có thể tùy chỉnh danh sách từ nhạy cảm cho phù hợp bối cảnh giáo dục.
                        </p>

                    </div>
                </div>
            </section>
            <style>
                .card {
                    background: linear-gradient(135deg, #f8d7da, #f1b0b7);
                    color: #721c24;
                    padding: 20px 25px;
                    border-radius: 12px;
                    border: 1px solid #f5c6cb;
                    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    max-width: 600px;
                    margin: 10px 0 10px 0;
                    text-align: left;
                    animation: fadeIn 0.4s ease-in-out;
                    position: relative;
                }

                /* Tiêu đề trong thẻ */
                .card h3 {
                    margin-top: 0;
                    font-size: 18px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                /* Nội dung chi tiết */
                .card p {
                    margin: 8px 0 0 0;
                    font-size: 15px;
                    line-height: 1.6;
                }

                /* Hiệu ứng xuất hiện */
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(-5px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>

            <section id="features" style="margin-top: 5rem;">
                <div class="container">
                    <h3>Tính Năng Nổi Bật</h3>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">🤖</div>
                            <h3>AI Thông Minh</h3>
                            <p>Sử dụng trí tuệ nhân tạo và luật tiếng Việt để phát hiện nội dung tiêu cực với độ chính xác cao</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">⚡</div>
                            <h3>Thời Gian Thực</h3>
                            <p>Tự động thu thập và chấm điểm rủi ro theo thời gian thực, cảnh báo tức thì khi phát hiện vấn đề</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🛡️</div>
                            <h3>Bảo Vệ Toàn Diện</h3>
                            <p>Phát hiện tục tĩu, miệt thị, kích động, lừa đảo, đường link độc hại trên fanpage và bình luận</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">📊</div>
                            <h3>Lưu Vết Xử Lý</h3>
                            <p>Mọi thao tác được lưu vết, dữ liệu thuộc về bạn với 1 lần bấm</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔧</div>
                            <h3>Tùy Chỉnh Linh Hoạt</h3>
                            <p>Có thể tùy chỉnh danh sách từ nhạy cảm cho phù hợp bối cảnh giáo dục</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔗</div>
                            <h3>API & Webhook</h3>
                            <p>Tích hợp dễ dàng với hệ thống của bạn thông qua API và Webhook</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="stats" style="margin-top: 5rem;">
                <div class="container">
                    <h3>Số Liệu Ấn Tượng</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Hoạt Động Liên Tục</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">365</div>
                            <div class="stat-label">Ngày Trong Năm</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">99%</div>
                            <div class="stat-label">Độ Chính Xác</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">
                                < 1s</div>
                                    <div class="stat-label">Thời Gian Phản Hồi</div>
                            </div>
                        </div>
                    </div>
            </section>

            <style>
                /* Features Grid */
                .features-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                    gap: 30px;
                    margin-top: 50px;
                }

                .feature-card {
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
                    transition: transform 0.3s, box-shadow 0.3s;
                    text-align: center;
                }

                .feature-card:hover {
                    transform: translateY(-10px);
                    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
                }

                .feature-icon {
                    width: 70px;
                    height: 70px;
                    background: linear-gradient(135deg, #2196F3, #0f2557);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 2em;
                    color: white;
                }

                .feature-card h3 {
                    color: #0f2557;
                    margin-bottom: 15px;
                    font-size: 1.4em;
                }

                .feature-card p {
                    color: #666;
                    line-height: 1.6;
                }

                /* Benefits */
                .benefits-list {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-top: 40px;
                }

                .benefit-item {
                    background: white;
                    padding: 25px;
                    border-radius: 10px;
                    border-left: 4px solid #2196F3;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
                    transition: transform 0.3s;
                }

                .benefit-item:hover {
                    transform: translateX(10px);
                }

                .benefit-item h4 {
                    color: #0f2557;
                    margin-bottom: 10px;
                    font-size: 1.2em;
                }

                /* Stats Section */
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 30px;
                    margin-top: 50px;
                }

                .stat-card {
                    text-align: center;
                    padding: 40px 20px;
                    background: white;
                    border-radius: 15px;
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
                }

                .stat-number {
                    font-size: 3em;
                    color: #2196F3;
                    font-weight: bold;
                    margin-bottom: 10px;
                }

                .stat-label {
                    color: #666;
                    font-size: 1.1em;
                }

                /* Animations */
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes fadeInDown {
                    from {
                        opacity: 0;
                        transform: translateY(-30px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes float {

                    0%,
                    100% {
                        transform: translateY(0);
                    }

                    50% {
                        transform: translateY(-20px);
                    }
                }

                /* Responsive */
                @media (max-width: 768px) {
                    header h1 {
                        font-size: 2em;
                    }

                    header p {
                        font-size: 1em;
                    }

                    .about-content {
                        grid-template-columns: 1fr;
                    }

                    section h2 {
                        font-size: 2em;
                    }

                    nav ul {
                        flex-direction: column;
                        align-items: center;
                    }

                    nav li {
                        margin: 10px 0;
                    }
                }
            </style>

            <!-- ANALYZE -->
            <section class="section" id="analyze" style="padding:40px;">
                <div class="container">
                    <form id="analyzeForm">
                        <h3 class="h3 section-title">
                            Phân tích bài viết Facebook (hoặc MXH khác)</h3>
                        <textarea style="width: 100%; padding: 10px;" id="text" name="text" rows="10" maxlength="<?= htmlspecialchars(envv('MAX_TEXT_LEN', 5000)) ?>" required></textarea>
                        <button type="button" id="analyzeBtn" class="btn">Phân tích</button>
                    </form>
                    <section id="result" hidden>
                        <h2>Kết quả</h2>
                        <div>
                            <div id="risk"></div>
                            <div id="warnings"></div>
                        </div>
                    </section>
                </div>
            </section>
        </article>
    </main>
    <!--FOOTER-->
    <footer class="footer" id="contact">
        <div class="footer-top section">
            <div class="container">
                <div class="footer-brand">
                    <a href="#" class="logo">iCheck</a>
                    <p class="footer-text">
                        iCheck là trợ lý kiểm duyệt nội dung cho fanpage và cộng đồng. Hệ thống tự động thu thập
                        bài viết & bình luận, chấm điểm rủi ro theo thời gian thực, cảnh báo tức thì và lưu vết xử lý.
                        Giúp bạn an tâm truyền thông – việc “soi rủi ro” cứ để iCheck lo.
                    </p>
                    <div class="schedule">
                        <div class="schedule-icon">
                            <ion-icon name="time-outline"></ion-icon>
                        </div>
                        <span class="span">
                            24 X 7:<br>
                            365 Days
                        </span>
                    </div>
                </div>
                <ul class="footer-list">
                    <li>
                        <p class="footer-list-title">Other Links</p>
                    </li>
                    <li>
                        <a href="#" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="#analyze" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Analyze</span>
                        </a>
                    </li>
                    <li>
                        <a href="#contact" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Contact</span>
                        </a>
                    </li>
                    <li>
                        <a href="http://localhost/negative-info-guard/php/admin/login.php" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Login</span>
                        </a>
                    </li>
                </ul>
                <ul class="footer-list">
                    <li>
                        <p class="footer-list-title">Our Services</p>
                    </li>
                    <li>
                        <a href="#" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Thu thập post & comment tự động</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Chấm điểm rủi ro theo thời gian thực</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">Bộ lọc tục tiếng Việt có thể tùy chỉnh</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="footer-link">
                            <ion-icon name="add-outline"></ion-icon>
                            <span class="span">API & Webhook để tích hợp hệ thống</span>
                        </a>
                    </li>
                </ul>
                <ul class="footer-list">
                    <li>
                        <p class="footer-list-title">Contact Us</p>
                    </li>
                    <li class="footer-item">
                        <div class="item-icon">
                            <ion-icon name="location-outline"></ion-icon>
                        </div>
                        <a href="https://goo.gl/maps/BYA5MxQUg5B8ZFLcA">
                            <address class="item-text">
                                TP.HCM, Viet Nam
                            </address>
                        </a>
                    </li>
                    <li class="footer-item">
                        <div class="item-icon">
                            <ion-icon name="call-outline"></ion-icon>
                        </div>
                        <a href="tel:+0123456789" class="footer-link">+0123456789</a>
                    </li>
                    <li class="footer-item">
                        <div class="item-icon">
                            <ion-icon name="mail-outline"></ion-icon>
                        </div>
                        <a href="mailto:help@example.com" class="footer-link">iCheck@gmail.com</a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p class="copyright">
                    &copy; 2025 All Rights Reserved by iCheck
                </p>
                <ul class="social-list">
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-facebook"></ion-icon>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-instagram"></ion-icon>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="social-link">
                            <ion-icon name="logo-twitter"></ion-icon>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>
    <!--BACK TO TOP-->
    <a href="#top" class="back-top-btn" aria-label="back to top" data-back-top-btn>
        <ion-icon name="caret-up" aria-hidden="true"></ion-icon>
    </a>

    <!--custom js link-->
    <script src="./assets/js/script.js" defer></script>
    <!--ionicon link-->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="/assets/app.js"></script>
    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animate on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .benefit-item, .stat-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Counter animation for stats
        function animateCounter(element) {
            const target = element.textContent;
            const isNumber = /^\d+$/.test(target);

            if (isNumber) {
                const duration = 2000;
                const start = 0;
                const end = parseInt(target);
                const increment = end / (duration / 16);
                let current = start;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= end) {
                        element.textContent = end;
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 16);
            }
        }

        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const number = entry.target.querySelector('.stat-number');
                    animateCounter(number);
                    statsObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.5
        });

        document.querySelectorAll('.stat-card').forEach(card => {
            statsObserver.observe(card);
        });
    </script>
</body>

</html>