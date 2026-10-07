<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Language version of a showtime, as tagged by the chain: dubbed in French, original with or
 * without subtitles, French with subtitles.
 */
enum ShowtimeVersion: string
{
    case Vf = 'vf';
    case Vost = 'vost';
    case Vo = 'vo';
    case Vfst = 'vfst';

    /**
     * Translation key of the name shown in the forms.
     */
    public function label(): string
    {
        return match ($this) {
            self::Vf => 'planner.form.version.vf',
            self::Vost => 'planner.form.version.vost',
            self::Vo => 'planner.form.version.vo',
            self::Vfst => 'planner.form.version.vfst',
        };
    }

    /**
     * Original version: the searches also include the films made in the language of the cinema.
     */
    public function isOriginal(): bool
    {
        return match ($this) {
            self::Vost, self::Vo => true,
            self::Vf, self::Vfst => false,
        };
    }
}
