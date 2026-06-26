<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Usa a NOSSA tela de login Bootstrap (Fortify é só o backend de auth).
        Fortify::loginView(fn () => view('auth.login'));

        // Autenticação contra o GLPI: o login do portal valida usuário+senha no
        // GLPI (initSession). Em caso de sucesso, sincroniza um usuário local
        // (espelho) e guarda o SESSION TOKEN do GLPI na sessão — os repositórios
        // passam a usar esse token, então o GLPI aplica o isolamento por entidade.
        Fortify::authenticateUsing(function (Request $request) {
            $login = trim((string) $request->input('login'));
            $password = (string) $request->input('password');
            if ($login === '' || $password === '') {
                return null;
            }

            $base = rtrim((string) config('glpi.api.url'), '/');
            try {
                $resp = Http::baseUrl($base)->acceptJson()
                    ->withBasicAuth($login, $password)
                    ->get('/initSession', ['get_full_session' => 'true']);
            } catch (\Throwable) {
                return null;
            }
            if (! $resp->successful() || ! $resp->json('session_token')) {
                return null;
            }

            $token = (string) $resp->json('session_token');
            $s = $resp->json('session') ?? [];
            $glpiId = (int) ($s['glpiID'] ?? 0);
            $profile = (string) ($s['glpiactiveprofile']['name'] ?? '');
            $name = (string) ($s['glpifriendlyname'] ?? ($s['glpiname'] ?? $login));

            $user = User::updateOrCreate(
                ['glpi_id' => $glpiId],
                [
                    'name' => $name,
                    'email' => Str::lower($login).'@glpi.local',
                    'role' => self::mapRole($profile),
                    'password' => Hash::make(Str::random(40)),
                ],
            );

            session([
                'glpi_token' => $token,
                'glpi_profile' => $profile,
                'glpi_entity' => $s['glpiactive_entity'] ?? null,
                'glpi_entity_recursive' => (bool) ($s['glpiactive_entity_recursive'] ?? false),
            ]);

            return $user;
        });

        // Ao sair, encerra também a sessão no GLPI (killSession) e limpa o token.
        Event::listen(Logout::class, function () {
            $token = session('glpi_token');
            if ($token) {
                try {
                    Http::baseUrl(rtrim((string) config('glpi.api.url'), '/'))
                        ->acceptJson()->withHeaders(['Session-Token' => $token])->get('/killSession');
                } catch (\Throwable) {
                }
            }
            session()->forget(['glpi_token', 'glpi_profile', 'glpi_entity', 'glpi_entity_recursive']);
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // (mapRole definido abaixo)

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }

    /** Converte o nome do perfil do GLPI no papel do portal. */
    private static function mapRole(string $profile): UserRole
    {
        // 1) Mapeamento explícito por config (config/portal.php) — fonte de verdade.
        $map = (array) config('portal.profile_roles', []);
        if (isset($map[$profile]) && ($r = UserRole::tryFrom($map[$profile]))) {
            return $r;
        }

        // 2) Fallback por palavra-chave (perfis não listados na config).
        $p = Str::lower($profile);

        return match (true) {
            str_contains($p, 'gestor'), str_contains($p, 'admin') => UserRole::Gestor, // 'admin' cobre 'Super-Admin'
            str_contains($p, 'técnico'), str_contains($p, 'tecnico'), str_contains($p, 'technician'), str_contains($p, 'supervisor') => UserRole::Tecnico,
            default => UserRole::Cliente,
        };
    }
}
