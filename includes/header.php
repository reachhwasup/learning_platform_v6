<?php
require_once 'functions.php';

// --- Determine the correct profile picture path ---
$profile_pic_path = 'assets/images/default_avatar.jpg';
if (isset($_SESSION['user_profile_picture']) && $_SESSION['user_profile_picture'] !== 'default_avatar.jpg' && $_SESSION['user_profile_picture'] !== 'default_avatar.png') {
    $user_pic = 'uploads/profile_pictures/' . $_SESSION['user_profile_picture'];
    if (file_exists($user_pic)) {
        $profile_pic_path = $user_pic;
    }
}

// Get current page for breadcrumb
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_names = [
    'dashboard' => 'Dashboard',
    'posters' => 'Monthly Posters',
    'materials' => 'Learning Materials',
    'my_certificates' => 'My Certificates',
    'profile' => 'My Profile',
    'final_assessment' => 'Final Assessment'
];
$current_page_name = $page_names[$current_page] ?? ucfirst(str_replace('_', ' ', $current_page));

// Check for unread notifications (example - you'll need to implement based on your notification system)
$notification_count = 0;
try {
    if (isset($_SESSION['user_id']) && isset($pdo)) {
        // Example query - adjust based on your notification table structure
        // $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        // $stmt->execute([$_SESSION['user_id']]);
        // $notification_count = $stmt->fetchColumn();
        
        // For demo purposes, showing 3 notifications
        $notification_count = 3;
    }
} catch (Exception $e) {
    error_log("Notification count error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Security Awareness Platform' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        'primary': '#0052cc', 
                        'primary-dark': '#0041a3', 
                        'secondary': '#f4f5f7', 
                        'accent': '#ffab00',
                        'gradient-start': '#667eea',
                        'gradient-end': '#764ba2'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-down': 'slideDown 0.3s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounceGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        .glass-effect {
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.95);
        }
        .notification-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .search-modal {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-gray-50 to-blue-50">
<div class="flex h-screen">
    <?php require_once 'user_sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <!-- Enhanced Header -->
        <header class="glass-effect border-b border-gray-200 z-20 relative shadow-sm">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    
                    <!-- Left Section: Mobile Menu + Icon + Title + Breadcrumb -->
                    <div class="flex items-center space-x-4 flex-1">
                        <!-- Mobile Menu Button -->
                        <button id="mobile-menu-button" class="md:hidden p-2 rounded-xl text-gray-600 hover:bg-white hover:shadow-md transition-all duration-200 hover:scale-105">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        
                        <!-- Page Icon + Title & Breadcrumb -->
                        <div class="flex items-center space-x-3">
                            <!-- Dynamic Page Icon -->
                            <div class="flex-shrink-0">
                                <?php
                                $page_icons = [
                                    'dashboard' => '<svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
                                    'posters' => '<svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l-1.586-1.586a2 2 0 010-2.828L16 8M4 16V8a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2z"></path></svg>',
                                    'materials' => '<svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>',
                                    'my_certificates' => '<svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>',
                                    'profile' => '<svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
                                    'final_assessment' => '<svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>'
                                ];
                                echo $page_icons[$current_page] ?? '<svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                                ?>
                            </div>
                            
                            <!-- Title & Breadcrumb -->
                            <div class="flex flex-col">
                                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                                    <?= isset($page_title) ? htmlspecialchars($page_title) : $current_page_name ?>
                                </h1>
                                <nav class="hidden sm:flex items-center space-x-2 text-sm text-gray-500">
                                    <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Home</a>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <span class="text-gray-900 font-medium"><?= $current_page_name ?></span>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- Right Section: Actions + Profile -->
                    <div class="flex items-center space-x-3">
                        
                        <!-- Search Button (Desktop) -->
                        <button id="search-button" class="hidden lg:flex p-2 rounded-xl text-gray-500 hover:bg-white hover:text-gray-700 hover:shadow-md transition-all duration-200 group">
                            <svg class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>

                        <!-- Notifications -->
                        <div class="relative">
                            <button id="notifications-button" class="p-2 rounded-xl text-gray-500 hover:bg-white hover:text-gray-700 hover:shadow-md transition-all duration-200 group relative">
                                <svg class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-5-5.917V5a2 2 0 10-4 0v.083A6 6 0 004 11v3.159c0 .538-.214 1.055-.595 1.436L2 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                <!-- Notification Badge -->
                                <?php if ($notification_count > 0): ?>
                                <span class="absolute -top-1 -right-1 flex h-5 w-5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs items-center justify-center font-medium shadow-lg">
                                        <?= $notification_count > 9 ? '9+' : $notification_count ?>
                                    </span>
                                </span>
                                <?php endif; ?>
                            </button>
                            
                            <!-- Notifications Dropdown -->
                            <div id="notifications-dropdown" class="hidden absolute right-0 mt-3 w-80 origin-top-right rounded-2xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none z-50 animate-slide-down border">
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                                        <?php if ($notification_count > 0): ?>
                                        <button class="text-sm text-blue-600 hover:text-blue-700 font-medium">Mark all read</button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($notification_count > 0): ?>
                                    <div class="space-y-3 max-h-64 overflow-y-auto">
                                        <!-- Sample notifications - replace with actual data -->
                                        <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl border border-blue-200">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">New learning material available</p>
                                                <p class="text-xs text-gray-500">Check out the latest cybersecurity module</p>
                                                <p class="text-xs text-blue-600 mt-1">2 hours ago</p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 rounded-xl border border-yellow-200">
                                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">Assessment reminder</p>
                                                <p class="text-xs text-gray-500">You're eligible to take the final assessment</p>
                                                <p class="text-xs text-yellow-600 mt-1">1 day ago</p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-start space-x-3 p-3 bg-green-50 rounded-xl border border-green-200">
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">Module completed!</p>
                                                <p class="text-xs text-gray-500">Great job on completing "Password Security"</p>
                                                <p class="text-xs text-green-600 mt-1">3 days ago</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-3 border-t border-gray-100">
                                        <button class="w-full text-center text-sm text-gray-600 hover:text-gray-900 font-medium">View all notifications</button>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-8">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-5-5.917V5a2 2 0 10-4 0v.083A6 6 0 004 11v3.159c0 .538-.214 1.055-.595 1.436L2 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">No new notifications</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Theme Toggle (Desktop) -->
                        <button id="theme-toggle" class="hidden md:flex p-2 rounded-xl text-gray-500 hover:bg-white hover:text-gray-700 hover:shadow-md transition-all duration-200 group">
                            <svg class="h-5 w-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                        </button>

                        <!-- Divider (Desktop) -->
                        <div class="hidden md:block w-px h-6 bg-gray-300"></div>

                        <!-- Profile Dropdown -->
                        <div class="relative" id="profile-dropdown-container">
                            <button id="profile-dropdown-button" class="flex items-center space-x-3 p-2 rounded-xl bg-white shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <!-- User Info (Hidden on Mobile) -->
                                <div class="hidden lg:block text-right">
                                    <p class="text-sm font-semibold text-gray-900 leading-tight">
                                        <?= htmlspecialchars(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? 'User')) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 leading-tight">
                                        <?= htmlspecialchars($_SESSION['user_position'] ?? 'Team Member') ?>
                                    </p>
                                </div>
                                <!-- Profile Picture -->
                                <div class="relative">
                                    <img class="h-10 w-10 rounded-full object-cover border-2 border-white shadow-sm" 
                                         src="<?= htmlspecialchars($profile_pic_path) ?>" 
                                         alt="Profile Picture">
                                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></div>
                                </div>
                                <!-- Dropdown Arrow -->
                                <svg class="h-4 w-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="profile-dropdown-menu" class="hidden absolute right-0 mt-3 w-64 origin-top-right rounded-2xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none z-50 animate-slide-down border" role="menu">
                                <div class="p-1" role="none">
                                    <!-- User Info Header -->
                                    <div class="px-4 py-4 border-b border-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <img class="h-12 w-12 rounded-full object-cover" 
                                                 src="<?= htmlspecialchars($profile_pic_path) ?>" 
                                                 alt="Profile Picture">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    <?= htmlspecialchars(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? '')) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($_SESSION['user_position'] ?? 'Team Member') ?>
                                                </p>
                                                <div class="flex items-center mt-1">
                                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                                    <span class="text-xs text-green-600 font-medium">Online</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Menu Items -->
                                    <div class="py-2">
                                        <a href="profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3 group-hover:bg-blue-200 transition-colors">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">My Profile</p>
                                                <p class="text-xs text-gray-500">Manage your account</p>
                                            </div>
                                        </a>
                                        
                                        <a href="my_certificates.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center mr-3 group-hover:bg-yellow-200 transition-colors">
                                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">My Certificates</p>
                                                <p class="text-xs text-gray-500">View achievements</p>
                                            </div>
                                        </a>

                                        <a href="#" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center mr-3 group-hover:bg-green-200 transition-colors">
                                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">Get Help</p>
                                                <p class="text-xs text-gray-500">Support & documentation</p>
                                            </div>
                                        </a>

                                        <!-- Settings -->
                                        <a href="#" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center mr-3 group-hover:bg-gray-200 transition-colors">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">Settings</p>
                                                <p class="text-xs text-gray-500">Preferences & privacy</p>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Logout Section -->
                                    <div class="border-t border-gray-100 py-2">
                                        <a href="api/auth/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 rounded-xl mx-2 transition-all duration-200 group" role="menuitem">
                                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center mr-3 group-hover:bg-red-200 transition-colors">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium">Sign Out</p>
                                                <p class="text-xs text-gray-500">End your session</p>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Search Modal -->
        <div id="search-modal" class="hidden fixed inset-0 z-50 search-modal">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="glass-effect rounded-2xl shadow-2xl w-full max-w-2xl border border-white/20">
                    <div class="p-6">
                        <div class="flex items-center space-x-4 mb-6">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" id="search-input" placeholder="Search for materials, assessments, certificates..." 
                                   class="flex-1 bg-transparent border-none outline-none text-lg text-gray-900 placeholder-gray-500">
                            <button id="close-search" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Quick Links -->
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-gray-600 mb-3">Quick Access</p>
                            <a href="materials.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Learning Materials</p>
                                    <p class="text-sm text-gray-500">Browse all training modules</p>
                                </div>
                            </a>
                            
                            <a href="final_assessment.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">Final Assessment</p>
                                    <p class="text-sm text-gray-500">Take your certification exam</p>
                                </div>
                            </a>
                            
                            <a href="my_certificates.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                                <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center group-hover:bg-yellow-200 transition-colors">
                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">My Certificates</p>
                                    <p class="text-sm text-gray-500">View your achievements</p>
                                </div>
                            </a>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <p class="text-xs text-gray-500">Press <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">Esc</kbd> to close</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-blue-50">
            <!-- Page content will be inserted here by individual pages -->

