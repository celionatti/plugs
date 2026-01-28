<?php

declare(strict_types=1);

namespace Plugs\Database\Factory;

use DateTime;

/**
 * Faker
 * 
 * A lightweight, zero-dependency fake data generator for Plugs.
 * 
 * @package Plugs\Database\Factory
 */
class Faker
{
    private static array $firstNames = ['James', 'Mary', 'Robert', 'Patricia', 'John', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Christopher', 'Karen', 'Celio', 'Tilly'];
    private static array $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzales', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Natti', 'Tinny'];
    private static array $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Fort Worth', 'El Paso', 'Lagos', 'Abuja', 'Ibadan', 'Port Harcourt', 'Kano', 'Oyo', 'Ogun', 'Ondo', 'Osun', 'Ilorin', 'Benin'];
    private static array $countries = ['United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'Japan', 'China', 'Brazil', 'Nigeria'];
    private static array $domains = ['example.com', 'test.org', 'sample.net', 'demo.io', 'plugs.dev'];
    private static array $words = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua'];

    protected bool $useUnique = false;
    protected array $uniqueValues = [];

    /**
     * Set the generator to return unique values
     */
    public function unique(): static
    {
        $this->useUnique = true;
        return $this;
    }

    /**
     * Generate a name
     */
    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    /**
     * Generate a first name
     */
    public function firstName(): string
    {
        return $this->randomValue(self::$firstNames);
    }

    /**
     * Generate a last name
     */
    public function lastName(): string
    {
        return $this->randomValue(self::$lastNames);
    }

    /**
     * Generate a safe email
     */
    public function email(): string
    {
        $user = strtolower($this->firstName()) . '.' . strtolower($this->lastName()) . rand(1, 999);
        $domain = $this->randomValue(self::$domains);

        $email = "{$user}@{$domain}";

        if ($this->useUnique && in_array($email, $this->uniqueValues)) {
            return $this->email();
        }

        if ($this->useUnique) {
            $this->uniqueValues[] = $email;
        }

        return $email;
    }

    /**
     * Alias for email()
     */
    public function safeEmail(): string
    {
        return $this->email();
    }

    /**
     * Generate a phone number
     */
    public function phone(): string
    {
        return sprintf('+1 (%03d) %03d-%04d', rand(200, 999), rand(100, 999), rand(1000, 9999));
    }

    /**
     * Generate a random word
     */
    public function word(): string
    {
        return $this->randomValue(self::$words);
    }

    /**
     * Generate a sentence
     */
    public function sentence(int $wordCount = 6): string
    {
        $words = [];
        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $this->word();
        }

        $sentence = implode(' ', $words);
        return ucfirst($sentence) . '.';
    }

    /**
     * Generate a number between range
     */
    public function numberBetween(int $min = 0, int $max = 100): int
    {
        return rand($min, $max);
    }

    /**
     * Generate a boolean
     */
    public function boolean(int $chanceOfTrue = 50): bool
    {
        return rand(1, 100) <= $chanceOfTrue;
    }

    /**
     * Generate a UUID v4
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Get a random element from array
     */
    public function randomElement(array $array): mixed
    {
        return $this->randomValue($array);
    }

    /**
     * Get a random value from array
     */
    protected function randomValue(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    /**
     * Handle magic calls for simple data types
     */
    public function __get(string $name): mixed
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        }

        return null;
    }
}
