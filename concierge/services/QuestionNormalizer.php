<?php

declare(strict_types=1);

final class QuestionNormalizer
{
    public static function normalize(string $question): string
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

    /**
     * @return array<int, string>
     */
    public static function extractKeywords(string $question): array
    {
        $normalized = self::normalize($question);

        $parts = preg_split(
            '/\s+/u',
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

        $conceptGroups = [
            ['pet', 'pets', 'animal', 'animals', 'dog', 'dogs', 'cat', 'cats'],
            ['rental', 'rentals', 'rent', 'lease', 'leases', 'leasing', 'tenant', 'tenants'],
            ['short', 'transient', 'hotel', 'motel', 'airbnb', 'vacation'],
            ['parking', 'park', 'vehicle', 'vehicles', 'car', 'cars', 'garage'],
            ['business', 'occupation', 'office', 'commercial', 'professional'],
            ['fee', 'fees', 'assessment', 'assessments', 'dues', 'charge', 'charges'],
            ['repair', 'repairs', 'maintain', 'maintenance', 'replacement', 'replace'],
            ['insurance', 'insured', 'policy', 'coverage', 'premium', 'premiums'],
            ['alteration', 'alterations', 'modify', 'modification', 'renovation', 'improvement'],
            ['vote', 'voting', 'ballot', 'election', 'quorum'],
        ];

        $expanded = $keywords;

        foreach ($conceptGroups as $group) {
            if (array_intersect($keywords, $group) !== []) {
                $expanded = array_merge($expanded, $group);
            }
        }

        return array_values(array_unique($expanded));
    }
}
