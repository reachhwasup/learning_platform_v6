<?php
$page_title = 'Monthly Posters';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

// --- Date Filtering Logic ---
$filter_year = isset($_GET['year']) && !empty($_GET['year']) ? (int)$_GET['year'] : null;
$filter_month = isset($_GET['month']) && !empty($_GET['month']) ? (int)$_GET['month'] : null;

try {
    // Base SQL query
    $sql = "SELECT title, description, image_path, assigned_month 
            FROM monthly_posters";
    
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

    $sql .= " ORDER BY assigned_month DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posters = $stmt->fetchAll();

    // Fetch all unique years that have posters, for the filter dropdown
    $years_stmt = $pdo->query("SELECT DISTINCT YEAR(assigned_month) as year FROM monthly_posters ORDER BY year DESC");
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Posters Page Error: " . $e->getMessage());
    $posters = [];
    $available_years = [];
}

require_once 'includes/header.php';
?>

<style>
.poster-card {
    transition: all 0.3s ease;
}
.poster-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}
.filter-dropdown {
    transition: all 0.2s ease;
}
.filter-dropdown:focus {
    transform: scale(1.02);
}
.poster-image {
    transition: transform 0.3s ease;
}
.poster-card:hover .poster-image {
    transform: scale(1.05);
}
.gradient-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.glass-effect {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}
</style>

<!-- Hero Section -->
<div class="gradient-bg text-white py-16 mb-12">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-5xl md:text-6xl font-bold mb-6 leading-tight">
                Monthly Security
                <span class="text-yellow-300">Posters</span>
            </h1>
            <p class="text-xl md:text-2xl text-blue-100 mb-8 leading-relaxed">
                Stay informed with our curated collection of security awareness content designed to keep you and your team protected
            </p>
        </div>
    </div>
</div>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    <!-- Enhanced Filter Section -->
    <div class="glass-effect rounded-2xl shadow-xl p-6 mb-12 border border-white border-opacity-20">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Filter Posters</h2>
            <p class="text-gray-600">Find specific posters by month and year</p>
        </div>
        
        <form id="filter-form" method="GET" action="posters.php" class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-6">
            <div class="relative">
                <label for="month-select" class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                <div class="relative">
                    <select name="month" id="month-select" class="filter-dropdown appearance-none bg-white border border-gray-300 rounded-lg px-4 py-3 pr-10 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none min-w-[150px]">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($m == $filter_month) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="relative">
                <label for="year-select" class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                <div class="relative">
                    <select name="year" id="year-select" class="filter-dropdown appearance-none bg-white border border-gray-300 rounded-lg px-4 py-3 pr-10 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none min-w-[120px]">
                        <option value="">All Years</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?= $year ?>" <?= ($year == $filter_year) ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6 sm:mt-8">
                <button type="button" id="clear-filters" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Clear
                </button>
                <div class="bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span id="results-count"><?= count($posters) ?> Results</span>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($posters)): ?>
        <div class="text-center py-20">
            <div class="max-w-md mx-auto">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-12 border border-blue-200">
                    <svg class="mx-auto h-16 w-16 text-blue-400 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Posters Found</h3>
                    <p class="text-gray-600 mb-6">No posters match your current search criteria. Try adjusting your filters or check back later for new content.</p>
                    <button id="clear-all-filters" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200">
                        View All Posters
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-12 max-w-5xl mx-auto">
            <?php foreach ($posters as $index => $poster): ?>
                <div class="poster-card bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="md:flex">
                        <!-- Image Section -->
                        <div class="md:w-1/2">
                            <div class="relative overflow-hidden h-64 md:h-full">
                                <img src="uploads/posters/<?= htmlspecialchars($poster['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($poster['title']) ?>" 
                                     class="poster-image w-full h-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <!-- Fallback if image fails to load -->
                                <div class="absolute inset-0 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-2xl" style="display:none;">
                                    Security Poster
                                </div>
                                <!-- Date Badge -->
                                <div class="absolute top-4 right-4 bg-white bg-opacity-90 rounded-full px-4 py-2 shadow-lg">
                                    <div class="text-sm font-bold text-gray-800">
                                        <?= $poster['assigned_month'] ? date('M', strtotime($poster['assigned_month'])) : 'N/A' ?>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?= $poster['assigned_month'] ? date('Y', strtotime($poster['assigned_month'])) : '' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content Section -->
                        <div class="md:w-1/2 p-8">
                            <div class="flex items-start justify-between mb-6">
                                <div>
                                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                                        <?= htmlspecialchars($poster['title']) ?>
                                    </h3>
                                    <div class="flex items-center text-blue-600 font-semibold">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <?= $poster['assigned_month'] ? date('F Y', strtotime($poster['assigned_month'])) : 'Unassigned' ?>
                                    </div>
                                </div>
                                <div class="bg-blue-100 rounded-full p-3">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <div class="prose max-w-none text-gray-700 space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-blue-500">
                                    <p class="font-semibold text-gray-800 mb-2">Dear Respective Managements and Colleagues,</p>
                                    <p class="leading-relaxed"><?= nl2br(htmlspecialchars($poster['description'])) ?></p>
                                </div>
                                
                                <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-400">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.316 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-yellow-800 mb-1">Security Reminder:</p>
                                            <p class="text-yellow-700">Make information security a part of your daily routine to stay one step ahead. Protect your data, stay aware, and stay safe!</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-right border-t border-gray-200 pt-4">
                                    <p class="text-gray-600">
                                        <span class="font-semibold">Best Regards,</span><br>
                                        <span class="text-blue-600 font-semibold">Information Security Team</span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-3 mt-6">
                                <button onclick="downloadPoster('<?= htmlspecialchars($poster['image_path']) ?>', '<?= htmlspecialchars($poster['title']) ?>')" 
                                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Download
                                </button>
                                <button onclick="sharePoster('<?= htmlspecialchars($poster['title']) ?>')" 
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"></path>
                                    </svg>
                                    Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filter-form');
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const clearAllFiltersBtn = document.getElementById('clear-all-filters');
    const resultsCount = document.getElementById('results-count');

    function autoSubmitForm() {
        // Add loading state
        resultsCount.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading...';
        filterForm.submit();
    }

    function clearFilters() {
        monthSelect.value = '';
        yearSelect.value = '';
        autoSubmitForm();
    }

    monthSelect.addEventListener('change', autoSubmitForm);
    yearSelect.addEventListener('change', autoSubmitForm);
    clearFiltersBtn.addEventListener('click', clearFilters);
    
    if (clearAllFiltersBtn) {
        clearAllFiltersBtn.addEventListener('click', clearFilters);
    }

    // Smooth scroll animation for poster cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Apply animation to poster cards
    document.querySelectorAll('.poster-card').forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(50px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });
});

// Download poster function
function downloadPoster(imagePath, title) {
    const link = document.createElement('a');
    link.href = 'uploads/posters/' + imagePath;
    link.download = title + '.jpg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Share poster function
function sharePoster(title) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: 'Check out this security awareness poster: ' + title,
            url: window.location.href
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        const url = window.location.href;
        const text = 'Check out this security awareness poster: ' + title + ' - ' + url;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Link copied to clipboard!');
            });
        } else {
            prompt('Copy this link to share:', text);
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>