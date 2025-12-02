<?php
// pages/news_detail.php
// หน้าสำหรับอ่านข่าวสาร/ประกาศ (ฉบับอัปเกรดแกลเลอรี)

// --- 1. Validate and get Post ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?page=news');
    exit;
}
$post_id = intval($_GET['id']);

// --- 2. Fetch post data ---
// FIX 3: Changed 'is_active' to 'is_published'
$post_stmt = $mysqli->prepare("SELECT * FROM posts WHERE id = ? AND is_published = 1 LIMIT 1");
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

if ($post_result->num_rows === 0) {
    // Post not found or not published
    header('Location: index.php?page=news');
    exit;
}
$post = $post_result->fetch_assoc();
$post_stmt->close();

// --- 3. [NEW] Fetch gallery images ---
$img_stmt = $mysqli->prepare("SELECT * FROM post_images WHERE post_id = ? ORDER BY sort_order ASC");
$img_stmt->bind_param("i", $post_id);
$img_stmt->execute();
$post_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$img_stmt->close();


// --- 4. Set Page Title ---
$page_title = e($post['title']);

// --- 5. Define allowed HTML tags for content ---
$allowed_html = '<p><strong><em><ul><ol><li><a><img><h2><h3><h4><h5><h6><br><blockquote>';

?>

<div class="space-y-4">
    <!-- Back Button -->
    <a href="index.php?page=news" class="text-sm text-gray-600 hover:text-primary transition">
        <i class="fa-solid fa-arrow-left mr-2"></i> กลับไปหน้ารวมข่าวสาร
    </a>

    <!-- Title -->
    <h1 class="text-3xl font-extrabold text-gray-800"><?= e($post['title']) ?></h1>
    
    <!-- Meta -->
    <p class="text-gray-500 text-sm mb-6">
        <i class="fa-solid fa-calendar-alt mr-2"></i> เผยแพร่เมื่อ: <?= formatThaiDate($post['created_at']) ?>
    </p>

    <!-- Cover Image -->
    <?php if (!empty($post['cover_image_url'])): ?>
        <img src="./<?= e($post['cover_image_url']) ?>" alt="<?= e($post['title']) ?>" class="w-full h-auto max-h-96 object-cover rounded-xl shadow-lg mb-6">
    <?php endif; ?>

    <!-- [NEW] Gallery Images Section -->
    <?php if (!empty($post_images)): ?>
    <div class="mb-6">
        <h3 class="font-bold text-lg mb-3 text-primary">รูปภาพประกอบ (คลิกเพื่อดูแกลเลอรี)</h3>
        <div class="grid grid-cols-3 md:grid-cols-5 gap-2">
            <?php foreach($post_images as $index => $img): ?>
                <div class="relative group overflow-hidden rounded-lg aspect-square cursor-pointer" onclick='openGallery("<?= e($post['title']) ?>", <?= json_encode(array_column($post_images, "image_url")) ?>, <?= $index ?>)'>
                    <img src="./<?= e($img['image_url']) ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-110" alt="ภาพประกอบข่าว">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="prose max-w-none text-gray-700 leading-relaxed description-content">
        <?= strip_tags($post['content'], $allowed_html) ?>
    </div>
</div>