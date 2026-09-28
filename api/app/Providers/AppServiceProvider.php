<?php

namespace App\Providers;

use App\Listeners\BroadcastDatabaseNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O link de redefinição de senha aponta para o SPA.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $base = rtrim(config('app.frontend_url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$base}/reset-password?token={$token}&email={$email}";
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = call_user_func(ResetPassword::$createUrlCallback, $notifiable, $token);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Redefinição de senha — FlowTree')
                ->greeting('Olá!')
                ->line('Recebemos um pedido para redefinir a senha da sua conta no FlowTree.')
                ->action('Redefinir minha senha', $url)
                ->line("Este link expira em {$minutes} minutos.")
                ->line('Se você não solicitou a redefinição, ignore este e-mail.')
                ->salutation('Equipe FlowTree');
        });

        $this->configureRateLimiting();

        // Toda notificação gravada no banco é reemitida em tempo real (Reverb).
        Event::listen(NotificationSent::class, BroadcastDatabaseNotification::class);
    }

    /**
     *  - `api`  : tráfego autenticado, por usuário (cai para IP se anônimo).
     *  - `auth` : login e fluxo de senha, por IP — estrito, contra força bruta.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });
    }
}
