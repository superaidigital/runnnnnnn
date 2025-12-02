<?php
// pages/news.php
// หน้ารวมข่าวสารและประกาศทั่วไป

$page_title = 'ข่าวสาร';

// Fetch all published posts
$posts = $mysqli->query("
    SELECT p.id, p.title, p.content, p.cover_image_url, p.created_at, s.full_name as author_name 
    FROM posts p 
    LEFT JOIN staff s ON p.author_id = s.id 
    WHERE p.is_published = 1 
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/**
 * ฟังก์ชันสำหรับตัดข้อความให้สั้นลง
 */
function truncate_content($text, $length = 100) {
    $text = strip_tags($text);
    if (mb_strlen($text) > $length) {
        $text = mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

?>

<div class="space-y-6">
    <h2 class="text-3xl font-extrabold text-gray-800">ข่าวสารและประกาศ</h2>
    <p class="text-gray-600">ติดตามข่าวสารล่าสุด, ประกาศ, และกิจกรรมทั่วไปจากผู้จัดงาน</p>

    <div class="border-t pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php if (empty($posts)): ?>
            <div class="col-span-1 md:col-span-2 text-center p-10 border-2 border-dashed border-gray-300 rounded-lg text-gray-500">
                <i class="fa-solid fa-newspaper text-3xl mb-3"></i>
                <p>ยังไม่มีข่าวสารในขณะนี้</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <a href="index.php?page=news_detail&id=<?= e($post['id']) ?>" class="group flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 transition duration-300 hover:shadow-xl hover:border-primary overflow-hidden">
                    <div class="h-48 w-full overflow-hidden">
                        <img src="<?= e($post['cover_image_url'] ?? 'https://placehold.co/800x400?text=News') ?>" 
                             alt="<?= e($post['title']) ?>" 
                             class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                    </div>
                    <div class="p-4 flex flex-col flex-grow">
                        <h3 class="text-lg font-bold text-primary mb-2 group-hover:underline"><?= e($post['title']) ?></h3>
                        <p class="text-sm text-gray-600 mb-4 flex-grow">
                            <?= e(truncate_content($post['content'], 120)) ?>
                        </p>
                        <div class="text-xs text-gray-500 border-t pt-2 flex justify-between items-center">
                            <span>
                                <i class="fa-solid fa-user mr-1"></i> <?= e($post['author_name'] ?? 'Admin') ?>
                            </span>
                            <span>
                                <i class="fa-solid fa-calendar-alt mr-1"></i> <?= date("d M Y", strtotime($post['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>