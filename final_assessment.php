<?php
$page_title = 'Final Assessment';
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$error = '';
$message = '';
$can_take_assessment = false;
$reason = '';
$show_results = false;
$result_data = [];

try {
    // 2. Check if user has completed all modules AND their corresponding quizzes
    $total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    
    // This query now calculates the number of fully completed modules.
    // A module is considered complete if the video is watched AND the quiz is taken (if one exists).
    $sql_completed_modules = "
        SELECT COUNT(DISTINCT up.module_id)
        FROM user_progress up
        WHERE up.user_id = :user_id AND (
            -- Condition 1: The module has no quiz, so watching the video is enough.
            (SELECT COUNT(*) FROM questions q WHERE q.module_id = up.module_id) = 0
            OR
            -- Condition 2: The module has a quiz, and the user has submitted answers for it.
            EXISTS (
                SELECT 1
                FROM user_answers ua
                JOIN questions q_ua ON ua.question_id = q_ua.id
                WHERE ua.user_id = up.user_id AND q_ua.module_id = up.module_id
            )
        )";
    $stmt_completed = $pdo->prepare($sql_completed_modules);
    $stmt_completed->execute([':user_id' => $user_id]);
    $completed_modules = $stmt_completed->fetchColumn();

    if ($total_modules > 0 && $completed_modules >= $total_modules) {
        $can_take_assessment = true;
    } else {
        $reason = "You must complete all learning modules and their quizzes before taking the final assessment.";
    }

    // 3. Check if user has already passed the assessment (but allow retaking if requested)
    $has_passed = false;
    if ($can_take_assessment) {
        $stmt_latest = $pdo->prepare("SELECT status FROM final_assessments WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1");
        $stmt_latest->execute([$user_id]);
        $latest = $stmt_latest->fetch();
        
        if ($latest && $latest['status'] === 'passed') {
            $has_passed = true;
            if (!isset($_GET['retake']) || $_GET['retake'] != '1') {
                $can_take_assessment = false;
                $reason = "Congratulations! You have already passed the final assessment.";
            }
        }
    }
    
    // 4. Initialize questions array and fetch questions if user is eligible
    $questions = [];
    $options = [];
    if ($can_take_assessment) {
        $sql_questions = "SELECT id, question_text, question_type FROM questions WHERE is_final_exam_question = 1 ORDER BY RAND() LIMIT 20";
        $stmt_questions = $pdo->query($sql_questions);
        $questions = $stmt_questions->fetchAll();

        if (!empty($questions)) {
            $question_ids = array_column($questions, 'id');
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $sql_options = "SELECT id, question_id, option_text FROM question_options WHERE question_id IN ($placeholders) ORDER BY RAND()";
            $stmt_options = $pdo->prepare($sql_options);
            $stmt_options->execute($question_ids);
            
            while ($row = $stmt_options->fetch()) {
                $options[$row['question_id']][] = $row;
            }
        }
    }
    
    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_take_assessment && !empty($questions)) {
        $submitted_answers = $_POST['answers'] ?? [];
        
        if (empty($submitted_answers)) {
            $error = "Please answer all questions before submitting.";
        } else {
            $question_ids = array_keys($submitted_answers);
            
            if (count($question_ids) < count($questions)) {
                 $error = "Please answer all " . count($questions) . " questions before submitting.";
            } else {
                // Process the answers
                $placeholders = rtrim(str_repeat('?,', count($question_ids)), ',');
                $sql = "SELECT qo.question_id, qo.id as answer_id FROM question_options qo WHERE qo.question_id IN ($placeholders) AND qo.is_correct = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($question_ids);
                $correct_options_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $correct_answers_map = [];
                foreach ($correct_options_raw as $opt) {
                    $correct_answers_map[$opt['question_id']][] = $opt['answer_id'];
                }

                $score = 0;
                $user_responses_to_save = [];
                
                foreach ($submitted_answers as $q_id => $user_selection) {
                    if (!is_array($user_selection)) {
                        $user_selection = [$user_selection];
                    }

                    $correct_selection = $correct_answers_map[$q_id] ?? [];
                    sort($user_selection);
                    sort($correct_selection);

                    $is_question_correct = ($user_selection == $correct_selection);
                    if ($is_question_correct) {
                        $score += 5; // 5 points per question
                    }

                    foreach ($user_selection as $selected_option_id) {
                        $user_responses_to_save[] = [
                            'question_id' => $q_id,
                            'selected_option_id' => $selected_option_id,
                            'is_correct' => $is_question_correct
                        ];
                    }
                }
    
                $status = ($score >= 80) ? 'passed' : 'failed';
    
                $pdo->beginTransaction();

                $sql_assessment = "INSERT INTO final_assessments (user_id, score, status) VALUES (?, ?, ?)";
                $stmt_assessment = $pdo->prepare($sql_assessment);
                $stmt_assessment->execute([$user_id, $score, $status]);
                $assessment_id = $pdo->lastInsertId();

                $sql_answers = "INSERT INTO user_answers (user_id, assessment_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?, ?)";
                $stmt_answers = $pdo->prepare($sql_answers);
                foreach ($user_responses_to_save as $response) {
                    $stmt_answers->execute([$user_id, $assessment_id, $response['question_id'], $response['selected_option_id'], $response['is_correct']]);
                }
                
                if ($status === 'passed') {
                    $cert_code = 'CERT-' . strtoupper(uniqid()) . '-' . $user_id;
                    $sql_cert = "INSERT INTO certificates (user_id, assessment_id, certificate_code) VALUES (?, ?, ?)";
                    $pdo->prepare($sql_cert)->execute([$user_id, $assessment_id, $cert_code]);
                }
                
                $pdo->commit();

                $show_results = true;
                $result_data = ['score' => $score, 'total_score' => count($questions) * 5, 'status' => $status];
                $can_take_assessment = false;
            }
        }
    }

} catch (PDOException $e) {
    if($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Final Assessment Page Error: " . $e->getMessage());
    die("An error occurred while loading the assessment. Please try again later.");
}

$page_title = 'Final Assessment';
require_once 'includes/header.php';
?>

<style>
.gradient-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card-shadow {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    transition: stroke-dashoffset 0.35s;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.animate-bounce-slow {
    animation: bounce 2s infinite;
}

.question-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.question-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.option-card {
    transition: all 0.2s ease;
    cursor: pointer;
}

.option-card:hover {
    transform: scale(1.02);
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.option-card.selected {
    border-color: #10b981;
    background-color: #d1fae5;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.pulse-animation {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style>

<div class="min-h-screen bg-gray-50">
    <!-- Header Section -->
    <div class="gradient-bg py-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-6">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                </svg>
            </div>
            <h1 class="text-5xl font-bold text-white mb-4">Final Assessment</h1>
            <p class="text-xl text-white/90 max-w-2xl mx-auto">Demonstrate your mastery of the course material and earn your certification</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 pb-16">
        
        <?php if ($error): ?>
            <!-- Enhanced Error Message -->
            <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg shadow-lg p-6 mb-8 animate-bounce-slow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-bold text-red-800">Assessment Error</h3>
                        <p class="text-red-700 mt-1"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_results): ?>
            <!-- Enhanced Results Display -->
            <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                <div class="<?= $result_data['status'] === 'passed' ? 'bg-green-500' : 'bg-red-500' ?> px-8 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                        <?php if ($result_data['status'] === 'passed'): ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-4xl font-bold text-white mb-4">
                        <?= $result_data['status'] === 'passed' ? 'Congratulations!' : 'Keep Trying!' ?>
                    </h2>
                    <p class="text-xl text-white/90">
                        <?= $result_data['status'] === 'passed' ? 'You have successfully passed the assessment!' : 'You can retake the assessment to improve your score.' ?>
                    </p>
                </div>
                
                <div class="p-8">
                    <!-- Score Display -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center">
                            <div class="relative w-32 h-32">
                                <svg class="w-32 h-32 progress-ring">
                                    <circle cx="64" cy="64" r="56" stroke="#e5e7eb" stroke-width="12" fill="transparent"></circle>
                                    <circle cx="64" cy="64" r="56" stroke="<?= $result_data['status'] === 'passed' ? '#10b981' : '#ef4444' ?>" 
                                            stroke-width="12" fill="transparent" 
                                            stroke-dasharray="<?= 2 * 3.14159 * 56 ?>" 
                                            stroke-dashoffset="<?= 2 * 3.14159 * 56 * (1 - ($result_data['score'] / $result_data['total_score'])) ?>"
                                            class="progress-ring-circle"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-gray-800"><?= (int)$result_data['score'] ?></div>
                                        <div class="text-sm text-gray-600">/ <?= (int)$result_data['total_score'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-lg text-gray-600 mt-4">
                            Score: <?= round(($result_data['score'] / $result_data['total_score']) * 100, 1) ?>% 
                            <span class="text-sm">(Passing: 80%)</span>
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if ($result_data['status'] === 'passed'): ?>
                            <a href="my_certificates.php" class="inline-flex items-center justify-center px-8 py-4 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                                View Certificate
                            </a>
                        <?php else: ?>
                            <a href="final_assessment.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Try Again
                            </a>
                            <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300 transition-all duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif (!$can_take_assessment): ?>
            <!-- Enhanced Not Eligible Message -->
            <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-8 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 rounded-full mb-6">
                        <?php if ($has_passed): ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-4xl font-bold text-white mb-4">Assessment Status</h2>
                    <p class="text-xl text-white/90"><?= htmlspecialchars($reason) ?></p>
                </div>
                
                <div class="p-8 text-center">
                    <?php if ($has_passed): ?>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="my_certificates.php" class="inline-flex items-center justify-center px-8 py-4 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                                View My Certificate
                            </a>
                            <form method="GET" action="final_assessment.php" class="inline">
                                <input type="hidden" name="retake" value="1">
                                <button type="submit" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Retake Assessment
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-200 transform hover:scale-105 shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Back to Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif (empty($questions)): ?>
            <!-- No Questions Available -->
            <div class="bg-white rounded-3xl card-shadow p-8 text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Assessment Not Ready</h2>
                <p class="text-xl text-gray-600 mb-8">The final assessment is not available yet. Please contact an administrator.</p>
                <a href="dashboard.php" class="inline-flex items-center justify-center px-8 py-4 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-all duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

        <?php else: ?>
            <!-- Enhanced Assessment Quiz Form -->
            <div class="bg-white rounded-3xl card-shadow overflow-hidden">
                <!-- Quiz Header -->
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-8 py-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold text-white mb-2">Ready to Begin?</h2>
                            <p class="text-white/90">Complete all <?= count($questions) ?> questions to earn your certification</p>
                        </div>
                        <div class="text-right">
                            <div class="bg-white/20 rounded-lg px-4 py-2">
                                <div class="text-white font-semibold">Questions</div>
                                <div class="text-2xl font-bold text-white"><?= count($questions) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($has_passed): ?>
                    <!-- Already Passed Notice -->
                    <div class="bg-green-50 border-l-4 border-green-400 p-6 m-6 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    <strong>Assessment Already Passed!</strong> 
                                    You can retake this assessment if you want to improve your score. Your current certificate will remain valid.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Assessment Instructions -->
                <div class="px-8 py-6 bg-blue-50 border-b">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-blue-900 mb-2">Assessment Instructions</h3>
                            <ul class="text-blue-800 space-y-1 text-sm">
                                <li>• Answer all <?= count($questions) ?> questions to complete the assessment</li>
                                <li>• You need 80% or higher to pass (<?= ceil(count($questions) * 0.8) ?> correct answers)</li>
                                <li>• Each question is worth 5 points</li>
                                <li>• Take your time and read each question carefully</li>
                                <li>• You can retake the assessment if you don't pass</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <form id="assessment-form" method="POST" action="final_assessment.php" class="p-8">
                    <!-- Progress Bar -->
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Progress</span>
                            <span class="text-sm font-medium text-gray-700"><span id="progress-count">0</span> / <?= count($questions) ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <?php foreach ($questions as $q_index => $question): ?>
                            <div class="question-card bg-white border-2 border-gray-200 rounded-2xl p-6 shadow-sm" data-question-id="<?= $question['id'] ?>">
                                <!-- Question Header -->
                                <div class="flex items-start space-x-4 mb-6">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                            <span class="text-white font-bold text-sm"><?= $q_index + 1 ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?= $question['question_type'] === 'single' ? 'Single Choice' : 'Multiple Choice' ?>
                                            </span>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-900 leading-relaxed">
                                            <?= htmlspecialchars($question['question_text']) ?>
                                        </h3>
                                    </div>
                                </div>

                                <!-- Answer Options -->
                                <div class="space-y-3 ml-14">
                                    <?php if (!empty($options[$question['id']])): ?>
                                        <?php foreach ($options[$question['id']] as $option_index => $option): ?>
                                            <label class="option-card block p-4 border-2 border-gray-200 rounded-xl bg-gray-50">
                                                <div class="flex items-center space-x-4">
                                                    <input type="<?= $question['question_type'] === 'single' ? 'radio' : 'checkbox' ?>" 
                                                           name="answers[<?= $question['id'] ?>]<?= $question['question_type'] === 'multiple' ? '[]' : '' ?>" 
                                                           value="<?= $option['id'] ?>" 
                                                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 focus:ring-2 border-gray-300 rounded"
                                                           onchange="updateProgress()">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 bg-white border-2 border-gray-300 rounded-full flex items-center justify-center text-sm font-semibold text-gray-600">
                                                            <?= chr(65 + $option_index) ?>
                                                        </div>
                                                    </div>
                                                    <span class="text-gray-800 font-medium flex-1"><?= htmlspecialchars($option['option_text']) ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Submit Section -->
                    <div class="mt-12 bg-gradient-to-r from-gray-50 to-blue-50 rounded-2xl p-8 text-center">
                        <div class="max-w-md mx-auto">
                            <h3 class="text-2xl font-bold text-gray-800 mb-4">Ready to Submit?</h3>
                            <p class="text-gray-600 mb-6">Make sure you've answered all questions before submitting your assessment.</p>
                            
                            <button type="submit" id="submit-btn" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-4 px-8 rounded-xl hover:from-green-600 hover:to-green-700 transition-all duration-200 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                                <div class="flex items-center justify-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    <span><?= $has_passed ? 'Retake Assessment' : 'Submit Final Assessment' ?></span>
                                </div>
                            </button>
                            
                            <?php if ($has_passed): ?>
                                <div class="mt-4">
                                    <a href="my_certificates.php" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                        Or view your current certificate →
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced option selection with visual feedback
    const options = document.querySelectorAll('.option-card');
    const progressBar = document.getElementById('progress-bar');
    const progressCount = document.getElementById('progress-count');
    const submitBtn = document.getElementById('submit-btn');
    const totalQuestions = <?= count($questions) ?>;

    options.forEach(option => {
        option.addEventListener('click', function() {
            const input = this.querySelector('input');
            const questionCard = this.closest('.question-card');
            
            if (input.type === 'radio') {
                // Remove selected class from all options in this question
                questionCard.querySelectorAll('.option-card').forEach(opt => {
                    opt.classList.remove('selected');
                });
            }
            
            // Toggle selection
            if (input.checked || input.type === 'radio') {
                this.classList.add('selected');
                if (input.type === 'radio') {
                    input.checked = true;
                }
            } else {
                this.classList.remove('selected');
            }
            
            updateProgress();
        });
    });

    function updateProgress() {
        const questions = document.querySelectorAll('.question-card');
        let answeredQuestions = 0;
        
        questions.forEach(question => {
            const inputs = question.querySelectorAll('input[type="radio"], input[type="checkbox"]');
            const hasAnswer = Array.from(inputs).some(input => input.checked);
            
            if (hasAnswer) {
                answeredQuestions++;
                question.style.borderColor = '#10b981';
                question.style.backgroundColor = '#f0fdf4';
            } else {
                question.style.borderColor = '#e5e7eb';
                question.style.backgroundColor = '#ffffff';
            }
        });
        
        const progressPercentage = (answeredQuestions / totalQuestions) * 100;
        progressBar.style.width = progressPercentage + '%';
        progressCount.textContent = answeredQuestions;
        
        // Enable/disable submit button
        if (answeredQuestions === totalQuestions) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            submitBtn.classList.add('pulse-animation');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.classList.remove('pulse-animation');
        }
    }

    // Form submission with confirmation
    document.getElementById('assessment-form').addEventListener('submit', function(e) {
        const answeredQuestions = document.getElementById('progress-count').textContent;
        
        if (parseInt(answeredQuestions) < totalQuestions) {
            e.preventDefault();
            alert('Please answer all questions before submitting.');
            return;
        }
        
        if (!confirm('Are you sure you want to submit your assessment? This action cannot be undone.')) {
            e.preventDefault();
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = `
            <div class="flex items-center justify-center space-x-2">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Submitting...</span>
            </div>
        `;
        submitBtn.disabled = true;
    });

    // Smooth scrolling for question navigation
    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach((card, index) => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.option-card')) {
                // Clicked on question card but not on an option
                const firstUnanswered = card.querySelector('input:not(:checked)');
                if (firstUnanswered) {
                    firstUnanswered.focus();
                }
            }
        });
    });

    // Initialize progress
    updateProgress();
});
</script>

<?php
require_once 'includes/footer.php';
?>
