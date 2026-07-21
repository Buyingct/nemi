<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../database/connect.php';
require_once __DIR__ . '/../services/AnswerRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review-answers.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION['knowledge_review_csrf'] ?? '');

if (
    $csrfToken === ''
    || $sessionToken === ''
    || !hash_equals($sessionToken, $csrfToken)
) {
    $_SESSION['knowledge_review_flash'] = [
        'type' => 'error',
        'message' => 'The request could not be verified. Please try again.',
    ];

    header('Location: review-answers.php');
    exit;
}

$answerPublicId = trim(
    (string) ($_POST['answer_public_id'] ?? '')
);

$action = trim(
    (string) ($_POST['action'] ?? '')
);

$answerText = trim(
    (string) ($_POST['answer_text'] ?? '')
);

if (
    $answerPublicId === ''
    || !preg_match('/^[a-f0-9]{16}$/', $answerPublicId)
) {
    $_SESSION['knowledge_review_flash'] = [
        'type' => 'error',
        'message' => 'A valid answer could not be identified.',
    ];

    header('Location: review-answers.php');
    exit;
}

try {
    $database = conciergeDatabase();
    $repository = new AnswerRepository($database);

    $answer = $repository->findByPublicId($answerPublicId);

    if (!$answer || (string) $answer['status'] !== 'draft') {
        throw new RuntimeException(
            'The draft answer no longer exists or has already been reviewed.'
        );
    }

    if ($action === 'approve') {
        if ($answerText === '') {
            throw new RuntimeException(
                'The answer cannot be empty.'
            );
        }

        $updated = $repository->approve(
            $answerPublicId,
            $answerText
        );

        $message = 'Answer approved and added to the answer library.';
    } elseif ($action === 'save') {
        if ($answerText === '') {
            throw new RuntimeException(
                'The answer cannot be empty.'
            );
        }

        $updated = $repository->saveDraftEdit(
            $answerPublicId,
            $answerText
        );

        $message = 'Draft changes saved.';
    } elseif ($action === 'reject') {
        $updated = $repository->reject(
            $answerPublicId
        );

        $message = 'Draft answer rejected.';
    } else {
        throw new RuntimeException(
            'The requested action is not supported.'
        );
    }

    if (!$updated) {
        throw new RuntimeException(
            'The answer could not be updated.'
        );
    }

    $_SESSION['knowledge_review_flash'] = [
        'type' => 'success',
        'message' => $message,
    ];
} catch (Throwable $exception) {
    error_log(
        'Knowledge review action failed: '
        . $exception->getMessage()
    );

    $_SESSION['knowledge_review_flash'] = [
        'type' => 'error',
        'message' => $exception->getMessage(),
    ];
}

header('Location: review-answers.php');
exit;
