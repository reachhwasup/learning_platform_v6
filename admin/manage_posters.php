<?php
$page_title = 'Manage Monthly Posters';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// --- Date Filtering Logic ---
$filter_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
$filter_month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;

try {
    // Base SQL query
    $sql = "SELECT id, title, description, image_path, assigned_month FROM monthly_posters";
    
    $params = [];
    $where_clauses = [];

    // Add filters to the query if they are selected
    if ($filter_year) {
        $where_clauses[] = "YEAR(assigned_month) = :year";
        $params[':year'] = $filter_year;
    }
    if ($filter_month) {
        $where_clauses[] = "MONTH(assigned_month) = :month";
        $params[':month'] = $filter_month;
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // Order by assigned_month ASC to show oldest first (chronological order)
    $sql .= " ORDER BY assigned_month ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posters = $stmt->fetchAll();

    // Fetch all unique years that have posters, for the filter dropdown
    $years_stmt = $pdo->query("SELECT DISTINCT YEAR(assigned_month) as year FROM monthly_posters ORDER BY year DESC");
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Manage Posters Error: " . $e->getMessage());
    $posters = [];
    $available_years = [];
}

require_once 'includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900">Manage Posters</h2>
        <button id="add-poster-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
            + Add New Poster
        </button>
    </div>

    <!-- Month and Year Filter -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-8 flex justify-center">
        <form id="filter-form" method="GET" action="manage_posters.php" class="flex items-center space-x-4">
            <select name="month" id="month-select" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                <option value="">All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($m == $filter_month) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" id="year-select" class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                <option value="">All Years</option>
                <?php foreach ($available_years as $year): ?>
                    <option value="<?= $year ?>" <?= ($year == $filter_year) ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">No.</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Thumbnail</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Title</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Assigned Month</th>
                    <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posters)): ?>
                    <tr><td colspan="5" class="text-center py-10 text-gray-500">No posters found for the selected criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($posters as $index => $poster): ?>
                        <tr id="poster-row-<?= $poster['id'] ?>">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= $index + 1 ?></td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <img src="../uploads/posters/<?= htmlspecialchars($poster['image_path']) ?>" alt="Thumbnail" class="w-24 h-16 object-cover rounded">
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 font-semibold whitespace-no-wrap"><?= htmlspecialchars($poster['title']) ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?= $poster['assigned_month'] ? date('F Y', strtotime($poster['assigned_month'])) : 'N/A' ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm whitespace-nowrap">
                                <button onclick="editPoster(<?= $poster['id'] ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <button onclick="deletePoster(<?= $poster['id'] ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Poster Modal -->
<div id="poster-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="poster-form" enctype="multipart/form-data">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="poster-modal-title">Add New Poster</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="poster_id" id="poster_id">
                        <input type="hidden" name="action" id="poster-form-action">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" id="title" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                        <div>
                            <label for="assigned_month" class="block text-sm font-medium text-gray-700">Assign to Month</label>
                            <input type="month" name="assigned_month" id="assigned_month" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-700">Poster Image</label>
                            <input type="file" name="image" id="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-1" id="image-help-text">Upload a new image. Required for new posters.</p>
                        </div>
                    </div>
                </div>
                <div id="poster-form-feedback" class="px-6 py-2 text-sm text-red-600"></div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700">Save</button>
                    <button type="button" id="poster-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('poster-modal');
    const addBtn = document.getElementById('add-poster-btn');
    const cancelBtn = document.getElementById('poster-cancel-btn');
    const form = document.getElementById('poster-form');
    const feedbackDiv = document.getElementById('poster-form-feedback');
    const filterForm = document.getElementById('filter-form');
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');

    addBtn.addEventListener('click', () => {
        form.reset();
        document.getElementById('poster-modal-title').textContent = 'Add New Poster';
        document.getElementById('poster-form-action').value = 'add_poster';
        document.getElementById('image').required = true;
        document.getElementById('image-help-text').textContent = 'Upload a new image. Required for new posters.';
        modal.classList.remove('hidden');
    });

    cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        feedbackDiv.textContent = '';

        fetch('../api/admin/poster_crud.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                modal.classList.add('hidden');
                location.reload();
            } else {
                feedbackDiv.textContent = data.message;
            }
        });
    });

    function autoSubmitFilter() {
        filterForm.submit();
    }

    monthSelect.addEventListener('change', autoSubmitFilter);
    yearSelect.addEventListener('change', autoSubmitFilter);
});

function editPoster(id) {
    fetch(`../api/admin/poster_crud.php?action=get_poster&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const poster = data.data;
                const form = document.getElementById('poster-form');
                form.reset();
                document.getElementById('poster-modal-title').textContent = 'Edit Poster';
                document.getElementById('poster-form-action').value = 'edit_poster';
                document.getElementById('poster_id').value = poster.id;
                document.getElementById('title').value = poster.title;
                document.getElementById('description').value = poster.description;
                // Format YYYY-MM for the month input
                document.getElementById('assigned_month').value = poster.assigned_month ? poster.assigned_month.substring(0, 7) : '';
                document.getElementById('image').required = false;
                document.getElementById('image-help-text').textContent = 'Upload a new image only if you want to replace the current one.';
                document.getElementById('poster-modal').classList.remove('hidden');
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function deletePoster(id) {
    if (confirm('Are you sure you want to delete this poster?')) {
        const formData = new FormData();
        formData.append('action', 'delete_poster');
        formData.append('poster_id', id);

        fetch('../api/admin/poster_crud.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
