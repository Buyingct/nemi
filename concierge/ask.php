<?php

declare(strict_types=1);

require_once __DIR__ . '/database/connect.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'error' => 'Only POST requests are allowed.',
    ]);
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    respond(400, [
        'success' => false,
        'error' => 'The request body is empty.',
    ]);
}

$data = json_decode($rawBody, true);

if (!is_array($data)) {
    respond(400, [
        'success' => false,
        'error' => 'The request body is not valid JSON.',
    ]);
}

$workspacePublicId = trim((string) ($data['workspace_id'] ?? ''));
$question = trim((string) ($data['question'] ?? ''));

if (
    $workspacePublicId === ''
    || !preg_match('/^[a-f0-9]{16}$/', $workspacePublicId)
) {
    respond(400, [
        'success' => false,
        'error' => 'A valid workspace is required.',
    ]);
}

if ($question === '') {
    respond(400, [
        'success' => false,
        'error' => 'Please enter a question.',
    ]);
}

if (mb_strlen($question) > 1000) {
    respond(400, [
        'success' => false,
        'error' => 'The question is too long.',
    ]);
}

$questionRecordId = null;

try {
    $database = conciergeDatabase();

    $workspaceStatement = $database->prepare(
        '
        SELECT id, public_id, name
        FROM workspaces
        WHERE public_id = :public_id
          AND status = "active"
        LIMIT 1
        '
    );

    $workspaceStatement->execute([
        ':public_id' => $workspacePublicId,
    ]);

    $workspace = $workspaceStatement->fetch();

    if (!$workspace) {
        respond(404, [
            'success' => false,
            'error' => 'The requested workspace was not found.',
        ]);
    }

    /*
     * STEP 2:
     * Save every valid submitted question before searching documents
     * or making any future API call.
     */
    $normalizedQuestion = normalizeQuestion($question);

    $questionRecordId = saveQuestion(
        $database,
        (int) $workspace['id'],
        $question,
        $normalizedQuestion
    );

    $knowledgeStatement = $database->prepare(
        '
        SELECT
            k.id,
            k.section_title,
            k.page_number,
            k.content,
            d.original_name,
            d.category,
            d.document_type,
            d.knowledge_source
        FROM knowledge AS k
        INNER JOIN documents AS d
            ON d.id = k.document_id
        WHERE k.workspace_id = :workspace_id
          AND d.workspace_id = :workspace_id
          AND d.status = "ready"
          AND d.knowledge_source != "none"
        ORDER BY k.id ASC
        '
    );

    $knowledgeStatement->execute([
        ':workspace_id' => (int) $workspace['id'],
    ]);

    $chunks = $knowledgeStatement->fetchAll();

    if ($chunks === []) {
        updateQuestionStatus(
            $database,
            $questionRecordId,
            'not_found'
        );

        respond(404, [
            'success' => false,
            'error' => 'No ready knowledge is available in this workspace yet.',
        ]);
    }

    $keywords = extractKeywords($question);

    if ($keywords === []) {
        updateQuestionStatus(
            $database,
            $questionRecordId,
            'not_found'
        );

        respond(400, [
            'success' => false,
            'error' => (
                'Please ask a more specific question using a subject such as '
                . 'pets, rentals, maintenance, insurance, fees, parking, '
                . 'alterations, or voting.'
            ),
        ]);
    }

    $rankedChunks = [];

    foreach ($chunks as $chunk) {
        $score = scoreChunk($chunk, $keywords, $question);

        if ($score > 0) {
            $rankedChunks[] = [
                'score' => $score,
                'chunk' => $chunk,
            ];
        }
    }

    usort(
        $rankedChunks,
        static fn (array $left, array $right): int =>
            $right['score'] <=> $left['score']
    );

    if ($rankedChunks === []) {
        updateQuestionStatus(
            $database,
            $questionRecordId,
            'not_found'
        );

        respond(200, [
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
        updateQuestionStatus(
            $database,
            $questionRecordId,
            'not_found'
        );

        respond(200, [
            'success' => true,
            'title' => 'The answer was not found.',
            'answer' => (
                'I found documents in this workspace, but none matched your '
                . 'question closely enough to provide a reliable answer.'
            ),
            'source' => 'No sufficiently relevant section was identified.',
            'strength' => 'Not found',
        ]);
    }

    $sourceParts = [
        friendlyDocumentName((string) $bestChunk['original_name']),
    ];

    $sectionTitle = trim(
        (string) ($bestChunk['section_title'] ?? '')
    );

    if ($sectionTitle !== '') {
        $sourceParts[] = $sectionTitle;
    }

    $pageNumber = $bestChunk['page_number'];

    if ($pageNumber !== null && (int) $pageNumber > 0) {
        $sourceParts[] = 'Page ' . (int) $pageNumber;
    }

    updateQuestionStatus(
        $database,
        $questionRecordId,
        'document_match'
    );

    respond(200, [
        'success' => true,
        'title' => (
            $sectionTitle !== ''
                ? $sectionTitle
                : 'Relevant document section'
        ),
        'answer' => cleanAnswerText((string) $bestChunk['content']),
        'source' => implode(' — ', $sourceParts),
        'strength' => relevanceLabel($bestScore),
    ]);
} catch (Throwable $exception) {
    if (
        isset($database)
        && $database instanceof PDO
        && is_int($questionRecordId)
    ) {
        try {
            updateQuestionStatus(
                $database,
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

    respond(500, [
        'success' => false,
        'error' => (
            'The Concierge could not review the documents right now. '
            . 'Please try again.'
        ),
    ]);
}

/**
 * @param array<string, mixed> $payload
 */
function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function normalizeQuestion(string $question): string
{
    $normalized = mb_strtolower(trim($question));

    $normalized = preg_replace(
        '/[^\p{L}\p{N}\s]+/u',
        ' ',
        $normalized
    ) ?? $normalized;

    $normalized = preg_replace(
        '/\s+/u',
        ' ',
        $normalized
    ) ?? $normalized;

    return trim($normalized);
}

function generatePublicId(): string
{
    return bin2hex(random_bytes(8));
}

function saveQuestion(
    PDO $database,
    int $workspaceId,
    string $question,
    string $normalizedQuestion
): int {
    $statement = $database->prepare(
        '
        INSERT INTO questions (
            workspace_id,
            public_id,
            question_text,
            normalized_question,
            result_status,
            api_used
        ) VALUES (
            :workspace_id,
            :public_id,
            :question_text,
            :normalized_question,
            "received",
            0
        )
        '
    );

    $statement->execute([
        ':workspace_id' => $workspaceId,
        ':public_id' => generatePublicId(),
        ':question_text' => $question,
        ':normalized_question' => $normalizedQuestion,
    ]);

    return (int) $database->lastInsertId();
}

function updateQuestionStatus(
    PDO $database,
    int $questionId,
    string $status
): void {
    $statement = $database->prepare(
        '
        UPDATE questions
        SET result_status = :result_status
        WHERE id = :id
        '
    );

    $statement->execute([
        ':result_status' => $status,
        ':id' => $questionId,
    ]);
}

/**
 * @return array<int, string>
 */
function extractKeywords(string $question): array
{
    $normalized = mb_strtolower($question);

    $normalized = preg_replace(
        '/[^\p{L}\p{N}\s-]+/u',
        ' ',
        $normalized
    ) ?? $normalized;

    $parts = preg_split(
        '/[\s-]+/u',
        $normalized,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if (!is_array($parts)) {
        return [];
    }

    $stopWords = [
        'a', 'about', 'am', 'an', 'and', 'are', 'as', 'at', 'be',
        'been', 'being', 'by', 'can', 'could', 'do', 'does', 'for',
        'from', 'had', 'has', 'have', 'how', 'i', 'if', 'in', 'is',
        'it', 'may', 'me', 'my', 'of', 'on', 'or', 'our', 'should',
        'that', 'the', 'their', 'there', 'they', 'this', 'to', 'was',
        'what', 'when', 'where', 'which', 'who', 'why', 'will',
        'with', 'would', 'you', 'your',
    ];

    $keywords = [];

    foreach ($parts as $part) {
        $part = trim($part);

        if (
            mb_strlen($part) < 3
            || in_array($part, $stopWords, true)
        ) {
            continue;
        }

        $keywords[] = $part;

        if (
            mb_strlen($part) > 4
            && str_ends_with($part, 's')
        ) {
            $keywords[] = mb_substr(
                $part,
                0,
                mb_strlen($part) - 1
            );
        }
    }

    return array_values(array_unique($keywords));
}

/**
 * @param array<string, mixed> $chunk
 * @param array<int, string> $keywords
 */
function scoreChunk(
    array $chunk,
    array $keywords,
    string $question
): int {
    $content = mb_strtolower(
        (string) ($chunk['content'] ?? '')
    );

    $sectionTitle = mb_strtolower(
        (string) ($chunk['section_title'] ?? '')
    );

    $documentName = mb_strtolower(
        (string) ($chunk['original_name'] ?? '')
    );

    $category = mb_strtolower(
        (string) ($chunk['category'] ?? '')
    );

    $score = 0;

    foreach ($keywords as $keyword) {
        $score += min(substr_count($content, $keyword), 6) * 2;
        $score += min(substr_count($sectionTitle, $keyword), 3) * 7;
        $score += min(substr_count($documentName, $keyword), 2) * 3;
        $score += min(substr_count($category, $keyword), 2) * 3;
    }

    $normalizedQuestion = mb_strtolower(
        preg_replace(
            '/\s+/u',
            ' ',
            trim($question)
        ) ?? trim($question)
    );

    if (
        mb_strlen($normalizedQuestion) >= 8
        && str_contains($content, $normalizedQuestion)
    ) {
        $score += 20;
    }

    return $score;
}

function friendlyDocumentName(string $filename): string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);

    $name = preg_replace(
        '/[_-]+/',
        ' ',
        $name
    ) ?? $name;

    $name = preg_replace(
        '/\s+/',
        ' ',
        trim($name)
    ) ?? trim($name);

    return mb_convert_case(
        $name,
        MB_CASE_TITLE,
        'UTF-8'
    );
}

function cleanAnswerText(string $content): string
{
    $content = preg_replace(
        '/\s+/u',
        ' ',
        trim($content)
    ) ?? trim($content);

    if (mb_strlen($content) > 1400) {
        $content = mb_substr($content, 0, 1400);

        $lastPeriod = mb_strrpos($content, '.');

        if (
            $lastPeriod !== false
            && $lastPeriod > 700
        ) {
            $content = mb_substr(
                $content,
                0,
                $lastPeriod + 1
            );
        } else {
            $content = rtrim($content) . '…';
        }
    }

    return $content;
}

function relevanceLabel(int $score): string
{
    if ($score >= 30) {
        return 'Strong match';
    }

    if ($score >= 15) {
        return 'Relevant match';
    }

    return 'Possible match';
}