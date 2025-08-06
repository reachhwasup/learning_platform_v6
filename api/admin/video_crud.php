<?php
/**
 * Video CRUD API Endpoint
 *
 * Handles adding, editing, and deleting the video for a specific module.
 */

header('Content-Type: application/json');
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid request.'];
$action = $_POST['action'] ?? '';

try {
    // Module ID is required for most actions
    $module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
    if (!$module_id && $action !== 'delete') {
         if (!$module_id) {
            throw new Exception('A valid Module ID is required.');
         }
    }

    switch ($action) {
        case 'add_video':
        case 'edit_video':
            if (!$module_id) throw new Exception('A valid Module ID is required for adding/editing.');
            $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
            $video_title = trim($_POST['video_title']);
            $video_description = trim($_POST['video_description']);

            $video_path = null;
            $thumbnail_path = null;

            // Handle Video File Upload
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir_vid = '../../uploads/videos/';
                if (!is_dir($upload_dir_vid)) mkdir($upload_dir_vid, 0755, true);
                
                // --- FIX --- Use the original file extension instead of hardcoding .mp4
                $video_extension = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
                $video_file_name = 'module_' . $module_id . '_' . time() . '.' . $video_extension;

                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $upload_dir_vid . $video_file_name)) {
                    $video_path = $video_file_name;
                } else {
                    throw new Exception('Failed to move uploaded video file.');
                }
            }

            // Handle Thumbnail File Upload
            if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir_thumb = '../../uploads/thumbnails/';
                if (!is_dir($upload_dir_thumb)) mkdir($upload_dir_thumb, 0755, true);
                $thumb_file_name = 'module_' . $module_id . '_thumb_' . time() . '.' . pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $upload_dir_thumb . $thumb_file_name)) {
                    $thumbnail_path = $thumb_file_name;
                }
            }

            if ($video_id) { // This is an EDIT
                $sql = "UPDATE videos SET title = ?, description = ?";
                $params = [$video_title, $video_description];
                if ($video_path) { $sql .= ", video_path = ?"; $params[] = $video_path; }
                if ($thumbnail_path) { $sql .= ", thumbnail_path = ?"; $params[] = $thumbnail_path; }
                $sql .= " WHERE id = ? AND module_id = ?";
                $params[] = $video_id;
                $params[] = $module_id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else { // This is an ADD
                if (!$video_path) throw new Exception('A video file is required.');
                $sql = "INSERT INTO videos (module_id, title, description, video_path, thumbnail_path, upload_by) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$module_id, $video_title, $video_description, $video_path, $thumbnail_path, $_SESSION['user_id']]);
            }
            $response = ['success' => true, 'message' => 'Video information saved successfully.'];
            break;

        case 'delete':
            if (!$module_id) {
                throw new Exception('Invalid Module ID for deletion.');
            }
            
            $pdo->beginTransaction();

            // Find the video record to get file paths
            $stmt = $pdo->prepare("SELECT id, video_path, thumbnail_path FROM videos WHERE module_id = ?");
            $stmt->execute([$module_id]);
            $video = $stmt->fetch();

            if ($video) {
                // 1. Delete the physical files if they exist, with error checking
                if ($video['video_path']) {
                    $video_file_path = '../../uploads/videos/' . $video['video_path'];
                    if (file_exists($video_file_path) && is_file($video_file_path)) {
                        if (!unlink($video_file_path)) {
                            throw new Exception('Could not delete video file. Check server permissions.');
                        }
                    }
                }

                if ($video['thumbnail_path']) {
                    $thumb_file_path = '../../uploads/thumbnails/' . $video['thumbnail_path'];
                     if (file_exists($thumb_file_path) && is_file($thumb_file_path)) {
                        if (!unlink($thumb_file_path)) {
                            throw new Exception('Could not delete thumbnail file. Check server permissions.');
                        }
                    }
                }

                // 2. Delete the database record ONLY if file deletion was successful
                $delete_stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
                $delete_stmt->execute([$video['id']]);

                $pdo->commit();
                $response = ['success' => true, 'message' => 'Video deleted successfully.'];
            } else {
                $pdo->rollBack();
                $response = ['success' => true, 'message' => 'No video was associated with this module.'];
            }
            break;
            
        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
