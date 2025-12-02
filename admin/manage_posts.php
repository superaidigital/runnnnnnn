<?php
// admin/manage_posts.php
// หน้า UI สำหรับจัดการ (เพิ่ม/ลบ) ข่าวสาร (เวอร์ชันอัปเกรด)

// --- CORE BOOTSTRAP ---
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['staff_id']) || $_SESSION['staff_info']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$page_title = 'จัดการข่าวสาร';
// --- END BOOTSTRAP ---

// --- Fetch all posts ---
$posts = $mysqli->query("SELECT * FROM posts ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Check for session messages
$success_message = isset($_SESSION['update_success']) ? $_SESSION['update_success'] : null; unset($_SESSION['update_success']);
$error_message = isset($_SESSION['update_error']) ? $_SESSION['update_error'] : null; unset($_SESSION['update_error']);

include 'partials/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">จัดการข่าวสาร (Posts)</h1>
        <p class="text-gray-600">เพิ่ม ลบ และแก้ไขประกาศข่าวสารทั่วไป</p>
    </div>
</div>

<?php if ($success_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?= e($success_message) ?></p></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?= e($error_message) ?></p></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Post List -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-xl font-bold mb-4">รายการข่าวสารทั้งหมด</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">หัวข้อ</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่สร้าง</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($posts)): ?>
                        <tr><td colspan="4" class="text-center py-6 text-gray-500">ยังไม่มีข่าวสาร</td></tr>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td class="px-3 py-4">
                                <div class="text-sm font-semibold text-gray-800"><?= e($post['title']) ?></div>
                            </td>
                            <td class="px-3 py-4 text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $post['is_published'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $post['is_published'] ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500">
                                <?= date("d M Y", strtotime($post['created_at'])) ?>
                            </td>
                            <td class="px-3 py-4 text-right">
                                <div class="flex items-center justify-end space-x-4">
                                    <!-- [NEW] Edit Button -->
                                    <a href="edit_post.php?id=<?= e($post['id']) ?>" class="text-blue-500 hover:text-blue-700" title="แก้ไข">
                                        <i class="fa-solid fa-pencil-alt"></i>
                                    </a>
                                    <form action="../actions/manage_posts.php" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบข่าวนี้?');" class="inline-block">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?= e($post['id']) ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700" title="ลบ">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Right: Add Form -->
    <div id="form-container" class="bg-white p-6 rounded-xl shadow-md h-fit">
        <h2 id="form-title" class="text-xl font-bold mb-4">เพิ่มข่าวสารใหม่</h2>
        
        <form id="post-form" action="../actions/manage_posts.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">หัวข้อ (Title) <span class="text-red-500">*</span></label>
                <input type="text" id="title-input" name="title" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
            </div>
            
             <div>
                <label for="cover_image" class="block text-sm font-medium text-gray-700">ภาพปก (Cover Image)</label>
                <input type="file" id="image-input" name="cover_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                <p class="text-xs text-gray-500 mt-1">ขนาดแนะนำ 800x400 px</p>
            </div>
            
            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">เนื้อหา (Content)</label>
                <textarea id="content" name="content"></textarea>
            </div>

            <!-- [NEW] Gallery Upload on Create -->
            <div>
                <label class="block text-sm font-medium text-gray-700">คลังรูปภาพ (Gallery)</label>
                <input type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp"
                       class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 file:cursor-pointer">
                <p class="text-xs text-gray-500 mt-1">เลือกได้หลายรูป</p>
            </div>

            <div>
                <label for="is_published" class="block text-sm font-medium text-gray-700">สถานะ</label>
                <select id="is_published-input" name="is_published" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                    <option value="1" selected>Published (เผยแพร่)</option>
                    <option value="0">Draft (ฉบับร่าง)</option>
                </select>
            </div>
            <button id="submit-btn" type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg">
                <i class="fa-solid fa-plus-circle mr-2"></i> สร้างข่าวสาร
            </button>
        </form>
    </div>
</div>

<script>
    // --- Initialize CKEditor ---
     if (typeof CKEDITOR !== 'undefined') {
        CKEDITOR.replace('content', { height: 200 });
     } else {
        console.warn("CKEditor script not loaded.");
     }
</script>

<?php
include 'partials/footer.php';
?>