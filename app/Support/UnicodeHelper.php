<?php

declare(strict_types=1);

namespace App\Support;

use Normalizer;

/**
 * Tiện ích chuẩn hóa chuỗi Unicode về chuẩn NFC (Normalization Form C - Precomposed).
 * Loại bỏ hoàn toàn hiện tượng tách dấu thanh tiếng Việt (NFD).
 */
final class UnicodeHelper
{
    public static function toNfc(mixed $data): mixed
    {
        if (is_string($data)) {
            if ($data === '') {
                return '';
            }
            if (class_exists(Normalizer::class) && !Normalizer::isNormalized($data, Normalizer::FORM_C)) {
                $normalized = Normalizer::normalize($data, Normalizer::FORM_C);
                return $normalized !== false ? $normalized : $data;
            }
            return $data;
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $val) {
                $normKey = is_string($key) && class_exists(Normalizer::class) && !Normalizer::isNormalized($key, Normalizer::FORM_C)
                    ? (Normalizer::normalize($key, Normalizer::FORM_C) ?: $key)
                    : $key;
                $result[$normKey] = self::toNfc($val);
            }
            return $result;
        }

        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $val) {
                $data->$key = self::toNfc($val);
            }
            return $data;
        }

        return $data;
    }

    public static function avatarUrl(?string $avatar): string
    {
        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            $avatar = 'assets/images/avatars/default.svg';
        }
        if (preg_match('~^(?:https?:)?//|^(?:data|blob):~i', $avatar)) {
            return $avatar;
        }
        return asset(ltrim($avatar, '/'));
    }
}
