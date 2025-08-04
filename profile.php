<?php
$page_title = 'My Profile';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

try {
    // Fetch current user data
    $stmt_user = $pdo->prepare(
        "SELECT u.first_name, u.last_name, u.username, u.staff_id, u.gender, u.position, u.phone_number, u.profile_picture, d.name as department_name 
         FROM users u
         LEFT JOIN departments d ON u.department_id = d.id
         WHERE u.id = ?"
    );
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();

    if (!$user) {
        // If user somehow doesn't exist, log them out.
        redirect('api/auth/logout.php');
    }

    // Check if the user has passed the final assessment to display the badge
    $stmt_badge = $pdo->prepare("SELECT id FROM final_assessments WHERE user_id = ? AND status = 'passed' LIMIT 1");
    $stmt_badge->execute([$user_id]);
    $has_passed = $stmt_badge->fetch();

    // --- Determine the correct profile picture path ---
    $profile_picture_filename = $user['profile_picture'] ?? null;
    // Check if a specific user picture exists and is not the default name
    if ($profile_picture_filename && $profile_picture_filename !== 'default_avatar.jpg' && file_exists('uploads/profile_pictures/' . $profile_picture_filename)) {
        $profile_picture_path = 'uploads/profile_pictures/' . htmlspecialchars($profile_picture_filename);
    } else {
        // Otherwise, use the default avatar
        $profile_picture_path = 'assets/images/default_avatar.jpg';
    }

} catch (PDOException $e) {
    error_log("Profile Page Error: " . $e->getMessage());
    die("An error occurred while loading your profile.");
}

