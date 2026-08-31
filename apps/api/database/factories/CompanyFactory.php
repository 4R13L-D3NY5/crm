<?php

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->company(),
            'industry' => fake()->randomElement(['Tecnologia', 'Salud', 'Retail', 'Servicios']),
            'website' => fake()->url(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'status' => fake()->randomElement(['active', 'lead', 'inactive']),
            'notes' => fake()->sentence(),
        ];
    }
}
