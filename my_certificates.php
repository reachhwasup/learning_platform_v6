<?php 
$page_title = 'My Certificates'; 
require_once 'includes/auth_check.php'; 
require_once 'includes/db_connect.php'; 

$user_id = $_SESSION['user_id']; 

// Fetch user's certificate data
try { 
    // FIX: Removed score and certificate_code from the query as they are no longer needed.
    $sql = "SELECT u.first_name, u.last_name, fa.completed_at 
            FROM certificates c 
            JOIN users u ON c.user_id = u.id 
            JOIN final_assessments fa ON c.assessment_id = fa.id 
            WHERE c.user_id = ? 
            ORDER BY fa.completed_at DESC LIMIT 1"; 
    $stmt = $pdo->prepare($sql); 
    $stmt->execute([$user_id]); 
    $certificate = $stmt->fetch(); 
} catch (PDOException $e) { 
    error_log("My Certificates Page Error: " . $e->getMessage()); 
    $certificate = null; 
} 

require_once 'includes/header.php'; 
?>

<style>
    .certificate-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 2rem 0;
    }
    
    .certificate-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        max-width: 900px;
        margin: 0 auto;
    }
    
    /* FIX: Reverted to a centered block layout */
    .certificate-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    
    .header-triangle-pattern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0.1;
        pointer-events: none;
        background-image: 
            linear-gradient(60deg, rgba(255,255,255,0.1) 25%, transparent 25.5%, transparent 75%, rgba(255,255,255,0.1) 75%, rgba(255,255,255,0.1)), 
            linear-gradient(120deg, rgba(255,255,255,0.1) 25%, transparent 25.5%, transparent 75%, rgba(255,255,255,0.1) 75%, rgba(255,255,255,0.1));
        background-size: 60px 104px;
        background-position: 0 0, 30px 52px;
    }
    
    /* FIX: Adjusted margin for centered layout */
    .certificate-logo {
        width: 140px;
        height: auto;
        margin: 0 auto 1.5rem; /* Centered with bottom margin */
    }
    
    .certificate-logo img {
        width: 100%;
        height: auto;
        object-fit: contain;
        filter: brightness(0) invert(1);
    }
    
    .certificate-body {
        padding: 3rem;
        text-align: center;
        position: relative;
        background: linear-gradient(to bottom, #f8faff 0%, #ffffff 100%);
        overflow: hidden;
    }
    
    .triangle-pattern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0.03;
        pointer-events: none;
        background-image: 
            linear-gradient(30deg, #1e3c72 12%, transparent 12.5%, transparent 87%, #1e3c72 87.5%, #1e3c72),
            linear-gradient(150deg, #1e3c72 12%, transparent 12.5%, transparent 87%, #1e3c72 87.5%, #1e3c72),
            linear-gradient(30deg, #1e3c72 12%, transparent 12.5%, transparent 87%, #1e3c72 87.5%, #1e3c72),
            linear-gradient(150deg, #1e3c72 12%, transparent 12.5%, transparent 87%, #1e3c72 87.5%, #1e3c72),
            linear-gradient(60deg, #2a5298 25%, transparent 25.5%, transparent 75%, #2a5298 75%, #2a5298), 
            linear-gradient(60deg, #2a5298 25%, transparent 25.5%, transparent 75%, #2a5298 75%, #2a5298);
        background-size: 80px 140px;
        background-position: 0 0, 0 0, 40px 70px, 40px 70px, 0 0, 40px 70px;
    }
    
    .certificate-ornament {
        position: absolute;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        width: 150px;
        height: 20px;
        background: linear-gradient(90deg, transparent 0%, #667eea 20%, #764ba2 50%, #667eea 80%, transparent 100%);
        border-radius: 10px;
        opacity: 0.3;
    }
    
    .certificate-title {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 2rem;
        letter-spacing: 2px;
    }
    
    .recipient-name {
        font-size: 3.5rem;
        font-weight: 900;
        color: #1e3c72;
        margin: 1.5rem 0;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        position: relative;
    }
    
    .recipient-name::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 200px;
        height: 3px;
        background: linear-gradient(90deg, transparent 0%, #667eea 50%, transparent 100%);
    }
    
    .course-title {
        font-size: 1.8rem;
        font-weight: 600;
        color: #2a5298;
        margin: 2rem 0;
        font-style: italic;
    }
    
    .certificate-details {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 2px solid #e5e7eb;
    }
    
    .detail-item {
        text-align: center;
    }
    
    .detail-label {
        font-size: 0.9rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
    }
    
    .detail-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e3c72;
    }
    
    .status-badge {
        display: inline-block;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }
    
    .download-section {
        padding: 2rem;
        background: #f9fafb;
        text-align: center;
        border-top: 1px solid #e5e7eb;
    }
    
    .download-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1rem 2rem;
        border-radius: 50px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
    }
    
    .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .no-certificate {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 20px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        max-width: 600px;
        margin: 0 auto;
    }
    
    .no-certificate-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 2rem;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
    }
    
    .floating-elements {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
    }
    
    .floating-elements::before,
    .floating-elements::after {
        content: '✦';
        position: absolute;
        color: #667eea;
        font-size: 2rem;
        opacity: 0.1;
        animation: float 6s ease-in-out infinite;
    }
    
    .floating-elements::before {
        top: 20%;
        left: 10%;
        animation-delay: 0s;
    }
    
    .floating-elements::after {
        top: 60%;
        right: 10%;
        animation-delay: 3s;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }
    
    @media (max-width: 768px) {
        /* FIX: No longer need specific header rules as it's now centered by default */
        .certificate-title { font-size: 2rem; }
        .recipient-name { font-size: 2.5rem; }
        .course-title { font-size: 1.4rem; }
        .certificate-details { grid-template-columns: 1fr; gap: 1.5rem; }
        .certificate-body { padding: 2rem 1rem; }
    }
</style>

<div class="certificate-container">
    <div class="container mx-auto px-4">
        <?php if ($certificate): ?>
            <div class="certificate-card">
                <div class="certificate-header">
                    <div class="header-triangle-pattern"></div>
                    <!-- FIX: Re-ordered for centered layout -->
                    <div>
                        <div class="certificate-logo">
                            <img src="assets/images/logo.png" alt="Organization Logo">
                        </div>
                        <h1 class="text-3xl font-bold mb-2">CERTIFICATE OF COMPLETION</h1>
                        <p class="text-xl opacity-90">Information Security Training Program</p>
                    </div>
                </div>
                
                <div class="certificate-body">
                    <div class="triangle-pattern"></div>
                    <div class="floating-elements"></div>
                    <div class="certificate-ornament"></div>
                    
                    <p class="text-lg text-gray-600 mb-4">This certificate is proudly presented to</p>
                    
                    <h2 class="recipient-name">
                        <?= htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']) ?>
                    </h2>
                    
                    <p class="text-lg text-gray-600 mb-4">for successfully completing the</p>
                    
                    <h3 class="course-title">Information Security Awareness Training</h3>
                    
                    <div class="certificate-details">
                        <div class="detail-item">
                            <div class="detail-label">Completion Date</div>
                            <div class="detail-value">
                                <?= date('F j, Y', strtotime($certificate['completed_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge">Passed</span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Authorized By</div>
                            <div class="detail-value">
                                <div style="border-top: 2px solid #1e3c72; padding-top: 8px; margin-bottom: 4px; width: 150px; margin: 0 auto;">
                                    <strong>Mr. Thol Lyna</strong>
                                </div>
                                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">
                                    Head of Information Technology
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="download-section">
                    <p class="text-gray-600 mb-4">Download your official certificate for your records</p>
                    <a href="api/user/generate_certificate.php" target="_blank" class="download-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download PDF Certificate
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="no-certificate">
                <div class="no-certificate-icon">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-4">No Certificate Available</h2>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    You haven't earned your certificate yet. Complete all training modules and pass the final assessment with a score of 80 points or higher to receive your official certificate.
                </p>
                <a href="final_assessment.php" class="download-btn">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    Take Final Assessment
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
