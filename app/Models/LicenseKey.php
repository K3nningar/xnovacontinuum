<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['key_hash', 'issued_by_user_id', 'redeemed_by_user_id', 'redeemed_at'])]
class LicenseKey extends Model
{
    public static function generateCode(): string
    {
        return 'XNOVA-'.strtoupper(implode('-', str_split(bin2hex(random_bytes(16)), 8)));
    }

    public static function digest(string $plainText): string
    {
        return hash('sha256', Str::upper(trim($plainText)));
    }

    /**
     * The account that redeemed this key, if it has been used.
     *
     * @return BelongsTo<User, $this>
     */
    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }
}
