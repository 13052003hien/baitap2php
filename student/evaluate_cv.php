<?php
require '../config/database.php';
require '../config/ai_config.php';
session_start();

// Check if composer autoloader exists
$autoloader = __DIR__ . '/../vendor/autoload.php';
$hasPdfParser = false;

if (file_exists($autoloader)) {
    require_once $autoloader;
    $hasPdfParser = class_exists('Smalot\PdfParser\Parser');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

function evaluateCV($content) {
    $score = 0;
    $feedback = [];
    
    // Check CV length
    $wordCount = str_word_count($content);
    if ($wordCount < 200) {
        $feedback[] = "CV is too short (Current: $wordCount words). Aim for at least 300 words.";
    } else if ($wordCount > 1000) {
        $feedback[] = "CV might be too long (Current: $wordCount words). Consider making it more concise.";
    }
    $score += min(30, ($wordCount / 300) * 30);

    // Check for key sections
    $sections = [
        'education' => ['education', 'academic', 'university', 'school', 'degree'],
        'experience' => ['experience', 'work', 'internship', 'project'],
        'skills' => ['skills', 'competencies', 'proficiency', 'expertise'],
        'contact' => ['phone', 'email', 'address', 'linkedin'],
        'achievements' => ['achievement', 'award', 'certification', 'honor']
    ];

    foreach ($sections as $section => $keywords) {
        $sectionFound = false;
        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $sectionFound = true;
                break;
            }
        }
        if ($sectionFound) {
            $score += 10;
        } else {
            $feedback[] = "Missing $section section. Consider adding this to strengthen your CV.";
        }
    }

    // Check for dates
    if (preg_match_all('/\b\d{4}\b/', $content, $matches)) {
        $score += 10;
    } else {
        $feedback[] = "Add specific dates for your experiences and education.";
    }

    // Final score calculation
    $finalScore = min(100, $score);
    $grade = $finalScore >= 90 ? 'Excellent' : ($finalScore >= 70 ? 'Good' : ($finalScore >= 50 ? 'Fair' : 'Needs Improvement'));

    return [
        'score' => $finalScore,
        'grade' => $grade,
        'feedback' => $feedback
    ];
}

function evaluateWithGemini($content) {
    $prompt = "Hãy đánh giá chi tiết CV này và đưa ra phản hồi bằng tiếng Việt. Phân tích các mục sau:\n" .
              "1. Điểm đánh giá (thang điểm 100)\n" .
              "2. Xếp loại (Xuất sắc/Tốt/Khá/Cần cải thiện)\n" .
              "3. Ưu điểm nổi bật của CV:\n" .
              "   - Các điểm mạnh về nội dung\n" .
              "   - Các điểm mạnh về hình thức\n" .
              "   - Các kỹ năng và thành tích đặc biệt\n" .
              "4. Nhược điểm cần khắc phục:\n" .
              "   - Những thiếu sót về nội dung\n" .
              "   - Những điểm yếu về hình thức\n" .
              "   - Các phần thiếu hoặc chưa đầy đủ\n" .
              "5. Đề xuất cải thiện cụ thể:\n" .
              "   - Cách cải thiện nội dung\n" .
              "   - Cách cải thiện hình thức\n" .
              "   - Các kỹ năng cần bổ sung\n" .
              "   - Cách trình bày hiệu quả hơn\n" .
              "Nội dung CV:\n" . $content;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
    
    // Additional CURL options for better debugging
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output
    
    // Create a temporary file handle for CURL debug output
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        // Get detailed error information
        $error = curl_error($ch);
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        
        curl_close($ch);
        fclose($verbose);
        
        throw new Exception("API Connection Error: $error\nDebug Info: $verboseLog");
    }
    
    curl_close($ch);
    fclose($verbose);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get AI evaluation. HTTP code: $httpCode\nResponse: $response");
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Invalid response format from AI service: " . print_r($result, true));
    }

    $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
    
    // Enhanced parsing for more detailed sections
    $sections = array(
        'điểm_đánh_giá' => array(),
        'xếp_loại' => array(),
        'hình_thức' => array(),
        'thông_tin' => array(),
        'học_vấn' => array(),
        'kinh_nghiệm' => array(),
        'kỹ_năng' => array(),
        'đề_xuất' => array(),
        'điểm_mạnh' => array(),
        'điểm_yếu' => array()
    );
    
    $lines = explode("\n", $aiResponse);
    $current_section = '';
    
    foreach ($lines as $line) {
        if (preg_match('/^(Hình thức|Thông tin|Học vấn|Kinh nghiệm|Kỹ năng|Đề xuất|Điểm mạnh|Điểm yếu)/ui', $line, $matches)) {
            $current_section = strtolower(str_replace(' ', '_', trim($matches[1])));
        } elseif ($current_section && trim($line)) {
            $sections[$current_section][] = trim($line);
        }
    }

    // Extract score and grade
    preg_match('/điểm.*?(\d+)/ui', $aiResponse, $scoreMatch);
    preg_match('/xếp loại.*?(Xuất sắc|Tốt|Khá|Cần cải thiện)/ui', $aiResponse, $gradeMatch);
    
    $score = isset($scoreMatch[1]) ? intval($scoreMatch[1]) : 0;
    $grade = isset($gradeMatch[1]) ? $gradeMatch[1] : 'Chưa xác định';

    return [
        'score' => $score,
        'grade' => $grade,
        'sections' => [
            'ưu_điểm' => extractSection($aiResponse, 'Ưu điểm'),
            'nhược_điểm' => extractSection($aiResponse, 'Nhược điểm'),
            'điểm_mạnh' => extractSection($aiResponse, 'Điểm mạnh'),
            'điểm_yếu' => extractSection($aiResponse, 'Điểm yếu'),
            'đề_xuất' => extractSection($aiResponse, 'Đề xuất'),
            'kỹ_năng' => extractSection($aiResponse, 'Kỹ năng'),
            'hình_thức' => extractSection($aiResponse, 'Hình thức')
        ],
        'raw_response' => $aiResponse
    ];
}

