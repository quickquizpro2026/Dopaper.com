<?php
/**
 * Get Papers Handler
 * DoPaper.com - Fetch user's papers, marks, and wrong questions
 */

require_once 'database.php';

startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    jsonResponse(['error' => 'Please login to view papers'], 401);
}

$userId = getCurrentUserId();

// Get action parameter
$action = $_GET['action'] ?? 'get_papers';

switch ($action) {
    case 'get_papers':
        getUserPapers($userId);
        break;
    case 'get_paper':
        getPaperDetails($userId);
        break;
    case 'delete_paper':
        deletePaper($userId);
        break;
    case 'get_wrong_questions':
        getWrongQuestions($userId);
        break;
    case 'get_scheduled_papers':
        getScheduledPapers($userId);
        break;
    case 'start_exam':
        startExam($userId);
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Get all papers for the current user
 */
function getUserPapers($userId) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                id, stream, subject, paper_type, year, num_questions,
                time_duration, total_marks, questions, 
                created_at, status, obtained_marks, scheduled_at, scheduled_time
            FROM papers 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$userId]);
        $papers = $stmt->fetchAll();
        
        // Decode questions JSON for each paper
        foreach ($papers as &$paper) {
            $paper['questions'] = json_decode($paper['questions'], true);
            $paper['question_count'] = is_array($paper['questions']) ? count($paper['questions']) : 0;
            $paper['created_at'] = date('M d, Y', strtotime($paper['created_at']));
            
            // Calculate percentage if marks obtained
            if ($paper['obtained_marks'] !== null && $paper['total_marks'] > 0) {
                $paper['percentage'] = round(($paper['obtained_marks'] / $paper['total_marks']) * 100, 1);
            } else {
                $paper['percentage'] = null;
            }
        }
        
        // Get statistics
        $stats = getUserStats($userId, $papers);
        
        jsonResponse([
            'success' => true,
            'papers' => $papers,
            'count' => count($papers),
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch papers: ' . $e->getMessage()], 500);
    }
}

/**
 * Get user statistics
 */
function getUserStats($userId, $papers) {
    $totalPapers = count($papers);
    $completedPapers = count(array_filter($papers, function($p) {
        return $p['status'] === 'completed';
    }));
    $scheduledPapers = count(array_filter($papers, function($p) {
        return $p['status'] === 'scheduled';
    }));
    
    // Calculate average percentage from completed papers
    $completedWithMarks = array_filter($papers, function($p) {
        return $p['status'] === 'completed' && $p['percentage'] !== null;
    });
    
    if (count($completedWithMarks) > 0) {
        $avgPercentage = round(array_sum(array_column($completedWithMarks, 'percentage')) / count($completedWithMarks), 1);
        $bestPercentage = max(array_column($completedWithMarks, 'percentage'));
    } else {
        $avgPercentage = 0;
        $bestPercentage = 0;
    }
    
    // Get count of wrong questions
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wrong_questions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wrongCount = $stmt->fetch()['count'];
    
    return [
        'total_papers' => $totalPapers,
        'completed_papers' => $completedPapers,
        'scheduled_papers' => $scheduledPapers,
        'avg_percentage' => $avgPercentage,
        'best_percentage' => $bestPercentage,
        'wrong_questions_count' => $wrongCount
    ];
}

/**
 * Get single paper details
 */
function getPaperDetails($userId) {
    $paperId = intval($_GET['paper_id'] ?? 0);
    
    if ($paperId <= 0) {
        jsonResponse(['error' => 'Invalid paper ID'], 400);
    }
    
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                id, user_id, stream, subject, paper_type, year, num_questions,
                time_duration, total_marks, questions, 
                created_at, status, obtained_marks, scheduled_at, scheduled_time
            FROM papers 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$paperId, $userId]);
        $paper = $stmt->fetch();
        
        if (!$paper) {
            jsonResponse(['error' => 'Paper not found'], 404);
        }
        
        $paper['questions'] = json_decode($paper['questions'], true);
        $paper['created_at'] = date('M d, Y h:i A', strtotime($paper['created_at']));
        
        if ($paper['obtained_marks'] !== null && $paper['total_marks'] > 0) {
            $paper['percentage'] = round(($paper['obtained_marks'] / $paper['total_marks']) * 100, 1);
        }
        
        jsonResponse([
            'success' => true,
            'paper' => $paper
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch paper: ' . $e->getMessage()], 500);
    }
}

/**
 * Delete a paper
 */
function deletePaper($userId) {
    $paperId = intval($_POST['paper_id'] ?? 0);
    
    if ($paperId <= 0) {
        jsonResponse(['error' => 'Invalid paper ID'], 400);
    }
    
    try {
        $pdo = getDB();
        
        // Delete related wrong questions first
        $stmt = $pdo->prepare("DELETE FROM wrong_questions WHERE paper_id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        // Delete related schedules
        $stmt = $pdo->prepare("DELETE FROM schedules WHERE paper_id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        // Delete related attempts
        $stmt = $pdo->prepare("DELETE FROM paper_attempts WHERE paper_id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        // Delete paper
        $stmt = $pdo->prepare("DELETE FROM papers WHERE id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Paper deleted successfully']);
        } else {
            jsonResponse(['error' => 'Paper not found or already deleted'], 404);
        }
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to delete paper: ' . $e->getMessage()], 500);
    }
}

/**
 * Get wrong questions for the user
 */
function getWrongQuestions($userId) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                wq.*, p.subject, p.paper_type, p.year
            FROM wrong_questions wq
            JOIN papers p ON wq.paper_id = p.id
            WHERE wq.user_id = ?
            ORDER BY wq.attempted_at DESC
            LIMIT 50
        ");
        
        $stmt->execute([$userId]);
        $wrongQuestions = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'wrong_questions' => $wrongQuestions,
            'count' => count($wrongQuestions)
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch wrong questions: ' . $e->getMessage()], 500);
    }
}

/**
 * Get scheduled papers
 */
function getScheduledPapers($userId) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT 
                id, stream, subject, paper_type, year, num_questions,
                time_duration, scheduled_at, scheduled_time
            FROM papers 
            WHERE user_id = ? AND status = 'scheduled'
            ORDER BY scheduled_at ASC, scheduled_time ASC
        ");
        
        $stmt->execute([$userId]);
        $papers = $stmt->fetchAll();
        
        foreach ($papers as &$paper) {
            $paper['scheduled_at'] = date('M d, Y', strtotime($paper['scheduled_at']));
        }
        
        jsonResponse([
            'success' => true,
            'scheduled_papers' => $papers,
            'count' => count($papers)
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch scheduled papers: ' . $e->getMessage()], 500);
    }
}

/**
 * Start an exam (update status to in_progress)
 */
function startExam($userId) {
    $paperId = intval($_POST['paper_id'] ?? 0);
    
    if ($paperId <= 0) {
        jsonResponse(['error' => 'Invalid paper ID'], 400);
    }
    
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("UPDATE papers SET status = 'in_progress' WHERE id = ? AND user_id = ?");
        $stmt->execute([$paperId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Exam started']);
        } else {
            jsonResponse(['error' => 'Paper not found'], 404);
        }
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to start exam: ' . $e->getMessage()], 500);
    }
}
