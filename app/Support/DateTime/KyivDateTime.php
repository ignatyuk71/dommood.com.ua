<?php

namespace App\Support\DateTime;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class KyivDateTime
{
    public const TIMEZONE = 'Europe/Kyiv';

    public static function timezone(): string
    {
        return (string) config('app.display_timezone', self::TIMEZONE);
    }

    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::today(self::timezone());
    }

    public static function fromStorage(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->setTimezone(self::timezone());
            }

            return CarbonImmutable::parse((string) $value, self::storageTimezone())
                ->setTimezone(self::timezone());
        } catch (Throwable) {
            return null;
        }
    }

    public static function toStorage(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->setTimezone(self::storageTimezone());
            }

            if (trim((string) $value) === '') {
                return null;
            }

            return CarbonImmutable::parse((string) $value, self::timezone())
                ->setTimezone(self::storageTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromAdminInput(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['!Y-m-d\TH:i:s', '!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value, self::timezone());

                if ($date !== false) {
                    return $date->setTimezone(self::storageTimezone());
                }
            } catch (Throwable) {
                continue;
            }
        }

        return self::toStorage($value);
    }

    public static function format(DateTimeInterface|string|null $value, string $format): ?string
    {
        return self::fromStorage($value)?->format($format);
    }

    public static function dateTime(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'd.m.Y H:i');
    }

    public static function date(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'd.m.Y');
    }

    public static function input(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y-m-d\TH:i');
    }

    public static function sql(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y-m-d H:i:s');
    }

    public static function isoDate(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y-m-d');
    }
}