// Add helper function to extract sections
function extractSection($text, $sectionName) {
    if (preg_match("/$sectionName.*?:(.*?)(?=\n\d|\n[A-Z]|$)/s", $text, $matches)) {
        $content = trim($matches[1]);
        return array_filter(array_map('trim', explode("\n", $content)));
    }
    return [];
}

$evaluation_result = null;
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === 0) {
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (in_array($_FILES['cv_file']['type'], $allowed_types)) {
            try {
                // For PDF files
                if ($_FILES['cv_file']['type'] === 'application/pdf') {
                    if ($hasPdfParser) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($_FILES['cv_file']['tmp_name']);
                        $content = $pdf->getText();
                    } else {
                        throw new Exception("PDF parsing is not available. The required library is not installed.");
                    }
                } 
                // For Word documents
                else {
                    if (extension_loaded('zip')) {
                        $content = '';
                        $zip = zip_open($_FILES['cv_file']['tmp_name']);
                        if ($zip) {
                            while ($zip_entry = zip_read($zip)) {
                                if (zip_entry_name($zip_entry) == "word/document.xml") {
                                    if (zip_entry_open($zip, $zip_entry, "r")) {
                                        $content = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                                        $content = strip_tags($content);
                                    }
                                }
                            }
                            zip_close($zip);
                        }
                    } else {
                        $error = "Word document parser not available. Please paste your CV content instead.";
                    }
                }

                if (!empty($content)) {
                    try {
                        $evaluation_result = evaluateWithGemini($content);
                        $message = "CV evaluated successfully using AI!";
                    } catch (Exception $e) {
                        $error = "AI Evaluation error: " . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $error = $e->getMessage() . " Please paste your CV content instead.";
            }
        } else {
            $error = "Invalid file type. Please upload a PDF or Word document.";
        }
    } else if (!empty($_POST['cv_content'])) {
        try {
            $evaluation_result = evaluateWithGemini($_POST['cv_content']);
            $message = "CV evaluated successfully using AI!";
        } catch (Exception $e) {
            $error = "AI Evaluation error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Đánh giá CV thông minh</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Base styles */
        :root {
            --primary-gradient: linear-gradient(135deg, #4CAF50 0%, #2196F3 100%);
            --surface-color: #f8f9fa;
            --text-primary: #2c3e50;
            --text-secondary: #444;
            --success-color: #4caf50;
            --error-color: #f44336;
            --border-radius: 15px;
        }

        .dashboard-container {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        /* Form enhancements */
        .upload-area {
            border: 2px dashed #2196F3;
            padding: 2rem;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .upload-area:hover {
            border-color: var(--success-color);
            background: #f0f7ff;
        }

        .upload-icon {
            font-size: 2rem;
            color: #2196F3;
            margin-bottom: 1rem;
        }

        /* Loading state */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #2196F3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success animation */
        .score-animation {
            animation: countUp 2s ease-out forwards;
            display: inline-block;
        }

        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Fix existing styles and remove duplicates */
        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 2.5em;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .evaluation-container {
            animation: fadeIn 0.6s ease-out;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .score-card {
            background: linear-gradient(135deg, #4CAF50 0%, #2196F3 100%);
            position: relative;
            overflow: hidden;
        }

        .score-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 0%, rgba(255,255,255,0.1) 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .modern-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .modern-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .form-group input[type="file"] {
            border: 2px dashed #2196F3;
            transition: all 0.3s ease;
        }

        .form-group input[type="file"]:hover {
            border-color: #4CAF50;
            background: #f0f7ff;
        }

        .form-group textarea {
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }

        .form-group textarea:focus {
            border-color: #2196F3;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
            outline: none;
        }

        .feedback-card {
            position: relative;
            padding: 20px;
            margin: 15px 0;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .feedback-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .strength, .weakness {
            position: relative;
            overflow: hidden;
        }

        .strength::after, .weakness::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 0%, rgba(255,255,255,0.1) 100%);
            pointer-events: none;
        }

        .nav-modern {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .nav-modern a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
        }

        .nav-modern a:hover {
            background: #f8f9fa;
            color: #2196F3;
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .strength-weakness {
                grid-template-columns: 1fr;
            }
            
            .evaluation-container {
                margin: 10px;
                padding: 15px;
            }
            
            .score-number {
                font-size: 48px;
            }
        }

        .evaluation-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        .score-card {
            background: linear-gradient(135deg, #4CAF50 0%, #2196F3 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .score-number {
            font-size: 64px;
            font-weight: bold;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .grade-badge {
            background: rgba(255,255,255,0.25);
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 20px;
            backdrop-filter: blur(5px);
        }
        .feedback-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        .feedback-section:hover {
            transform: translateY(-2px);
        }
        .feedback-section h3 {
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-size: 1.4em;
        }
        .feedback-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            line-height: 1.6;
            color: #444;
        }
        .strength-weakness {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 25px 0;
        }
        .strength, .weakness {
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .strength {
            background: linear-gradient(to right bottom, #e8f5e9, #fff);
            border-left: 5px solid #4caf50;
        }
        .weakness {
            background: linear-gradient(to right bottom, #ffebee, #fff);
            border-left: 5px solid #f44336;
        }
        .section-header {
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1.2em;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
        }
        .recommendation {
            background: #f8f9fa;
            padding: 15px;
            margin: 12px 0;
            border-radius: 10px;
            border-left: 4px solid #2196f3;
            transition: transform 0.2s ease;
        }
        .recommendation:hover {
            transform: translateX(5px);
            background: #f1f3f4;
        }
        .modern-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            min-height: 150px;
        }
        .btn-modern {
            background: linear-gradient(135deg, #4CAF50 0%, #2196F3 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="dashboard-container">
        <nav class="nav-modern">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Quay lại Dashboard</a>
        </nav>

        <div class="content-wrapper">
            <h2 class="page-title"><i class="fas fa-file-alt"></i> Đánh giá CV bằng AI</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="modern-form" id="cvForm">
                <div class="form-group">
                    <div class="upload-area">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <label>Tải lên CV của bạn (PDF hoặc Word)</label>
                        <input type="file" name="cv_file" accept=".pdf,.doc,.docx" class="file-input">
                        <p class="hint">Kéo thả file hoặc click để chọn</p>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-paste"></i> Hoặc dán nội dung CV vào đây:</label>
                    <textarea name="cv_content" rows="10" placeholder="Dán nội dung CV của bạn vào đây..."></textarea>
                </div>

                <button type="submit" class="btn-modern">
                    <i class="fas fa-robot"></i> Đánh giá CV
                </button>
            </form>

            <?php if ($evaluation_result): ?>
            <div class="evaluation-container">
                <div class="score-card">
                    <h3>Kết quả đánh giá</h3>
                    <div class="score-number"><?= $evaluation_result['score'] ?>/100</div>
                    <span class="grade-badge"><?= $evaluation_result['grade'] ?></span>
                </div>

                <?php if (!empty($evaluation_result['sections']['hình_thức'])): ?>
                <div class="feedback-section">
                    <h3>Hình thức trình bày</h3>
                    <?php foreach ($evaluation_result['sections']['hình_thức'] as $feedback): ?>
                        <div class="feedback-item"><?= htmlspecialchars($feedback) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="feedback-section">
                    <h3>Đề xuất cải thiện</h3>
                    <?php foreach ($evaluation_result['sections']['đề_xuất'] as $suggestion): ?>
                        <div class="recommendation"><?= htmlspecialchars($suggestion) ?></div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($evaluation_result['sections']['nhận_xét'])): ?>
                <div class="feedback-section">
                    <h3>Nhận xét chi tiết</h3>
                    <?php foreach ($evaluation_result['sections']['nhận_xét'] as $feedback): ?>
                        <div class="feedback-item"><?= htmlspecialchars($feedback) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($evaluation_result['sections']['đề_xuất'])): ?>
                <div class="feedback-section">
                    <h3>Đề xuất cải thiện</h3>
                    <ul class="suggestion-list">
                    <?php foreach ($evaluation_result['sections']['đề_xuất'] as $suggestion): ?>
                        <li><?= htmlspecialchars($suggestion) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($evaluation_result['sections']['kỹ_năng'])): ?>
                <div class="feedback-section">
                    <h3>Đánh giá kỹ năng</h3>
                    <?php foreach ($evaluation_result['sections']['kỹ_năng'] as $skill): ?>
                        <div class="feedback-item"><?= htmlspecialchars($skill) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('cvForm').addEventListener('submit', function() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        });

        // Drag and drop handling
        const uploadArea = document.querySelector('.upload-area');
        const fileInput = document.querySelector('.file-input');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.classList.add('highlight');
        }

        function unhighlight(e) {
            uploadArea.classList.remove('highlight');
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html>
