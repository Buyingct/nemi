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
require_once __DIR__ . '/services/OpenAIService.php';

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
     * 1. Approved exact answer: local, instant, no API charge.
     */
    $approvedAnswer = $answerLibrary->findApprovedExact(
        $workspaceId,
        $normalizedQuestion
    );

    if ($approvedAnswer) {
        $questionLogger->updateOutcome(
            $questionRecordId,
            'local_match',
            0,
            (int) $approvedAnswer['id']
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

    /*
     * 2. Search local document knowledge before considering OpenAI.
     */
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
                'Please ask a more specific document question.'
            ),
        ]);
    }

    $rankedChunks = $knowledgeSearch->rankChunks(
        $chunks,
        $keywords,
        $question
    );

    /*
     * No meaningful local candidate means no OpenAI call and no charge.
     */
    if (
        $rankedChunks === []
        || (int) $rankedChunks[0]['score'] < 10
    ) {
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
            'source' => 'No sufficiently relevant document section was found.',
            'strength' => 'Not found',
        ]);
    }

    /*
     * 3. Send only the five best local excerpts to OpenAI.
     */
    $topRankedChunks = array_slice(
        $rankedChunks,
        0,
        5
    );

    $openAI = OpenAIService::fromEnvFile(
    dirname(__DIR__) . '/config/.env.php'
);

    $generated = $openAI->createGroundedDraft(
        $question,
        $topRankedChunks
    );

    /*
     * OpenAI may determine that the supplied excerpts do not support an
     * answer. This API call is still billable because the model reviewed
     * the excerpts, but no unreliable draft is saved.
     */
    if (!$generated['supported']) {
        $questionLogger->updateOutcome(
            $questionRecordId,
            'not_found',
            1
        );

        JsonResponse::send(200, [
            'success' => true,
            'title' => 'The answer was not found.',
            'answer' => (
                'The available document excerpts did not clearly answer that '
                . 'question. It has been saved for review.'
            ),
            'source' => 'No clearly supporting section was confirmed.',
            'strength' => 'Not found',
        ]);
    }

    $usedSourceChunks = [];

    foreach ($generated['source_numbers'] as $sourceNumber) {
        $index = $sourceNumber - 1;

        if (isset($topRankedChunks[$index]['chunk'])) {
            $usedSourceChunks[] = $topRankedChunks[$index]['chunk'];
        }
    }

    if ($usedSourceChunks === []) {
        $usedSourceChunks[] = $topRankedChunks[0]['chunk'];
    }

    $bestScore = (int) $topRankedChunks[0]['score'];
    $sourceStrength = DocumentFormatter::relevanceLabel(
        $bestScore
    );

    $draftAnswer = $answerLibrary->createDraft(
        $workspaceId,
        $question,
        $normalizedQuestion,
        $generated['answer'],
        $sourceStrength,
        $usedSourceChunks
    );

    $questionLogger->updateOutcome(
        $questionRecordId,
        'ai_answered',
        1,
        (int) $draftAnswer['id']
    );

    JsonResponse::send(200, [
        'success' => true,
        'title' => $generated['title'],
        'answer' => $generated['answer'],
        'source' => (
            count($usedSourceChunks) === 1
                ? DocumentFormatter::sourceLabel(
                    $usedSourceChunks[0]
                )
                : count($usedSourceChunks)
                    . ' supporting document sections'
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
