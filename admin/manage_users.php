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
$search_term = isset($_GET['search']) && trim($_GET['search']) !== '' ? trim($_GET['search']) : null;

// Initialize variables to prevent undefined variable warnings
$total_records = 0;
$total_pages = 0;
$normal_users = [];
$admin_users = [];
$departments = [];
$total_modules = 1;

try {
    // Get total number of modules for progress calculation
    $total_modules_stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $total_modules = $total_modules_stmt->fetchColumn();
    $total_modules = $total_modules > 0 ? $total_modules : 1; // Avoid division by zero

    // --- Build the WHERE clause for filtering normal users ---
    $where_clauses = ["u.role = 'user'"];
    $params = [];

    // FIXED SEARCH LOGIC - Completely rewritten for reliability
    if ($search_term) {
        $clean_search_term = trim($search_term);
        
        if (strlen($clean_search_term) >= 1) { // Allow even single character searches
            // Create a comprehensive search that covers all possible scenarios
            $search_sql = "(
                u.first_name LIKE :search1 OR 
                u.last_name LIKE :search2 OR 
                CONCAT(u.first_name, ' ', u.last_name) LIKE :search3 OR
                u.username LIKE :search4 OR 
                u.staff_id LIKE :search5
            )";
            
            $where_clauses[] = $search_sql;
            
            // Bind the same search term to multiple parameters
            $search_value = "%{$clean_search_term}%";
            $params[':search1'] = $search_value;
            $params[':search2'] = $search_value;
            $params[':search3'] = $search_value;
            $params[':search4'] = $search_value;
            $params[':search5'] = $search_value;
            
            // Debug logging
            error_log("Search term: '{$clean_search_term}'");
            error_log("Search value: '{$search_value}'");
        }
    }

    // Add department filter
    if ($filter_dept) {
        $where_clauses[] = "u.department_id = :dept_id";
        $params[':dept_id'] = $filter_dept;
    }
    
    // Add status filter
    if ($filter_status) {
        $where_clauses[] = "u.status = :status";
        $params[':status'] = $filter_status;
    }
    
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
    
    // Debug the final query
    error_log("Final WHERE SQL: " . $where_sql);
    error_log("Final parameters: " . print_r($params, true));

    // --- Get total records for pagination ---
    $total_records_sql = "SELECT COUNT(*) FROM users u " . $where_sql;
    $total_records_stmt = $pdo->prepare($total_records_sql);
    
    // Execute with parameters
    foreach ($params as $key => $value) {
        $total_records_stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    $total_records_stmt->execute();
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Debug total records
    error_log("Total records found: " . $total_records);

    // --- Fetch paginated and filtered normal users ---
    $sql_users = "SELECT 
                u.id, 
                CONCAT(u.first_name, ' ', u.last_name) as fullname, 
                u.username, 
                u.staff_id, 
                u.position, 
                u.phone_number, 
                u.gender, 
                u.role, 
                u.status, 
                d.name as department_name, 
                    (
                        SELECT COUNT(DISTINCT up.module_id)
                        FROM user_progress up
                        WHERE up.user_id = u.id AND (
                            (SELECT COUNT(*) FROM questions q WHERE q.module_id = up.module_id) = 0
                            OR
                            EXISTS (
                                SELECT 1
                                FROM user_answers ua
                                JOIN questions q_ua ON ua.question_id = q_ua.id
                                WHERE ua.user_id = u.id AND q_ua.module_id = up.module_id
                            )
                        )
                    ) as completed_modules
                  FROM users u 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  {$where_sql}
                  ORDER BY u.first_name, u.last_name
                  LIMIT :limit OFFSET :offset";
                      
    $stmt_users = $pdo->prepare($sql_users);

    // Bind dynamic filter parameters
    foreach ($params as $key => $val) {
        $stmt_users->bindValue($key, $val, PDO::PARAM_STR);
    }
    
    // Bind pagination parameters
    $stmt_users->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt_users->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt_users->execute();
    $normal_users = $stmt_users->fetchAll();

    // Debug results
    error_log("Number of users returned: " . count($normal_users));

    // --- Fetch all admin users (no pagination/filters for this list) ---
    $sql_admins = "SELECT 
              u.id, 
              CONCAT(u.first_name, ' ', u.last_name) as fullname, 
              u.username, 
              u.staff_id, 
              u.position, 
              u.phone_number, 
              u.gender, 
              u.role, 
              u.status, 
              d.name as department_name
                  FROM users u 
                  LEFT JOIN departments d ON u.department_id = d.id
                  WHERE u.role = 'admin'
                  ORDER BY u.first_name, u.last_name";
    $stmt_admins = $pdo->query($sql_admins);
    $admin_users = $stmt_admins->fetchAll();
    
    // Fetch all departments for filter dropdown
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    // Ensure variables have safe defaults on error
    $normal_users = [];
    $admin_users = [];
    $departments = [];
    $total_pages = 0;
    $total_records = 0;
    $total_modules = 1;
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
             <button id="delete-all-btn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
                Delete All Users
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form method="GET" action="manage_users.php" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filter-form">
            <div class="relative">
                <label for="search" class="block text-sm font-medium text-gray-700">Search User</label>
                <input type="text" 
                       name="search" 
                       id="search" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-10" 
                       placeholder="Name, Staff ID, Username..." 
                       value="<?= htmlspecialchars($search_term ?? '') ?>">
                
                <!-- Clear Search Button -->
                <?php if ($search_term): ?>
                <button type="button" 
                        id="clear-search" 
                        class="absolute inset-y-0 right-0 top-6 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                        title="Clear search">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
            
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
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex-1 md:flex-none">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Filter
                </button>
                
                <?php if ($search_term || $filter_dept || $filter_status): ?>
                <button type="button" 
                        id="clear-all-filters" 
                        class="bg-gray-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Search Results Info -->
        <?php if ($search_term || $filter_dept || $filter_status): ?>
        <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-blue-800">
                    <span class="font-medium">Search Results:</span> 
                    Found <?= number_format($total_records) ?> user<?= $total_records !== 1 ? 's' : '' ?>
                    <?php if ($search_term): ?>
                        matching "<?= htmlspecialchars($search_term) ?>"
                    <?php endif; ?>
                    <?php if ($filter_dept): ?>
                        <?php 
                        $dept_name = 'Unknown Department';
                        foreach ($departments as $dept) {
                            if ($dept['id'] == $filter_dept) {
                                $dept_name = $dept['name'];
                                break;
                            }
                        }
                        ?>
                        in <?= htmlspecialchars($dept_name) ?>
                    <?php endif; ?>
                    <?php if ($filter_status): ?>
                        with status "<?= htmlspecialchars($filter_status) ?>"
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Test Query Section (Remove after debugging) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
    <div class="bg-yellow-50 p-4 rounded-lg mb-6 border border-yellow-200">
        <h4 class="font-medium text-yellow-800 mb-2">Debug Information:</h4>
        <div class="text-sm text-yellow-700 space-y-2">
            <p><strong>Search Term:</strong> "<?= htmlspecialchars($search_term ?? 'None') ?>"</p>
            <p><strong>Total Records Found:</strong> <?= $total_records ?></p>
            <p><strong>SQL WHERE:</strong> <?= htmlspecialchars($where_sql) ?></p>
            <p><strong>Parameters:</strong> <?= htmlspecialchars(json_encode($params)) ?></p>
            
            <!-- Test direct query -->
            <?php if ($search_term): ?>
                <?php
                $test_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as fullname, username, staff_id 
                            FROM users 
                            WHERE role = 'user' 
                            AND (first_name LIKE :test OR last_name LIKE :test OR CONCAT(first_name, ' ', last_name) LIKE :test)
                            LIMIT 5";
                $test_stmt = $pdo->prepare($test_sql);
                $test_stmt->bindValue(':test', "%{$search_term}%");
                $test_stmt->execute();
                $test_results = $test_stmt->fetchAll();
                ?>
                <p><strong>Direct Test Query Results:</strong></p>
                <ul class="ml-4">
                    <?php foreach ($test_results as $test_user): ?>
                        <li>ID: <?= $test_user['id'] ?> - <?= htmlspecialchars($test_user['fullname']) ?> (<?= htmlspecialchars($test_user['staff_id']) ?>)</li>
                    <?php endforeach; ?>
                    <?php if (empty($test_results)): ?>
                        <li>No results found in test query</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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
                    <tr><td colspan="11" class="text-center py-10 text-gray-500">
                        <?php if ($search_term || $filter_dept || $filter_status): ?>
                            No users found matching your search criteria.
                            <br>
                            <button onclick="clearAllFilters()" class="mt-2 text-blue-600 hover:text-blue-800 underline">
                                Clear all filters
                            </button>
                        <?php else: ?>
                            No users found.
                        <?php endif; ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($normal_users as $index => $user): ?>
                        <?php $progress_percentage = $total_modules > 0 ? round(($user['completed_modules'] / $total_modules) * 100) : 0; ?>
                        <tr id="user-row-<?= $user['id'] ?>" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $offset + $index + 1 ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold"><?= htmlspecialchars($user['fullname']) ?></td>
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
    
    <!-- Pagination for Normal Users -->
    <?php if ($total_pages > 1): ?>
    <div class="py-6 flex justify-center">
        <nav class="flex rounded-md shadow">
            <?php 
            $pagination_params = http_build_query(['department' => $filter_dept, 'status' => $filter_status, 'search' => $search_term]);
            $range = 2; // Number of pages to show around the current page
            ?>
            <a href="?page=<?= max(1, $current_page - 1) ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-l-md hover:bg-gray-50">Previous</a>
            
            <?php if ($current_page > $range + 1): ?>
                <a href="?page=1&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">1</a>
                <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-white">...</span>
            <?php endif; ?>

            <?php for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++): ?>
                <a href="?page=<?= $i ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium <?= $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white hover:bg-gray-50' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages - $range): ?>
                <span class="px-4 py-2 text-sm font-medium text-gray-700 bg-white">...</span>
                <a href="?page=<?= $total_pages ?>&<?= $pagination_params ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><?= $total_pages ?></a>
            <?php endif; ?>

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
                        <tr id="user-row-<?= $user['id'] ?>" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $index + 1 ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold"><?= htmlspecialchars($user['fullname']) ?></td>
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
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="user-modal-title">Add New User</h3>
                        <button type="button" id="close-user-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
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
                <div id="user-form-feedback" class="px-6 py-2 text-sm text-red-600 hidden"></div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" id="user-form-submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save User
                    </button>
                    <button type="button" id="cancel-user-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="bulk-upload-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Bulk Upload Users</h3>
                    <button type="button" id="close-bulk-upload-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-medium text-blue-900 mb-2">Instructions:</h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• Upload a CSV file with the following columns: first_name, last_name, staff_id, position, gender, department_name, phone_number</li>
                            <li>• Department name should match existing departments in the system</li>
                            <li>• Gender should be: Male, Female, or Other</li>
                            <li>• Default password will be set to: APD@123456789</li>
                            <li>• Username will be auto-generated as: firstname.lastname</li>
                        </ul>
                        <div class="mt-3">
                            <a href="download_user_template.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 underline">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Download CSV Template
                            </a>
                        </div>
                    </div>
                    
                    <form id="bulk-upload-form" enctype="multipart/form-data">
                        <div>
                            <label for="csv_file" class="block text-sm font-medium text-gray-700">Choose CSV or XLSX File</label>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv,.xlsx" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div id="bulk-upload-feedback" class="mt-4 text-sm hidden"></div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancel-bulk-upload" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </button>
                            <button type="submit" id="bulk-upload-submit" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Upload Users
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete All Users Confirmation Modal -->
<div id="delete-all-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Delete All Users</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to delete ALL users? This action cannot be undone and will remove all user accounts, progress, and associated data.
                            </p>
                            <div class="mt-3 p-3 bg-red-50 rounded-lg border border-red-200">
                                <p class="text-sm text-red-800 font-medium">⚠️ Warning: This will delete all user data permanently!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-delete-all" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete All Users
                </button>
                <button type="button" id="cancel-delete-all" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const userModal = document.getElementById('user-modal');
    const bulkUploadModal = document.getElementById('bulk-upload-modal');
    const deleteAllModal = document.getElementById('delete-all-modal');
    const userForm = document.getElementById('user-form');
    const bulkUploadForm = document.getElementById('bulk-upload-form');

    // Button elements
    const addUserBtn = document.getElementById('add-user-btn');
    const bulkUploadBtn = document.getElementById('bulk-upload-btn');
    const deleteAllBtn = document.getElementById('delete-all-btn');
    const clearSearchBtn = document.getElementById('clear-search');
    const clearAllFiltersBtn = document.getElementById('clear-all-filters');

    // Modal close buttons
    const closeUserModal = document.getElementById('close-user-modal');
    const cancelUserModal = document.getElementById('cancel-user-modal');
    const closeBulkUploadModal = document.getElementById('close-bulk-upload-modal');
    const cancelBulkUpload = document.getElementById('cancel-bulk-upload');
    const cancelDeleteAll = document.getElementById('cancel-delete-all');
    const confirmDeleteAll = document.getElementById('confirm-delete-all');

    // Show modals
    addUserBtn?.addEventListener('click', () => {
        document.getElementById('user-modal-title').textContent = 'Add New User';
        document.getElementById('user-form-action').value = 'add';
        document.getElementById('user-form-submit').textContent = 'Add User';
        userForm.reset();
        document.getElementById('user_id').value = '';
        showModal(userModal);
    });

    bulkUploadBtn?.addEventListener('click', () => {
        showModal(bulkUploadModal);
    });

    deleteAllBtn?.addEventListener('click', () => {
        showModal(deleteAllModal);
    });

    // Close modals
    [closeUserModal, cancelUserModal].forEach(btn => {
        btn?.addEventListener('click', () => hideModal(userModal));
    });

    [closeBulkUploadModal, cancelBulkUpload].forEach(btn => {
        btn?.addEventListener('click', () => hideModal(bulkUploadModal));
    });

    cancelDeleteAll?.addEventListener('click', () => hideModal(deleteAllModal));

    // Clear search functionality
    clearSearchBtn?.addEventListener('click', () => {
        document.getElementById('search').value = '';
        document.getElementById('filter-form').submit();
    });

    // Clear all filters
    clearAllFiltersBtn?.addEventListener('click', clearAllFilters);

    // User form submission
    userForm?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Set the correct action based on form state
        const action = document.getElementById('user-form-action').value;
        if (action === 'add') {
            formData.set('action', 'add');
        } else if (action === 'edit') {
            formData.set('action', 'update');
        }
        
        try {
            const response = await fetch('crud_user.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                hideModal(userModal);
                location.reload(); // Refresh to show updated data
            } else {
                showFeedback('user-form-feedback', result.message, 'error');
            }
        } catch (error) {
            showFeedback('user-form-feedback', 'An error occurred. Please try again.', 'error');
        }
    });

    // Bulk upload form submission
    bulkUploadForm?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Change the file input name to match what crud_user.php expects
        const fileInput = document.getElementById('csv_file');
        if (fileInput.files.length > 0) {
            formData.delete('csv_file');
            formData.append('user_file', fileInput.files[0]);
        }
        formData.append('action', 'bulk_upload');
        
        const submitBtn = document.getElementById('bulk-upload-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';
        
        try {
            const response = await fetch('crud_user.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showFeedback('bulk-upload-feedback', result.message, 'success');
                if (result.failed_entries && result.failed_entries.length > 0) {
                    showFeedback('bulk-upload-feedback', result.message + '<br><br>Failed entries:<br>' + result.failed_entries.join('<br>'), 'error');
                }
                setTimeout(() => {
                    hideModal(bulkUploadModal);
                    location.reload();
                }, 3000);
            } else {
                showFeedback('bulk-upload-feedback', result.message, 'error');
            }
        } catch (error) {
            showFeedback('bulk-upload-feedback', 'An error occurred during upload.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload Users';
        }
    });

    // Delete all users confirmation
    confirmDeleteAll?.addEventListener('click', async function() {
        try {
            const response = await fetch('crud_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_all_normal_users'
            });
            
            const result = await response.json();
            
            if (result.success) {
                hideModal(deleteAllModal);
                location.reload();
            } else {
                alert(result.message);
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        }
    });

    // Utility functions
    function showModal(modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function hideModal(modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function showFeedback(elementId, message, type) {
        const element = document.getElementById(elementId);
        element.innerHTML = message;
        element.className = `px-6 py-2 text-sm ${type === 'error' ? 'text-red-600' : 'text-green-600'}`;
        element.classList.remove('hidden');
    }
});

// Global functions for user actions
function editUser(userId) {
    // Fetch user data and populate form
    fetch(`crud_user.php?action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('user-form-action').value = 'edit';
                document.getElementById('user-form-submit').textContent = 'Update User';
                document.getElementById('user_id').value = user.id;
                document.getElementById('first_name').value = user.first_name;
                document.getElementById('last_name').value = user.last_name;
                document.getElementById('staff_id').value = user.staff_id;
                document.getElementById('position').value = user.position || '';
                document.getElementById('gender').value = user.gender || '';
                document.getElementById('department_id').value = user.department_id;
                document.getElementById('phone_number').value = user.phone_number || '';
                document.getElementById('role').value = user.role;
                document.getElementById('user_status').value = user.status;
                
                document.getElementById('user-modal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('crud_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`user-row-${userId}`).remove();
            } else {
                alert(data.message);
            }
        });
    }
}

function resetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password to the default?')) {
        fetch('crud_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reset_password&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
        });
    }
}

function unlockUser(userId) {
    if (confirm('Are you sure you want to unlock this user?')) {
        fetch('crud_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=unlock&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
    }
}

function clearAllFilters() {
    window.location.href = 'manage_users.php';
}
</script>

<?php require_once 'includes/footer.php'; ?>