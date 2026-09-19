<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class AdminAuthenticate extends FilamentAuthenticate
{
    /**
     * Redirect unauthenticated requests to the /access admin portal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request): ?string
    {
        return url('/access');
    }
}
