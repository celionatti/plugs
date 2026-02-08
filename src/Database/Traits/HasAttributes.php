<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use DateTime;
use Exception;
use Plugs\Database\Collection;

/**
 * @phpstan-ignore trait.unused
 */
trait HasAttributes
{
    protected $attributes = [];
    protected $original = [];
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $hidden = [];
    protected $casts = [];
    protected $appends = [];
    protected $dates = [];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $visible = [];
    protected $castNulls = true;

    public function setRawAttributes(array|object $attributes, bool $sync = true): self
    {
        $this->attributes = $this->parseAttributes($attributes);

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    public function syncOriginal(): self
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function fill(array|object $attributes)
    {
        $attributes = $this->parseAttributes($attributes);

        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        if (in_array('*', $this->guarded)) {
            return false;
        }

        return !in_array($key, $this->guarded);
    }

    public function forceFill(array|object $attributes)
    {
        $attributes = $this->parseAttributes($attributes);

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttribute(string $key)
    {
        // Check for accessor method (getXxxAttribute)
        $accessor = 'get' . $this->studly($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            $value = $this->attributes[$key] ?? null;

            return $this->$accessor($value);
        }

        // Check for relationship
        if (array_key_exists($key, $this->relations ?? [])) {
            return $this->relations[$key];
        }

        // Check if it's a relationship method
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        $value = $this->attributes[$key] ?? null;

        // Apply casts
        if (isset($this->casts[$key]) && $value !== null) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationValue(string $key)
    {
        if (!isset($this->relations[$key])) {
            $this->relations[$key] = $this->$key();
        }

        return $this->relations[$key];
    }

    public function setAttribute(string $key, $value): void
    {
        // Check for mutator method (setXxxAttribute)
        $mutator = 'set' . $this->studly($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $this->$mutator($value);

            return;
        }

        // Cast value before storing if cast is defined
        if (isset($this->casts[$key])) {
            $value = $this->castForStorage($key, $value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Cast a value for storage in the database.
     */
    protected function castForStorage(string $key, $value)
    {
        $castType = $this->casts[$key];

        // Handle custom cast classes
        if (is_string($castType) && class_exists($castType) && is_subclass_of($castType, \Plugs\Database\Contracts\CastsAttributes::class)) {
            $caster = new $castType();
            return $caster->set($this, $key, $value, $this->attributes);
        }

        switch ($castType) {
            case 'array':
            case 'json':
            case 'object':
            case 'collection':
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                return $value;

            case 'encrypted':
                return $this->encrypt($value);

            case 'datetime':
            case 'date':
                if ($value instanceof \DateTimeInterface) {
                    return $value->format($this->dateFormat);
                }

                return $value;

            default:
                return $value;
        }
    }

    /**
     * Convert a string to StudlyCase.
     */
    protected function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        $studly = array_map('ucfirst', $words);

        return implode('', $studly);
    }

    protected function castAttribute($key, $value)
    {
        if ($value === null && !$this->castNulls) {
            return null;
        }

        if (!isset($this->casts[$key])) {
            // Check if it's in the dates array or a timestamp column
            if ($this->isDateAttribute($key)) {
                $datetime = $this->asDateTime($value);

                // Return as string by default for easier usage
                return $datetime ? $datetime->format($this->dateFormat) : null;
            }

            return $value;
        }

        $castType = $this->casts[$key];

        // Handle custom cast classes
        if (is_string($castType) && class_exists($castType) && is_subclass_of($castType, \Plugs\Database\Contracts\CastsAttributes::class)) {
            $caster = new $castType();
            return $caster->get($this, $key, $value, $this->attributes);
        }

        // Handle null values based on cast type
        if ($value === null) {
            return $this->getNullCastValue($castType);
        }

        // Special casts
        if ($castType === 'encrypted') {
            return $this->decrypt($value);
        }

        if ($castType === 'collection') {
            $data = is_string($value) ? json_decode($value, true) : $value;

            return new Collection(is_array($data) ? $data : []);
        }

        // Standard casts
        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;

            case 'float':
            case 'double':
            case 'real':
                return (float) $value;

            case 'decimal':
                if (strpos($castType, ':') !== false) {
                    $parts = explode(':', $castType);
                    $decimals = isset($parts[1]) ? (int) $parts[1] : 2;

                    return number_format((float) $value, $decimals, '.', '');
                }

                return number_format((float) $value, 2, '.', '');

            case 'string':
                return (string) $value;

            case 'bool':
            case 'boolean':
                return $this->castToBoolean($value);

            case 'array':
            case 'json':
                return $this->fromJson($value);

            case 'object':
                return $this->fromJson($value, false);

            case 'datetime':
            case 'date':
                $datetime = $this->asDateTime($value);

                // Return as formatted string to prevent "Object could not be converted to string" errors
                return $datetime ? $datetime->format($this->dateFormat) : null;

            case 'timestamp':
                return $this->asTimestamp($value);

            case 'immutable_datetime':
                $datetime = $this->asDateTimeImmutable($value);

                return $datetime ? $datetime->format($this->dateFormat) : null;

            default:
                // Support for custom cast format like 'datetime:Y-m-d'
                if (strpos($castType, 'datetime:') === 0) {
                    $format = substr($castType, 9);
                    $datetime = $this->asDateTime($value, $format);

                    return $datetime ? $datetime->format($format) : null;
                }

                if (strpos($castType, 'date:') === 0) {
                    $format = substr($castType, 5);
                    $datetime = $this->asDateTime($value, $format);

                    return $datetime ? $datetime->format($format) : null;
                }

                return $value;
        }
    }

    protected function getNullCastValue($castType)
    {
        switch ($castType) {
            case 'array':
            case 'json':
                return [];
            case 'collection':
                return new Collection([]);
            case 'object':
                return null;
            default:
                return null;
        }
    }

    protected function castToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    protected function isDateAttribute($key): bool
    {
        // Check timestamps
        /** @phpstan-ignore-next-line */
        if (property_exists($this, 'timestamps') && $this->timestamps && in_array($key, ['created_at', 'updated_at'])) {
            return true;
        }

        // Check soft delete column
        /** @phpstan-ignore-next-line */
        if (property_exists($this, 'softDelete') && $this->softDelete && $key === ($this->deletedAtColumn ?? 'deleted_at')) {
            return true;
        }

        // Check dates array
        return in_array($key, $this->dates);
    }

    protected function asDateTime($value, $format = null)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeImmutable) {
            return DateTime::createFromImmutable($value);
        }

        if (is_numeric($value)) {
            $datetime = new DateTime();
            $datetime->setTimestamp((int) $value);

            return $datetime;
        }

        if (is_string($value)) {
            if ($format && $format !== $this->dateFormat) {
                $datetime = DateTime::createFromFormat($format, $value);
                if ($datetime !== false) {
                    return $datetime;
                }
            }

            $datetime = DateTime::createFromFormat($this->dateFormat, $value);
            if ($datetime !== false) {
                return $datetime;
            }

            try {
                return new DateTime($value);
            } catch (Exception $e) {
                throw new Exception("Could not parse datetime value: {$value}");
            }
        }

        return null;
    }

    protected function asDateTimeImmutable($value)
    {
        $datetime = $this->asDateTime($value);

        if ($datetime === null) {
            return null;
        }

        return \DateTimeImmutable::createFromMutable($datetime);
    }

    protected function asTimestamp($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $datetime = $this->asDateTime($value);

        return $datetime ? $datetime->getTimestamp() : null;
    }

    protected function fromJson($value, $asArray = true)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, $asArray);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }

