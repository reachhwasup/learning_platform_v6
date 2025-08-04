<?php
/**
 * Enhanced User Panel Sidebar - Fixed Version
 */

// Check user progress for sidebar display
$sidebar_total_modules = 0;
$sidebar_completed_modules = 0;
$sidebar_can_take_assessment = false;
$sidebar_has_passed = false;

try {
    if (isset($_SESSION['user_id'])) {
        // Get total modules and user progress
        $sidebar_total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $stmt_sidebar_progress = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ?");
        $stmt_sidebar_progress->execute([$_SESSION['user_id']]);
        $sidebar_completed_modules = $stmt_sidebar_progress->fetchColumn();
        
        // Check if user can take assessment
        $sidebar_can_take_assessment = ($sidebar_total_modules > 0 && $sidebar_completed_modules >= $sidebar_total_modules);
        
        // Check if user has already passed
        $stmt_sidebar_passed = $pdo->prepare("SELECT id FROM final_assessments WHERE user_id = ? AND status = 'passed' LIMIT 1");
        $stmt_sidebar_passed->execute([$_SESSION['user_id']]);
        $sidebar_has_passed = $stmt_sidebar_passed->fetch() ? true : false;
    }
} catch (Exception $e) {
    // Silently handle errors for sidebar
    error_log("Sidebar assessment check error: " . $e->getMessage());
}
?>

