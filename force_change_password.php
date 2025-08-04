<?php
/*
File: force_change_password.php (FULLY SECURED)
Description: Enforces a mandatory password change with full complexity and history checks.
*/

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// is_logged_in() should start the session if not already started.
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$message = '';

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // --- 1. Basic Validation ---
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "The new passwords do not match.";
    
    // --- 2. Password Complexity Check ---
    } elseif (
        strlen($new_password) < 12 ||
        !preg_match('/[A-Z]/', $new_password) ||    // Must contain at least one uppercase letter
        !preg_match('/[a-z]/', $new_password) ||    // Must contain at least one lowercase letter
        !preg_match('/[0-9]/', $new_password) ||    // Must contain at least one number
        !preg_match('/[\W_]/', $new_password)       // Must contain at least one special character
    ) {
        $error = "Password must be at least 12 characters long and include uppercase, lowercase, numbers, and special characters.";
    
    } else {
        try {
            // --- 3. Password History Check ---
            $history_sql = "SELECT password_hash FROM password_history WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 20";
            $history_stmt = $pdo->prepare($history_sql);
            $history_stmt->execute(['user_id' => $user_id]);
            $past_passwords = $history_stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($past_passwords as $past_hash) {
                if (password_verify($new_password, $past_hash)) {
                    // Throw an exception to be caught below. This stops execution.
                    throw new Exception("You cannot reuse one of your last 20 passwords.");
                }
            }

            // --- 4. Update Password in a Transaction ---
            $pdo->beginTransaction();

            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // a) Update the users table: set new password, reset flag, and update timestamp
            $update_sql = "UPDATE users SET password = ?, password_reset_required = 0, password_last_changed = NOW() WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$hashed_password, $user_id]);

            // b) Insert the new password into the history table
            $insert_sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$user_id, $hashed_password]);
            
            // c) Optional: Keep history clean by deleting old records if more than 20 exist
            // This is good practice but not strictly required by the prompt
            $count_sql = "SELECT COUNT(*) FROM password_history WHERE user_id = ?";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute([$user_id]);
            if ($count_stmt->fetchColumn() > 20) {
                 $delete_sql = "DELETE FROM password_history WHERE user_id = ? ORDER BY created_at ASC LIMIT 1";
                 $pdo->prepare($delete_sql)->execute([$user_id]);
            }

            // If everything is successful, commit the changes
            $pdo->commit();

            // --- 5. Success ---
            $_SESSION['password_reset_required'] = false; // Update session
            $message = "Password updated successfully! Redirecting to your dashboard...";
            
            // Determine redirect URL based on role
            $redirect_url = ($_SESSION['user_role'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
            header("Refresh: 3; url=$redirect_url");

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack(); // Roll back changes on error
            }
            // Use the exception message for specific errors (like password history)
            $error = $e->getMessage();
            if ($e instanceof PDOException) {
                // For database errors, show a generic message
                $error = "A database error occurred. Please try again.";
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Your Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">
            <div>
                <svg class="mx-auto h-12 w-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
                <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
                    Update Your Password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    For your security, you must set a new password.
                </p>
            </div>

            <div class="bg-white p-8 rounded-lg shadow-md space-y-6">
                <?php if ($message): ?>
                    <div class="text-center p-4 bg-green-100 text-green-700 rounded-lg">
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                            <p><?= htmlspecialchars($error) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="force_change_password.php" class="space-y-6">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="new_password" id="new_password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <ul class="text-xs text-gray-500 list-disc list-inside space-y-1">
                            <li>At least 12 characters</li>
                            <li>Contains uppercase and lowercase letters</li>
                            <li>Contains at least one number</li>
                            <li>Contains at least one special character (e.g., !@#$%)</li>
                        </ul>
                        <div>
                            <button type="submit" class="group relative flex w-full justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Set New Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
