<?php
/**
 * Poster CRUD API Endpoint (CORRECTED)
 *
 * Handles Create, Read, Update, and Delete operations for monthly posters.
 * This version fixes the file upload issue during edits.
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
$action = $_REQUEST['action'] ?? null;
$upload_dir = '../../uploads/posters/';

try {
    switch ($action) {
        case 'get_poster':
            $poster_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$poster_id) throw new Exception('Invalid Poster ID.');
            
            $stmt = $pdo->prepare("SELECT id, title, description, assigned_month FROM monthly_posters WHERE id = ?");
            $stmt->execute([$poster_id]);
            $poster = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($poster) {
                $response = ['success' => true, 'data' => $poster];
            } else {
                throw new Exception('Poster not found.');
            }
            break;

        case 'add_poster':
            if (empty($_POST['title']) || empty($_POST['assigned_month']) || !isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Title, assigned month, and an image are required.');
            }

            $image_path = handle_image_upload($_FILES['image'], $upload_dir);

            $sql = "INSERT INTO monthly_posters (title, description, assigned_month, image_path, uploaded_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['assigned_month'] . '-01', // Append day for DATE type
                $image_path,
                $_SESSION['user_id']
            ]);
            $response = ['success' => true, 'message' => 'Poster added successfully.'];
            break;

        case 'edit_poster':
            $poster_id = filter_input(INPUT_POST, 'poster_id', FILTER_VALIDATE_INT);
            if (!$poster_id || empty($_POST['title']) || empty($_POST['assigned_month'])) {
                throw new Exception('Missing required fields for editing.');
            }

            $sql = "UPDATE monthly_posters SET title = ?, description = ?, assigned_month = ? WHERE id = ?";
            $params = [
                $_POST['title'],
                $_POST['description'],
                $_POST['assigned_month'] . '-01',
                $poster_id
            ];
            
            // Check if a new image is uploaded
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Get old image path to delete it later
                $stmt_old = $pdo->prepare("SELECT image_path FROM monthly_posters WHERE id = ?");
                $stmt_old->execute([$poster_id]);
                $old_image = $stmt_old->fetchColumn();

                // Upload new image
                $new_image_path = handle_image_upload($_FILES['image'], $upload_dir);
                
                // Update SQL to include the new image path
                $sql = "UPDATE monthly_posters SET title = ?, description = ?, assigned_month = ?, image_path = ? WHERE id = ?";
                $params = [
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['assigned_month'] . '-01',
                    $new_image_path,
                    $poster_id
                ];

                // Delete the old image file
                if ($old_image && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $response = ['success' => true, 'message' => 'Poster updated successfully.'];
            break;

        case 'delete_poster':
            $poster_id = filter_input(INPUT_POST, 'poster_id', FILTER_VALIDATE_INT);
            if (!$poster_id) throw new Exception('Invalid Poster ID.');

            // Get image path to delete the file
            $stmt = $pdo->prepare("SELECT image_path FROM monthly_posters WHERE id = ?");
            $stmt->execute([$poster_id]);
            $image_to_delete = $stmt->fetchColumn();

            // Delete from database
            $delete_stmt = $pdo->prepare("DELETE FROM monthly_posters WHERE id = ?");
            $delete_stmt->execute([$poster_id]);

            // Delete the file
            if ($image_to_delete && file_exists($upload_dir . $image_to_delete)) {
                unlink($upload_dir . $image_to_delete);
            }
            
            $response = ['success' => true, 'message' => 'Poster deleted successfully.'];
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $response['message'] = 'A database error occurred.';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


/**
 * Handles image upload, validation, and moving.
 * @param array $file The $_FILES['image'] array.
 * @param string $upload_dir The directory to upload to.
 * @return string The new unique filename.
 * @throws Exception
 */
function handle_image_upload($file, $upload_dir) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }

    if ($file['size'] > 50 * 1024 * 1024) { // 50 MB limit
        throw new Exception('File is too large. Maximum size is 50MB.');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid('poster_', true) . '.' . $extension;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
        throw new Exception('Failed to upload the image.');
    }

    return $new_filename;
}
?>