<script>
// Enhanced Header Script
document.addEventListener('DOMContentLoaded', function() {
    const profileDropdownButton = document.getElementById('profile-dropdown-button');
    const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
    const profileDropdownContainer = document.getElementById('profile-dropdown-container');
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const notificationsButton = document.getElementById('notifications-button');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const searchButton = document.getElementById('search-button');
    const searchModal = document.getElementById('search-modal');
    const searchInput = document.getElementById('search-input');
    const closeSearch = document.getElementById('close-search');
    const themeToggle = document.getElementById('theme-toggle');

    // Profile dropdown functionality
    if (profileDropdownButton) {
        profileDropdownButton.addEventListener('click', function(event) {
            event.stopPropagation();
            profileDropdownMenu.classList.toggle('hidden');
            
            // Close other dropdowns
            if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
            
            // Rotate arrow
            const arrow = this.querySelector('svg:last-child');
            if (arrow) {
                arrow.style.transform = profileDropdownMenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    }

    // Notifications dropdown functionality
    if (notificationsButton) {
        notificationsButton.addEventListener('click', function(event) {
            event.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            if (profileDropdownMenu) profileDropdownMenu.classList.add('hidden');
        });
    }

    // Search modal functionality
    if (searchButton) {
        searchButton.addEventListener('click', function() {
            searchModal.classList.remove('hidden');
            setTimeout(() => searchInput.focus(), 100);
        });
    }

    if (closeSearch) {
        closeSearch.addEventListener('click', function() {
            searchModal.classList.add('hidden');
        });
    }

    // Theme toggle functionality
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            // Add your theme toggle logic here
            document.body.classList.toggle('dark');
            
            // Update icon
            const icon = this.querySelector('svg');
            if (document.body.classList.contains('dark')) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>';
            } else {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>';
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (profileDropdownContainer && !profileDropdownContainer.contains(event.target)) {
            profileDropdownMenu.classList.add('hidden');
            const arrow = profileDropdownButton?.querySelector('svg:last-child');
            if (arrow) {
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        if (notificationsDropdown && !notificationsButton.contains(event.target) && !notificationsDropdown.contains(event.target)) {
            notificationsDropdown.classList.add('hidden');
        }
    });

    // Mobile menu functionality
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function() {
            const sidebar = document.getElementById('user-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (sidebar && overlay) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key functionality
        if (e.key === 'Escape') {
            // Close search modal
            if (!searchModal.classList.contains('hidden')) {
                searchModal.classList.add('hidden');
            }
            // Close profile dropdown
            if (!profileDropdownMenu.classList.contains('hidden')) {
                profileDropdownMenu.classList.add('hidden');
                const arrow = profileDropdownButton?.querySelector('svg:last-child');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }
            }
            // Close notifications dropdown
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) {
                notificationsDropdown.classList.add('hidden');
            }
        }

        // Ctrl/Cmd + K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchModal) {
                searchModal.classList.remove('hidden');
                setTimeout(() => searchInput.focus(), 100);
            }
        }
    });

    // Search modal background click to close
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) {
                searchModal.classList.add('hidden');
            }
        });
    }

    // Add smooth animations to interactive elements
    const interactiveElements = document.querySelectorAll('button, a');
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            if (this.classList.contains('hover:scale-105')) {
                this.style.transform = 'scale(1.05)';
            }
        });
        
        element.addEventListener('mouseleave', function() {
            if (this.classList.contains('hover:scale-105')) {
                this.style.transform = 'scale(1)';
            }
        });
    });
});
</script>