<?php

namespace Tests\Feature;

use App\Models\{Role, User, Address, Territory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private Territory $territory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin    = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->first();
        $this->operator = User::factory()->create([
            'role_id' => Role::where('slug', 'operator')->value('id'),
        ]);
        $this->territory = Territory::create(['name' => 'Центр']);
    }

    /** @test */
    public function addresses_index_is_accessible(): void
    {
        $this->actingAs($this->operator)
             ->get(route('addresses.index'))
             ->assertStatus(200)
             ->assertStatus(200);
    }

    /** @test */
    public function can_create_address_manually(): void
    {
        $this->actingAs($this->operator)
             ->post(route('addresses.store'), [
                 'street'          => 'Пушкина',
                 'building'        => '10',
                 'city'            => 'Макеевка',
                 'territory_id'    => $this->territory->id,
                 'subscriber_name' => 'Иван Петров',
                 'phone'           => '+79491112233',
                 'contract_no'     => '12345',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'street'   => 'Пушкина',
            'building' => '10',
            'phone'    => '+79491112233',
        ]);
    }

    /** @test */
    public function street_and_building_are_required(): void
    {
        $this->actingAs($this->operator)
             ->post(route('addresses.store'), [
                 'street'   => '',
                 'building' => '',
             ])
             ->assertSessionHasErrors(['street', 'building']);
    }

    /** @test */
    public function can_autogenerate_apartments(): void
    {
        $this->actingAs($this->operator)
             ->post(route('addresses.store'), [
                 'street'   => 'Советская',
                 'building' => '5',
                 'apt_from' => 1,
                 'apt_to'   => 10,
             ])
             ->assertRedirect();

        // Должно создать 10 записей (кв. 1—10)
        $this->assertDatabaseCount('addresses', 10);
        $this->assertDatabaseHas('addresses', ['street' => 'Советская', 'building' => '5', 'apartment' => '1']);
        $this->assertDatabaseHas('addresses', ['street' => 'Советская', 'building' => '5', 'apartment' => '10']);
    }

    /** @test */
    public function can_update_address(): void
    {
        $address = Address::create(['street' => 'Старая', 'building' => '1']);

        $this->actingAs($this->operator)
             ->put(route('addresses.update', $address), [
                 'street'          => 'Новая',
                 'building'        => '2',
                 'subscriber_name' => 'Обновлённый абонент',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'id'              => $address->id,
            'street'          => 'Новая',
            'subscriber_name' => 'Обновлённый абонент',
        ]);
    }

    /** @test */
    public function can_delete_address(): void
    {
        $address = Address::create(['street' => 'Удаляемая', 'building' => '99']);

        $this->actingAs($this->admin)
             ->delete(route('addresses.destroy', $address))
             ->assertRedirect();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    /** @test */
    public function search_returns_matching_addresses(): void
    {
        Address::create(['street' => 'Ленина', 'building' => '5', 'phone' => '+79490001111']);
        Address::create(['street' => 'Маркса', 'building' => '3', 'phone' => '+79490002222']);

        $response = $this->actingAs($this->operator)
             ->getJson(route('addresses.search', ['q' => 'Ленина']));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => Address::where('street', 'Ленина')->value('id')]);
    }

    /** @test */
    public function import_csv_creates_addresses(): void
    {
        Storage::fake('local');

        $csv = "street,building,apartment,city,subscriber_name,phone,contract_no\n"
             . "Проспект Мира,10,1,Макеевка,Петров И.И.,+79491234567,11111\n"
             . "Проспект Мира,10,2,Макеевка,Сидоров А.А.,+79497654321,22222\n";

        $file = UploadedFile::fake()->createWithContent('addresses.csv', $csv);

        $this->actingAs($this->admin)
             ->post(route('addresses.import'), ['file' => $file])
             ->assertRedirect();

        $this->assertDatabaseHas('addresses', ['street' => 'Проспект Мира', 'apartment' => '1']);
        $this->assertDatabaseHas('addresses', ['street' => 'Проспект Мира', 'apartment' => '2']);
    }
}
