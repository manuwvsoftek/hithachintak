<?php

declare(strict_types=1);

namespace App\Libraries\Enrolment;

/**
 * The enrolment form's "Age / DOB" field accepts either a plain age in
 * years or a date of birth — whichever the Karyakarta has to hand — with
 * no fixed format. This is the one place that turns that free text into
 * an actual age in years, so the minimum-age rule can be enforced
 * consistently wherever the field is validated (the form's own JS, and
 * EnrolmentController server-side, for the primary member and every
 * family member row alike).
 */
class AgeParser
{
    /** Accepted DOB formats, tried in order; the first clean, unambiguous match wins. */
    private const DATE_FORMATS = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y'];

    /**
     * @return int|null The computed age in whole years, or null if $value
     *                   is empty, unparseable as either a plain age or one
     *                   of the accepted date formats, or parses to a date
     *                   that isn't a real, past, plausible birth date.
     */
    public static function yearsFromInput(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // A plain whole number is taken as the age itself — reject
        // anything absurd rather than silently accepting e.g. "9999".
        if (preg_match('/^\d{1,3}$/', $value)) {
            $age = (int) $value;

            return $age <= 130 ? $age : null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $dob = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if (! $dob || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                continue;
            }

            $today = new \DateTime('today');
            if ($dob > $today) {
                continue; // a birth date in the future isn't a real DOB
            }

            $age = $today->diff($dob)->y;
            if ($age > 130) {
                continue; // implausibly old — likely a typo, not a real DOB
            }

            return $age;
        }

        return null;
    }

    public const MIN_AGE = 15;

    public static function meetsMinimumAge(?string $value): bool
    {
        $age = self::yearsFromInput($value);

        return $age !== null && $age >= self::MIN_AGE;
    }
}
