<?php

declare(strict_types=1);

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function send(int $statusCode, array $payload): never
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
}
