<?php
// Start session and output buffering
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$page_title = 'Information Security Awareness Training';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables to prevent undefined errors
$all_modules = [];
$completed_modules = [];
$in_progress_modules = [];
$all_modules_with_status = [];
$error_message = null;

try {
    // Check if PDO connection exists
    if (!isset($pdo) || !$pdo) {
        throw new Exception("Database connection not available");
    }

    // Fetch all modules with their video information
    $sql_modules = "SELECT m.id, m.title, m.description, m.module_order, v.thumbnail_path 
                    FROM modules m
                    LEFT JOIN videos v ON m.id = v.module_id
                    ORDER BY m.module_order ASC";
    
    $stmt_modules = $pdo->query($sql_modules);
    
    if ($stmt_modules) {
        $all_modules = $stmt_modules->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("Failed to fetch modules");
    }

    // Fetch the user's completed modules
    $sql_progress = "SELECT module_id FROM user_progress WHERE user_id = ?";
    $stmt_progress = $pdo->prepare($sql_progress);
    
    if ($stmt_progress && $stmt_progress->execute([$user_id])) {
        $completed_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);
    } else {
        throw new Exception("Failed to fetch user progress");
    }

    // Process modules to determine status
    if (is_array($all_modules)) {
        foreach ($all_modules as $index => $module) {
            $is_completed = in_array($module['id'], $completed_modules);
            $is_unlocked = ($index === 0 || ($index > 0 && in_array($all_modules[$index - 1]['id'], $completed_modules)));

            $status = 'locked';
            if ($is_completed) {
                $status = 'completed';
            } elseif ($is_unlocked) {
                $status = 'in_progress';
                // Add module to in-progress list only if it's not completed
                if (!$is_completed) {
                    $in_progress_modules[] = $module;
                }
            }
            
            $module['status'] = $status;
            $all_modules_with_status[] = $module;
        }
    }

} catch (PDOException $e) {
    error_log("Dashboard PDO Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard. Please try again later.";
}

// Include header
require_once 'includes/header.php';

// End output buffering
ob_end_flush();
?>

<?php if ($error_message): ?>
<div class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
require_once 'includes/footer.php';
exit;
endif; 
?>

<!-- Welcome Banner Section -->
<div class="relative bg-cover bg-center h-48 sm:h-56 md:h-64" style="background-image: url('assets/images/welcome_banner.png');">
    <div class="absolute inset-0 bg-black bg-opacity-60 flex flex-col items-center justify-center text-center p-4">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white">Welcome to Your Customized Learning Experience</h1>
        <p class="text-base sm:text-lg md:text-xl text-gray-200 mt-2">Powered by APD Bank Security Awareness</p>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="p-4 sm:p-6">
    <div class="container mx-auto -mt-16 sm:-mt-24 relative z-10">
        
        <!-- Search Bar Section -->
        <div class="bg-white p-4 rounded-lg shadow-lg mb-8">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" 
                       id="module-search" 
                       placeholder="Search modules by title..." 
                       class="block w-full rounded-md border-gray-300 py-3 pl-10 pr-3 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
        </div>

        <!-- In Progress Section -->
        <div class="mb-12">
            <h2 class="text-2xl font-semibold text-gray-900 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                In Progress (<?= count($in_progress_modules) ?>)
            </h2>
            
            <?php if (empty($in_progress_modules)): ?>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg shadow-md text-center border border-blue-200">
                    <svg class="mx-auto h-12 w-12 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Ready to Start Learning?</h3>
                    <p class="text-gray-600 mb-4">You have no modules in progress. Start your security awareness journey below!</p>
                    <button onclick="document.getElementById('modules-container').scrollIntoView({behavior: 'smooth'})" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Browse All Modules
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($in_progress_modules as $module): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500 hover:shadow-lg transition-shadow">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">
                                Module <?= htmlspecialchars($module['module_order']) ?>: <?= htmlspecialchars($module['title']) ?>
                            </h3>
                            <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                               class="block w-full text-center bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                Continue Learning
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Courses Section -->
        <div id="modules-container">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-2xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    All Courses (<?= count($all_modules_with_status) ?>)
                </h2>
                
                <div class="flex space-x-2">
                    <button id="grid-view-btn" 
                            class="p-2 rounded-md bg-blue-600 text-white transition-colors" 
                            title="Grid View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </button>
                    <button id="list-view-btn" 
                            class="p-2 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 transition-colors" 
                            title="List View">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Grid View Container -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="modules-grid-container">
                <?php foreach ($all_modules_with_status as $module): ?>
                    <?php 
                    // Handle thumbnail
                    if (!empty($module['thumbnail_path']) && file_exists('uploads/thumbnails/' . $module['thumbnail_path'])) {
                        $thumbnail_url = 'uploads/thumbnails/' . htmlspecialchars($module['thumbnail_path']);
                    } else {
                        // Create a simple colored div as fallback
                        $thumbnail_url = null;
                    }
                    ?>
                    <div class="bg-white shadow-lg rounded-lg overflow-hidden hover:shadow-xl transition-shadow module-card" 
                         data-title="<?= strtolower(htmlspecialchars($module['title'] ?? '')) ?>"
                         data-description="<?= strtolower(htmlspecialchars($module['description'] ?? '')) ?>">
                        
                        <?php if ($module['status'] !== 'locked'): ?>
                            <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" class="block">
                        <?php endif; ?>
                        
                        <div class="relative">
                            <?php if ($thumbnail_url): ?>
                                <img class="w-full h-48 object-cover" 
                                     src="<?= $thumbnail_url ?>" 
                                     alt="Module <?= htmlspecialchars($module['module_order']) ?> Thumbnail"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php endif; ?>
                            
                            <!-- Fallback thumbnail -->
                            <div class="w-full h-48 bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-2xl" 
                                 <?= $thumbnail_url ? 'style="display:none;"' : '' ?>>
                                Module <?= htmlspecialchars($module['module_order']) ?>
                            </div>
                            
                            <?php if ($module['status'] === 'locked'): ?>
                                <div class="absolute inset-0 bg-black bg-opacity-70 flex flex-col items-center justify-center">
                                    <svg class="h-10 w-10 text-white mb-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-white text-sm font-medium text-center px-4">Complete previous modules</span>
                                </div>
                            <?php elseif ($module['status'] === 'completed'): ?>
                                <div class="absolute top-2 right-2 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    COMPLETED
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($module['status'] !== 'locked'): ?>
                            </a>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                Module <?= htmlspecialchars($module['module_order']) ?>: <?= htmlspecialchars($module['title']) ?>
                            </h3>
                            
                            <?php if (!empty($module['description'])): ?>
                                <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($module['description']) ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <?php if ($module['status'] === 'locked'): ?>
                                    <button disabled class="w-full bg-gray-300 text-gray-500 font-bold py-2 px-4 rounded-lg cursor-not-allowed">
                                        Locked
                                    </button>
                                <?php else: ?>
                                    <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                                       class="block w-full text-center bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                        <?= $module['status'] === 'completed' ? 'Review Module' : 'Start Module' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List View Container -->
            <div class="hidden space-y-4" id="modules-list-container">
                <?php foreach ($all_modules_with_status as $module): ?>
                    <div class="bg-white shadow-md rounded-lg p-4 flex items-center space-x-4 hover:shadow-lg transition-shadow module-row" 
                         data-title="<?= strtolower(htmlspecialchars($module['title'] ?? '')) ?>"
                         data-description="<?= strtolower(htmlspecialchars($module['description'] ?? '')) ?>">
                        
                        <div class="flex-shrink-0">
                            <?php if ($module['status'] === 'completed'): ?>
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            <?php elseif ($module['status'] === 'locked'): ?>
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-grow">
                            <h3 class="text-lg font-semibold text-gray-800">
                                Module <?= htmlspecialchars($module['module_order']) ?>: <?= htmlspecialchars($module['title']) ?>
                            </h3>
                            <?php if (!empty($module['description'])): ?>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($module['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($module['status'] === 'completed'): ?>
                            <div class="flex items-center space-x-2 text-green-600">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="font-medium">Completed</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex-shrink-0">
                            <?php if ($module['status'] === 'locked'): ?>
                                <button disabled class="bg-gray-200 text-gray-500 font-bold py-2 px-4 rounded-lg cursor-not-allowed">
                                    Locked
                                </button>
                            <?php else: ?>
                                <a href="view_module.php?id=<?= htmlspecialchars($module['id']) ?>" 
                                   class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                    <?= $module['status'] === 'completed' ? 'Review' : 'Start' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="no-results-message" class="hidden text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No modules found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your search terms or browse all available modules.</p>
                <button onclick="clearSearch()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    Clear Search
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    const gridContainer = document.getElementById('modules-grid-container');
    const listContainer = document.getElementById('modules-list-container');
    const searchInput = document.getElementById('module-search');
    const noResultsMessage = document.getElementById('no-results-message');
    
    let searchTimeout;

    // View switching functionality
    function switchToGridView() {
        gridContainer.classList.remove('hidden');
        listContainer.classList.add('hidden');
        gridViewBtn.classList.add('bg-blue-600', 'text-white');
        gridViewBtn.classList.remove('bg-gray-200', 'text-gray-600');
        listViewBtn.classList.add('bg-gray-200', 'text-gray-600');
        listViewBtn.classList.remove('bg-blue-600', 'text-white');
    }

    function switchToListView() {
        listContainer.classList.remove('hidden');
        gridContainer.classList.add('hidden');
        listViewBtn.classList.add('bg-blue-600', 'text-white');
        listViewBtn.classList.remove('bg-gray-200', 'text-gray-600');
        gridViewBtn.classList.add('bg-gray-200', 'text-gray-600');
        gridViewBtn.classList.remove('bg-blue-600', 'text-white');
    }

    gridViewBtn.addEventListener('click', switchToGridView);
    listViewBtn.addEventListener('click', switchToListView);

    // Search functionality
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const moduleCards = document.querySelectorAll('.module-card');
        const moduleRows = document.querySelectorAll('.module-row');
        let visibleCountGrid = 0;
        let visibleCountList = 0;

        moduleCards.forEach(card => {
            const title = card.dataset.title || '';
            const description = card.dataset.description || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
                visibleCountGrid++;
            } else {
                card.style.display = 'none';
            }
        });

        moduleRows.forEach(row => {
            const title = row.dataset.title || '';
            const description = row.dataset.description || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                row.style.display = 'flex';
                visibleCountList++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        const isGridActive = !gridContainer.classList.contains('hidden');
        const isListActive = !listContainer.classList.contains('hidden');
        
        if ((isGridActive && visibleCountGrid === 0) || (isListActive && visibleCountList === 0)) {
            noResultsMessage.classList.remove('hidden');
        } else {
            noResultsMessage.classList.add('hidden');
        }
    }

    // Clear search function
    window.clearSearch = function() {
        searchInput.value = '';
        performSearch();
        searchInput.focus();
    };

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>