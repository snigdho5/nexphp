<?php

declare(strict_types=1);

namespace Nex\Testing;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class TestResponse
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getBody(): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $body = $this->getBody();
        $data = json_decode($body, true);
        Assert::assertIsArray($data, "Response body could not be decoded as JSON: $body");
        return $data;
    }

    public function assertStatus(int $expected): static
    {
        $actual = $this->getStatusCode();
        Assert::assertSame(
            $expected,
            $actual,
            "Expected HTTP status $expected but received $actual. Response body: " . $this->getBody()
        );
        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    /**
     * @param array<string, mixed> $expected
     */
    public function assertJson(array $expected): static
    {
        $this->assertArraySubset($expected, $this->json());
        return $this;
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private function assertArraySubset(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            Assert::assertArrayHasKey($key, $actual, "Missing expected JSON key [$key].");
            if (is_array($value) && is_array($actual[$key])) {
                $this->assertArraySubset($value, $actual[$key]);
            } else {
                Assert::assertSame($value, $actual[$key], "Mismatch for JSON key [$key].");
            }
        }
    }

    public function assertHeader(string $name, string $expected): static
    {
        Assert::assertTrue($this->response->hasHeader($name), "Response does not have header [$name].");
        Assert::assertSame($expected, $this->response->getHeaderLine($name));
        return $this;
    }

    public function assertSee(string $needle): static
    {
        Assert::assertStringContainsString($needle, $this->getBody());
        return $this;
    }
}
