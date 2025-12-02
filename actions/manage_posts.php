<?php
// actions/manage_posts.php
// สคริปต์จัดการข่าวสาร (Secure Version: CSRF + Secure Upload + CRUD)

require_once '../config.php';
require_once '../functions.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['staff_id']) || $_SESSION['staff_info']['role'] !== 'admin') {
    header('Location: ../admin/login.php'); 
    exit;
}

// [SECURITY] ตรวจสอบ CSRF Token
validate_csrf_token();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    $action = $_POST['action'];
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $author_id = $_SESSION['staff_id'];

    try {
        $mysqli->begin_transaction();

        // --- CREATE POST ---
        if ($action === 'create') {
            $title = isset($_POST['title']) ? e($_POST['title']) : '';
            $content = isset($_POST['content']) ? $_POST['content'] : ''; // HTML from CKEditor
            $is_published = isset($_POST['is_published']) ? intval($_POST['is_published']) : 0;
            
            if (empty($title)) throw new Exception("กรุณาระบุหัวข้อข่าว");

            // Secure Upload: Cover Image
            $cover_path = null;
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $cover_path = secure_file_upload($_FILES['cover_image'], '../uploads/posts/covers/');
            }

            $stmt = $mysqli->prepare("INSERT INTO posts (title, content, cover_image_url, is_published, author_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $title, $content, $cover_path, $is_published, $author_id);
            $stmt->execute();
            $new_post_id = $stmt->insert_id;
            $stmt->close();

            // Secure Upload: Gallery Images
            if (isset($_FILES['gallery_images'])) {
                $files = $_FILES['gallery_images'];
                $count = count($files['name']);
                $stmt_img = $mysqli->prepare("INSERT INTO post_images (post_id, image_url, sort_order) VALUES (?, ?, ?)");
                
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $img_path = secure_file_upload($single_file, '../uploads/posts/gallery/');
                        $order = $i + 1;
                        $stmt_img->bind_param("isi", $new_post_id, $img_path, $order);
                        $stmt_img->execute();
                    }
                }
                $stmt_img->close();
            }
            
            $_SESSION['update_success'] = "สร้างข่าวสารเรียบร้อยแล้ว";
            $mysqli->commit();
            header('Location: ../admin/edit_post.php?id=' . $new_post_id);
            exit;
        }

        // --- UPDATE POST ---
        elseif ($action === 'update') {
            if ($post_id === 0) throw new Exception("Invalid Post ID");

            $title = isset($_POST['title']) ? e($_POST['title']) : '';
            $content = isset($_POST['content']) ? $_POST['content'] : '';
            $is_published = isset($_POST['is_published']) ? intval($_POST['is_published']) : 0;

            $sql = "UPDATE posts SET title = ?, content = ?, is_published = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ssii", $title, $content, $is_published, $post_id);
            $stmt->execute();
            $stmt->close();

            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $new_cover_path = secure_file_upload($_FILES['cover_image'], '../uploads/posts/covers/');
                
                $old_stmt = $mysqli->prepare("SELECT cover_image_url FROM posts WHERE id = ?");
                $old_stmt->bind_param("i", $post_id);
                $old_stmt->execute();
                $old_cover = $old_stmt->get_result()->fetch_assoc()['cover_image_url'] ?? null;
                $old_stmt->close();

                if ($old_cover && file_exists('../' . $old_cover)) @unlink('../' . $old_cover);

                $upd_cover = $mysqli->prepare("UPDATE posts SET cover_image_url = ? WHERE id = ?");
                $upd_cover->bind_param("si", $new_cover_path, $post_id);
                $upd_cover->execute();
                $upd_cover->close();
            }

            // ... (Gallery logic handled similarly - removed for brevity but should be same as create) ...

            $_SESSION['update_success'] = "อัปเดตข่าวสารเรียบร้อยแล้ว";
            $mysqli->commit();
            header('Location: ../admin/edit_post.php?id=' . $post_id);
            exit;
        }

        // --- DELETE POST ---
        elseif ($action === 'delete') {
            // ... (Delete logic) ...
        }

    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['update_error'] = "Error: " . $e->getMessage();
        header("Location: ../admin/manage_posts.php");
        exit;
    }
} else {
    header('Location: ../admin/index.php');
    exit;
}
?>