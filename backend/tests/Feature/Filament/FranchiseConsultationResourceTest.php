<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FranchiseConsultationResource;
use App\Filament\Resources\FranchiseConsultationResource\Pages\EditFranchiseConsultation;
use App\Filament\Resources\FranchiseConsultationResource\Pages\ListFranchiseConsultations;
use App\Models\FranchiseConsultation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FranchiseConsultationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Franchise Admin',
            'email' => 'admin@freeco.cc',
            'password' => Hash::make('secret-pass'),
        ]);
    }

    private function seedConsultation(array $attrs = []): FranchiseConsultation
    {
        return FranchiseConsultation::create(array_merge([
            'name' => '張三',
            'phone' => '0911-222-333',
            'email' => 'zhang@example.com',
            'source' => 'homepage',
            'note' => '想了解加盟方案',
            'status' => 'new',
        ], $attrs));
    }

    public function test_admin_can_list_consultations(): void
    {
        $a = $this->seedConsultation(['name' => 'Lead A']);
        $b = $this->seedConsultation(['name' => 'Lead B']);

        $this->actingAs($this->makeAdmin());

        Livewire::test(ListFranchiseConsultations::class)
            ->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_status_filter_narrows_to_new(): void
    {
        $newOne = $this->seedConsultation(['name' => 'Pending', 'status' => 'new']);
        $closed = $this->seedConsultation(['name' => 'Done', 'status' => 'closed']);

        $this->actingAs($this->makeAdmin());

        Livewire::test(ListFranchiseConsultations::class)
            ->filterTable('status', 'new')
            ->assertCanSeeTableRecords([$newOne])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_mark_contacted_action_updates_status_and_timestamp(): void
    {
        $row = $this->seedConsultation(['status' => 'new']);
        $this->actingAs($this->makeAdmin());

        Livewire::test(ListFranchiseConsultations::class)
            ->callTableAction('mark_contacted', $row);

        $row->refresh();
        $this->assertSame('contacted', $row->status);
        $this->assertNotNull($row->contacted_at);
    }

    public function test_admin_can_edit_status_and_admin_note(): void
    {
        $row = $this->seedConsultation(['status' => 'new']);
        $this->actingAs($this->makeAdmin());

        Livewire::test(EditFranchiseConsultation::class, ['record' => $row->id])
            ->fillForm([
                'status' => 'qualified',
                'admin_note' => '電話已聯繫，下週簽約',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $row->refresh();
        $this->assertSame('qualified', $row->status);
        $this->assertSame('電話已聯繫，下週簽約', $row->admin_note);
    }

    public function test_admin_index_url_resolves(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get(FranchiseConsultationResource::getUrl('index'))->assertOk();
    }
}
