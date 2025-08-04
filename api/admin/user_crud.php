<?php
/**
 * User CRUD API Endpoint (BULK UPLOAD FIXED)
 *
 * Handles all admin actions for users, with corrected logic for bulk CSV uploads.
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

try {
    switch ($action) {
        case 'get':
            $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, staff_id, position, phone_number, gender, department_id, role, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $response = ['success' => true, 'user' => $user];
            } else {
                throw new Exception('User not found.');
            }
            break;

        case 'add':
            $pdo->beginTransaction();
            $required = ['first_name', 'last_name', 'staff_id', 'department_id', 'role', 'status'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
            }

            $username = generate_unique_username($pdo, $_POST['first_name'], $_POST['last_name']);
            $password = $_POST['password'] ?: 'APD@123456789';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (first_name, last_name, username, staff_id, position, phone_number, gender, password, department_id, role, status, password_reset_required) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $username, $_POST['staff_id'],
                $_POST['position'], $_POST['phone_number'], $_POST['gender'], $hashed_password, 
                $_POST['department_id'], $_POST['role'], $_POST['status']
            ]);
            $new_user_id = $pdo->lastInsertId();

            $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $pdo->prepare($hist_sql)->execute([$new_user_id, $hashed_password]);

            $pdo->commit();
            $response = ['success' => true, 'message' => 'User added successfully.'];
            break;

        case 'update':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');

            if ($user_id == $_SESSION['user_id'] && $_POST['role'] !== 'admin') {
                throw new Exception('Admins cannot change their own role.');
            }

            $sql = "UPDATE users SET first_name=?, last_name=?, staff_id=?, position=?, phone_number=?, gender=?, department_id=?, role=?, status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['staff_id'],
                $_POST['position'], $_POST['phone_number'], $_POST['gender'], $_POST['department_id'],
                $_POST['role'], $_POST['status'], $user_id
            ]);

            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 12) {
                    throw new Exception('New password must be at least 12 characters.');
                }
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pass_sql = "UPDATE users SET password = ?, password_reset_required = 0, password_last_changed = NOW() WHERE id = ?";
                $pdo->prepare($pass_sql)->execute([$hashed_password, $user_id]);
                $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
                $pdo->prepare($hist_sql)->execute([$user_id, $hashed_password]);
            }

            $response = ['success' => true, 'message' => 'User updated successfully.'];
            break;

        case 'delete':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            if ($user_id == $_SESSION['user_id']) throw new Exception('Admins cannot delete their own account.');

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $response = ['success' => true, 'message' => 'User deleted successfully.'];
            break;
        
        case 'reset_password':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');

            $pdo->beginTransaction();
            $new_password = 'APD@123456789';
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password = ?, password_reset_required = 1, password_last_changed = NOW() WHERE id = ?";
            $pdo->prepare($sql)->execute([$hashed_password, $user_id]);

            $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $pdo->prepare($hist_sql)->execute([$user_id, $hashed_password]);

            $pdo->commit();
            $response = ['success' => true, 'message' => "Password has been reset to: $new_password"];
            break;

        case 'unlock':
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) throw new Exception('Invalid User ID.');
            
            $sql = "UPDATE users SET status = 'active', failed_login_attempts = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $response = ['success' => true, 'message' => 'User account has been unlocked.'];
            break;

        case 'bulk_upload':
            if (!isset($_FILES['user_file']) || $_FILES['user_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error.');
            }
            $file_path = $_FILES['user_file']['tmp_name'];
            
            $file_type = mime_content_type($file_path);
            if ($file_type !== 'text/plain' && $file_type !== 'text/csv') {
                 throw new Exception('Invalid file type. Please upload a CSV file.');
            }

            $file = fopen($file_path, 'r');
            if ($file === false) throw new Exception('Could not open uploaded file.');

            $depts_stmt = $pdo->query("SELECT name, id FROM departments");
            $departments = $depts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $departments_lower = array_change_key_case($departments, CASE_LOWER);

            $header = fgetcsv($file); // Skip header row
            $success_count = 0;
            $fail_count = 0;
            $failed_entries = [];

            $pdo->beginTransaction();
            while (($row = fgetcsv($file)) !== false) {
                try {
                    $staff_id = $row[0] ?? '';
                    $first_name = $row[1] ?? '';
                    $last_name = $row[2] ?? '';
                    $gender = $row[3] ?? null;
                    $position = $row[4] ?? null;
                    $department_name_csv = $row[5] ?? '';
                    $department_name = strtolower(trim($department_name_csv));
                    $phone_number = $row[6] ?? null;

                    if (empty($staff_id) || empty($first_name) || empty($last_name) || empty($department_name)) {
                        throw new Exception("Missing required fields.");
                    }
                    
                    $department_id = $departments_lower[$department_name] ?? false;
                    if ($department_id === false) {
                        throw new Exception("Department '{$department_name_csv}' not found.");
                    }
                    
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE staff_id = ?");
                    $stmt_check->execute([$staff_id]);
                    if ($stmt_check->fetch()) {
                        throw new Exception("Staff ID already exists.");
                    }

                    $username = generate_unique_username($pdo, $first_name, $last_name);
                    $password = 'APD@123456789';
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO users (staff_id, first_name, last_name, username, gender, position, department_id, phone_number, password, password_reset_required) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$staff_id, $first_name, $last_name, $username, $gender, $position, $department_id, $phone_number, $hashed_password]);
                    $new_user_id = $pdo->lastInsertId();

                    $hist_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
                    $pdo->prepare($hist_sql)->execute([$new_user_id, $hashed_password]);
                    
                    $success_count++;
                } catch (Exception $e) {
                    $fail_count++;
                    $failed_entries[] = "Row with Staff ID '{$staff_id}': " . $e->getMessage();
                }
            }
            $pdo->commit();
            fclose($file);

            $message = "$success_count users imported successfully.";
            if ($fail_count > 0) {
                $message .= " $fail_count users failed to import. Details: " . implode(" | ", $failed_entries);
            }
            $response = ['success' => true, 'message' => $message];
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    $response['message'] = 'A database error occurred. Please check the logs.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// --- Helper Functions ---

function generate_unique_username(PDO $pdo, string $first_name, string $last_name): string {
    $base_username = strtolower(preg_replace('/[^a-zA-Z]/', '', $first_name) . '.' . preg_replace('/[^a-zA-Z]/', '', $last_name));
    $username = $base_username;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch() === false) {
            return $username;
        }
        $username = $base_username . $counter;
        $counter++;
    }
}
?>
