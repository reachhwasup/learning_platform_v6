<?php
$page_title = 'Assessment Reports';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Get Filter Values ---
$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['passed', 'failed']) ? $_GET['status'] : null;

try {
    // --- Build SQL Query with Filters ---
    // UPDATED: Query now gets the latest assessment for each user and counts their total attempts.
    $sql = "SELECT 
                u.first_name, u.last_name, u.staff_id, u.position, d.name as department_name,
                fa.id as assessment_id, fa.score, fa.status, fa.completed_at,
                (SELECT COUNT(*) FROM final_assessments fa_count WHERE fa_count.user_id = u.id) as attempt_count
            FROM users u
            JOIN final_assessments fa ON u.id = fa.user_id
            -- This join ensures we only get the row for the user's LATEST assessment
            JOIN (
                SELECT user_id, MAX(completed_at) AS max_completed_at 
                FROM final_assessments 
                GROUP BY user_id
            ) latest_fa ON fa.user_id = latest_fa.user_id AND fa.completed_at = latest_fa.max_completed_at
            LEFT JOIN departments d ON u.department_id = d.id";
    
    $where_clauses = [];
    $params = [];

    if ($filter_dept) {
        $where_clauses[] = "u.department_id = :dept_id";
        $params[':dept_id'] = $filter_dept;
    }
    if ($filter_status) {
        $where_clauses[] = "fa.status = :status";
        $params[':status'] = $filter_status;
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY fa.completed_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_results = $stmt->fetchAll();

    // Separate results into passed and failed arrays
    $passed_users = array_filter($all_results, fn($r) => $r['status'] === 'passed');
    $failed_users = array_filter($all_results, fn($r) => $r['status'] === 'failed');
    
    // Fetch departments for the filter dropdown
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log("Reports Page Error: " . $e->getMessage());
    $all_results = [];
    $passed_users = [];
    $failed_users = [];
    $departments = [];
}

require_once 'includes/header.php';

// Helper function to render the results table
function render_results_table($title, $users, $status_context, $color) {
    echo "<div class='mb-12'>";
    echo "<div class='flex justify-between items-center mb-4'>";
    echo "<h2 class='text-2xl font-semibold text-gray-800'>" . htmlspecialchars($title) . " (" . count($users) . ")</h2>";
    
    $export_params = http_build_query(['status' => $status_context] + $_GET);
    echo "<a href='../api/admin/generate_report.php?{$export_params}' class='bg-{$color}-600 hover:bg-{$color}-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors'>Export List (Excel)</a>";
    echo "</div>";

    echo "<div class='bg-white shadow-md rounded-lg overflow-x-auto'>";
    echo "<table class='min-w-full leading-normal'>";
    echo "<thead class='bg-gray-200'><tr>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>No.</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Name</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Staff ID</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Position</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Department</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Attempt</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Score (%)</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Status</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Date Completed</th>
            <th class='px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase'>Details</th>
          </tr></thead>";
    echo "<tbody>";

    if (empty($users)) {
        echo "<tr><td colspan='10' class='text-center py-10 text-gray-500'>No results found in this category.</td></tr>";
    } else {
        foreach ($users as $index => $user) {
            $status_badge_class = $user['status'] === 'passed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            echo "<tr>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . ($index + 1) . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . htmlspecialchars($user['staff_id']) . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . htmlspecialchars($user['position'] ?? 'N/A') . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . htmlspecialchars($user['department_name'] ?? 'N/A') . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm text-center font-semibold'>" . htmlspecialchars($user['attempt_count']) . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm font-semibold'>" . (int)$user['score'] . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>
                        <span class='relative inline-block px-3 py-1 font-semibold leading-tight rounded-full {$status_badge_class}'>" . ucfirst(htmlspecialchars($user['status'])) . "</span>
                    </td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>" . date('M d, Y H:i', strtotime($user['completed_at'])) . "</td>
                    <td class='px-5 py-5 border-b border-gray-200 bg-white text-sm'>
                        <a href='view_exam_details.php?assessment_id={$user['assessment_id']}' class='text-primary hover:underline'>View Details</a>
                    </td>
                  </tr>";
        }
    }
    echo "</tbody></table></div></div>";
}
?>

<div class="container mx-auto p-6">
    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-8">
        <form method="GET" action="reports.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                <label for="status" class="block text-sm font-medium text-gray-700">Filter by Status</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="passed" <?= ($filter_status === 'passed') ? 'selected' : '' ?>>Passed</option>
                    <option value="failed" <?= ($filter_status === 'failed') ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-primary text-white font-semibold py-2 px-4 rounded-lg hover:bg-primary-dark transition-colors w-full md:w-auto">Filter</button>
            </div>
        </form>
    </div>

    <?php
        // Conditionally render tables based on the filter
        if (!$filter_status) {
            render_results_table('All Assessments', $all_results, 'all', 'blue');
        }
        if (!$filter_status || $filter_status === 'passed') {
            render_results_table('Passed Assessments', $passed_users, 'passed', 'green');
        }
        if (!$filter_status || $filter_status === 'failed') {
            render_results_table('Failed Assessments', $failed_users, 'failed', 'red');
        }
    ?>
</div>

<?php require_once 'includes/footer.php'; ?>
