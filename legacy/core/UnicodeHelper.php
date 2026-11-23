<?php
declare(strict_types=1);

/**
 * Tiện ích chuẩn hóa chuỗi Unicode về chuẩn NFC (Normalization Form C - Precomposed).
 * Giúp loại bỏ triệt để hiện tượng tách dấu thanh tiếng Việt (NFD: kiê´n, thô´ng, vê``).
 */
final class UnicodeHelper
{
    /**
     * Chuẩn hóa đệ quy chuỗi hoặc mảng dữ liệu về Unicode NFC.
     */
    public static function toNfc(mixed $data): mixed
    {
        if (is_string($data)) {
            if ($data === '') {
                return '';
            }
            if (class_exists('Normalizer')) {
                if (!Normalizer::isNormalized($data, Normalizer::FORM_C)) {
                    $normalized = Normalizer::normalize($data, Normalizer::FORM_C);
                    return $normalized !== false ? $normalized : $data;
                }
            }
            return $data;
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $val) {
                $normKey = is_string($key) && class_exists('Normalizer') && !Normalizer::isNormalized($key, Normalizer::FORM_C)
                    ? (Normalizer::normalize($key, Normalizer::FORM_C) ?: $key)
                    : $key;
                $result[$normKey] = self::toNfc($val);
            }
            return $result;
        }

        if (is_object($data)) {
            $vars = get_object_vars($data);
            foreach ($vars as $key => $val) {
                $data->$key = self::toNfc($val);
            }
            return $data;
        }

        return $data;
    }
}
