<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Panel sign-in that ignores the letter case of the typed email.
 *
 * PostgreSQL compares strings case-sensitively and invited operators are
 * stored in lowercase, so "Jane@Example.com" would otherwise be rejected as
 * wrong credentials.
 */
class Login extends BaseLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $credentials = parent::getCredentialsFromFormData($data);
        $credentials['email'] = $this->storedEmailFor((string) $credentials['email']);

        return $credentials;
    }

    /**
     * Resolve the typed email to the exact value on record. Falls back to the
     * typed value when there is no match, or when the match is ambiguous.
     */
    protected function storedEmailFor(string $typed): string
    {
        $typed = trim($typed);
        $provider = Filament::auth()->getProvider(); /** @phpstan-ignore-line */
        if (! $provider instanceof EloquentUserProvider) {
            return $typed;
        }

        $matches = $provider->createModel()->newQuery()
            ->whereRaw('LOWER(email) = ?', [Str::lower($typed)])
            ->limit(2)
            ->pluck('email');

        if ($matches->contains($typed)) {
            return $typed;
        }

        return $matches->count() === 1 ? (string) $matches->first() : $typed;
    }
}
