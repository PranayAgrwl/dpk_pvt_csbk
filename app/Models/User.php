<?php
/**
 * User model
 * ------------------------------------------------------------
 * Represents a row in the `users` table.
 *
 * Columns: id, name, username, password, created_at, updated_at
 *
 * Helpers:
 *   User::byUsername('admin')
 *   User::verifyPassword($hash, 'plain')
 *   User::hashPassword('plain')
 *   User::register(['name'=>'p','username'=>'admin','password'=>'321'])
 *   User::updateProfile($id, ['name'=>'..', 'username'=>'..'])
 *   User::changePassword($id, 'newPlain')
 * ------------------------------------------------------------
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Env;
use App\Core\Model;

class User extends Model
{
    /** @var string Table name used by the parent Model helpers. */
    protected static string $table = 'users';

    /**
     * Find a user by their username (returns null if none).
     * @return array<string,mixed>|null
     */
    public static function byUsername(string $username): ?array
    {
        return self::findBy('username', $username);
    }

    /**
     * Verify a plaintext password against a stored bcrypt hash.
     */
    public static function verifyPassword(string $hash, string $plain): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Hash a plaintext password using bcrypt at the cost configured in .env.
     */
    public static function hashPassword(string $plain): string
    {
        $cost = (int) Env::get('BCRYPT_COST', 12);
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Create a new user. Expects keys: name, username, password (plaintext).
     * @return int New user's id.
     */
    public static function register(array $data): int
    {
        return self::create([
            'name'       => $data['name'],
            'username'   => $data['username'],
            'password'   => self::hashPassword((string) $data['password']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update editable profile fields (name, username) for a user.
     * Returns the number of affected rows.
     */
    public static function updateProfile(int $id, array $data): int
    {
        $clean = [];
        if (array_key_exists('name', $data))     $clean['name']     = (string) $data['name'];
        if (array_key_exists('username', $data)) $clean['username'] = (string) $data['username'];
        if (empty($clean)) return 0;
        $clean['updated_at'] = date('Y-m-d H:i:s');
        return self::updateById($id, $clean);
    }

    /**
     * Replace a user's password (plain string is hashed first).
     */
    public static function changePassword(int $id, string $newPlain): int
    {
        return self::updateById($id, [
            'password'   => self::hashPassword($newPlain),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check if a username is already used by SOMEONE OTHER than $excludeId.
     * Handy for the profile-edit form so the user can keep their own username.
     */
    public static function usernameTakenByOther(string $username, int $excludeId): bool
    {
        $row = Database::queryOne(
            "SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1",
            [$username, $excludeId]
        );
        return $row !== null;
    }
}
