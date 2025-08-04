<?php
$page_title = 'Admin Dashboard';
require_once 'includes/auth_check.php';
require_once '../includes/db_connect.php';

// Fetch stats for dashboard cards
try {
    $total_users = $pdo->query("SELECT count(*) FROM users WHERE role = 'user'")->fetchColumn();
    $total_admins = $pdo->query("SELECT count(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $total_modules = $pdo->query("SELECT count(*) FROM modules")->fetchColumn();
    $passed_exams = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM final_assessments WHERE status = 'passed'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin Dashboard Stats Error: " . $e->getMessage());
    // Set defaults on error to prevent page crash
    $total_users = $total_admins = $total_modules = $passed_exams = 'N/A';
}

require_once 'includes/header.php';
?>

<style>
    .gradient-bg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .card-hover {
        transition: all 0.3s ease;
        transform: translateY(0);
    }
    
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .stat-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .chart-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .icon-gradient-blue {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }
    
    .icon-gradient-purple {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    }
    
    .icon-gradient-green {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .icon-gradient-orange {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .glassmorphism {
        backdrop-filter: blur(16px) saturate(180%);
        background-color: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(209, 213, 219, 0.3);
    }
</style>

<!-- Dashboard Content -->
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <div class="container mx-auto p-6">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">Admin Dashboard</h1>
                    <p class="text-gray-600">Welcome back! Here's what's happening with your training platform.</p>
                </div>
                <div class="hidden md:flex items-center space-x-3">
                    <div class="bg-white px-4 py-2 rounded-full shadow-sm border">
                        <span class="text-sm text-gray-600">Last updated: </span>
                        <span class="text-sm font-medium text-gray-800" id="lastUpdated"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card card-hover p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Users</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total_users ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-green-600 font-medium">↗ 12%</span>
                            <span class="text-xs text-gray-500 ml-1">vs last month</span>
                        </div>
                    </div>
                    <div class="icon-gradient-blue text-white p-4 rounded-2xl shadow-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197m0 0A10.99 10.99 0 0012 5.197a10.99 10.99 0 00-3-3.999z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card card-hover p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Training Modules</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total_modules ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-blue-600 font-medium">→ 0%</span>
                            <span class="text-xs text-gray-500 ml-1">no change</span>
                        </div>
                    </div>
                    <div class="icon-gradient-purple text-white p-4 rounded-2xl shadow-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card card-hover p-6 rounded-2xl shadow-lg pulse-animation">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Exam Completions</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $passed_exams ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-green-600 font-medium">↗ 23%</span>
                            <span class="text-xs text-gray-500 ml-1">vs last month</span>
                        </div>
                    </div>
                    <div class="icon-gradient-green text-white p-4 rounded-2xl shadow-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="stat-card card-hover p-6 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Administrators</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total_admins ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-orange-600 font-medium">↗ 5%</span>
                            <span class="text-xs text-gray-500 ml-1">vs last month</span>
                        </div>
                    </div>
                    <div class="icon-gradient-orange text-white p-4 rounded-2xl shadow-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6.364-6.364l-1.414 1.414M21 12h-2M12 3V1m-6.364 6.364L4.222 7.222M19.778 7.222l-1.414 1.414M12 21a9 9 0 110-18 9 9 0 010 18zM12 9v6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="space-y-8">
            <!-- Top Row Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="chart-container glassmorphism p-8 rounded-3xl shadow-xl card-hover">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Training Progress Overview</h3>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm text-gray-600">Live Data</span>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="overallProgressChart"></canvas>
                    </div>
                </div>

                <div class="chart-container glassmorphism p-8 rounded-3xl shadow-xl card-hover">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Monthly Activity Trends</h3>
                        <div class="flex items-center space-x-2">
                            <select class="text-sm border-0 bg-transparent text-gray-600 focus:ring-0">
                                <option>Last 6 months</option>
                                <option>Last 12 months</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="monthlyActivityChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Row Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="chart-container glassmorphism p-8 rounded-3xl shadow-xl card-hover">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Department Distribution</h3>
                        <div class="bg-blue-100 px-3 py-1 rounded-full">
                            <span class="text-xs font-medium text-blue-800">Active Users</span>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="usersByDepartmentChart"></canvas>
                    </div>
                </div>

                <div class="chart-container glassmorphism p-8 rounded-3xl shadow-xl card-hover">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Assessment Performance</h3>
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-xs text-gray-600">Passed</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                <span class="text-xs text-gray-600">Failed</span>
                            </div>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="assessmentDepartmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glassmorphism p-6 rounded-2xl shadow-lg card-hover">
                <h4 class="font-semibold text-gray-800 mb-3">Quick Actions</h4>
                <div class="space-y-3">
                    <button class="w-full text-left px-4 py-2 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <span class="text-sm font-medium text-blue-700">Add New User</span>
                    </button>
                    <button class="w-full text-left px-4 py-2 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <span class="text-sm font-medium text-green-700">Create Module</span>
                    </button>
                    <button class="w-full text-left px-4 py-2 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                        <span class="text-sm font-medium text-purple-700">Generate Report</span>
                    </button>
                </div>
            </div>

            <div class="glassmorphism p-6 rounded-2xl shadow-lg card-hover">
                <h4 class="font-semibold text-gray-800 mb-3">System Status</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Server Status</span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Online</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Database</span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Connected</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Last Backup</span>
                        <span class="text-xs text-gray-500">2 hours ago</span>
                    </div>
                </div>
            </div>

            <div class="glassmorphism p-6 rounded-2xl shadow-lg card-hover">
                <h4 class="font-semibold text-gray-800 mb-3">Recent Activity</h4>
                <div class="space-y-3">
                    <div class="text-sm">
                        <div class="font-medium text-gray-700">New user registered</div>
                        <div class="text-xs text-gray-500">5 minutes ago</div>
                    </div>
                    <div class="text-sm">
                        <div class="font-medium text-gray-700">Module completed</div>
                        <div class="text-xs text-gray-500">12 minutes ago</div>
                    </div>
                    <div class="text-sm">
                        <div class="font-medium text-gray-700">Assessment submitted</div>
                        <div class="text-xs text-gray-500">23 minutes ago</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Update timestamp
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
    
    // Chart.js default configuration
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 20;
    
    // Fetch data for charts from our API endpoint
    fetch('../api/admin/dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            if (!data || !data.signupData || !data.assessmentData) {
                console.error('Invalid data structure received from API.');
                return;
            }

            // Enhanced color palette
            const colorPalette = [
                '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', 
                '#ef4444', '#6366f1', '#ec4899', '#14b8a6'
            ];

            // --- Chart 1: Users by Department (Pie Chart) ---
            const signupCtx = document.getElementById('usersByDepartmentChart').getContext('2d');
            new Chart(signupCtx, {
                type: 'doughnut',
                data: {
                    labels: data.signupData.map(d => d.name),
                    datasets: [{
                        label: 'Users',
                        data: data.signupData.map(d => d.user_count),
                        backgroundColor: colorPalette,
                        borderWidth: 0,
                        cutout: '65%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // --- Chart 2: Assessment Results by Department (Bar Chart) ---
            const assessmentCtx = document.getElementById('assessmentDepartmentChart').getContext('2d');
            new Chart(assessmentCtx, {
                type: 'bar',
                data: {
                    labels: data.assessmentData.map(d => d.name),
                    datasets: [
                        {
                            label: 'Passed',
                            data: data.assessmentData.map(d => d.passed_count),
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: '#10b981',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false
                        },
                        {
                            label: 'Failed',
                            data: data.assessmentData.map(d => d.failed_count),
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: '#ef4444',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { padding: 20 }
                        }
                    }
                }
            });

            // --- Chart 3: Overall Training Progress (Doughnut Chart) ---
            const progressCtx = document.getElementById('overallProgressChart').getContext('2d');
            new Chart(progressCtx, {
                type: 'doughnut',
                data: {
                    labels: data.overallProgressData.labels,
                    datasets: [{
                        label: 'User Progress',
                        data: data.overallProgressData.data,
                        backgroundColor: ['#10b981', '#f59e0b', '#6b7280'],
                        borderWidth: 0,
                        cutout: '70%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // --- Chart 4: Monthly Assessment Completions (Area Chart) ---
            const monthlyCtx = document.getElementById('monthlyActivityChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: data.monthlyActivityData.labels,
                    datasets: [{
                        label: 'Assessments Completed',
                        data: data.monthlyActivityData.data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        })
        .catch(error => console.error('Error fetching dashboard data:', error));
});
</script>

<?php require_once 'includes/footer.php'; ?>