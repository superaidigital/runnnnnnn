<?php
// admin/edit_post.php
// หน้าสำหรับ "แก้ไข" ข่าวสาร (รองรับแกลเลอรี)

// --- CORE BOOTSTRAP ---
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['staff_id']) || $_SESSION['staff_info']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$page_title = 'แก้ไขข่าวสาร';
// --- END BOOTSTRAP ---

// --- Get Post ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['update_error'] = "Invalid Post ID.";
    header('Location: admin/manage_posts.php');
    exit;
}
$post_id = intval($_GET['id']);

// --- Fetch Post Data ---
$stmt = $mysqli->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post_result = $stmt->get_result();
if ($post_result->num_rows === 0) {
    $_SESSION['update_error'] = "ไม่พบข่าวสารที่ต้องการแก้ไข";
    header('Location: admin/manage_posts.php');
    exit;
}
$post = $post_result->fetch_assoc();
$stmt->close();

// --- Fetch Post Images ---
$img_stmt = $mysqli->prepare("SELECT * FROM post_images WHERE post_id = ? ORDER BY sort_order ASC");
$img_stmt->bind_param("i", $post_id);
$img_stmt->execute();
$post_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$img_stmt->close();

// Check for session messages
$success_message = isset($_SESSION['update_success']) ? $_SESSION['update_success'] : null; unset($_SESSION['update_success']);
$error_message = isset($_SESSION['update_error']) ? $_SESSION['update_error'] : null; unset($_SESSION['update_error']);

include 'partials/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">แก้ไขข่าวสาร</h1>
        <p class="text-gray-600">กำลังแก้ไข: <?= e($post['title']) ?></p>
    </div>
    <a href="manage_posts.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm">
        <i class="fa-solid fa-arrow-left mr-2"></i> กลับไปหน้ารวม
    </a>
</div>

<?php if ($success_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?= e($success_message) ?></p></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?= e($error_message) ?></p></div>
<?php endif; ?>

<form action="../actions/manage_posts.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-md space-y-6">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="post_id" value="<?= e($post['id']) ?>">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="md:col-span-2 space-y-4">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">หัวข้อข่าว (Title) <span class="text-red-500">*</span></label>
                <input type="text" id="title" name="title" value="<?= e($post['title']) ?>" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
            </div>
            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">เนื้อหา (Content)</label>
                <textarea id="content" name="content"><?= htmlspecialchars($post['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="md:col-span-1 space-y-4">
            <!-- Publish Status -->
            <div class="bg-gray-50 p-4 rounded-lg border">
                <label for="is_published" class="block text-sm font-medium text-gray-700">สถานะ</label>
                <select id="is_published" name="is_published" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                    <option value="1" <?= $post['is_published'] ? 'selected' : '' ?>>Published (เผยแพร่)</option>
                    <option value="0" <?= !$post['is_published'] ? 'selected' : '' ?>>Draft (ฉบับร่าง)</option>
                </select>
            </div>

            <!-- Cover Image -->
            <div class="bg-gray-50 p-4 rounded-lg border">
                <label class="block text-sm font-medium text-gray-700">ภาพปก (Cover Image)</label>
                <?php if (!empty($post['cover_image_url'])): ?>
                    <img src="../<?= e($post['cover_image_url']) ?>?v=<?= time() ?>" alt="Cover" class="w-full h-32 object-cover rounded-md mt-2 border bg-white">
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                <p class="text-xs text-gray-500 mt-1">อัปโหลดใหม่เพื่อเปลี่ยนแปลง</p>
            </div>
        </div>
    </div>

    <!-- Gallery Management -->
    <div class="border-t pt-4">
        <h2 class="text-xl font-bold text-primary border-b pb-2 mb-4">คลังรูปภาพ (Gallery)</h2>
        <div class="p-4 border rounded-lg bg-gray-50 space-y-4">
            <div id="gallery-image-list" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php if (empty($post_images)): ?>
                    <p class="text-xs text-gray-500 col-span-full">ยังไม่มีรูปภาพในแกลเลอรี</p>
                <?php else: ?>
                    <?php foreach ($post_images as $img): ?>
                        <div class="relative group" data-image-id="<?= $img['id'] ?>">
                            <img src="../<?= e($img['image_url']) ?>?v=<?= time() ?>"
                                 class="w-full h-24 object-cover rounded-md border-2 border-transparent group-hover:border-blue-500 transition shadow-sm bg-white cursor-move">
                            <label class="absolute top-1 right-1 cursor-pointer bg-white/70 backdrop-blur-sm rounded-full p-0.5 flex items-center justify-center shadow" title="Mark for deletion">
                                <input type="checkbox" name="delete_images[]" value="<?= e($img['id']) ?>" class="h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                            </label>
                            <input type="hidden" name="image_order[]" value="<?= e($img['id']) ?>">
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="border-t pt-3">
                <label class="block text-xs font-medium text-gray-500 mb-1">อัปโหลดรูปภาพใหม่ (เลือกได้หลายรูป):</label>
                <input type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp"
                       class="w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 file:cursor-pointer">
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end pt-6 border-t">
        <button type="submit" class="w-full md:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg">
            <i class="fa-solid fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
        </button>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    // --- Initialize CKEditor ---
    if (typeof CKEDITOR !== 'undefined') {
        CKEDITOR.replace('content', { height: 400 });
    } else {
        console.warn("CKEditor script not loaded.");
    }

    // --- Initialize SortableJS for Gallery ---
    const galleryList = document.getElementById('gallery-image-list');
    if (galleryList) {
        new Sortable(galleryList, {
            animation: 150,
            ghostClass: 'bg-blue-100',
            // Update hidden inputs for order on drop
            onEnd: function (evt) {
                const items = galleryList.querySelectorAll('.group');
                items.forEach((item, index) => {
                    const hiddenInput = item.querySelector('input[name="image_order[]"]');
                    if(hiddenInput) {
                        hiddenInput.value = item.dataset.imageId;
                    }
                });
            }
        });
    }
</script>

<?php
include 'partials/footer.php';
?>