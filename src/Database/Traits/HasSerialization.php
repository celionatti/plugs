<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\Serialized;
use ReflectionClass;

trait HasSerialization
{
    /**
     * The active serialization profile name.
     */
    protected ?string $activeProfile = null;

    /**
     * Cached serialization profiles for the model class.
     */
    protected static array $serializationProfiles = [];

    /**
     * Set the active serialization profile for this instance.
     */
    public function serializeAs(string $name): self
    {
        $this->activeProfile = $name;

        return $this;
    }

    /**
     * Get the current active serialization profile configuration.
     */
    public function getActiveSerializationProfile(): ?array
    {
        if ($this->activeProfile === null) {
            return null;
        }

        $class = static::class;

        if (!isset(static::$serializationProfiles[$class])) {
            static::bootHasSerialization();
        }

        return static::$serializationProfiles[$class][$this->activeProfile] ?? null;
    }

    /**
     * Boot the serialization trait and cache attributes.
     */
    public static function bootHasSerialization(): void
    {
        $class = static::class;

        if (isset(static::$serializationProfiles[$class])) {
            return;
        }

        static::$serializationProfiles[$class] = [];

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(Serialized::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            static::$serializationProfiles[$class][$instance->profile] = [
                'visible' => $instance->visible,
                'hidden' => $instance->hidden,
                'appends' => $instance->appends,
            ];
        }
    }
}
