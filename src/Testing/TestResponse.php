<?php

declare(strict_types=1);

namespace Plugs\Testing;

use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class TestResponse
{
    /**
     * Create a new test response instance.
     *
     * @param ResponseInterface $baseResponse
     */
    public function __construct(
        public readonly ResponseInterface $baseResponse
    ) {
    }

    /**
     * Assert that the response has a given status code.
     *
     * @param int $status
     * @return $this
     */
    public function assertStatus(int $status): self
    {
        PHPUnit::assertEquals($status, $this->baseResponse->getStatusCode());
        return $this;
    }

    /**
     * Assert that the response has a success status code.
     *
     * @return $this
     */
    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    /**
     * Assert that the response contains the given JSON.
     *
     * @param array $data
     * @return $this
     */
    public function assertJson(array $data): self
    {
        $actual = json_decode((string) $this->baseResponse->getBody(), true);

        PHPUnit::assertIsArray($actual, 'Response is not valid JSON');

        foreach ($data as $key => $value) {
            PHPUnit::assertArrayHasKey($key, $actual);
            PHPUnit::assertEquals($value, $actual[$key]);
        }

        return $this;
    }

    /**
     * Assert that the response has the given header.
     *
     * @param string $header
     * @param string|null $value
     * @return $this
     */
    public function assertHeader(string $header, string $value = null): self
    {
        PHPUnit::assertTrue($this->baseResponse->hasHeader($header));

        if ($value !== null) {
            PHPUnit::assertEquals($value, $this->baseResponse->getHeaderLine($header));
        }

        return $this;
    }

    /**
     * Dynamic proxy to the base response.
     */
    public function __call($method, $args)
    {
        return $this->baseResponse->{$method}(...$args);
    }
}
