<?php

declare(strict_types=1);

require_once __DIR__ . '/database/connect.php';
require_once __DIR__ . '/services/JsonResponse.php';
require_once __DIR__ . '/services/QuestionNormalizer.php';
require_once __DIR__ . '/services/QuestionLogger.php';
require_once __DIR__ . '/services/WorkspaceRepository.php';
require_once __DIR__ . '/services/KnowledgeSearch.php';
require_once __DIR__ . '/services/DocumentFormatter.php';
require_once __DIR__ . '/services/AnswerLibrary.php';

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
    $answerLibrary = new AnswerLibrary($database);

    $workspace = $workspaceRepository->findActiveByPublicId(
        $workspacePublicId
    );

    if (!$workspace) {
        JsonResponse::send(404, [
            'success' => false,
            'error' => 'The requested workspace was not found.',
        ]);
    }

    $workspaceId = (int) $workspace['id'];

    $normalizedQuestion = QuestionNormalizer::normalize(
        $question
    );

    $questionRecordId = $questionLogger->create(
        $workspaceId,
        $question,
        $normalizedQuestion
    );

    /*
     * First priority: use an approved answer stored in this workspace.
     * This path never calls OpenAI.
     */
    $approvedAnswer = $answerLibrary->findApprovedExact(
        $workspaceId,
        $normalizedQuestion
    );

    if ($approvedAnswer) {
        $answerId = (int) $approvedAnswer['id'];

        $questionLogger->updateOutcome(
            $questionRecordId,
            'local_match',
            0,
            $answerId
        );

        JsonResponse::send(200, [
            'success' => true,
            'title' => 'Approved answer',
            'answer' => (string) $approvedAnswer['answer_text'],
            'source' => 'Rocha Circle approved answer library',
            'strength' => (
                (string) ($approvedAnswer['source_strength'] ?? '')
                ?: 'Approved'
            ),
        ]);
    }

    $chunks = $knowledgeSearch->getReadyChunks(
        $workspaceId
    );

    if ($chunks === []) {
        $questionLogger->updateOutcome(
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
        $questionLogger->updateOutcome(
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
        $questionLogger->updateOutcome(
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
        $questionLogger->updateOutcome(
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

    $answerText = DocumentFormatter::cleanAnswerText(
        (string) $bestChunk['content']
    );

    $sourceStrength = DocumentFormatter::relevanceLabel(
        $bestScore
    );

    /*
     * For now, create a draft from the best document section.
     * In the next step, OpenAI will replace this raw draft text with
     * a concise plain-English answer before it enters Knowledge Review.
     */
    $draftAnswer = $answerLibrary->createDraftFromDocument(
        $workspaceId,
        $question,
        $normalizedQuestion,
        $answerText,
        $sourceStrength,
        $bestChunk
    );

    $questionLogger->updateOutcome(
        $questionRecordId,
        'draft_created',
        0,
        (int) $draftAnswer['id']
    );

    JsonResponse::send(200, [
        'success' => true,
        'title' => (
            $sectionTitle !== ''
                ? $sectionTitle
                : 'Relevant document section'
        ),
        'answer' => $answerText,
        'source' => DocumentFormatter::sourceLabel(
            $bestChunk
        ),
        'strength' => $sourceStrength,
    ]);
} catch (Throwable $exception) {
    if (
        $questionLogger instanceof QuestionLogger
        && is_int($questionRecordId)
    ) {
        try {
            $questionLogger->updateOutcome(
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
