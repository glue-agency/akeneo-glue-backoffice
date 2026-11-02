<?php

namespace GlueAgency\Backoffice\Tests\Fixtures;

use GlueAgency\Backoffice\Client;
use Throwable;

/**
 * A Dashboard that answers as told and remembers what it was sent.
 */
class FakeClient extends Client
{
    /**
     * @var array<int, array{url: string, token: string, payload: array<string, mixed>}>
     */
    public $sent = [];

    /**
     * @var array{0: bool, 1: array<int, string>}
     */
    private $answer;

    /**
     * @var Throwable|null
     */
    private $failure;

    /**
     * @param  array{0: bool, 1: array<int, string>}  $answer
     */
    public function __construct(array $answer = [true, []], ?Throwable $failure = null)
    {
        $this->answer = $answer;
        $this->failure = $failure;
    }

    public function report(string $url, string $token, array $payload): array
    {
        $this->sent[] = ['url' => $url, 'token' => $token, 'payload' => $payload];

        if ($this->failure) {
            throw $this->failure;
        }

        return $this->answer;
    }
}
