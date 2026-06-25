<?php

namespace Wonder\Data;

use Exception;
use Wonder\Data\Fields\Date;
use Wonder\Data\Fields\Email;
use Wonder\Data\Fields\File;
use Wonder\Data\Fields\Image;
use Wonder\Data\Fields\Json;
use Wonder\Data\Fields\Number;
use Wonder\Data\Fields\Password;
use Wonder\Data\Fields\Text;
use Wonder\Data\Fields\Tin;
use Wonder\Data\Fields\Vat;

class UploadSchema
{
    private const FIELDS = [
        'text' => Text::class,
        'number' => Number::class,
        'date' => Date::class,
        'email' => Email::class,
        'json' => Json::class,
        'password' => Password::class,
        'file' => File::class,
        'image' => Image::class,
        'tin' => Tin::class,
        'vat' => Vat::class,
    ];

    public string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public static function key(string $key): static
    {
        return new self($key);
    }

    public function __call(string $method, array $args)
    {
        $method = strtolower($method);
        $class = self::FIELDS[$method] ?? null;

        if ($class !== null) {
            return new $class($this->key);
        }

        throw new Exception("Campo {$method} non supportato in UploadSchema.");
    }
}
