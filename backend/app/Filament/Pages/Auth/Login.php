<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Modules\Auth\Services\Sub2RebateAuthService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $result = app(Sub2RebateAuthService::class)->validate(
            (string) ($data['email'] ?? ''),
            (string) ($data['password'] ?? '')
        );

        if ($result === null || ($result['error'] ?? null) === 'disabled') {
            $this->throwFailureValidationException();
        }

        /** @var User $user */
        $user = $result['user'];

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $this->throwFailureValidationException();
        }

        Auth::guard(Filament::getAuthGuard())->login($user, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => '账号或密码错误，或没有后台权限',
        ]);
    }
}
