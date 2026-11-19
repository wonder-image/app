<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\App\Support\SyncAnonymize;
use Wonder\App\Support\SyncClass;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class User extends Model
{
    public static string $table = 'user';
    public static string $folder = 'app/config/user';
    public static string $icon = 'bi bi-people';

    public static function syncClass(): ?SyncClass
    {
        return SyncSchema::content();
    }

    /** Utenti, anche clienti del frontend: copiati in staging solo anonimizzati. */
    public static function syncAnonymize(): array
    {
        return [
            'name' => SyncAnonymize::Name,
            'surname' => SyncAnonymize::Surname,
            'email' => SyncAnonymize::Email,
            'phone' => SyncAnonymize::Phone,
            'username' => SyncAnonymize::Username,
            'profile_picture' => SyncAnonymize::Clear,
            'password' => SyncAnonymize::Clear,
        ];
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('name'),
            Column::key('surname'),
            Column::key('email')->unique(),
            Column::key('phone')->unique(),
            Column::key('username')->unique(),
            Column::key('profile_picture')->json(),
            Column::key('color'),
            Column::key('password'),
            Column::key('email_verified')->bool()->default('0'),
            Column::key('email_verified_at')->datetime()->null(),
            Column::key('authority')->json(),
            Column::key('area')->json(),
            Column::key('active')->default('true'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->sanitizeFirst(),
            Field::key('surname')->text()->sanitizeFirst(),
            Field::key('email')->text()->unique(),
            Field::key('phone')->text()->unique(),
            Field::key('username')->text()->unique(),
            Field::key('profile_picture')->image()
                ->extensions(['png', 'jpg', 'jpeg'])
                ->maxSize(1)
                ->maxFile(1)
                ->dir('/profile-picture/')
                ->responsive(),
            Field::key('color')->text(),
            Field::key('password')->text(),
            Field::key('email_verified')->text(),
            Field::key('email_verified_at')->text(),
            Field::key('authority')->text()->json()->sanitize(false),
            Field::key('area')->text()->json()->sanitize(false),
            Field::key('active')->text(),
        ];
    }
}
