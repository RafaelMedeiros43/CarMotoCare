<?php

namespace Tests\Feature;

use App\Clients\SapiVehiclesClient;
use App\Exceptions\ProblemException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SapiVehiclesClientTest extends TestCase
{
    private const URL = 'http://sapi-vehicles.test';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const VEHICLE_ID = '5d1d7b0e-3f4a-4a52-9a07-0a4c9b7d1c22';

    private const VEHICLE = [
        'id' => self::VEHICLE_ID,
        'userId' => self::USER_ID,
        'type' => 'CARRO',
        'brand' => 'Volkswagen',
        'model' => 'Golf',
        'firstRegistration' => '2021-06',
        'plate' => 'AB-24-CD',
        'km' => 68420,
        'nextService' => null,
        'openAlerts' => 0,
        'createdAt' => '2026-11-06T10:48:00Z',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sapi_vehicles.url' => self::URL.'/']);
    }

    private function problem(int $status, string $title, ?string $detail = null, array $errors = []): array
    {
        return array_filter([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'errors' => $errors ?: null,
        ]);
    }

    public function test_list_sends_the_user_header_and_query_and_returns_vehicles_and_total(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([self::VEHICLE], 200, ['X-Total-Count' => '7'])]);

        $result = (new SapiVehiclesClient)->listVehicles(self::USER_ID, ['type' => 'CARRO', 'limit' => 5, 'offset' => 10]);

        $this->assertSame([self::VEHICLE], $result['vehicles']);
        $this->assertSame(7, $result['total']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), self::URL.'/vehicles?')
            && str_contains($r->url(), 'type=CARRO') && str_contains($r->url(), 'limit=5') && str_contains($r->url(), 'offset=10')
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_list_total_defaults_to_the_page_size_when_the_header_is_missing(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([self::VEHICLE], 200)]);

        $this->assertSame(1, (new SapiVehiclesClient)->listVehicles(self::USER_ID)['total']);
    }

    public function test_create_posts_the_data_with_the_user_header(): void
    {
        Http::fake([self::URL.'/vehicles' => Http::response(self::VEHICLE, 201)]);

        $data = ['type' => 'CARRO', 'brand' => 'Volkswagen', 'model' => 'Golf', 'firstRegistration' => '2021-06', 'plate' => 'AB-24-CD', 'km' => 68420];
        $vehicle = (new SapiVehiclesClient)->createVehicle(self::USER_ID, $data);

        $this->assertSame(self::VEHICLE, $vehicle);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::URL.'/vehicles'
            && $r->data() === $data
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_get_update_and_delete_use_the_vehicle_url_and_the_user_header(): void
    {
        Http::fake([
            self::URL.'/vehicles/'.self::VEHICLE_ID => Http::sequence()
                ->push(self::VEHICLE, 200)
                ->push(self::VEHICLE, 200)
                ->push('', 204),
        ]);
        $client = new SapiVehiclesClient;

        $this->assertSame(self::VEHICLE, $client->getVehicle(self::USER_ID, self::VEHICLE_ID));
        $this->assertSame(self::VEHICLE, $client->updateVehicle(self::USER_ID, self::VEHICLE_ID, ['model' => 'Polo']));
        $client->deleteVehicle(self::USER_ID, self::VEHICLE_ID);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->data() === ['model' => 'Polo']);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_business_errors_are_rethrown_with_the_same_status_and_field_errors(): void
    {
        $errors = [['field' => 'plate', 'message' => 'Já existe um veículo com esta matrícula.']];

        $cases = [[400, 'Pedido inválido', $errors], [404, 'Não encontrado', []]];
        $headers = ['Content-Type' => 'application/problem+json'];
        // A single fake with a sequence: a second Http::fake() would not replace the first one.
        Http::fake([self::URL.'/*' => Http::sequence()
            ->push($this->problem(400, 'Pedido inválido', 'detalhe', $errors), 400, $headers)
            ->push($this->problem(404, 'Não encontrado', 'detalhe'), 404, $headers)]);

        foreach ($cases as [$status, $title, $fieldErrors]) {
            try {
                (new SapiVehiclesClient)->getVehicle(self::USER_ID, self::VEHICLE_ID);
                $this->fail('ProblemException expected');
            } catch (ProblemException $e) {
                $this->assertSame($status, $e->status);
                $this->assertSame($title, $e->title);
                $this->assertSame('detalhe', $e->detail);
                $this->assertSame($fieldErrors, $e->errors);
            }
        }
    }

    public function test_connection_failure_becomes_502(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        try {
            (new SapiVehiclesClient)->listVehicles(self::USER_ID);
            $this->fail('ProblemException expected');
        } catch (ProblemException $e) {
            $this->assertSame(502, $e->status);
        }
    }

    public function test_server_error_becomes_502_without_leaking_the_body(): void
    {
        Http::fake([self::URL.'/*' => Http::response('SQLSTATE secret', 500)]);

        try {
            (new SapiVehiclesClient)->getVehicle(self::USER_ID, self::VEHICLE_ID);
            $this->fail('ProblemException expected');
        } catch (ProblemException $e) {
            $this->assertSame(502, $e->status);
            $this->assertStringNotContainsString('SQLSTATE', (string) $e->detail);
        }
    }
}