<style>
.sidebar-gradient {
    background: linear-gradient(180deg, #0052cc 0%, #003d99 50%, #002d73 100%);
}
.nav-item {
    position: relative;
    overflow: hidden;
}
.nav-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}
.nav-item:hover::before {
    left: 100%;
}
.active-nav {
    background: rgba(255, 255, 255, 0.1);
    border-left: 4px solid #fbbf24;
    box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.1);
}
.nav-badge {
    animation: pulse 2s infinite;
}
.locked-nav {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.locked-nav:hover {
    background: none !important;
}
.passed-nav {
    background: rgba(34, 197, 94, 0.1);
    border-left: 4px solid #22c55e;
}
.progress-ring {
    transform: rotate(-90deg);
}
.progress-ring-circle {
    transition: stroke-dashoffset 0.35s;
    transform-origin: 50% 50%;
}
.assessment-section {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>

<!-- Sidebar -->
<aside id="user-sidebar" class="fixed inset-y-0 left-0 z-30 w-64 flex-shrink-0 sidebar-gradient text-white flex flex-col transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl">
    
    <!-- Header Section -->
    <div class="h-20 flex items-center justify-between px-4 border-b border-white border-opacity-20 bg-black bg-opacity-20">
        <a href="dashboard.php" class="flex-grow flex items-center justify-center group">
            <div class="relative">
                <img src="assets/images/logo.png" alt="Company Logo" class="h-12 transition-transform duration-300 group-hover:scale-110 drop-shadow-lg"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <!-- Fallback logo -->
                <div class="h-12 w-12 bg-gradient-to-br from-blue-400 to-purple-600 rounded-xl flex items-center justify-center shadow-lg" style="display: none;">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <div class="absolute -inset-2 bg-white bg-opacity-10 rounded-full blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>
        </a>
        <!-- Mobile Close Button -->
        <button id="sidebar-close-button" class="md:hidden p-2 rounded-lg text-white hover:bg-white hover:bg-opacity-20 transition-all duration-200 hover:rotate-90">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- User Info Section -->
    <div class="px-4 py-6 border-b border-white border-opacity-10">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                    <?= strtoupper(substr($_SESSION['user_first_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">
                    <?= htmlspecialchars(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? 'User')) ?>
                </p>
                <p class="text-xs text-blue-200 truncate">
                    <?= htmlspecialchars($_SESSION['user_position'] ?? 'Team Member') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Progress Summary (if modules exist) -->
    <?php if ($sidebar_total_modules > 0): ?>
    <div class="px-4 py-4 border-b border-white border-opacity-10 bg-black bg-opacity-10">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-blue-200 uppercase tracking-wide">Learning Progress</p>
                <p class="text-sm text-white font-medium">
                    <?= $sidebar_completed_modules ?> / <?= $sidebar_total_modules ?> Modules
                </p>
            </div>
            <div class="relative w-10 h-10">
                <?php 
                $progress_percentage = $sidebar_total_modules > 0 ? ($sidebar_completed_modules / $sidebar_total_modules) * 100 : 0;
                $circumference = 2 * 3.14159 * 15; // radius = 15
                $stroke_dashoffset = $circumference - ($progress_percentage / 100) * $circumference;
                ?>
                <svg class="progress-ring w-10 h-10" width="40" height="40">
                    <circle class="text-white text-opacity-20" stroke-width="3" stroke="currentColor" fill="transparent" r="15" cx="20" cy="20"/>
                    <circle class="progress-ring-circle text-green-400" stroke-width="3" stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $stroke_dashoffset ?>" stroke-linecap="round" stroke="currentColor" fill="transparent" r="15" cx="20" cy="20"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-xs font-bold text-white"><?= round($progress_percentage) ?>%</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Section -->
    <nav class="flex-1 px-3 py-6 space-y-2 overflow-y-auto">
        <div class="mb-4">
            <p class="px-3 text-xs font-semibold text-blue-200 uppercase tracking-wider">Main Menu</p>
        </div>

        <!-- Dashboard -->
        <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 rounded-xl hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active-nav' : '' ?>">
            <div class="w-8 h-8 rounded-lg bg-blue-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
            <span class="font-medium group-hover:translate-x-1 transition-transform duration-300">Dashboard</span>
            <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
                <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
            <?php endif; ?>
        </a>

        <!-- Monthly Posters -->
        <a href="posters.php" class="nav-item flex items-center px-4 py-3 rounded-xl hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'posters.php' ? 'active-nav' : '' ?>">
            <div class="w-8 h-8 rounded-lg bg-purple-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l-1.586-1.586a2 2 0 010-2.828L16 8M4 16V8a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <span class="font-medium group-hover:translate-x-1 transition-transform duration-300">Monthly Posters</span>
            <?php if (basename($_SERVER['PHP_SELF']) == 'posters.php'): ?>
                <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
            <?php endif; ?>
        </a>

        <!-- Learning Materials -->
        <a href="materials.php" class="nav-item flex items-center px-4 py-3 rounded-xl hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'materials.php' ? 'active-nav' : '' ?>">
            <div class="w-8 h-8 rounded-lg bg-green-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <span class="font-medium group-hover:translate-x-1 transition-transform duration-300">Learning Materials</span>
            <?php if (basename($_SERVER['PHP_SELF']) == 'materials.php'): ?>
                <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
            <?php endif; ?>
        </a>

        <!-- Divider -->
        <div class="my-6 border-t border-white border-opacity-10"></div>

        <!-- Assessment Section -->
        <div class="assessment-section p-3 mx-1">
            <div class="mb-3">
                <p class="px-2 text-xs font-semibold text-blue-200 uppercase tracking-wider flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                    Assessment
                </p>
            </div>

            <div class="space-y-2">
                <!-- Final Assessment -->
                <?php
                $assessment_classes = "nav-item flex items-center px-3 py-3 rounded-lg transition-all duration-300 group";
                $assessment_link = "final_assessment.php";
                
                if (!$sidebar_can_take_assessment) {
                    $assessment_classes .= " locked-nav";
                    $assessment_link = "#";
                } elseif ($sidebar_has_passed) {
                    $assessment_classes .= " passed-nav hover:bg-green-500 hover:bg-opacity-20";
                } else {
                    $assessment_classes .= " hover:bg-white hover:bg-opacity-10";
                }
                
                if (basename($_SERVER['PHP_SELF']) == 'final_assessment.php') {
                    $assessment_classes .= " active-nav";
                }
                ?>
                
                <div class="relative group">
                    <a href="<?= $assessment_link ?>" class="<?= $assessment_classes ?>" 
                       <?= !$sidebar_can_take_assessment ? 'onclick="showAssessmentTooltip(event)"' : '' ?>>
                        <div class="w-7 h-7 rounded-lg <?= $sidebar_has_passed ? 'bg-green-500' : ($sidebar_can_take_assessment ? 'bg-orange-500' : 'bg-gray-500') ?> bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300 relative flex-shrink-0">
                            <?php if ($sidebar_has_passed): ?>
                                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                                </svg>
                            <?php elseif ($sidebar_can_take_assessment): ?>
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-sm <?= !$sidebar_can_take_assessment ? '' : 'group-hover:translate-x-1' ?> transition-transform duration-300 block">
                                Final Assessment
                            </span>
                            <?php if ($sidebar_has_passed): ?>
                                <div class="text-xs text-green-400 mt-1">Passed ✓ Can retake</div>
                            <?php elseif (!$sidebar_can_take_assessment): ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    Complete <?= $sidebar_total_modules - $sidebar_completed_modules ?> more module<?= ($sidebar_total_modules - $sidebar_completed_modules) > 1 ? 's' : '' ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-orange-300 mt-1">Ready to take!</div>
                            <?php endif; ?>
                        </div>
                        <?php if (basename($_SERVER['PHP_SELF']) == 'final_assessment.php'): ?>
                            <div class="ml-2 w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Tooltip for locked assessment -->
                    <?php if (!$sidebar_can_take_assessment): ?>
                    <div id="assessment-tooltip" class="hidden absolute left-full top-0 ml-2 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap z-50 shadow-lg">
                        Complete all <?= $sidebar_total_modules ?> learning modules first
                        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 -translate-x-1 w-2 h-2 bg-gray-900 rotate-45"></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- My Certificates -->
                <a href="my_certificates.php" class="nav-item flex items-center px-3 py-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'my_certificates.php' ? 'active-nav' : '' ?>">
                    <div class="w-7 h-7 rounded-lg bg-yellow-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300 flex-shrink-0">
                        <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-sm group-hover:translate-x-1 transition-transform duration-300 block">My Certificates</span>
                        <div class="text-xs text-yellow-300 mt-1">View & download</div>
                    </div>
                    <?php if (basename($_SERVER['PHP_SELF']) == 'my_certificates.php'): ?>
                        <div class="ml-2 w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Mobile Profile Link -->
        <a href="profile.php" class="md:hidden nav-item flex items-center px-4 py-3 rounded-xl hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active-nav' : '' ?>">
            <div class="w-8 h-8 rounded-lg bg-indigo-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <span class="font-medium group-hover:translate-x-1 transition-transform duration-300">My Profile</span>
            <?php if (basename($_SERVER['PHP_SELF']) == 'profile.php'): ?>
                <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
            <?php endif; ?>
        </a>

        <!-- Divider -->
        <div class="my-6 border-t border-white border-opacity-10"></div>

        <!-- Quick Actions Section -->
        <div class="mb-4">
            <p class="px-3 text-xs font-semibold text-blue-200 uppercase tracking-wider">Quick Actions</p>
        </div>

        <!-- Help & Support -->
        <a href="help_support.php" class="nav-item flex items-center px-4 py-3 rounded-xl hover:bg-white hover:bg-opacity-10 transition-all duration-300 group <?= basename($_SERVER['PHP_SELF']) == 'help_support.php' ? 'active-nav' : '' ?>">
            <div class="w-8 h-8 rounded-lg bg-red-500 bg-opacity-20 flex items-center justify-center mr-3 group-hover:bg-opacity-30 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <span class="font-medium group-hover:translate-x-1 transition-transform duration-300">Help & Support</span>
            <?php if (basename($_SERVER['PHP_SELF']) == 'help_support.php'): ?>
                <div class="ml-auto w-2 h-2 bg-yellow-400 rounded-full nav-badge"></div>
            <?php endif; ?>
        </a>
    </nav>

    <!-- Footer Section -->
    <div class="px-4 py-4 border-t border-white border-opacity-10 bg-black bg-opacity-20">
        <div class="flex items-center justify-between text-xs text-blue-200">
            <span>© 2025 Security Platform</span>
            <div class="flex items-center space-x-1">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span>Online</span>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black bg-opacity-50 md:hidden hidden transition-opacity duration-200"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('user-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const sidebarCloseButton = document.getElementById('sidebar-close-button');

    // Mobile menu toggle
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
    }

    // Close sidebar
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    if (sidebarCloseButton) {
        sidebarCloseButton.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            closeSidebar();
        }
    });

    // Add smooth scrolling to nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Add click ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'absolute inset-0 bg-white bg-opacity-20 rounded-xl transform scale-0 transition-transform duration-300';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.classList.add('scale-100');
                setTimeout(() => {
                    ripple.remove();
                }, 300);
            }, 10);
        });
    });
});

// Function to show assessment tooltip
function showAssessmentTooltip(event) {
    event.preventDefault();
    const tooltip = document.getElementById('assessment-tooltip');
    if (tooltip) {
        tooltip.classList.remove('hidden');
        setTimeout(() => {
            tooltip.classList.add('hidden');
        }, 3000);
    }
}
</script>