<?php

namespace App\Planner;

/**
 * What to tell the user about a plan that does offer programmes, but not quite what was asked.
 */
enum PlanNotice: string
{
    /** No marathon of the requested size: the programmes have fewer films (single films at the limit). */
    case FewerFilms = 'fewer_films';
    /** Fewer than the three programmes wanted. */
    case NotEnoughProgrammes = 'not_enough_programmes';
}
