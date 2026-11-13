<?php

namespace Tests\Feature\Admin;

use App\Models\GateStation;
use App\Models\User;
use App\Support\GateNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GateStationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_adds_tabs_with_suggested_names_and_codes(): void
    {
        $this->actingAs($this->admin)->get('/tab-gerbang')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/GateStations/Index')
            ->where('suggestion', ['name' => 'Tab 1', 'code' => 'TAB1'])
        );

        $this->actingAs($this->admin)->post('/tab-gerbang', ['name' => 'Tab 1', 'code' => ' tab1 '])->assertSessionHas('success');
        $this->assertSame('TAB1', GateStation::sole()->code);

        $this->actingAs($this->admin)->get('/tab-gerbang')->assertInertia(fn (Assert $page) => $page
            ->where('suggestion', ['name' => 'Tab 2', 'code' => 'TAB2'])
            ->where('stations.0.isOnline', false)
        );

        $this->actingAs($this->admin)->post('/tab-gerbang', ['name' => 'Tab 2', 'code' => 'TAB1'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/tab-gerbang', ['name' => 'Tab 2', 'code' => 'tab 2!'])->assertSessionHasErrors('code');
    }

    public function test_changing_the_code_logs_the_tablet_out(): void
    {
        $station = GateStation::factory()->create(['name' => 'Tab 1', 'code' => 'TAB1']);
        $oldToken = $station->login_token;

        $this->actingAs($this->admin)->put("/tab-gerbang/{$station->id}", ['name' => 'Tab Depan', 'code' => 'TAB1'])->assertSessionHas('success');
        $this->assertSame($oldToken, $station->fresh()->login_token);

        $this->actingAs($this->admin)->put("/tab-gerbang/{$station->id}", ['name' => 'Tab Depan', 'code' => 'depan1'])->assertSessionHas('success');
        $this->assertNotSame($oldToken, $station->fresh()->login_token);
        $this->assertSame('DEPAN1', $station->fresh()->code);
    }

    public function test_admin_revokes_and_restores_a_tab(): void
    {
        $station = GateStation::factory()->create();

        $this->actingAs($this->admin)->post("/tab-gerbang/{$station->id}/cabut")->assertSessionHas('success');
        $this->assertTrue($station->fresh()->isRevoked());

        $this->actingAs($this->admin)->delete("/tab-gerbang/{$station->id}/cabut")->assertSessionHas('success');
        $this->assertFalse($station->fresh()->isRevoked());

        $this->actingAs($this->admin)->delete("/tab-gerbang/{$station->id}")->assertSessionHas('success');
        $this->assertModelMissing($station);
    }

    public function test_admin_limits_tabs_to_the_school_network(): void
    {
        $this->actingAs($this->admin)->put('/jaringan-gerbang', ['allowed_ips' => "203.0.113.7\n10.0.0.0/24"])->assertSessionHas('success');

        $network = app(GateNetwork::class);
        $this->assertSame(['203.0.113.7', '10.0.0.0/24'], $network->allowedIps());
        $this->assertTrue($network->allows('10.0.0.42'));
        $this->assertFalse($network->allows('198.51.100.1'));

        $this->actingAs($this->admin)->put('/jaringan-gerbang', ['allowed_ips' => 'bukan-ip'])->assertSessionHasErrors('allowed_ips');

        $this->actingAs($this->admin)->put('/jaringan-gerbang', ['allowed_ips' => ''])->assertSessionHas('success');
        $this->assertFalse(app(GateNetwork::class)->isRestricted());
    }

    public function test_wrong_codes_on_the_code_screen_are_throttled(): void
    {
        GateStation::factory()->create(['code' => 'TAB1']);

        foreach (range(1, 10) as $attempt) {
            $this->post('/kode', ['code' => "SALAH{$attempt}"])->assertSessionHasErrors('code');
        }

        $this->post('/kode', ['code' => 'TAB1'])->assertSessionHasErrors(['code' => 'Terlalu banyak kode salah. Coba lagi dalam 60 detik.']);
    }

    public function test_only_admins_manage_tabs(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/tab-gerbang')->assertForbidden();
        $this->actingAs(User::factory()->guruPiket()->create())->post('/tab-gerbang', ['name' => 'Tab 9', 'code' => 'TAB9'])->assertForbidden();
    }
}
