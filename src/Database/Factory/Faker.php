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
    private static array $firstNames = [
        'James',
        'Mary',
        'Robert',
        'Patricia',
        'John',
        'Jennifer',
        'Michael',
        'Linda',
        'William',
        'Elizabeth',
        'David',
        'Barbara',
        'Richard',
        'Susan',
        'Joseph',
        'Jessica',
        'Thomas',
        'Sarah',
        'Christopher',
        'Karen',
        'Celio',
        'Tilly',
        'Daniel',
        'Nancy',
        'Matthew',
        'Margaret',
        'Anthony',
        'Sandra',
        'Mark',
        'Ashley',
        'Donald',
        'Dorothy',
        'Steven',
        'Kimberly',
        'Andrew',
        'Emily',
        'Paul',
        'Donna',
        'Joshua',
        'Michelle',
        'Kenneth',
        'Carol',
        'Kevin',
        'Amanda',
        'Brian',
        'Melissa',
        'George',
        'Deborah',
        'Edward',
        'Stephanie'
    ];
    private static array $lastNames = [
        'Smith',
        'Johnson',
        'Williams',
        'Brown',
        'Jones',
        'Garcia',
        'Miller',
        'Davis',
        'Rodriguez',
        'Martinez',
        'Hernandez',
        'Lopez',
        'Gonzales',
        'Wilson',
        'Anderson',
        'Thomas',
        'Taylor',
        'Moore',
        'Jackson',
        'Martin',
        'Natti',
        'Tinny',
        'Lee',
        'Perez',
        'Thompson',
        'White',
        'Harris',
        'Sanchez',
        'Clark',
        'Ramirez',
        'Lewis',
        'Robinson',
        'Walker',
        'Young',
        'Allen',
        'King',
        'Wright',
        'Scott',
        'Torres',
        'Nguyen',
        'Hill',
        'Flores',
        'Green',
        'Adams',
        'Nelson',
        'Baker',
        'Hall',
        'Rivera',
        'Campbell',
        'Mitchell'
    ];
    private static array $cities = [
        'New York',
        'Los Angeles',
        'Chicago',
        'Houston',
        'Phoenix',
        'Philadelphia',
        'San Antonio',
        'San Diego',
        'Dallas',
        'San Jose',
        'Fort Worth',
        'El Paso',
        'Lagos',
        'Abuja',
        'Ibadan',
        'Port Harcourt',
        'Kano',
        'Oyo',
        'Ogun',
        'Ondo',
        'Osun',
        'Ilorin',
        'Benin',
        'London',
        'Manchester',
        'Birmingham',
        'Paris',
        'Berlin',
        'Madrid',
        'Rome',
        'Tokyo',
        'Beijing',
        'Sydney',
        'Toronto',
        'Dubai'
    ];
    private static array $countries = [
        'United States',
        'United Kingdom',
        'Canada',
        'Australia',
        'Germany',
        'France',
        'Japan',
        'China',
        'Brazil',
        'Nigeria',
        'India',
        'Mexico',
        'South Africa',
        'Russia',
        'Italy',
        'Spain',
        'Netherlands',
        'Sweden',
        'Switzerland',
        'Singapore'
    ];
    private static array $domains = ['example.com', 'test.org', 'sample.net', 'demo.io', 'plugs.dev', 'dummy.com', 'fake.net', 'celio.io'];
    private static array $words = [
        'the',
        'of',
        'to',
        'and',
        'a',
        'in',
        'is',
        'it',
        'you',
        'that',
        'he',
        'was',
        'for',
        'on',
        'are',
        'with',
        'as',
        'I',
        'his',
        'they',
        'be',
        'at',
        'one',
        'have',
        'this',
        'from',
        'or',
        'had',
        'by',
        'hot',
        'word',
        'but',
        'what',
        'some',
        'we',
        'can',
        'out',
        'other',
        'were',
        'all',
        'there',
        'when',
        'up',
        'use',
        'your',
        'how',
        'said',
        'an',
        'each',
        'she',
        'which',
        'do',
        'how',
        'their',
        'if',
        'will',
        'up',
        'other',
        'about',
        'out',
        'many',
        'then',
        'them',
        'these',
        'so',
        'some',
        'her',
        'would',
        'make',
        'like',
        'him',
        'into',
        'time',
        'has',
        'look',
        'two',
        'more',
        'write',
        'go',
        'see',
        'number',
        'no',
        'way',
        'could',
        'people',
        'my',
        'than',
        'first',
        'water',
        'been',
        'call',
        'who',
        'oil',
        'its',
        'now',
        'find',
        'long',
        'down',
        'day',
        'did',
        'get',
        'come',
        'made',
        'may',
        'part',
        'over',
        'new',
        'sound',
        'take',
        'only',
        'little',
        'work',
        'know',
        'place',
        'year',
        'live',
        'me',
        'back',
        'give',
        'most',
        'very',
        'after',
        'thing',
        'our',
        'just',
        'name',
        'good',
        'sentence',
        'man',
        'say',
        'great',
        'where',
        'help',
        'through',
        'much',
        'before',
        'line',
        'right',
        'too',
        'mean',
        'old',
        'any',
        'same',
        'tell',
        'boy',
        'follow',
        'came',
        'want',
        'show',
        'also',
        'around',
        'form',
        'three',
        'small',
        'set',
        'put',
        'end',
        'does',
        'another',
        'well',
        'large',
        'must',
        'big',
        'even',
        'such',
        'because',
        'turn',
        'here',
        'why',
        'ask',
        'went',
        'men',
        'read',
        'need',
        'land',
        'different',
        'home',
        'us',
        'move',
        'try',
        'kind',
        'hand',
        'picture',
        'again',
        'change',
        'off',
        'play',
        'spell',
        'air',
        'away',
        'animal',
        'house',
        'point',
        'page',
        'letter',
        'mother',
        'answer',
        'found',
        'study',
        'still',
        'learn',
        'should',
        'world',
        'high',
        'every',
        'near',
        'add',
        'food',
        'between',
        'own',
        'below',
        'country',
        'plant',
        'last',
        'school',
        'father',
        'keep',
        'tree',
        'never',
        'start',
        'city',
        'earth',
        'eyes',
        'light',
        'thought',
        'head',
        'under',
        'story',
        'saw',
        'left',
        'few',
        'while',
        'along',
        'might',
        'close',
        'something',
        'seem',
        'next',
        'hard',
        'open',
        'example',
        'begin',
        'life',
        'always',
        'those',
        'both',
        'paper',
        'together',
        'got',
        'group',
        'often',
        'run',
        'important',
        'until',
        'children',
        'side',
        'feet',
        'car',
        'miles',
        'night',
        'walk',
        'white',
        'sea',
        'began',
        'grow',
        'took',
        'river',
        'four',
        'carry',
        'state',
        'once',
        'book',
        'hear',
        'stop',
        'without',
        'second',
        'late',
        'miss',
        'idea',
        'enough',
        'eat',
        'face',
        'watch',
        'far',
        'real',
        'almost',
        'let',
        'above',
        'girl',
        'sometimes',
        'mountain',
        'cut',
        'young',
        'talk',
        'soon',
        'list',
        'song',
        'being',
        'leave',
        'family',
        'body',
        'music',
        'color',
        'stand',
        'sun',
        'questions',
        'fish',
        'area',
        'mark',
        'dog',
        'horse',
        'birds',
        'problem',
        'complete',
        'room',
        'knew',
        'since',
        'ever',
        'piece',
        'told',
        'usually',
        'friends',
        'easy',
        'heard',
        'order',
        'red',
        'door',
        'sure',
        'become',
        'top',
        'ship',
        'across',
        'today',
        'during',
        'short',
        'better',
        'best',
        'however',
        'low',
        'hours',
        'black',
        'products',
        'happened',
        'whole',
        'measure',
        'remember',
        'early',
        'waves',
        'reached',
        'listen',
        'wind',
        'rock',
        'space',
        'covered',
        'fast',
        'several',
        'hold',
        'himself',
        'toward',
        'five',
        'step',
        'morning',
        'passed',
        'vowel',
        'true',
        'hundred',
        'against',
        'patterns',
        'numeral',
        'table',
        'north',
        'slowly',
        'money',
        'map',
        'farm',
        'pulled',
        'draw',
        'voice',
        'seen',
        'cold',
        'cried',
        'plan',
        'notice',
        'south',
        'sing',
        'war',
        'ground',
        'fall',
        'king',
        'town',
        'unit',
        'figure',
        'certain',
        'field',
        'travel',
        'wood',
        'fire',
        'upon'
    ];
    private static array $companies = ['Google', 'Microsoft', 'Apple', 'Meta', 'Amazon', 'Antigravity', 'Celionatti', 'Innovate', 'Pixel', 'Vertex', 'Cyberdyne', 'Umbrella', 'Stark', 'Wayne', 'Oscorp', 'Aperture', 'Hooli', 'Pied Piper'];
    private static array $companySuffixes = ['Inc', 'Corp', 'LLC', 'Group', 'Solutions', 'Tech', 'Ventures', 'Associates', 'International', 'Limited'];
    private static array $jobTitles = ['Developer', 'Manager', 'Designer', 'Engineer', 'CEO', 'CTO', 'Analyst', 'Consultant', 'Architect', 'Specialist', 'Coordinator', 'Director'];
    private static array $streets = ['Main St', 'High St', 'Broadway', 'Bridge Rd', 'Station Rd', 'Oxford St', 'Victoria Rd', 'London Rd', 'Church St', 'Park St', 'Maple Ave', 'Oak St', 'Washington Blvd'];
    private static array $titles = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.', 'Rev.'];
    private static array $suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'PhD', 'MD'];

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
     * Generate a title
     */
    public function title(): string
    {
        return $this->randomValue(self::$titles);
    }

    /**
     * Generate a suffix
     */
    public function suffix(): string
    {
        return $this->randomValue(self::$suffixes);
    }

    /**
     * Generate a company name
     */
    public function company(): string
    {
        return $this->randomValue(self::$companies) . ' ' . $this->randomValue(self::$companySuffixes);
    }

    /**
     * Generate a job title
     */
    public function jobTitle(): string
    {
        return $this->randomValue(self::$jobTitles);
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
     * Generate a street address
     */
    public function address(): string
    {
        return rand(100, 9999) . ' ' . $this->randomValue(self::$streets) . ', ' . $this->randomValue(self::$cities);
    }

    /**
     * Generate a city
     */
    public function city(): string
    {
        return $this->randomValue(self::$cities);
    }

    /**
     * Generate a country
     */
    public function country(): string
    {
        return $this->randomValue(self::$countries);
    }

    /**
     * Generate a random word
     */
    public function word(): string
    {
        return $this->randomValue(self::$words);
    }

    /**
     * Generate random words
     * 
     * @param int $count Number of words to generate
     * @param bool $asText Whether to return as a space-separated string
     * @return array|string
     */
    public function words(int $count = 3, bool $asText = false): array|string
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = $this->word();
        }

        return $asText ? implode(', ', $words) : $words;
    }

    /**
     * Generate a sentence
     */
    public function sentence(int $wordCount = 6): string
    {
        $words = $this->words($wordCount);
        $sentence = implode(' ', $words);
        return ucfirst($sentence) . '.';
    }

    /**
     * Generate a paragraph
     */
    public function paragraph(int $sentenceCount = 3): string
    {
        $sentences = [];
        for ($i = 0; $i < $sentenceCount; $i++) {
            $sentences[] = $this->sentence(rand(6, 12));
        }

        return implode(' ', $sentences);
    }

    /**
     * Generate a slug from a sentence
     */
    public function slug(int $wordCount = 3): string
    {
        return implode('-', $this->words($wordCount));
    }

    /**
     * Generate a number between range
     */
    public function numberBetween(int $min = 0, int $max = 100): int
    {
        return rand($min, $max);
    }

    /**
     * Generate a float between range
     */
    public function randomFloat(int $decimals = 2, float $min = 0, float $max = 100): float
    {
        $scale = pow(10, $decimals);
        return rand((int) ($min * $scale), (int) ($max * $scale)) / $scale;
    }

    /**
     * Generate a boolean
     */
    public function boolean(int $chanceOfTrue = 50): bool
    {
        return rand(1, 100) <= $chanceOfTrue;
    }

    /**
     * Generate a random date
     */
    public function date(string $format = 'Y-m-d'): string
    {
        $timestamp = rand(1, time());
        return date($format, $timestamp);
    }

    /**
     * Generate a random datetime
     */
    public function dateTime(string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = rand(1, time());
        return date($format, $timestamp);
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

    /**
     * Generate text with specified character limit
     */
    public function text(int $limit = 200): string
    {
        $text = $this->paragraph(rand(3, 6));

        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit - 3) . '...';
    }

    /**
     * Generate a username
     */
    public function userName(): string
    {
        $formats = [
            fn() => strtolower($this->firstName()) . rand(1, 999),
            fn() => strtolower($this->firstName()) . '.' . strtolower($this->lastName()),
            fn() => strtolower($this->firstName()) . '_' . strtolower($this->lastName()),
        ];

        return $this->randomElement($formats)();
    }

    /**
     * Generate a random URL
     */
    public function url(): string
    {
        return 'https://' . $this->randomValue(self::$domains) . '/' . $this->slug();
    }

    /**
     * Generate a DateTime between two dates
     */
    public function dateTimeBetween(string $startDate = '-30 years', string $endDate = 'now', string $format = 'Y-m-d H:i:s'): string
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        $timestamp = rand($start, $end);

        return date($format, $timestamp);
    }

    /**
     * Generate a placeholder image URL
     */
    public function imageUrl(int $width = 640, int $height = 480, ?string $category = null): string
    {
        $url = "https://via.placeholder.com/{$width}x{$height}";

        if ($category) {
            $url .= "?text=" . urlencode($category);
        }

        return $url;
    }

    /**
     * Generate random HTML content
     */
    public function randomHtml(int $count = 3): string
    {
        $html = '';
        for ($i = 0; $i < $count; $i++) {
            $html .= '<p>' . $this->paragraph(rand(2, 5)) . '</p>';
        }
        return $html;
    }

    /**
     * Generate a random hex color
     */
    public function hexColor(): string
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random RGB color string
     */
    public function rgbColor(): string
    {
        return sprintf('rgb(%d,%d,%d)', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
    }

    /**
     * Generate a random IPv4 address
     */
    public function ipv4(): string
    {
        return long2ip(mt_rand(0, mt_getrandmax()));
    }

    /**
     * Generate a random IPv6 address
     */
    public function ipv6(): string
    {
        $res = [];
        for ($i = 0; $i < 8; $i++) {
            $res[] = dechex(mt_rand(0, 65535));
        }
        return implode(':', $res);
    }

    /**
     * Generate a random MAC address
     */
    public function macAddress(): string
    {
        $res = [];
        for ($i = 0; $i < 6; $i++) {
            $res[] = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
        }
        return strtoupper(implode(':', $res));
    }

    /**
     * Generate a random User Agent
     */
    public function userAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
        ];
        return $this->randomElement($agents);
    }

    /**
     * Generate a random password
     */
    public function password(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        return substr(str_shuffle($chars), 0, $length);
    }

}
