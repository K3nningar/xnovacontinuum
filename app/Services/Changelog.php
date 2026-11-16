<?php

namespace App\Services;

use UnexpectedValueException;

class Changelog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return json_decode(file_get_contents(resource_path('js/changelog.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function currentVersion(): string
    {
        $version = $this->definitions()[0]['version'] ?? null;

        if (! is_string($version) || $version === '') {
            throw new UnexpectedValueException('The changelog must start with the current game version.');
        }

        return $version;
    }
}
