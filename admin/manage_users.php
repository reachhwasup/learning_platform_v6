<?php
$page_title = 'Manage Users';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Filter & Pagination Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'locked']) ? $_GET['status'] : null;

try {
    $total_modules_stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $total_modules = $total_modules_stmt->fetchColumn();
    $total_modules = $total_modules > 0 ? $total_modules : 1;

    $where_clauses = ["u.role = 'user'"];
    $params = [];

    if ($filter_dept) {
        $where_clauses[] = "u.department_id = :dept_id";
        $params[':dept_id'] = $filter_dept;
    }
    if ($filter_status) {
        $where_clauses[] = "u.status = :status";
        $params[':status'] = $filter_status;
    }
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);

    $total_records_sql = "SELECT COUNT(*) FROM users u " . $where_sql;
    $total_records_stmt = $pdo->prepare($total_records_sql);
    $total_records_stmt->execute($params);
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    $sql_users = "SELECT 
                    u.id, u.first_name, u.last_name, u.username, u.staff_id, u.position, u.phone_number, u.gender, u.role, u.status, d.name as department_name, 
                    (SELECT COUNT(*) FROM user_progress up WHERE up.user_id = u.id) as completed_modules
                  FROM users u 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  {$where_sql}
                  ORDER BY u.first_name, u.last_name
                  LIMIT :limit OFFSET :offset";
                  
    $stmt_users = $pdo->prepare($sql_users);
    foreach ($params as $key => &$val) {
        $stmt_users->bindParam($key, $val);
    }
    $stmt_users->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt_users->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_users->execute();
    $normal_users = $stmt_users->fetchAll();

    $sql_admins = "SELECT u.id, u.first_name, u.last_name, u.username, u.staff_id, u.position, u.phone_number, u.gender, u.role, u.status, d.name as department_name 
                   FROM users u 
                   LEFT JOIN departments d ON u.department_id = d.id
                   WHERE u.role = 'admin'
                   ORDER BY u.first_name, u.last_name";
    $stmt_admins = $pdo->query($sql_admins);
    $admin_users = $stmt_admins->fetchAll();
    
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    $normal_users = [];
    $admin_users = [];
    $departments = [];
    $total_pages = 0;
}

