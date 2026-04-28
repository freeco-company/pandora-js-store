<?php

namespace App\Services\Identity;

/**
 * PandoraCoreClient 回傳結果 DTO。
 *
 * - ok: 2xx
 * - failed: 4xx / 5xx；retryable 由 status 4xx vs 5xx 決定
 * - misconfigured: 環境變數缺失，視為 retryable=false 但不算 dead_letter（dev 環境正常）
 */
class PandoraCoreResponse
{
    private function __construct(
        public readonly bool $success,
        public readonly int $status,
        public readonly ?string $error,
        public readonly array $data,
        public readonly bool $retryable,
    ) {}

    public static function ok(array $data): self
    {
        return new self(true, 200, null, $data, false);
    }

    public static function failed(int $status, string $body): self
    {
        return new self(
            success: false,
            status: $status,
            error: substr($body, 0, 1000),
            data: [],
            retryable: $status >= 500 || $status === 429,
        );
    }

    public static function misconfigured(string $reason): self
    {
        return new self(false, 0, $reason, [], false);
    }
}