            return $decoded;
        }

        return $asArray ? (array) $value : (object) $value;
    }

    /**
     * @phpstan-consistent-constructor
     */
    public static function createFromJson(string $json)
    {
        $instance = new static();

        return $instance->fill($instance->fromJson($json));
    }

    /**
     * Parse attributes from array or object.
     */
    protected function parseAttributes(array|object $attributes): array
    {
        if (is_object($attributes)) {
            if (method_exists($attributes, 'toArray')) {
                return $attributes->toArray();
            }

            return (array) $attributes;
        }

        return $attributes;
    }

    protected function encrypt(string $value): string
    {
        $key = getenv('APP_KEY') ?: throw new Exception('APP_KEY not set');

        // Derive a proper key
        $salt = random_bytes(16);
        $derivedKey = hash_pbkdf2('sha256', $key, $salt, 10000, 32, true);

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $derivedKey, OPENSSL_RAW_DATA, $iv);

        return base64_encode($salt . $iv . $encrypted);
    }

    protected function decrypt(string $value): string
    {
        $key = getenv('APP_KEY') ?: throw new Exception('APP_KEY not set');
        $data = base64_decode($value);

        $salt = substr($data, 0, 16);
        $iv = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        $derivedKey = hash_pbkdf2('sha256', $key, $salt, 10000, 32, true);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $derivedKey, OPENSSL_RAW_DATA, $iv);
    }

    private function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();

        if ($key === null) {
            return !empty($dirty);
        }

        return array_key_exists($key, $dirty);
    }

    public function getOriginal(?string $key = null)
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    public function toArray(): array
    {
        $attributes = $this->getArrayableAttributes();

        // Add appended attributes
        foreach ($this->appends as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        // Add loaded relationships
        if (property_exists($this, 'relations')) {
            foreach ($this->relations as $key => $value) {
                if (in_array($key, $this->hidden)) {
                    continue;
                }

                if ($value instanceof Collection) {
                    $attributes[$key] = $value->all();
                    /** @phpstan-ignore-next-line */
                } else if (isset($value) && method_exists($value, 'toArray')) {
                    $attributes[$key] = $value->toArray();
                } else {
                    $attributes[$key] = $value;
                }
            }
        }

        return $attributes;
    }

    protected function getArrayableAttributes(): array
    {
        $attributes = $this->attributes;

        if (count($this->visible) > 0) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (count($this->hidden) > 0) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }
}