require_once 'includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
        <h2 class="text-2xl font-semibold text-gray-900">User Management</h2>
        <div class="flex gap-2">
            <button id="add-user-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
                + Add User
            </button>
            <button id="bulk-upload-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
                ↑ Bulk Upload
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form method="GET" action="manage_users.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="department" class="block text-sm font-medium text-gray-700">Filter by Department</label>
                <select name="department" id="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($filter_dept == $dept['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status" class="block text-sm font-medium text-gray-700">Filter by Status</label>
                <select name="status" id="filter_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($filter_status === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filter_status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    <option value="locked" <?= ($filter_status === 'locked') ? 'selected' : '' ?>>Locked</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors w-full md:w-auto">Filter</button>
            </div>
        </form>
    </div>

    <!-- Normal Users Table -->
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Normal Users</h3>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">No</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Staff ID</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Username</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Gender</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Position</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Department</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Phone Number</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Progress</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($normal_users)): ?>
                    <tr><td colspan="11" class="text-center py-10 text-gray-500">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($normal_users as $index => $user): ?>
                        <?php $progress_percentage = round(($user['completed_modules'] / $total_modules) * 100); ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $offset + $index + 1 ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['staff_id']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['gender'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['position'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <div class="w-full bg-gray-200 rounded-full">
                                    <div class="bg-blue-500 text-xs font-medium text-blue-100 text-center p-0.5 leading-none rounded-full" style="width: <?= $progress_percentage ?>%"><?= $progress_percentage ?>%</div>
                                </div>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-green-900"><span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"></span><span class="relative capitalize">Active</span></span>
                                <?php elseif ($user['status'] === 'inactive'): ?>
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-red-900"><span aria-hidden class="absolute inset-0 bg-red-200 opacity-50 rounded-full"></span><span class="relative capitalize">Inactive</span></span>
                                <?php elseif ($user['status'] === 'locked'): ?>
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-yellow-900"><span aria-hidden class="absolute inset-0 bg-yellow-200 opacity-50 rounded-full"></span><span class="relative capitalize">Locked</span></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm whitespace-nowrap">
                                <?php if ($user['status'] === 'locked'): ?>
                                    <button onclick="unlockUser(<?= $user['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">Unlock</button>
                                <?php endif; ?>
                                <button onclick="editUser(<?= $user['id'] ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <button onclick="resetPassword(<?= $user['id'] ?>)" class="text-yellow-600 hover:text-yellow-900 mr-3">Reset Pass</button>
                                <button onclick="deleteUser(<?= $user['id'] ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination for Normal Users -->
    <?php if ($total_pages > 1): ?>
        <div class="py-6 flex justify-center">
            <nav class="flex rounded-md shadow">
                <?php $pagination_params = http_build_query(['department' => $filter_dept, 'status' => $filter_status]); ?>
                <a href="?page=<?= max(1, $current_page - 1) ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-l-md hover:bg-gray-50">Previous</a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium <?= $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white hover:bg-gray-50' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= min($total_pages, $current_page + 1) ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-r-md hover:bg-gray-50">Next</a>
            </nav>
        </div>
    <?php endif; ?>

    <!-- Admin Users Table -->
    <h3 class="text-xl font-semibold text-gray-800 mt-12 mb-4">Administrators</h3>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">No</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Staff ID</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Username</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Gender</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Position</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Department</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Phone Number</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admin_users)): ?>
                    <tr><td colspan="10" class="text-center py-10 text-gray-500">No administrators found.</td></tr>
                <?php else: ?>
                    <?php foreach ($admin_users as $index => $user): ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $index + 1 ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['staff_id']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['gender'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['position'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <span class="relative inline-block px-3 py-1 font-semibold leading-tight <?= $user['status'] === 'active' ? 'text-green-900' : 'text-red-900' ?>">
                                    <span aria-hidden class="absolute inset-0 <?= $user['status'] === 'active' ? 'bg-green-200' : 'bg-red-200' ?> opacity-50 rounded-full"></span>
                                    <span class="relative capitalize"><?= htmlspecialchars($user['status']) ?></span>
                                </span>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm whitespace-nowrap">
                                <button onclick="editUser(<?= $user['id'] ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="resetPassword(<?= $user['id'] ?>)" class="text-yellow-600 hover:text-yellow-900 mr-3">Reset Pass</button>
                                    <button onclick="deleteUser(<?= $user['id'] ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="user-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="user-form">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="user-modal-title">Add New User</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="user_id" id="user_id">
                        <input type="hidden" name="action" id="user-form-action">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                <input name="first_name" id="first_name" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                <input name="last_name" id="last_name" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="staff_id" class="block text-sm font-medium text-gray-700">Staff ID</label>
                                <input name="staff_id" id="staff_id" type="text" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700">Position</label>
                                <input name="position" id="position" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                                <select name="gender" id="gender" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="" disabled selected>Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="department_id" class="block text-sm font-medium text-gray-700">Department</label>
                                <select name="department_id" id="department_id" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="" disabled selected>Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept['id']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input name="phone_number" id="phone_number" type="text" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            <div class="md:col-span-2">
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <input name="password" id="password" type="password" placeholder="Default Password (APD@123456789)" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <p class="text-xs text-gray-500 mt-1">Username is auto-generated (firstname.lastname).</p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select name="role" id="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label for="user_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="user_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="locked">Locked</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="user-form-feedback" class="px-6 py-2 text-sm text-red-600"></div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700">Save</button>
                    <button type="button" id="user-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="bulk-upload-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="bulk-upload-form" enctype="multipart/form-data">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Bulk Upload Users</h3>
                    <div class="mt-4">
                        <label for="user_file" class="block text-sm font-medium text-gray-700">Upload CSV File</label>
                        <input type="file" name="user_file" id="user_file" required accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-500 mt-2">
                            File must be a CSV with columns: `staff_id`, `first_name`, `last_name`, `gender`, `position`, `department_name`, `phone_number`. <br>
                            <a href="../templates/question_template.csv" class="text-blue-600 hover:underline" download>Download Template</a>
                        </p>
                    </div>
                </div>
                <div id="bulk-form-feedback" class="px-6 py-2 text-sm"></div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700">Upload and Create Users</button>
                    <button type="button" id="bulk-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Modal Handling ---
    const userModal = document.getElementById('user-modal');
    const bulkModal = document.getElementById('bulk-upload-modal');
    const addUserBtn = document.getElementById('add-user-btn');
    const bulkUploadBtn = document.getElementById('bulk-upload-btn');
    const userCancelBtn = document.getElementById('user-cancel-btn');
    const bulkCancelBtn = document.getElementById('bulk-cancel-btn');
    const userForm = document.getElementById('user-form');
    const bulkForm = document.getElementById('bulk-upload-form');

    if (addUserBtn) {
        addUserBtn.addEventListener('click', () => {
            userForm.reset();
            document.getElementById('user-modal-title').textContent = 'Add New User';
            document.getElementById('user-form-action').value = 'add';
            document.getElementById('password').placeholder = 'Default Password (APD@123456789)';
            userModal.classList.remove('hidden');
        });
    }

    if (bulkUploadBtn) {
        bulkUploadBtn.addEventListener('click', () => {
            if (bulkForm) bulkForm.reset();
            bulkModal.classList.remove('hidden');
        });
    }
    
    if (userCancelBtn) {
        userCancelBtn.addEventListener('click', () => userModal.classList.add('hidden'));
    }

    if (bulkCancelBtn) {
        bulkCancelBtn.addEventListener('click', () => bulkModal.classList.add('hidden'));
    }

    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const feedbackDiv = document.getElementById('user-form-feedback');
            
            fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        userModal.classList.add('hidden');
                        location.reload();
                    } else {
                        feedbackDiv.textContent = data.message;
                    }
                });
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bulk_upload');
            const feedbackDiv = document.getElementById('bulk-form-feedback');
            feedbackDiv.textContent = 'Uploading, please wait...';
            feedbackDiv.className = 'px-6 py-2 text-sm text-blue-600';

            fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        feedbackDiv.textContent = data.message;
                        feedbackDiv.className = 'px-6 py-2 text-sm text-green-600';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        feedbackDiv.textContent = 'Error: ' + data.message;
                        feedbackDiv.className = 'px-6 py-2 text-sm text-red-600';
                    }
                });
        });
    }
});

