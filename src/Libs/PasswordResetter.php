<?php

namespace AporteWeb\Dashboard\Libs;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Cache\RedisStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

class PasswordResetter
{
    public function resetMultiple(array $userUuids, $actor = null)
    {
        $users = User::whereIn('uuid', $userUuids)->get();
        $count = 0;

        foreach ($users as $user) {
            $tempPassword = Str::random(12);
            $user->password = bcrypt($tempPassword);
            $user->must_change_password = true;
            $user->save();

            // Si el usuario esta logueado, invalidar su sesión
            $this->invalidateUserSessions($user);

            // Enviar correo con la nueva contraseña temporal
            $loginUrl = url('/login');
            Mail::to($user->email)
                ->send(new \App\Mail\SendNotification(
                    "Hola {$user->name},<br><br>"
                    . "Tu contraseña temporal para el usuario: <strong>{$user->username}</strong>, es: <strong>{$tempPassword}</strong><br>Inicia sesión aquí: <a href=\"{$loginUrl}\">{$loginUrl}</a><br>Por favor, inicia sesión y cambia tu contraseña inmediatamente.",
                    'Recuperación de Contraseña'
                ));
            
            $actorName = $actor ? $actor->name : 'system';
            $description = "El usuario " . $actorName . " restableció la contraseña del usuario {$user->name}";

            $activity = activity()
                ->causedBy($actor)
                ->onModel($user)
                ->withRef('uuid', $user->uuid)
                ->forEvent('reset-password');
            
            // Intentar capturar IP y User Agent si están disponibles (contexto web)
            try {
                if (request()->ip()) {
                    $activity->withProperties([
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            } catch (\Throwable $th) {
                // Ignorar si no hay request context
            }
               
            $activity->log($description);
            $count++;
        }

        return ['message' => 'Contraseñas restablecidas para ' . $count . ' usuarios.'];
    }

    protected function invalidateUserSessions(User $user): void
    {
        $userId = $user->getAuthIdentifier();

        try {
            $driver = Config::get('session.driver');

            if ($driver === 'database') {
                $this->invalidateDatabaseSessions($userId);
                return;
            }

            if ($driver === 'file') {
                $this->invalidateFileSessions($userId);
                return;
            }

            $handler = Session::getHandler();

            if ($handler instanceof CacheBasedSessionHandler) {
                $this->invalidateCacheBasedSessions($handler, $user);
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo invalidar las sesiones del usuario', [
                'user_id' => $userId,
                'user_uuid' => $user->uuid ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function invalidateDatabaseSessions($userId): void
    {
        $table = Config::get('session.table', 'sessions');
        $connection = Config::get('session.connection');

        $query = $connection
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        $query->where('user_id', $userId)->delete();
    }

    protected function invalidateFileSessions($userId): void
    {
        $path = Config::get('session.files', App::storagePath('framework/sessions'));

        if (! is_dir($path)) {
            return;
        }

        $files = glob($path.'/*');

        if (! is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (! is_file($file) || substr($file, -9) === '.gitignore') {
                continue;
            }

            $payload = @file_get_contents($file);

            if ($payload !== false && $this->sessionPayloadBelongsToUser($payload, $userId)) {
                @unlink($file);
            }
        }
    }

    protected function invalidateCacheBasedSessions(CacheBasedSessionHandler $handler, User $user): void
    {
        $store = $handler->getCache()->getStore();

        if (! $store instanceof RedisStore) {
            return;
        }

        $this->invalidateRedisSessions($handler, $store, $user);
    }

    protected function invalidateRedisSessions(CacheBasedSessionHandler $handler, RedisStore $store, User $user): void
    {
        $connection = $store->connection();

        $connectionPrefix = match (true) {
            $connection instanceof PhpRedisConnection => (string) $connection->_prefix(''),
            $connection instanceof PredisConnection => (string) ($connection->getOptions()->prefix ?? ''),
            default => '',
        };

        $prefix = $connectionPrefix.$store->getPrefix();
        $pattern = $prefix.'*';

        $defaultCursor = match (true) {
            $connection instanceof PhpRedisConnection && extension_loaded('redis') && version_compare(phpversion('redis'), '6.1.0', '>=') => null,
            default => '0',
        };

        $cursor = $defaultCursor;

        do {
            $scanResult = $connection->scan($cursor, ['match' => $pattern, 'count' => 100]);

            if (! is_array($scanResult) || count($scanResult) !== 2) {
                break;
            }

            [$cursor, $keys] = $scanResult;

            if (! is_array($keys) || empty($keys)) {
                continue;
            }

            foreach (array_unique($keys) as $redisKey) {
                if (! is_string($redisKey) || strpos($redisKey, $prefix) !== 0) {
                    continue;
                }

                $sessionId = substr($redisKey, strlen($prefix));

                if ($sessionId === false || $sessionId === '') {
                    continue;
                }

                $payload = $handler->read($sessionId);

                if ($this->sessionPayloadBelongsToUser($payload, $user->getAuthIdentifier())) {
                    $handler->destroy($sessionId);
                }
            }
        } while ($cursor !== 0 && $cursor !== '0' && $cursor !== null);
    }

    protected function sessionPayloadBelongsToUser(?string $payload, $userId): bool
    {
        if (! is_string($payload) || $payload === '') {
            return false;
        }

        $data = $this->decodeSessionPayload($payload);

        if (! is_array($data)) {
            return false;
        }

        $userId = (string) $userId;

        foreach ($data as $key => $value) {
            if (! is_string($key) || strpos($key, 'login_') !== 0) {
                continue;
            }

            if ((string) $value === $userId) {
                return true;
            }
        }

        return false;
    }

    protected function decodeSessionPayload(string $payload): array
    {
        $serialization = Config::get('session.serialization', 'php');

        if ($serialization === 'json') {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        $decoded = @unserialize($payload, ['allowed_classes' => false]);

        return is_array($decoded) ? $decoded : [];
    }
}
