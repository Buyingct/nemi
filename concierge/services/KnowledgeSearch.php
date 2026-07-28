<?php

declare(strict_types=1);

final class KnowledgeSearch
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function getReadyChunks(int $workspaceId): array
    {
        $statement = $this->database->prepare(
            'SELECT k.id, k.document_id, k.section_title, k.page_number,
                    k.content, d.original_name, d.category, d.document_type,
                    d.knowledge_source
             FROM knowledge AS k
             INNER JOIN documents AS d ON d.id = k.document_id
             WHERE k.workspace_id = :workspace_id
               AND d.workspace_id = :workspace_id
               AND d.status = "ready"
               AND d.knowledge_source != "none"
             ORDER BY k.id ASC'
        );

        $statement->execute([
            ':workspace_id' => $workspaceId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     * @param array<int, string> $keywords
     * @return array<int, array{score:int,chunk:array<string,mixed>}>
     */
    public function rankChunks(
    array $chunks,
    array $keywords,
    string $question
): array {
    $keywords = $this->expandKeywords(
        $keywords,
        $question
    );

    $ranked = [];

        foreach ($chunks as $chunk) {
            $score = $this->scoreChunk(
                $chunk,
                $keywords,
                $question
            );

            if ($score > 0) {
                $ranked[] = [
                    'score' => $score,
                    'chunk' => $chunk,
                ];
            }
        }

        usort(
            $ranked,
            static fn(array $left, array $right): int =>
                $right['score'] <=> $left['score']
        );

        return $ranked;
    }

    /**
     * @param array<string, mixed> $chunk
     * @param array<int, string> $keywords
     */
    private function scoreChunk(
        array $chunk,
        array $keywords,
        string $question
    ): int {
        $content = $this->normalizeSearchText(
            (string) ($chunk['content'] ?? '')
        );

        $sectionTitle = $this->normalizeSearchText(
            (string) ($chunk['section_title'] ?? '')
        );

        $documentName = $this->normalizeSearchText(
            (string) ($chunk['original_name'] ?? '')
        );

        $category = $this->normalizeSearchText(
            (string) ($chunk['category'] ?? '')
        );

        $normalizedQuestion = $this->normalizeSearchText(
            $question
        );

        $score = 0;
        $distinctMatches = 0;

        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalizeSearchText(
                $keyword
            );

            if ($normalizedKeyword === '') {
                continue;
            }

            $contentCount = substr_count(
                $content,
                $normalizedKeyword
            );

            if ($contentCount > 0) {
                $distinctMatches++;
            }

            $score += min($contentCount, 6) * 2;

            $score += min(
                substr_count($sectionTitle, $normalizedKeyword),
                3
            ) * 8;

            $score += min(
                substr_count($documentName, $normalizedKeyword),
                2
            ) * 3;

            $score += min(
                substr_count($category, $normalizedKeyword),
                2
            ) * 3;
        }

        if ($distinctMatches >= 2) {
            $score += 8;
        }

        if ($distinctMatches >= 4) {
            $score += 12;
        }

        $isQuantityQuestion =
            str_contains($normalizedQuestion, 'how many')
            || str_contains($normalizedQuestion, 'number of')
            || str_contains($normalizedQuestion, 'size of');

        if ($isQuantityQuestion) {
            if (
                preg_match(
                    '/\b(one|two|three|four|five|six|seven|eight|nine|ten)\b/u',
                    $content
                ) === 1
            ) {
                $score += 10;
            }

            if (
                preg_match(
                    '/\b\d+\s*\(\d+\)|\(\d+\)|\b\d+\b/u',
                    $content
                ) === 1
            ) {
                $score += 10;
            }

            foreach (
                [
                    'shall consist of',
                    'consists of',
                    'consist of',
                    'composed of',
                    'number of directors',
                    'number of members',
                ] as $phrase
            ) {
                if (str_contains($content, $phrase)) {
                    $score += 28;
                }
            }
        }

        $isBoardQuestion =
            str_contains($normalizedQuestion, 'board')
            || str_contains($normalizedQuestion, 'director');

        if ($isBoardQuestion) {
            if (
                str_contains($content, 'executive board')
                && (
                    str_contains($content, 'shall consist of')
                    || str_contains($content, 'consists of')
                )
            ) {
                $score += 35;
            }

            if (
                str_contains($content, 'vacancy')
                || str_contains($content, 'vacancies')
            ) {
                $score -= 8;
            }

            if (
                str_contains($content, 'records of')
                || str_contains($content, 'minutes of')
            ) {
                $score -= 8;
            }
        }

$isLandscapingQuestion =
    str_contains($normalizedQuestion, 'flower')
    || str_contains($normalizedQuestion, 'plant')
    || str_contains($normalizedQuestion, 'garden')
    || str_contains($normalizedQuestion, 'landscap')
    || str_contains($normalizedQuestion, 'yard')
    || str_contains($normalizedQuestion, 'tree')
    || str_contains($normalizedQuestion, 'shrub');

if ($isLandscapingQuestion) {
    foreach (
        [
            'landscape',
            'landscaping',
            'planting',
            'plantings',
            'garden',
            'gardens',
            'shrub',
            'shrubs',
            'tree',
            'trees',
            'lawn',
            'exterior',
            'alter',
            'alteration',
            'alterations',
            'modify',
            'modification',
            'common element',
            'common elements',
            'limited common element',
            'limited common elements',
        ] as $phrase
    ) {
        if (
            str_contains($content, $phrase)
            || str_contains($sectionTitle, $phrase)
        ) {
            $score += 18;
        }
    }

    foreach (
        [
            'board approval',
            'prior approval',
            'written approval',
            'without approval',
            'without the consent',
            'shall not alter',
            'may not alter',
            'shall not modify',
            'may not modify',
        ] as $approvalPhrase
    ) {
        if (str_contains($content, $approvalPhrase)) {
            $score += 28;
        }
    }
}
$isShortTermRentalQuestion =
    str_contains($normalizedQuestion, 'short term')
    || str_contains($normalizedQuestion, 'airbnb')
    || str_contains($normalizedQuestion, 'vacation rental')
    || str_contains($normalizedQuestion, 'transient rental');

if ($isShortTermRentalQuestion) {
    foreach (
        [
            'short term rental',
            'short term purposes',
            'transient',
            'hotel',
            'motel',
        ] as $phrase
    ) {
        if (
            str_contains($content, $phrase)
            || str_contains($sectionTitle, $phrase)
        ) {
            $score += 35;
        }
    }
}

$isFeeQuestion =
    str_contains($normalizedQuestion, 'hoa fee')
    || str_contains($normalizedQuestion, 'hoa fees')
    || str_contains($normalizedQuestion, 'monthly fee')
    || str_contains($normalizedQuestion, 'monthly fees')
    || str_contains($normalizedQuestion, 'condo fee')
    || str_contains($normalizedQuestion, 'condo fees')
    || str_contains($normalizedQuestion, 'common expense')
    || str_contains($normalizedQuestion, 'assessment');

if ($isFeeQuestion) {
    foreach (
        [
            'common expense assessment',
            'estimated monthly common expense',
            'monthly common expense',
            'assessment per unit',
        ] as $phrase
    ) {
        if (
            str_contains($content, $phrase)
            || str_contains($sectionTitle, $phrase)
        ) {
            $score += 40;
        }
    }

    if (
        preg_match(
            '/\$\s*\d+(?:,\d{3})*(?:\.\d{2})?/u',
            $content
        ) === 1
    ) {
        $score += 20;
    }
}

$isWindowDoorQuestion =
    str_contains($normalizedQuestion, 'window')
    || str_contains($normalizedQuestion, 'windows')
    || str_contains($normalizedQuestion, 'door')
    || str_contains($normalizedQuestion, 'doors');

if ($isWindowDoorQuestion) {
    foreach (
        [
            'window',
            'windows',
            'door',
            'doors',
        ] as $phrase
    ) {
        if (
            str_contains($content, $phrase)
            || str_contains($sectionTitle, $phrase)
        ) {
            $score += 28;
        }
    }

    foreach (
        [
            'maintain',
            'maintenance',
            'repair',
            'replacement',
            'responsible',
            'responsibility',
        ] as $phrase
    ) {
        if (
            str_contains($content, $phrase)
            || str_contains($sectionTitle, $phrase)
        ) {
            $score += 24;
        }
    }
}
    
    /*
 * Prefer higher-authority governing documents when multiple
 * documents contain relevant language.
 */
switch ($category) {
    case 'declaration':
        $score += 20;
        break;

    case 'bylaws':
        $score += 16;
        break;

    case 'rules':
        $score += 12;
        break;

    case 'budget':
        $score += 6;
        break;

    case 'other':
        $score += 4;
        break;
}

return max($score, 0);
    }

     /**
 * Adds common owner-language synonyms to the search terms.
 *
 * @param array<int, string> $keywords
 * @return array<int, string>
 */
private function expandKeywords(
    array $keywords,
    string $question
): array {
    $normalizedQuestion = $this->normalizeSearchText(
        $question
    );

    $expandedKeywords = $keywords;

    $addKeywords = static function (
        array &$target,
        array $newKeywords
    ): void {
        foreach ($newKeywords as $keyword) {
            if (!in_array($keyword, $target, true)) {
                $target[] = $keyword;
            }
        }
    };

    $isRentalQuestion =
        str_contains($normalizedQuestion, 'short term')
        || str_contains($normalizedQuestion, 'airbnb')
        || str_contains($normalizedQuestion, 'vacation rental')
        || str_contains($normalizedQuestion, 'temporary rental')
        || str_contains($normalizedQuestion, 'transient rental');

    if ($isRentalQuestion) {
        $addKeywords(
            $expandedKeywords,
            [
                'short term rental',
                'short term',
                'transient',
                'hotel',
                'motel',
                'rental',
                'lease',
            ]
        );
    }

    $isFeeQuestion =
        str_contains($normalizedQuestion, 'hoa fee')
        || str_contains($normalizedQuestion, 'hoa fees')
        || str_contains($normalizedQuestion, 'monthly fee')
        || str_contains($normalizedQuestion, 'monthly fees')
        || str_contains($normalizedQuestion, 'condo fee')
        || str_contains($normalizedQuestion, 'condo fees')
        || str_contains($normalizedQuestion, 'common charge')
        || str_contains($normalizedQuestion, 'common charges')
        || str_contains($normalizedQuestion, 'assessment');

    if ($isFeeQuestion) {
        $addKeywords(
            $expandedKeywords,
            [
                'common expense assessment',
                'common expense',
                'assessment',
                'monthly',
                'budget',
                'per unit',
            ]
        );
    }

    $isHomeBusinessQuestion =
        str_contains($normalizedQuestion, 'business from home')
        || str_contains($normalizedQuestion, 'home business')
        || str_contains($normalizedQuestion, 'work from home')
        || str_contains($normalizedQuestion, 'operate a business')
        || str_contains($normalizedQuestion, 'home occupation');

    if ($isHomeBusinessQuestion) {
        $addKeywords(
            $expandedKeywords,
            [
                'home occupation',
                'home occupations',
                'commercial',
                'professional use',
                'residential use',
                'employees',
                'visits from the public',
            ]
        );
    }

    $isWindowDoorQuestion =
        str_contains($normalizedQuestion, 'window')
        || str_contains($normalizedQuestion, 'windows')
        || str_contains($normalizedQuestion, 'door')
        || str_contains($normalizedQuestion, 'doors');

    if ($isWindowDoorQuestion) {
        $addKeywords(
            $expandedKeywords,
            [
                'window',
                'windows',
                'door',
                'doors',
                'maintenance',
                'maintain',
                'repair',
                'replacement',
                'unit owner',
                'association',
                'limited common element',
            ]
        );
    }

    return array_values(
        array_unique($expandedKeywords)
    );
}

    private function normalizeSearchText(
        string $text
    ): string {
        $text = mb_strtolower(trim($text));

        $text = str_replace(
            ['-', '–', '—'],
            ' ',
            $text
        );

        $text = preg_replace(
            '/[^\p{L}\p{N}\s]+/u',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\btime\s+shares?\b/u',
            'timeshare',
            $text
        ) ?? $text;

        return trim($text);
    }
}