require_once 'includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <!-- Cover Background -->
            <div class="h-32 sm:h-40 bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 relative">
                <div class="absolute inset-0 bg-black/10"></div>
            </div>
            
            <!-- Profile Info -->
            <div class="relative px-6 pb-8 -mt-16">
                <div class="flex flex-col sm:flex-row items-center sm:items-end space-y-4 sm:space-y-0 sm:space-x-6">
                    <!-- Profile Picture -->
                    <div class="relative group">
                        <img id="profile-pic-display" 
                             src="<?= $profile_picture_path ?>" 
                             alt="Profile Picture" 
                             class="w-32 h-32 rounded-full border-4 border-white shadow-xl object-cover bg-white group-hover:scale-105 transition-transform duration-300" 
                             onerror="this.src='assets/images/default_avatar.jpg'">
                        
                        <!-- Upload Overlay -->
                        <form id="picture-form" class="absolute inset-0">
                            <label for="profile_picture" class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </label>
                            <input type="file" name="profile_picture" id="profile_picture" class="hidden" accept="image/*">
                        </form>
                        
                        <!-- Online Status -->
                        <div class="absolute bottom-2 right-2 w-6 h-6 bg-green-400 border-3 border-white rounded-full shadow-lg"></div>
                    </div>
                    
                    <!-- User Info -->
                    <div class="text-center sm:text-left flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 mb-3">
                            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2 sm:mb-0">
                                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                            </h1>
                            <?php if ($has_passed): ?>
                                <div class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a.75.75 0 00-1.06-1.06L9 10.94l-1.72-1.72a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.06 0l4.25-4.25z" clip-rule="evenodd" />
                                    </svg>
                                    Certified
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-lg text-gray-600 font-medium mb-1"><?= htmlspecialchars($user['position'] ?? 'Team Member') ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['department_name'] ?? 'No Department') ?></p>
                        
                        <!-- Status Message -->
                        <div id="picture-feedback" class="mt-3 text-sm"></div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex space-x-3">
                        <button class="hidden sm:flex items-center px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Personal Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                    <div class="flex items-center mb-8">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900">Personal Information</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">First Name</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['first_name'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Username</label>
                                <p class="text-lg text-gray-900 font-medium">
                                    <span class="bg-gray-100 px-3 py-1 rounded-lg font-mono">@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Department</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['department_name'] ?? 'Not assigned') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Gender</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['gender'] ?? 'Not specified') ?></p>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Last Name</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['last_name'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Staff ID</label>
                                <p class="text-lg text-gray-900 font-medium">
                                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-lg font-mono"><?= htmlspecialchars($user['staff_id'] ?? 'N/A') ?></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Phone Number</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['phone_number'] ?? 'Not provided') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Position</label>
                                <p class="text-lg text-gray-900 font-medium"><?= htmlspecialchars($user['position'] ?? 'Not specified') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Stats -->
            <div class="space-y-6">
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h4 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        Quick Actions
                    </h4>
                    <div class="space-y-3">
                        <a href="my_certificates.php" class="flex items-center p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-xl hover:from-yellow-100 hover:to-yellow-200 transition-all duration-200 group">
                            <div class="w-10 h-10 bg-yellow-200 rounded-lg flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">My Certificates</p>
                                <p class="text-sm text-gray-600">View achievements</p>
                            </div>
                        </a>
                        
                        <a href="materials.php" class="flex items-center p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-xl hover:from-green-100 hover:to-green-200 transition-all duration-200 group">
                            <div class="w-10 h-10 bg-green-200 rounded-lg flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Learning Materials</p>
                                <p class="text-sm text-gray-600">Continue learning</p>
                            </div>
                        </a>
                        
                        <a href="dashboard.php" class="flex items-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl hover:from-blue-100 hover:to-blue-200 transition-all duration-200 group">
                            <div class="w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Dashboard</p>
                                <p class="text-sm text-gray-600">Back to overview</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Account Actions -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                    <h4 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        Account
                    </h4>
                    <div class="space-y-3">
                        <button class="w-full flex items-center p-4 bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all duration-200 group">
                            <div class="w-10 h-10 bg-indigo-200 rounded-lg flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-semibold text-gray-900">Edit Profile</p>
                                <p class="text-sm text-gray-600">Update information</p>
                            </div>
                        </button>
                        
                        <a href="api/auth/logout.php" class="w-full flex items-center p-4 bg-gradient-to-r from-red-50 to-red-100 rounded-xl hover:from-red-100 hover:to-red-200 transition-all duration-200 group">
                            <div class="w-10 h-10 bg-red-200 rounded-lg flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="font-semibold text-gray-900">Sign Out</p>
                                <p class="text-sm text-gray-600">End your session</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pictureInput = document.getElementById('profile_picture');

    // Handle profile picture change automatically on file selection
    pictureInput.addEventListener('change', function() {
        if (pictureInput.files.length > 0) {
            const formData = new FormData();
            formData.append('profile_picture', pictureInput.files[0]);
            formData.append('action', 'change_picture');
            submitFormData(formData, 'picture-feedback', (data) => {
                // Update image on success
                if (data.success && data.new_path) {
                    document.getElementById('profile-pic-display').src = data.new_path + '?t=' + new Date().getTime();
                }
            });
        }
    });

    function submitFormData(formData, feedbackDivId, callback) {
        const feedbackDiv = document.getElementById(feedbackDivId);
        feedbackDiv.innerHTML = `
            <div class="flex items-center justify-center space-x-2 text-blue-600">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Uploading...</span>
            </div>
        `;
        feedbackDiv.className = 'mt-3 text-sm animate-fade-in';

        // This path assumes the API endpoint is in api/user/
        fetch('api/user/update_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                feedbackDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-green-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>${data.message}</span>
                    </div>
                `;
                feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            } else {
                feedbackDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-red-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>${data.message}</span>
                    </div>
                `;
                feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            }
            
            if (data.success && callback) {
                callback(data);
            }
            
            // Clear feedback after 3 seconds
            setTimeout(() => {
                feedbackDiv.innerHTML = '';
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            feedbackDiv.innerHTML = `
                <div class="flex items-center justify-center space-x-2 text-red-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Upload failed. Please try again.</span>
                </div>
            `;
            feedbackDiv.className = 'mt-3 text-sm animate-fade-in';
            
            // Clear feedback after 3 seconds
            setTimeout(() => {
                feedbackDiv.innerHTML = '';
            }, 3000);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>