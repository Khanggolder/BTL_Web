    
    <footer>
        <div class="container">
            <div class="footer-grid">
                
                
                <div class="footer-col">
                    <a href="index.php" class="footer-logo">
                        <i data-lucide="book-open" style="width: 32px; height: 32px;"></i>
                        <span>LearnHub</span>
                    </a>
                    <p style="font-size: 14px; line-height: 1.8;">LearnHub là nền tảng học trực tuyến chất lượng cao hàng đầu, cung cấp các chương trình đào tạo lập trình thực tế từ cơ bản đến nâng cao để giúp bạn thăng tiến sự nghiệp.</p>
                </div>

                
                <div class="footer-col">
                    <h3>Khám phá</h3>
                    <ul>
                        <li><a href="courses.php">Tất cả khóa học</a></li>
                        <li><a href="courses.php?category=Web+Development">Lập trình Web</a></li>
                        <li><a href="courses.php?level=BEGINNER">Dành cho người mới</a></li>
                        <li><a href="about.php">Về chúng tôi</a></li>
                    </ul>
                </div>

                
                <div class="footer-col">
                    <h3>Hỗ trợ</h3>
                    <ul>
                        <li><a href="contact.php">Liên hệ hỗ trợ</a></li>
                        <li><a href="terms.php">Điều khoản dịch vụ</a></li>
                        <li><a href="privacy.php">Chính sách bảo mật</a></li>
                        <li><a href="faq.php">Câu hỏi thường gặp (FAQs)</a></li>
                    </ul>
                </div>

                
                <div class="footer-col">
                    <h3>Thông tin liên hệ</h3>
                    <p style="font-size: 14px; margin-bottom: 8px;"><strong style="color: white;">Địa chỉ:</strong> Số 1 Đại Cồ Việt, Hai Bà Trưng, Hà Nội</p>
                    <p style="font-size: 14px; margin-bottom: 8px;"><strong style="color: white;">Điện thoại:</strong> 024 1234 5678</p>
                    <p style="font-size: 14px; margin-bottom: 8px;"><strong style="color: white;">Email:</strong> support@learnhub.edu.vn</p>
                </div>

            </div>

            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> LearnHub Education Platform. Bảo lưu mọi quyền.</p>
                <div style="display: flex; gap: 16px;">
                    <a href="#" style="color: inherit;"><i data-lucide="facebook" style="width: 20px; height: 20px;"></i></a>
                    <a href="#" style="color: inherit;"><i data-lucide="youtube" style="width: 20px; height: 20px;"></i></a>
                    <a href="#" style="color: inherit;"><i data-lucide="github" style="width: 20px; height: 20px;"></i></a>
                </div>
            </div>
        </div>
    </footer>

    
    <script>
        
        lucide.createIcons();

        
        window.addEventListener('scroll', function() {
            const header = document.getElementById('main-header');
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        
        const searchBtn = document.getElementById('search-toggle-btn');
        const searchDropdown = document.getElementById('search-dropdown-wrapper');
        
        if (searchBtn && searchDropdown) {
            searchBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (searchDropdown.style.display === 'none') {
                    searchDropdown.style.display = 'block';
                    searchDropdown.style.opacity = '0';
                    setTimeout(() => {
                        searchDropdown.style.opacity = '1';
                    }, 50);
                    searchDropdown.querySelector('input').focus();
                } else {
                    searchDropdown.style.opacity = '0';
                    setTimeout(() => {
                        searchDropdown.style.display = 'none';
                    }, 300);
                }
            });

            
            document.addEventListener('click', function(e) {
                if (!searchDropdown.contains(e.target) && e.target !== searchBtn && !searchBtn.contains(e.target)) {
                    searchDropdown.style.opacity = '0';
                    setTimeout(() => {
                        searchDropdown.style.display = 'none';
                    }, 300);
                }
            });
        }

        const notificationMenu = document.getElementById('notification-menu');
        const notificationToggle = document.getElementById('notification-toggle');

        if (notificationMenu && notificationToggle) {
            notificationToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = notificationMenu.classList.toggle('open');
                notificationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            notificationMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function() {
                notificationMenu.classList.remove('open');
                notificationToggle.setAttribute('aria-expanded', 'false');
            });
        }

        
        const userMenu = document.getElementById('user-menu');
        const userMenuToggle = document.getElementById('user-menu-toggle');

        if (userMenu && userMenuToggle) {
            userMenuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = userMenu.classList.toggle('open');
                userMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            userMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function() {
                userMenu.classList.remove('open');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            });
        }
    </script>
</body>
</html>
