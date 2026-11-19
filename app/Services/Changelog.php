<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Implements the Changelog application service.
 */

namespace App\Services;

use UnexpectedValueException;

class Changelog
{
    /**
     * Loads release definitions from the shared frontend JSON source; invalid JSON raises a decoding exception.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return json_decode(file_get_contents(resource_path('js/changelog.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the first release definition's version and fails fast when the changelog has no valid current version.
     */
    public function currentVersion(): string
    {
        $version = $this->definitions()[0]['version'] ?? null;

        if (! is_string($version) || $version === '') {
            throw new UnexpectedValueException('The changelog must start with the current game version.');
        }

        return $version;
    }
}
