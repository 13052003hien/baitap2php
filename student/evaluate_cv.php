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
              "3. Đánh giá chi tiết:\n" .
              "   - Hình thức trình bày và bố cục\n" .
              "   - Thông tin cá nhân và liên hệ\n" .
              "   - Học vấn và chứng chỉ\n" .
              "   - Kinh nghiệm làm việc\n" .
              "   - Kỹ năng chuyên môn\n" .
              "   - Kỹ năng mềm\n" .
              "   - Thành tích và hoạt động ngoại khóa\n" .
              "4. Đề xuất cải thiện:\n" .
              "   - Nội dung cần bổ sung\n" .
              "   - Cách trình bày hiệu quả hơn\n" .
              "   - Từ ngữ và cách diễn đạt\n" .
              "   - Định hướng phát triển\n" .
              "5. Điểm mạnh và điểm yếu:\n" .
              "   - Ưu điểm nổi bật\n" .
              "   - Hạn chế cần khắc phục\n\n" .
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
        'sections' => $sections,
        'raw_response' => $aiResponse
    ];
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
    <style>
        .evaluation-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .score-card {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .score-number {
            font-size: 48px;
            font-weight: bold;
            margin: 10px 0;
        }
        .grade-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 18px;
        }
        .feedback-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .feedback-section h3 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .feedback-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .feedback-item:last-child {
            border-bottom: none;
        }
        .suggestion-list {
            list-style: none;
            padding: 0;
        }
        .suggestion-list li {
            padding: 8px 0 8px 25px;
            position: relative;
        }
        .suggestion-list li:before {
            content: '→';
            position: absolute;
            left: 0;
            color: #6B73FF;
        }
        .strength-weakness {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 15px 0;
        }
        .strength, .weakness {
            padding: 15px;
            border-radius: 8px;
        }
        .strength {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .weakness {
            background: #ffebee;
            border-left: 4px solid #f44336;
        }
        .section-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .recommendation {
            background: #e3f2fd;
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="nav-modern">
            <a href="dashboard.php">Quay lại Dashboard</a>
        </nav>

        <div class="content-wrapper">
            <h2>Đánh giá CV bằng AI</h2>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="modern-form">
                <div class="form-group">
                    <label>Tải lên CV của bạn (PDF hoặc Word):</label>
                    <input type="file" name="cv_file" accept=".pdf,.doc,.docx">
                </div>

                <div class="form-group"></div>
                    <label>Hoặc dán nội dung CV vào đây:</label>
                    <textarea name="cv_content" rows="10" placeholder="Dán nội dung CV của bạn vào đây..."></textarea>
                </div>

                <button type="submit" class="btn-modern">Đánh giá CV</button>
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

                <div class="strength-weakness">
                    <div class="strength"></div>
                        <div class="section-header">Điểm mạnh</div>
                        <?php foreach ($evaluation_result['sections']['điểm_mạnh'] as $strength): ?>
                            <div class="feedback-item"><?= htmlspecialchars($strength) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="weakness">
                        <div class="section-header">Điểm cần cải thiện</div>
                        <?php foreach ($evaluation_result['sections']['điểm_yếu'] as $weakness): ?>
                            <div class="feedback-item"><?= htmlspecialchars($weakness) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="feedback-section">
                    <h3>Đề xuất cải thiện</h3>
                    <?php foreach ($evaluation_result['sections']['đề_xuất'] as $suggestion): ?>
                        <div class="recommendation"><?= htmlspecialchars($suggestion) ?></div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($evaluation_result['sections']['nhận_xét'])): ?>
                <div class="feedback-section"></div>
                    <h3>Nhận xét chi tiết</h3>
                    <?php foreach ($evaluation_result['sections']['nhận_xét'] as $feedback): ?>
                        <div class="feedback-item"><?= htmlspecialchars($feedback) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($evaluation_result['sections']['đề_xuất'])): ?>
                <div class="feedback-section">
                    <h3>Đề xuất cải thiện</h3>
                    <ul class="suggestion-list"></ul>
                    <?php foreach ($evaluation_result['sections']['đề_xuất'] as $suggestion): ?>
                        <li><?= htmlspecialchars($suggestion) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($evaluation_result['sections']['kỹ_năng'])): ?>
                <div class="feedback-section"></div>
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
</body>
</html>