// --- CRUD Functions (must be in global scope) ---
function editUser(id) {
    const userForm = document.getElementById('user-form');
    const userModal = document.getElementById('user-modal');
    
    fetch(`../api/admin/user_crud.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                userForm.reset();
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('user-form-action').value = 'update';
                document.getElementById('user_id').value = data.user.id;
                document.getElementById('first_name').value = data.user.first_name;
                document.getElementById('last_name').value = data.user.last_name;
                document.getElementById('staff_id').value = data.user.staff_id;
                document.getElementById('position').value = data.user.position;
                document.getElementById('phone_number').value = data.user.phone_number;
                document.getElementById('gender').value = data.user.gender;
                document.getElementById('department_id').value = data.user.department_id;
                document.getElementById('role').value = data.user.role;
                document.getElementById('user_status').value = data.user.status;
                document.getElementById('password').placeholder = 'New Password (leave blank to keep current)';
                userModal.classList.remove('hidden');
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function resetPassword(id) {
    if (confirm('Are you sure you want to reset this user\'s password? They will be forced to create a new one on their next login.')) {
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function unlockUser(id) {
    if (confirm('Are you sure you want to unlock this user\'s account?')) {
        const formData = new FormData();
        formData.append('action', 'unlock');
        formData.append('user_id', id);
        fetch('../api/admin/user_crud.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('User account unlocked successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
