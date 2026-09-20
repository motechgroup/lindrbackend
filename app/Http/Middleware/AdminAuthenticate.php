<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;

class AdminAuthenticate extends FilamentAuthenticate
{
    /**
     * Redirect unauthenticated requests to the /access admin portal.
     *
     * @param  Request  $request
     */
    protected function redirectTo($request): ?string
    {
        return url('/access');
    }
}
