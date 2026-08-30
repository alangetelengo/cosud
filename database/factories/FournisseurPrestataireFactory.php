<?php

namespace Database\Factories;

use App\Models\FournisseurPrestataire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FournisseurPrestataire>
 */
class FournisseurPrestataireFactory extends Factory
{
    protected $model = FournisseurPrestataire::class;

    public function definition(): array
    {
        $nom = fake()->unique()->company();

        return [
            'nom' => $nom,
            'nom_normalise' => FournisseurPrestataire::normaliserNom($nom),
            'type' => fake()->randomElement(FournisseurPrestataire::TYPES),
            'email' => fake()->optional()->companyEmail(),
            'telephone' => fake()->optional()->numerify('06########'),
            'telephone_2' => null,
            'notifier_telephone' => true,
            'notifier_telephone_2' => true,
            'type_contrat' => fake()->optional()->sentence(4),
            'a_contrat' => fake()->boolean(60),
            'a_dossier_fiscal' => fake()->boolean(40),
            'observation' => fake()->optional()->sentence(),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
