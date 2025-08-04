<?php
// 1. Authentication and Initialization
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

// 2. Input Validation
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    redirect('dashboard.php');
}
$module_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // 3. Fetch all modules to determine the correct order and previous/next module
    $all_modules_stmt = $pdo->query("SELECT id, module_order FROM modules ORDER BY module_order ASC");
    $all_modules = $all_modules_stmt->fetchAll();

    $current_module_index = -1;
    $next_module_id = null;

    foreach ($all_modules as $index => $mod) {
        if ($mod['id'] == $module_id) {
            $current_module_index = $index;
            if (isset($all_modules[$index + 1])) {
                $next_module_id = $all_modules[$index + 1]['id'];
            }
            break;
        }
    }

    if ($current_module_index === -1) {
        redirect('dashboard.php');
    }

    // 4. Fetch user's completed modules
    $stmt_progress = $pdo->prepare("SELECT module_id FROM user_progress WHERE user_id = :user_id");
    $stmt_progress->execute(['user_id' => $user_id]);
    $completed_modules = $stmt_progress->fetchAll(PDO::FETCH_COLUMN);

    // 5. Authorization Check
    $is_completed = in_array($module_id, $completed_modules);
    $is_locked = true;

    if ($current_module_index === 0) {
        $is_locked = false;
    } else {
        $previous_module_id = $all_modules[$current_module_index - 1]['id'];
        if (in_array($previous_module_id, $completed_modules)) {
            $is_locked = false;
        }
    }
    
    if ($is_completed) {
        $is_locked = false;
    }

    if ($is_locked) {
        redirect('dashboard.php');
    }

    // 6. Fetch module details
    $sql_module = "SELECT m.title, m.description, m.module_order, v.video_path 
                   FROM modules m
                   LEFT JOIN videos v ON m.id = v.module_id
                   WHERE m.id = :module_id";
    $stmt_module = $pdo->prepare($sql_module);
    $stmt_module->execute(['module_id' => $module_id]);
    $module = $stmt_module->fetch();

    // 7. Fetch 4 Random Quiz Questions
    $sql_questions = "SELECT q.id, q.question_text, q.question_type FROM questions q WHERE q.module_id = :module_id ORDER BY RAND() LIMIT 4";
    $stmt_questions = $pdo->prepare($sql_questions);
    $stmt_questions->execute(['module_id' => $module_id]);
    $questions = $stmt_questions->fetchAll();

    // 8. Fetch Randomized Options for each question
    $options = [];
    if (!empty($questions)) {
        $question_ids = array_column($questions, 'id');
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $sql_options = "SELECT id, question_id, option_text FROM question_options WHERE question_id IN ($placeholders) ORDER BY RAND()";
        $stmt_options = $pdo->prepare($sql_options);
        $stmt_options->execute($question_ids);
        $all_options_raw = $stmt_options->fetchAll();
        foreach($all_options_raw as $option) {
            $options[$option['question_id']][] = $option;
        }
    }
    
    // Determine the next step URL
    $next_step_url = $next_module_id ? "view_module.php?id=$next_module_id" : 'final_assessment.php';
    $next_step_text = $next_module_id ? 'Next Module' : 'Final Assessment';


} catch (PDOException $e) {
    error_log("View Module Error: " . $e->getMessage());
    die("An error occurred while loading the module. Please try again later.");
}

$page_title = 'Module ' . htmlspecialchars($module['module_order']) . ': ' . htmlspecialchars($module['title']);
require_once 'includes/header.php';
?>

<style>
    #video-player-container:fullscreen #custom-controls {
        opacity: 1;
    }
</style>

