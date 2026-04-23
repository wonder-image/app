<?php

namespace Wonder\App\Models\User;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class User extends Model
{
    public static string $table = 'user';
    public static string $folder = 'app/config/user';
    public static string $icon = 'bi bi-people';

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
                ->reset()
                ->resize([
                    ['width' => 960, 'height' => 960],
                    ['width' => 480, 'height' => 480],
                    ['width' => 240, 'height' => 240],
                    ['width' => 120, 'height' => 120],
                ]),
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
