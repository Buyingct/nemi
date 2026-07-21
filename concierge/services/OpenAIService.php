<?php

declare(strict_types=1);

final class OpenAIService
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';
    private const MODEL = 'gpt-5-mini';

    public function __construct(
        private string $apiKey
    ) {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException(
                'The OpenAI API key is missing.'
            );
        }
    }

    public static function fromEnvFile(string $envPath): self
    {
        if (!is_readable($envPath)) {
            throw new RuntimeException(
                'The OpenAI environment file could not be read.'
            );
        }

        $lines = file(
            $envPath,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if (!is_array($lines)) {
            throw new RuntimeException(
                'The OpenAI environment file could not be loaded.'
            );
        }

        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                $line === ''
                || str_starts_with($line, '#')
                || !str_contains($line, '=')
            ) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && $value[-1] === '"')
                    || ($value[0] === "'" && $value[-1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$name] = $value;
        }

        return new self(
            (string) ($values['OPENAI_API_KEY'] ?? '')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rankedChunks
     *
     * @return array{
     *     supported: bool,
     *     title: string,
     *     answer: string,
     *     source_numbers: array<int, int>
     * }
     */
    public function createGroundedDraft(
        string $question,
        array $rankedChunks
    ): array {
        $sourceBlocks = [];

        foreach ($rankedChunks as $index => $rankedChunk) {
            $chunk = $rankedChunk['chunk'] ?? [];
            $sourceNumber = $index + 1;

            $documentName = trim(
                (string) ($chunk['original_name'] ?? 'Unknown document')
            );

            $sectionTitle = trim(
                (string) ($chunk['section_title'] ?? '')
            );

            $pageNumber = (
                isset($chunk['page_number'])
                && (int) $chunk['page_number'] > 0
                    ? (int) $chunk['page_number']
                    : null
            );

            $content = trim(
                (string) ($chunk['content'] ?? '')
            );

            $sourceBlocks[] = implode("\n", [
                'SOURCE ' . $sourceNumber,
                'Document: ' . $documentName,
                'Section: ' . (
                    $sectionTitle !== ''
                        ? $sectionTitle
                        : 'Not specified'
                ),
                'Page: ' . (
                    $pageNumber !== null
                        ? (string) $pageNumber
                        : 'Not specified'
                ),
                'Text:',
                $content,
            ]);
        }

        $instructions = implode("\n", [
            'You are the Rocha Circle condominium document concierge.',
            'Answer only from the supplied source excerpts.',
            'Do not use outside knowledge, assumptions, or general HOA rules.',
            'If the excerpts do not clearly answer the question, set supported',
            'to false and leave answer concise.',
            'If supported is true, explain the answer in plain English.',
            'Do not overstate. Preserve qualifications, exceptions, approvals,',
            'time limits, and board discretion found in the source.',
            'Do not provide legal advice.',
            'Return valid JSON only, with exactly these keys:',
            'supported (boolean), title (string), answer (string),',
            'source_numbers (array of source numbers used).',
        ]);

        $input = implode("\n\n", [
            'USER QUESTION:',
            $question,
            'DOCUMENT EXCERPTS:',
            implode("\n\n---\n\n", $sourceBlocks),
        ]);

        $payload = [
            'model' => self::MODEL,
            'store' => false,
            'instructions' => $instructions,
            'input' => $input,
            'max_output_tokens' => 700,
        ];

        $response = $this->postJson($payload);
        $text = $this->extractOutputText($response);

        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'OpenAI returned an unreadable draft response.'
            );
        }

        $supported = (bool) ($decoded['supported'] ?? false);
        $title = trim((string) ($decoded['title'] ?? ''));
        $answer = trim((string) ($decoded['answer'] ?? ''));
        $sourceNumbers = $decoded['source_numbers'] ?? [];

        if (!is_array($sourceNumbers)) {
            $sourceNumbers = [];
        }

        $cleanSourceNumbers = [];

        foreach ($sourceNumbers as $number) {
            $number = (int) $number;

            if (
                $number >= 1
                && $number <= count($rankedChunks)
            ) {
                $cleanSourceNumbers[] = $number;
            }
        }

        $cleanSourceNumbers = array_values(
            array_unique($cleanSourceNumbers)
        );

        if ($supported && $answer === '') {
            throw new RuntimeException(
                'OpenAI did not provide an answer.'
            );
        }

        return [
            'supported' => $supported,
            'title' => (
                $title !== ''
                    ? $title
                    : 'Document answer'
            ),
            'answer' => $answer,
            'source_numbers' => $cleanSourceNumbers,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function postJson(array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHP cURL is not installed on this server.'
            );
        }

        $curl = curl_init(self::ENDPOINT);

        if ($curl === false) {
            throw new RuntimeException(
                'The OpenAI request could not be initialized.'
            );
        }

        $encodedPayload = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($encodedPayload)) {
            throw new RuntimeException(
                'The OpenAI request could not be encoded.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $encodedPayload,
        ]);

        $rawResponse = curl_exec($curl);

        if ($rawResponse === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new RuntimeException(
                'OpenAI could not be reached: ' . $error
            );
        }

        $statusCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'OpenAI returned an invalid response.'
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $apiMessage = (string) (
                $decoded['error']['message']
                ?? 'Unknown OpenAI API error.'
            );

            throw new RuntimeException(
                'OpenAI request failed: ' . $apiMessage
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractOutputText(array $response): string
    {
        if (
            isset($response['output_text'])
            && is_string($response['output_text'])
        ) {
            return trim($response['output_text']);
        }

        $parts = [];

        foreach (($response['output'] ?? []) as $outputItem) {
            if (!is_array($outputItem)) {
                continue;
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (
                    is_array($contentItem)
                    && ($contentItem['type'] ?? '') === 'output_text'
                    && isset($contentItem['text'])
                    && is_string($contentItem['text'])
                ) {
                    $parts[] = $contentItem['text'];
                }
            }
        }

        $text = trim(implode("\n", $parts));

        if ($text === '') {
            throw new RuntimeException(
                'OpenAI returned no text.'
            );
        }

        return $text;
    }
}
