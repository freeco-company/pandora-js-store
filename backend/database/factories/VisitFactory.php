<?php

namespace Database\Factories;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for Visit rows in tests. Only populates the fields the
 * pipeline report + source-bucket tests actually inspect; everything else
 * gets a sensible default or NULL.
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'visitor_id'      => fn () => fake()->sha256(),
            'session_id'      => fn () => fake()->uuid(),
            'ip'              => fn () => fake()->ipv4(),
            'country'         => 'TW',
            'user_agent'      => 'Mozilla/5.0',
            'device_type'     => 'desktop',
            'os'              => 'OS X',
            'browser'         => 'Chrome',
            'referer_source'  => 'direct',
            'path'            => '/',
            'landing_path'    => '/',
            'visited_at'      => fn () => now(),
        ];
    }
}
