<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ACSIFonctionsSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StructureSeeder;
use Database\Seeders\UsersAvecDirectionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersAvecDirectionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_ne_reimpose_pas_changement_mot_de_passe(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            StructureSeeder::class,
            ACSIFonctionsSeeder::class,
            UsersAvecDirectionsSeeder::class,
        ]);

        $admin = User::where('email', 'admin@acsi.cg')->firstOrFail();
        $admin->update(['must_change_password' => false]);
        $ancienHash = $admin->password;

        $this->seed(UsersAvecDirectionsSeeder::class);

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertSame($ancienHash, $admin->password);
    }
}
