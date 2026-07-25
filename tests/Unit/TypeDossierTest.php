<?php

namespace Tests\Unit;

use App\Models\TypeDossier;
use Tests\TestCase;

class TypeDossierTest extends TestCase
{
    public function test_est_projet_reconnait_les_codes_projet_et_project(): void
    {
        $p = new TypeDossier(['code' => 'projet']);
        $this->assertTrue($p->estProjet());

        $p2 = new TypeDossier(['code' => 'PROJECT']);
        $this->assertTrue($p2->estProjet());

        $a = new TypeDossier(['code' => 'administratif']);
        $this->assertFalse($a->estProjet());
    }
}
