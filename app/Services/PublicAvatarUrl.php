<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicAvatarUrl
{
    public function for(Request $request, string $path): string
    {
        if (config('filesystems.disks.public.driver') === 'local') {
            return $request->getSchemeAndHttpHost().'/storage/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url($path);
    }
}