<div class="bg-white shadow-md rounded-lg p-6">
    <!-- Header with Back and Next buttons -->
    <div class="flex justify-between items-center pb-4 mb-6 border-b">
        <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 transition-colors flex items-center text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Back
        </a>
        <?php if ($is_completed): ?>
        <a href="<?= $next_step_url ?>" class="text-primary hover:text-primary-dark transition-colors flex items-center text-sm font-semibold">
            <?= $next_step_text ?>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
        </a>
        <?php endif; ?>
    </div>

    <!-- Module Title -->
    <h2 class="text-3xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($module['title']) ?></h2>
    <p class="text-gray-600 mb-6"><?= htmlspecialchars($module['description']) ?></p>


    <!-- Video Player Section -->
    <div id="video-container">
        <?php if (empty($module['video_path'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Video Not Found</p>
                <p>The video for this module has not been uploaded yet. Please contact an administrator.</p>
            </div>
        <?php else: ?>
            <div id="video-player-container" class="relative bg-black rounded-lg overflow-hidden">
                <video id="learning-video" class="w-full h-auto max-h-[70vh]" data-module-id="<?= $module_id ?>">
                    <source src="uploads/videos/<?= htmlspecialchars($module['video_path']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                
                <!-- Custom Controls -->
                <div id="custom-controls" class="absolute bottom-0 left-0 right-0 h-14 bg-black bg-opacity-60 text-white flex items-center px-4 opacity-0 transition-opacity duration-300">
                    <!-- Video player controls will be injected by JS if needed -->
                </div>
            </div>
            
            <div id="no-quiz-alert" class="hidden mt-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p class="font-bold">Module Complete!</p>
                <p>This module does not have a quiz. You can now proceed to the next step.</p>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Quiz Modal -->
<div id="quiz-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <form id="quiz-form">
                <input type="hidden" name="module_id" value="<?= $module_id ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-2xl leading-6 font-bold text-gray-900 text-center" id="quiz-modal-title">Module Test</h3>
                    <div class="mt-4" id="quiz-modal-body">
                        <?php if (!empty($questions)): ?>
                            <?php foreach ($questions as $q_index => $question): ?>
                                <div class="question-slide hidden" data-question-index="<?= $q_index ?>">
                                    <p class="font-semibold text-lg text-gray-800"><?= ($q_index + 1) . '. ' . htmlspecialchars($question['question_text']) ?></p>
                                    <div class="mt-4 space-y-2">
                                        <?php foreach ($options[$question['id']] as $option): ?>
                                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                                <input type="<?= $question['question_type'] === 'single' ? 'radio' : 'checkbox' ?>" 
                                                       name="answers[<?= $question['id'] ?>]<?= $question['question_type'] === 'multiple' ? '[]' : '' ?>" 
                                                       value="<?= $option['id'] ?>" 
                                                       class="h-5 w-5 text-primary focus:ring-primary border-gray-300">
                                                <span class="ml-3 text-gray-700"><?= htmlspecialchars($option['option_text']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-between items-center" id="quiz-modal-footer">
                    <div id="quiz-progress" class="text-sm text-gray-500"></div>
                    <div id="quiz-nav-buttons" class="flex items-center ml-auto">
                        <button type="button" id="quiz-prev-btn" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm">Previous</button>
                        <button type="button" id="quiz-next-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-dark ml-3 sm:w-auto sm:text-sm">Next</button>
                        <button type="submit" id="quiz-submit-btn" class="hidden w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 ml-3 sm:w-auto sm:text-sm">Submit</button>
                    </div>
                    <div id="quiz-result-buttons" class="hidden w-full sm:w-auto sm:flex sm:justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                        <button type="button" id="rewatch-btn" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm">Re-watch Video</button>
                        <button type="button" id="retake-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-500 text-base font-medium text-white hover:bg-yellow-600 sm:w-auto sm:text-sm">Re-take Quiz</button>
                        <a href="<?= $next_step_url ?>" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-dark sm:w-auto sm:text-sm">
                            <?= $next_step_text ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('learning-video');
    const noQuizAlert = document.getElementById('no-quiz-alert');
    const quizModal = document.getElementById('quiz-modal');
    const quizForm = document.getElementById('quiz-form');

    if (!video) return;

    // --- Video Player Controls ---
    const videoContainer = document.getElementById('video-player-container'); 
    const customControls = document.getElementById('custom-controls');
    
    const isModuleCompleted = <?php echo json_encode($is_completed); ?>;
    let seekHandler = null;
    let videoWatchingMode = !isModuleCompleted; // Track if user is in restricted watching mode

    function setupVideoControls() {
        if (!videoWatchingMode) {
            // Module completed or re-watching - enable full controls
            video.controls = true;
            customControls.style.display = 'none';
            if (seekHandler) {
                video.removeEventListener('timeupdate', seekHandler);
                seekHandler = null;
            }
        } else {
            // First time watching - restricted controls
            video.controls = false;
            customControls.style.display = 'flex';
            customControls.innerHTML = `
                <button id="play-pause-btn" class="p-2">
                    <svg id="play-icon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                    <svg id="pause-icon" class="w-6 h-6 hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>
                </button>
                <div class="flex items-center mx-2 group">
                    <button id="mute-btn" class="p-2">
                        <svg id="volume-high-icon" class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>
                        <svg id="volume-off-icon" class="w-6 h-6 hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L7 9.01V11h1.73l4.01-4.01L12 4z"></path></svg>
                    </button>
                    <input id="volume-slider" type="range" min="0" max="1" step="0.1" value="1" class="w-0 group-hover:w-24 transition-all duration-300">
                </div>
                <div class="text-sm ml-2">
                    <span id="current-time">00:00</span> / <span id="duration">00:00</span>
                </div>
                <div class="flex-grow"></div>
                <button id="fullscreen-btn" class="p-2">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"></path></svg>
                </button>
            `;
            
            setupCustomControls();
            setupSeekPrevention();
        }
    }

    function setupCustomControls() {
        const playPauseBtn = document.getElementById('play-pause-btn');
        const playIcon = document.getElementById('play-icon');
        const pauseIcon = document.getElementById('pause-icon');
        const muteBtn = document.getElementById('mute-btn');
        const volumeHighIcon = document.getElementById('volume-high-icon');
        const volumeOffIcon = document.getElementById('volume-off-icon');
        const volumeSlider = document.getElementById('volume-slider');
        const currentTimeEl = document.getElementById('current-time');
        const durationEl = document.getElementById('duration');
        const fullscreenBtn = document.getElementById('fullscreen-btn');

        if (!playPauseBtn) return; // Exit if elements not found

        videoContainer.addEventListener('mouseenter', () => { customControls.style.opacity = '1'; });
        videoContainer.addEventListener('mouseleave', () => { if (!video.paused) customControls.style.opacity = '0'; });
        
        const togglePlay = () => { video.paused ? video.play() : video.pause(); };
        playPauseBtn.addEventListener('click', togglePlay);
        video.addEventListener('click', togglePlay);
        video.addEventListener('play', () => { playIcon.classList.add('hidden'); pauseIcon.classList.remove('hidden'); });
        video.addEventListener('pause', () => { pauseIcon.classList.add('hidden'); playIcon.classList.remove('hidden'); customControls.style.opacity = '1'; });
        
        muteBtn.addEventListener('click', () => { video.muted = !video.muted; });
        video.addEventListener('volumechange', () => {
            volumeSlider.value = video.volume;
            if (video.muted || video.volume === 0) {
                volumeHighIcon.classList.add('hidden');
                volumeOffIcon.classList.remove('hidden');
            } else {
                volumeOffIcon.classList.add('hidden');
                volumeHighIcon.classList.remove('hidden');
            }
        });
        volumeSlider.addEventListener('input', (e) => { video.volume = e.target.value; video.muted = e.target.value == 0; });
        
        const formatTime = (timeInSeconds) => { 
            const result = new Date(timeInSeconds * 1000).toISOString().substr(14, 5); 
            return result; 
        };
        video.addEventListener('loadedmetadata', () => { if(video.duration) durationEl.textContent = formatTime(video.duration); });
        video.addEventListener('timeupdate', () => { currentTimeEl.textContent = formatTime(video.currentTime); });
        
        fullscreenBtn.addEventListener('click', () => {
            if (document.fullscreenElement) { 
                document.exitFullscreen(); 
            } else { 
                videoContainer.requestFullscreen().catch(err => alert(`Error: ${err.message}`)); 
            }
        });
    }

    function setupSeekPrevention() {
        const createSeekHandler = () => {
            let lastPlayedTime = 0;
            return () => {
                if (!video.seeking && (video.currentTime > lastPlayedTime + 1.5)) {
                    video.currentTime = lastPlayedTime;
                }
                lastPlayedTime = video.currentTime;
            };
        };
        seekHandler = createSeekHandler();
        video.addEventListener('timeupdate', seekHandler);
    }

    // Initialize video controls
    setupVideoControls();

    // --- Completion Logic ---
    let progressTracked = false;
    video.addEventListener('ended', () => {
        const wasAlreadyCompleted = <?php echo json_encode($is_completed); ?>;
        if (!progressTracked && !wasAlreadyCompleted) {
             const moduleId = video.dataset.moduleId;
             if (moduleId) {
                fetch('api/learning/track_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ module_id: moduleId })
                })
                .then(res => res.json())
                .then(data => { 
                    if (data.success) {
                        progressTracked = true;
                        videoWatchingMode = false; // Module completed, allow full controls
                        setupVideoControls(); // Update controls
                        const hasQuiz = <?php echo json_encode(!empty($questions)); ?>;
                        if (hasQuiz) {
                            startQuiz();
                        } else {
                            noQuizAlert.classList.remove('hidden');
                            setTimeout(() => location.reload(), 2000);
                        }
                    }
                });
             }
        } else {
            const hasQuiz = <?php echo json_encode(!empty($questions)); ?>;
            if (hasQuiz) {
                startQuiz();
            } else {
                noQuizAlert.classList.remove('hidden');
            }
        }
    });

    // --- Quiz Logic ---
    if (quizForm) {
        const questions = quizForm.querySelectorAll('.question-slide');
        const nextBtn = document.getElementById('quiz-next-btn');
        const prevBtn = document.getElementById('quiz-prev-btn');
        const submitBtn = document.getElementById('quiz-submit-btn');
        const progressText = document.getElementById('quiz-progress');
        const modalBody = document.getElementById('quiz-modal-body');
        const modalTitle = document.getElementById('quiz-modal-title');
        const quizNavButtons = document.getElementById('quiz-nav-buttons');
        const quizResultButtons = document.getElementById('quiz-result-buttons');
        let currentQuestion = 0;

function showQuestion(index) {
    const currentQuestions = modalBody.querySelectorAll('.question-slide'); // Get fresh references
    currentQuestions.forEach((q, i) => q.classList.toggle('hidden', i !== index));
    progressText.textContent = `Question ${index + 1} of ${currentQuestions.length}`;
    prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';
    nextBtn.style.display = index === currentQuestions.length - 1 ? 'none' : 'inline-flex';
    submitBtn.style.display = index === currentQuestions.length - 1 ? 'inline-flex' : 'none';
}

function resetQuizForm() {
    // Reset the form
    quizForm.reset();
    
    // Clear all radio button and checkbox selections in the entire modal
    const allInputs = document.querySelectorAll('#quiz-modal input[type="radio"], #quiz-modal input[type="checkbox"]');
    allInputs.forEach(input => {
        input.checked = false;
        input.removeAttribute('checked');
    });
    
    // Reset visual state of labels
    const allLabels = document.querySelectorAll('#quiz-modal label');
    allLabels.forEach(label => {
        label.classList.remove('bg-blue-50', 'border-blue-300', 'selected', 'bg-gray-50');
        label.style.backgroundColor = '';
        label.style.borderColor = '';
    });
}

function startQuiz() {
    currentQuestion = 0;
    
    // Reset modal content first
    modalTitle.textContent = 'Module Test';
    modalBody.innerHTML = '';
    
    // Re-add all question slides to modal body
    questions.forEach(q => {
        modalBody.appendChild(q.cloneNode(true)); // Use cloneNode to get fresh copies
    });
    
    // Get fresh references to the newly added questions
    const freshQuestions = modalBody.querySelectorAll('.question-slide');
    
    // Reset all form inputs in the fresh questions
    resetQuizForm();
    
    // Setup modal state
    quizNavButtons.style.display = 'flex';
    quizResultButtons.style.display = 'none';
    quizResultButtons.classList.add('hidden');
    
    // Show first question
    freshQuestions.forEach((q, i) => q.classList.toggle('hidden', i !== 0));
    progressText.textContent = `Question 1 of ${freshQuestions.length}`;
    prevBtn.style.display = 'none';
    nextBtn.style.display = freshQuestions.length > 1 ? 'inline-flex' : 'none';
    submitBtn.style.display = freshQuestions.length === 1 ? 'inline-flex' : 'none';
    
    quizModal.classList.remove('hidden');
}

nextBtn.addEventListener('click', () => {
    const currentQuestions = modalBody.querySelectorAll('.question-slide');
    if (currentQuestion < currentQuestions.length - 1) {
        currentQuestion++;
        showQuestion(currentQuestion);
    }
});

prevBtn.addEventListener('click', () => {
    if (currentQuestion > 0) {
        currentQuestion--;
        showQuestion(currentQuestion);
    }
});
        quizForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            
            fetch('api/learning/submit_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalTitle.textContent = 'Result';
                    modalBody.innerHTML = `
                        <div class="text-center p-8">
                            <h3 class="text-2xl font-bold text-gray-800">Quiz Completed!</h3>
                            <p class="mt-4 text-lg">Your answers are corrected <span class="font-bold text-green-600">${data.score ?? 0}</span> of ${questions.length} questions.</p>
                        </div>
                    `;
                    quizNavButtons.style.display = 'none';
                    quizResultButtons.style.display = 'flex';
                    quizResultButtons.classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to submit quiz.');
                }
            })
            .catch(error => {
                console.error('Quiz submission error:', error);
                alert('A server error occurred.');
            });
        });

        // Fixed Re-take Quiz button
        document.getElementById('retake-btn').addEventListener('click', function() {
            startQuiz(); // This now properly resets all quiz answers
        });

        // Fixed Re-watch Video button  
        document.getElementById('rewatch-btn').addEventListener('click', function() {
            // Clear quiz answers from server
            const formData = new FormData();
            formData.append('action', 'clear_answers');
            formData.append('module_id', '<?= $module_id ?>');
            
            fetch('api/learning/submit_quiz.php', { method: 'POST', body: formData })
                .then(() => {
                    // Close modal and enable full video controls for re-watching
                    quizModal.classList.add('hidden');
                    videoWatchingMode = false; // Allow seeking for re-watching
                    setupVideoControls(); // Update controls to allow seeking
                    video.currentTime = 0; // Reset video to beginning
                });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
