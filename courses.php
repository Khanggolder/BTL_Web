<?php
$page_title = "Khóa học - Danh sách chương trình đào tạo";
require_once __DIR__ . '/includes/header.php';


$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? 'all');
$level = trim($_GET['level'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'newest');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 9;
$total_courses = 0;
$total_pages = 1;
$offset = 0;
$level_labels = [
    'all' => 'Tất cả cấp độ',
    'BEGINNER' => 'Cơ bản',
    'INTERMEDIATE' => 'Trung cấp',
    'ADVANCED' => 'Nâng cao',
];
$sort_labels = [
    'newest' => 'Mới nhất',
    'price_asc' => 'Giá thấp đến cao',
    'price_desc' => 'Giá cao đến thấp',
];
if (!array_key_exists($sort, $sort_labels)) {
    $sort = 'newest';
}
$selected_level_label = $level_labels[$level] ?? 'Tất cả cấp độ';
$selected_category_label = 'Tất cả khóa học';
$selected_sort_label = $sort_labels[$sort];

try {
    $cat_stmt = $pdo->query("SELECT DISTINCT category FROM courses WHERE published = 1 AND category IS NOT NULL ORDER BY category");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($category !== 'all') {
        $selected_category_label = $category;
    }

    $where = ['published = 1'];
    $params = [];

    if ($category !== 'all') {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    if ($level !== 'all') {
        $where[] = 'level = ?';
        $params[] = $level;
    }

    if ($search !== '') {
        $where[] = '(title LIKE ? OR description LIKE ? OR instructor LIKE ?)';
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_sql = implode(' AND ', $where);
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE $where_sql");
    $count_stmt->execute($params);
    $total_courses = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_courses / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $order_by = match ($sort) {
        'price_asc' => 'CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE price END ASC, id DESC',
        'price_desc' => 'CASE WHEN discount_price > 0 AND discount_price < price THEN discount_price ELSE price END DESC, id DESC',
        default => 'id DESC',
    };
    $sql = "SELECT * FROM courses WHERE $where_sql ORDER BY $order_by LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    $error_msg = 'Có lỗi xảy ra: ' . $e->getMessage();
    $courses = [];
    $total_courses = 0;
    $total_pages = 1;
}
?>

<div class="section" style="background-color: var(--bg-main); min-height: 80vh; padding: 40px 0;">
    <div class="container">
        
        
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: 36px; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Tất cả Khóa học</h1>
            <p style="color: var(--text-muted); font-size: 16px;">Khám phá bộ sưu tập các khóa học lập trình thực chiến, chất lượng cao của chúng tôi.</p>
        </div>

        
        <div class="filter-bar">
            
            
            <form action="courses.php" method="GET" style="display: flex; gap: 12px; width: 100%; max-width: 500px;">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="hidden" name="level" value="<?php echo htmlspecialchars($level); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                
                <div style="position: relative; flex-grow: 1;">
                    <input type="text" name="search" placeholder="Tìm theo tên, giảng viên..." class="form-control" value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 40px; height: 46px;">
                    <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px;"></i>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0 20px; font-size: 14px; height: 46px; border-radius: var(--radius-sm);">Tìm kiếm</button>
            </form>

            <div class="filter-left">
                
                <form action="courses.php" method="GET" id="filter-level-form">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <select name="level" class="filter-select" onchange="document.getElementById('filter-level-form').submit();" style="height: 46px; border-radius: var(--radius-sm);">
                        <option value="all" <?php echo $level === 'all' ? 'selected' : ''; ?>>Tất cả cấp độ</option>
                        <option value="BEGINNER" <?php echo $level === 'BEGINNER' ? 'selected' : ''; ?>>Cơ bản</option>
                        <option value="INTERMEDIATE" <?php echo $level === 'INTERMEDIATE' ? 'selected' : ''; ?>>Trung cấp</option>
                        <option value="ADVANCED" <?php echo $level === 'ADVANCED' ? 'selected' : ''; ?>>Nâng cao</option>
                    </select>
                    <div class="course-mobile-level-filter">
                        <button type="button" class="course-mobile-level-toggle" id="course-level-toggle" aria-expanded="false">
                            <span><?php echo htmlspecialchars($selected_level_label); ?></span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="course-mobile-level-menu" id="course-level-menu">
                            <?php foreach ($level_labels as $level_value => $level_label): ?>
                                <a href="courses.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level_value); ?>&sort=<?php echo urlencode($sort); ?>" class="<?php echo $level === $level_value ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($level_label); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
                <form action="courses.php" method="GET" id="filter-sort-form">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <input type="hidden" name="level" value="<?php echo htmlspecialchars($level); ?>">
                    <select name="sort" class="filter-select" onchange="document.getElementById('filter-sort-form').submit();" style="height: 46px; border-radius: var(--radius-sm);">
                        <?php foreach ($sort_labels as $sort_value => $sort_label): ?>
                            <option value="<?php echo htmlspecialchars($sort_value); ?>" <?php echo $sort === $sort_value ? 'selected' : ''; ?>><?php echo htmlspecialchars($sort_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="course-mobile-sort-filter">
                        <button type="button" class="course-mobile-sort-toggle" id="course-sort-toggle" aria-expanded="false">
                            <span><?php echo htmlspecialchars($selected_sort_label); ?></span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="course-mobile-sort-menu" id="course-sort-menu">
                            <?php foreach ($sort_labels as $sort_value => $sort_label): ?>
                                <a href="courses.php?search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level); ?>&sort=<?php echo urlencode($sort_value); ?>" class="<?php echo $sort === $sort_value ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($sort_label); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>

        </div>

        
        <div class="course-mobile-category-filter">
            <button type="button" class="course-mobile-category-toggle" id="course-category-toggle" aria-expanded="false">
                <span><?php echo htmlspecialchars($selected_category_label); ?></span>
                <i data-lucide="chevron-down"></i>
            </button>
            <div class="course-mobile-category-menu" id="course-category-menu">
                <a href="courses.php?search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($level); ?>&category=all&sort=<?php echo urlencode($sort); ?>" class="<?php echo $category === 'all' ? 'active' : ''; ?>">
                    Tất cả khóa học
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="courses.php?search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($level); ?>&category=<?php echo urlencode($cat); ?>&sort=<?php echo urlencode($sort); ?>" class="<?php echo $category === $cat ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="course-category-pills" style="margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 10px;">
            <a href="courses.php?search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($level); ?>&category=all&sort=<?php echo urlencode($sort); ?>" 
               class="btn" 
               style="padding: 8px 18px; font-size: 14px; border-radius: 99px; font-weight: 600; 
                      <?php echo $category === 'all' ? 'background-color: var(--primary); color: white;' : 'background-color: #e2e8f0; color: var(--text-main);'; ?>">
                Tất cả khóa học
            </a>
            
            <?php foreach ($categories as $cat): ?>
                <a href="courses.php?search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($level); ?>&category=<?php echo urlencode($cat); ?>&sort=<?php echo urlencode($sort); ?>" 
                   class="btn" 
                   style="padding: 8px 18px; font-size: 14px; border-radius: 99px; font-weight: 600; 
                          <?php echo $category === $cat ? 'background-color: var(--primary); color: white;' : 'background-color: #e2e8f0; color: var(--text-main);'; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

        
        <div style="margin-bottom: 24px; color: var(--text-muted); font-size: 14px; font-weight: 600;">
            Hiển thị <span style="color: var(--primary);"><?php echo $total_courses > 0 ? ($offset + 1) . '-' . min($offset + count($courses), $total_courses) : '0'; ?></span> trong tổng số <span style="color: var(--primary);"><?php echo $total_courses; ?></span> khóa học
        </div>

        
        <?php if (empty($courses)): ?>
            <div style="text-align: center; padding: 80px 0; background-color: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border); box-shadow: var(--shadow-sm);">
                <i data-lucide="search-code" style="width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Không tìm thấy kết quả</h3>
                <p style="color: var(--text-muted);">Thử thay đổi từ khóa tìm kiếm hoặc các tiêu chí bộ lọc của bạn.</p>
                <a href="courses.php" class="btn btn-primary" style="margin-top: 20px; font-size: 14px;">Làm mới bộ lọc</a>
            </div>
        <?php else: ?>
            <div class="grid-3">
                <?php foreach ($courses as $course): ?>
                    <?php $has_discount = $course['discount_price'] > 0 && $course['discount_price'] < $course['price']; ?>
                    <div class="course-card">
                        
                        
                        <div class="card-banner">
                            <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600'); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <span class="card-tag">
                                <?php 
                                    $lvl = $course['level'];
                                    if ($lvl === 'BEGINNER') echo 'Cơ bản';
                                    elseif ($lvl === 'INTERMEDIATE') echo 'Trung cấp';
                                    elseif ($lvl === 'ADVANCED') echo 'Nâng cao';
                                    else echo htmlspecialchars($lvl);
                                ?>
                            </span>
                        </div>

                        
                        <div class="card-body">
                            <span class="card-category"><?php echo htmlspecialchars($course['category']); ?></span>
                            <h3 class="card-title">
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </a>
                            </h3>

                            
                            <div class="card-meta">
                                <div class="card-meta-item">
                                    <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo round(($course['duration'] ?? 0) / 60); ?> giờ</span>
                                </div>
                                <div class="card-meta-item">
                                    <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                    <span><?php echo htmlspecialchars($course['total_lectures'] ?? 0); ?> bài học</span>
                                </div>
                            </div>

                            
                            <div class="card-rating">
                                <div class="stars">
                                    <?php 
                                        $rating = $course['rating'] ?: 5.0;
                                        $full_stars = floor($rating);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $full_stars) {
                                                echo '<i data-lucide="star" style="fill: var(--accent); stroke: var(--accent); width: 14px; height: 14px;"></i>';
                                            } else {
                                                echo '<i data-lucide="star" style="width: 14px; height: 14px;"></i>';
                                            }
                                        }
                                    ?>
                                </div>
                                <span><?php echo number_format($rating, 1); ?> (<?php echo htmlspecialchars($course['rating_count'] ?: 0); ?>)</span>
                            </div>

                            
                            <div class="card-footer">
                                <div class="card-price">
                                    <?php if ($has_discount): ?>
                                        <span class="price-old"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                                        <span class="price-new"><?php echo number_format($course['discount_price'], 0, ',', '.'); ?>đ</span>
                                    <?php else: ?>
                                        <span class="price-new"><?php echo number_format($course['price'], 0, ',', '.'); ?>đ</span>
                                    <?php endif; ?>
                                </div>
                                <a href="course-detail.php?id=<?php echo $course['id']; ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px; border-radius: var(--radius-sm);">
                                    Xem chi tiết
                                </a>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <nav class="course-pagination" aria-label="Phân trang khóa học">
                    <?php $pagination_params = ['search' => $search, 'category' => $category, 'level' => $level, 'sort' => $sort]; ?>
                    <?php if ($page > 1): ?>
                        <a href="courses.php?<?php echo htmlspecialchars(http_build_query(array_merge($pagination_params, ['page' => $page - 1]))); ?>" aria-label="Trang trước"><i data-lucide="chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($page_number = max(1, $page - 2); $page_number <= min($total_pages, $page + 2); $page_number++): ?>
                        <a href="courses.php?<?php echo htmlspecialchars(http_build_query(array_merge($pagination_params, ['page' => $page_number]))); ?>" class="<?php echo $page_number === $page ? 'active' : ''; ?>" <?php echo $page_number === $page ? 'aria-current="page"' : ''; ?>><?php echo $page_number; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="courses.php?<?php echo htmlspecialchars(http_build_query(array_merge($pagination_params, ['page' => $page + 1]))); ?>" aria-label="Trang sau"><i data-lucide="chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<style>
    .course-mobile-category-filter,
    .course-mobile-level-filter {
        display: none;
    }

    .course-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 34px;
    }

    .course-pagination a {
        width: 40px;
        height: 40px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-card);
        color: var(--text-main);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 800;
    }

    .course-pagination a:hover,
    .course-pagination a.active {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }

    .course-pagination svg {
        width: 18px;
        height: 18px;
    }

    @media (max-width: 640px) {
        .course-category-pills {
            display: none !important;
        }

        #filter-level-form .filter-select,
        #filter-sort-form .filter-select {
            display: none !important;
        }

        .course-mobile-category-filter,
        .course-mobile-level-filter {
            display: block;
            margin-bottom: 24px;
        }

        .course-mobile-category-toggle,
        .course-mobile-level-toggle {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background-color: var(--bg-card);
            color: var(--text-main);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
            line-height: 1.35;
            cursor: pointer;
        }

        .course-mobile-category-toggle span,
        .course-mobile-level-toggle span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .course-mobile-category-toggle svg,
        .course-mobile-level-toggle svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .course-mobile-category-menu,
        .course-mobile-level-menu {
            display: none;
            margin-top: 8px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background-color: var(--bg-card);
            padding: 6px;
            box-shadow: var(--shadow-sm);
        }

        .course-mobile-category-filter.is-open .course-mobile-category-menu,
        .course-mobile-level-filter.is-open .course-mobile-level-menu {
            display: grid;
            gap: 4px;
        }

        .course-mobile-category-menu a,
        .course-mobile-level-menu a {
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
        }

        .course-mobile-category-menu a.active,
        .course-mobile-category-menu a:hover,
        .course-mobile-level-menu a.active,
        .course-mobile-level-menu a:hover {
            background-color: var(--primary);
            color: white;
        }
    }

    .course-mobile-sort-filter {
        display: none;
    }

    @media (max-width: 640px) {
        .course-mobile-sort-filter {
            display: block;
            margin-bottom: 24px;
        }

        .course-mobile-sort-toggle {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background-color: var(--bg-card);
            color: var(--text-main);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
            line-height: 1.35;
            cursor: pointer;
        }

        .course-mobile-sort-toggle span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .course-mobile-sort-toggle svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .course-mobile-sort-menu {
            display: none;
            margin-top: 8px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background-color: var(--bg-card);
            padding: 6px;
            box-shadow: var(--shadow-sm);
        }

        .course-mobile-sort-filter.is-open .course-mobile-sort-menu {
            display: grid;
            gap: 4px;
        }

        .course-mobile-sort-menu a {
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
        }

        .course-mobile-sort-menu a.active,
        .course-mobile-sort-menu a:hover {
            background-color: var(--primary);
            color: white;
        }
    }</style>

<script>
    function bindCourseMobileDropdown(filterSelector, toggleSelector) {
        const filter = document.querySelector(filterSelector);
        const toggle = document.querySelector(toggleSelector);

        if (!filter || !toggle) return;

        toggle.addEventListener('click', function() {
            const isOpen = filter.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    bindCourseMobileDropdown('.course-mobile-category-filter', '#course-category-toggle');
    bindCourseMobileDropdown('.course-mobile-level-filter', '#course-level-toggle');
    bindCourseMobileDropdown('.course-mobile-sort-filter', '#course-sort-toggle');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
