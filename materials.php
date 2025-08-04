<?php
$page_title = 'Download Materials';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch all modules that have a PDF material attached
    $sql_materials = "SELECT id, title, description, module_order, pdf_material_path 
                      FROM modules 
                      WHERE pdf_material_path IS NOT NULL AND pdf_material_path != ''
                      ORDER BY module_order ASC";
    $stmt_materials = $pdo->query($sql_materials);
    $materials = $stmt_materials->fetchAll();

    // Fetch completed modules for the user
    $stmt_progress = $pdo->prepare("SELECT module_id FROM user_progress WHERE user_id = ?");
    $stmt_progress->execute([$user_id]);
    $completed_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Materials Page Error: " . $e->getMessage());
    $materials = [];
    $completed_modules = [];
}

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-16">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-6">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h1 class="text-5xl font-bold mb-4">Training Materials</h1>
                <p class="text-xl text-blue-100 max-w-2xl mx-auto">
                    Access and download supplementary PDF materials for completed modules to enhance your learning experience.
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-12">
        <?php if (empty($materials)): ?>
            <!-- Empty State -->
            <div class="max-w-md mx-auto text-center">
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Materials Available</h3>
                    <p class="text-gray-600">Training materials will appear here once they're added to the modules.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Filter/Sort Options -->
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm font-medium text-gray-700">Filter:</span>
                            <select class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="materialFilter">
                                <option value="all">All Materials</option>
                                <option value="available">Available for Download</option>
                                <option value="locked">Locked</option>
                            </select>
                        </div>
                        <div class="text-sm text-gray-600">
                            Showing <span id="materialCount"><?= count($materials) ?></span> materials
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="materialsGrid">
                <?php foreach ($materials as $material): ?>
                    <?php
                        $can_download = in_array($material['id'], $completed_modules);
                        $status_class = $can_download ? 'available' : 'locked';
                    ?>
                    <!-- Material Card -->
                    <div class="material-card bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden group <?= $status_class ?>" data-status="<?= $status_class ?>">
                        <!-- Status Badge -->
                        <div class="relative">
                            <div class="absolute top-4 right-4 z-10">
                                <?php if ($can_download): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Available
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Locked
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Module Header -->
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-8 text-white">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-blue-100 text-sm font-medium">Module <?= escape($material['module_order']) ?></span>
                                </div>
                                <h3 class="text-xl font-bold leading-tight">
                                    <?= escape($material['title']) ?>
                                </h3>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6">
                            <p class="text-gray-600 text-sm leading-relaxed mb-6 line-clamp-3">
                                <?= escape($material['description']) ?>
                            </p>

                            <!-- Action Button -->
                            <div class="mt-auto">
                                <?php if ($can_download): ?>
                                    <a href="download.php?module_id=<?= escape($material['id']) ?>" 
                                       class="group/btn w-full inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                        <svg class="w-5 h-5 mr-2 group-hover/btn:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Download PDF
                                    </a>
                                <?php else: ?>
                                    <div class="w-full">
                                        <button disabled class="w-full flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-500 font-semibold rounded-lg cursor-not-allowed border-2 border-dashed border-gray-300">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6.364-6.364l-1.414 1.414M21 12h-2M12 3V1m-6.364 6.364L4.222 7.222M19.778 7.222l-1.414 1.414M12 21a9 9 0 110-18 9 9 0 010 18zM12 9v6"></path>
                                            </svg>
                                            Complete Module First
                                        </button>
                                        <p class="text-xs text-gray-500 text-center mt-2">
                                            Finish Module <?= escape($material['module_order']) ?> to unlock
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for filtering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('materialFilter');
    const materialsGrid = document.getElementById('materialsGrid');
    const materialCount = document.getElementById('materialCount');
    
    if (filterSelect && materialsGrid) {
        filterSelect.addEventListener('change', function() {
            const filterValue = this.value;
            const cards = materialsGrid.querySelectorAll('.material-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const status = card.dataset.status;
                let shouldShow = false;
                
                switch(filterValue) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'available':
                        shouldShow = status === 'available';
                        break;
                    case 'locked':
                        shouldShow = status === 'locked';
                        break;
                }
                
                if (shouldShow) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (materialCount) {
                materialCount.textContent = visibleCount;
            }
        });
    }
});
</script>

<style>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.material-card {
    transition: all 0.3s ease;
}

.material-card:hover {
    transform: translateY(-4px);
}

@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0,-8px,0);
    }
    70% {
        transform: translate3d(0,-4px,0);
    }
    90% {
        transform: translate3d(0,-2px,0);
    }
}
</style>

<?php
require_once 'includes/footer.php';
?>