<?php
/**
 * Save Paper Handler
 * DoPaper.com - Create customized exam paper with random questions
 */

require_once 'database.php';

startSession();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid request method'], 405);
}

// Check action
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_paper':
        createPaper();
        break;
    case 'submit_answers':
        submitAnswers();
        break;
    case 'save_schedule':
        saveSchedule();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Create a new exam paper with random questions
 */
function createPaper() {
    // Check if user is logged in
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Please login to create papers'], 401);
    }
    
    $userId = getCurrentUserId();
    
    // Get form data
    $stream = trim($_POST['stream'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $paperType = trim($_POST['paper_type'] ?? '');
    $year = intval($_POST['year'] ?? 0);
    $numQuestions = intval($_POST['num_questions'] ?? 0);
    $timeDuration = intval($_POST['time_duration'] ?? 0);
    
    // Validate required fields
    $errors = [];
    
    if (empty($stream)) {
        $errors[] = 'Stream is required';
    }
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    if (empty($paperType)) {
        $errors[] = 'Paper type is required';
    }
    if ($year < 2021 || $year > 2024) {
        $errors[] = 'Year must be between 2021 and 2024';
    }
    if ($numQuestions < 1 || $numQuestions > 50) {
        $errors[] = 'Number of questions must be between 1 and 50';
    }
    if ($timeDuration < 1) {
        $errors[] = 'Time duration is required';
    }
    
    if (!empty($errors)) {
        jsonResponse(['error' => implode(', ', $errors)], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Get random questions from the database for this subject and year
        $stmt = $pdo->prepare("
            SELECT id, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, marks
            FROM questions
            WHERE subject = ? AND paper_year = ? AND paper_type = ?
            ORDER BY RAND()
            LIMIT ?
        ");
        
        $stmt->execute([$subject, $year, $paperType, $numQuestions]);
        $questions = $stmt->fetchAll();
        
        // If not enough questions found, try without paper type filter
        if (count($questions) < $numQuestions) {
            $stmt = $pdo->prepare("
                SELECT id, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, marks
                FROM questions
                WHERE subject = ? AND paper_year = ?
                ORDER BY RAND()
                LIMIT ?
            ");
            $stmt->execute([$subject, $year, $numQuestions]);
            $questions = $stmt->fetchAll();
        }
        
        // If still not enough, get from any year
        if (count($questions) < $numQuestions) {
            $stmt = $pdo->prepare("
                SELECT id, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, marks
                FROM questions
                WHERE subject = ?
                ORDER BY RAND()
                LIMIT ?
            ");
            $stmt->execute([$subject, $numQuestions]);
            $questions = $stmt->fetchAll();
        }
        
        // Re-number questions
        $numberedQuestions = [];
        foreach ($questions as $index => $q) {
            $numberedQuestions[] = [
                'id' => $index + 1,
                'question_id' => $q['id'],
                'text' => $q['question_text'],
                'option_a' => $q['option_a'],
                'option_b' => $q['option_b'],
                'option_c' => $q['option_c'],
                'option_d' => $q['option_d'],
                'option_e' => $q['option_e'] ?? '',
                'correct_answer' => $q['correct_answer'],
                'explanation' => $q['explanation'],
                'marks' => $q['marks']
            ];
        }
        
        $questionsJson = json_encode($numberedQuestions);
        
        // Calculate total marks
        $totalMarks = array_sum(array_column($numberedQuestions, 'marks'));
        
        // Insert paper into database
        $stmt = $pdo->prepare("
            INSERT INTO papers (user_id, stream, subject, paper_type, year, num_questions, time_duration, questions, status, total_marks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ready', ?)
        ");
        
        $stmt->execute([
            $userId,
            $stream,
            $subject,
            $paperType,
            $year,
            $numQuestions,
            $timeDuration,
            $questionsJson,
            $totalMarks
        ]);
        
        $paperId = $pdo->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Paper created successfully',
            'paper_id' => $paperId,
            'stream' => $stream,
            'subject' => $subject,
            'paper_type' => $paperType,
            'year' => $year,
            'num_questions' => count($numberedQuestions),
            'time_duration' => $timeDuration,
            'questions' => $numberedQuestions
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to create paper: ' . $e->getMessage()], 500);
    }
}

/**
 * Submit answers and calculate marks
 */
function submitAnswers() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Please login to submit answers'], 401);
    }
    
    $userId = getCurrentUserId();
    $paperId = intval($_POST['paper_id'] ?? 0);
    $answers = json_decode($_POST['answers'] ?? '[]', true);
    $timeTaken = intval($_POST['time_taken'] ?? 0);
    $readingTime = intval($_POST['reading_time'] ?? 0);
    
    if ($paperId <= 0) {
        jsonResponse(['error' => 'Invalid paper ID'], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Get paper details
        $stmt = $pdo->prepare("SELECT * FROM papers WHERE id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        $paper = $stmt->fetch();
        
        if (!$paper) {
            jsonResponse(['error' => 'Paper not found'], 404);
        }
        
        $questions = json_decode($paper['questions'], true);
        
        // Calculate marks and track wrong answers
        $obtainedMarks = 0;
        $wrongQuestions = [];
        
        foreach ($questions as $q) {
            $questionId = $q['id'];
            $userAnswer = $answers[$questionId] ?? '';
            $correctAnswer = $q['correct_answer'];
            
            if ($userAnswer === $correctAnswer) {
                $obtainedMarks += $q['marks'];
            } else if (!empty($userAnswer)) {
                // Track wrong answer
                $wrongQuestions[] = [
                    'question_id' => $q['question_id'],
                    'question_text' => $q['text'],
                    'user_answer' => $userAnswer,
                    'correct_answer' => $correctAnswer,
                    'explanation' => $q['explanation']
                ];
                
                // Save wrong question to database
                $stmt = $pdo->prepare("
                    INSERT INTO wrong_questions (user_id, paper_id, question_id, user_answer, correct_answer, is_wrong)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$userId, $paperId, $q['question_id'], $userAnswer, $correctAnswer]);
            }
        }
        
        // Update paper with marks
        $stmt = $pdo->prepare("
            UPDATE papers
            SET obtained_marks = ?, status = 'completed', completed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$obtainedMarks, $paperId]);
        
        // Record attempt
        $stmt = $pdo->prepare("
            INSERT INTO paper_attempts (paper_id, user_id, obtained_marks, time_taken, reading_time, completed_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$paperId, $userId, $obtainedMarks, $timeTaken, $readingTime]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Answers submitted successfully',
            'obtained_marks' => $obtainedMarks,
            'total_marks' => $paper['total_marks'],
            'percentage' => round(($obtainedMarks / $paper['total_marks']) * 100, 1),
            'wrong_questions' => $wrongQuestions,
            'questions' => $questions
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to submit answers: ' . $e->getMessage()], 500);
    }
}

/**
 * Save schedule for "Do Later" papers
 */
function saveSchedule() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Please login to schedule papers'], 401);
    }
    
    $userId = getCurrentUserId();
    $paperId = intval($_POST['paper_id'] ?? 0);
    $scheduledDate = $_POST['scheduled_date'] ?? '';
    $scheduledTime = $_POST['scheduled_time'] ?? '';
    
    if ($paperId <= 0) {
        jsonResponse(['error' => 'Invalid paper ID'], 400);
    }
    
    if (empty($scheduledDate) || empty($scheduledTime)) {
        jsonResponse(['error' => 'Date and time are required'], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Check if paper exists and belongs to user
        $stmt = $pdo->prepare("SELECT id FROM papers WHERE id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Paper not found'], 404);
        }
        
        // Update paper status to scheduled
        $stmt = $pdo->prepare("
            UPDATE papers
            SET status = 'scheduled', scheduled_at = ?, scheduled_time = ?
            WHERE id = ?
        ");
        $stmt->execute([$scheduledDate, $scheduledTime, $paperId]);
        
        // Save to schedules table
        $stmt = $pdo->prepare("
            INSERT INTO schedules (user_id, paper_id, scheduled_date, scheduled_time)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $paperId, $scheduledDate, $scheduledTime]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Paper scheduled successfully',
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $scheduledTime
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to schedule paper: ' . $e->getMessage()], 500);
    }
}
