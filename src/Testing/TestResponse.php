<?php

declare(strict_types=1);

namespace Plugs\Testing;

use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * TestResponse
 */
class TestResponse
{
    /**
     * The response instance.
     */
    protected ResponseInterface $response;

    /**
     * Create a new test response instance.
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Assert that the response has a given status code.
     */
    public function assertStatus(int $status): self
    {
        PHPUnit::assertEquals($status, $this->response->getStatusCode(), "Expected status code {$status} but received " . $this->response->getStatusCode());

        return $this;
    }

    /**
     * Assert that the response has a success status code.
     * @return self
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
        $json = json_decode((string) $this->response->getBody(), true);

        PHPUnit::assertIsArray($json, "Response body is not valid JSON.");

        foreach ($data as $key => $value) {
            PHPUnit::assertArrayHasKey($key, $json, "Response JSON does not contain key {$key}.");
            PHPUnit::assertEquals($value, $json[$key], "Response JSON key {$key} does not match expected value.");
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
    public function assertHeader(string $header, ?string $value = null): self
    {
        PHPUnit::assertTrue($this->response->hasHeader($header), "Response does not have header {$header}.");

        if ($value !== null) {
            PHPUnit::assertEquals($value, $this->response->getHeaderLine($header), "Response header {$header} does not match expected value.");
        }

        return $this;
    }

    /**
     * Dynamic proxy to the base response.
     */
    public function __call($method, $args)
    {
        return $this->response->{$method}(...$args);
    }
}
