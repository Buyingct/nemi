<?php

declare(strict_types=1);

require_once __DIR__ . '/database/connect.php';
require_once __DIR__ . '/services/JsonResponse.php';
require_once __DIR__ . '/services/QuestionNormalizer.php';
require_once __DIR__ . '/services/QuestionLogger.php';
require_once __DIR__ . '/services/WorkspaceRepository.php';
require_once __DIR__ . '/services/KnowledgeSearch.php';
require_once __DIR__ . '/services/DocumentFormatter.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(405, [
        'success' => false,
        'error' => 'Only POST requests are allowed.',
    ]);
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    JsonResponse::send(400, [
        'success' => false,
        'error' => 'The request body is empty.',
    ]);
}

$data = json_decode($rawBody, true);

if (!is_array($data)) {
    JsonResponse::send(400, [
        'success' => false,
        'error' => 'The request body is not valid JSON.',
    ]);
}

$workspacePublicId = trim(
    (string) ($data['workspace_id'] ?? '')
);

$question = trim(
    (string) ($data['question'] ?? '')
);

if (
    $workspacePublicId === ''
    || !preg_match('/^[a-f0-9]{16}$/', $workspacePublicId)
) {
    JsonResponse::send(400, [
        'success' => false,
        'error' => 'A valid workspace is required.',
    ]);
}

if ($question === '') {
    JsonResponse::send(400, [
        'success' => false,
        'error' => 'Please enter a question.',
    ]);
}

if (mb_strlen($question) > 1000) {
    JsonResponse::send(400, [
        'success' => false,
        'error' => 'The question is too long.',
    ]);
}

$questionRecordId = null;
$questionLogger = null;

try {
    $database = conciergeDatabase();

    $workspaceRepository = new WorkspaceRepository($database);
    $questionLogger = new QuestionLogger($database);
    $knowledgeSearch = new KnowledgeSearch($database);

    $workspace = $workspaceRepository->findActiveByPublicId(
        $workspacePublicId
    );

    if (!$workspace) {
        JsonResponse::send(404, [
            'success' => false,
            'error' => 'The requested workspace was not found.',
        ]);
    }

    $normalizedQuestion = QuestionNormalizer::normalize(
        $question
    );

    $questionRecordId = $questionLogger->create(
        (int) $workspace['id'],
        $question,
        $normalizedQuestion
    );

    $chunks = $knowledgeSearch->getReadyChunks(
        (int) $workspace['id']
    );

    if ($chunks === []) {
        $questionLogger->updateStatus(
            $questionRecordId,
            'not_found'
        );

        JsonResponse::send(404, [
            'success' => false,
            'error' => (
                'No ready knowledge is available in this workspace yet.'
            ),
        ]);
    }

    $keywords = QuestionNormalizer::extractKeywords(
        $question
    );

    if ($keywords === []) {
        $questionLogger->updateStatus(
            $questionRecordId,
            'not_found'
        );

        JsonResponse::send(400, [
            'success' => false,
            'error' => (
                'Please ask a more specific question using a subject such as '
                . 'pets, rentals, maintenance, insurance, fees, parking, '
                . 'alterations, or voting.'
            ),
        ]);
    }

    $rankedChunks = $knowledgeSearch->rankChunks(
        $chunks,
        $keywords,
        $question
    );

    if ($rankedChunks === []) {
        $questionLogger->updateStatus(
            $questionRecordId,
            'not_found'
        );

        JsonResponse::send(200, [
            'success' => true,
            'title' => 'The answer was not found.',
            'answer' => (
                'I could not find information answering that question in the '
                . 'documents currently available in this workspace.'
            ),
            'source' => 'No matching document section was found.',
            'strength' => 'Not found',
        ]);
    }

    $best = $rankedChunks[0];
    $bestChunk = $best['chunk'];
    $bestScore = (int) $best['score'];

    if ($bestScore < 5) {
        $questionLogger->updateStatus(
            $questionRecordId,
            'not_found'
        );

        JsonResponse::send(200, [
            'success' => true,
            'title' => 'The answer was not found.',
            'answer' => (
                'I found documents in this workspace, but none matched your '
                . 'question closely enough to provide a reliable answer.'
            ),
            'source' => (
                'No sufficiently relevant section was identified.'
            ),
            'strength' => 'Not found',
        ]);
    }

    $sectionTitle = trim(
        (string) ($bestChunk['section_title'] ?? '')
    );

    $questionLogger->updateStatus(
        $questionRecordId,
        'document_match'
    );

    JsonResponse::send(200, [
        'success' => true,
        'title' => (
            $sectionTitle !== ''
                ? $sectionTitle
                : 'Relevant document section'
        ),
        'answer' => DocumentFormatter::cleanAnswerText(
            (string) $bestChunk['content']
        ),
        'source' => DocumentFormatter::sourceLabel(
            $bestChunk
        ),
        'strength' => DocumentFormatter::relevanceLabel(
            $bestScore
        ),
    ]);
} catch (Throwable $exception) {
    if (
        $questionLogger instanceof QuestionLogger
        && is_int($questionRecordId)
    ) {
        try {
            $questionLogger->updateStatus(
                $questionRecordId,
                'failed'
            );
        } catch (Throwable $statusException) {
            error_log(
                'Question status update failed: '
                . $statusException->getMessage()
            );
        }
    }

    error_log(
        'Concierge question failed: '
        . $exception->getMessage()
    );

    JsonResponse::send(500, [
        'success' => false,
        'error' => (
            'The Concierge could not review the documents right now. '
            . 'Please try again.'
        ),
    ]);
}

