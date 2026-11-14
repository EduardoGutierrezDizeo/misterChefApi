<?php

use App\Models\AuditInvoice;
use App\Models\Client;
use App\Models\Detail;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function loginInvoiceOwnershipUser(string $email, string $password): string
{
    return test()->postJson('/api/v1/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('token');
}

/**
 * @return array<string, mixed>
 */
function invoiceOwnershipPayload(string $idInvoice, string $idClient): array
{
    return [
        'id_invoice' => $idInvoice,
        'id_client' => $idClient,
        'details' => [
            [
                'id_product' => 'P0001',
                'amount' => 2,
            ],
        ],
    ];
}

it('allows a vendor to invoice their own client', function () {
    $token = loginInvoiceOwnershipUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9001', 'CLI001'))
        ->assertCreated()
        ->assertJsonPath('message', 'Factura creada correctamente.')
        ->assertJsonPath('invoice.id_invoice', 'F9001')
        ->assertJsonPath('invoice.id_client', 'CLI001');

    expect(Invoice::find('F9001'))->not->toBeNull();
});

it('forbids a vendor from invoicing another vendor client', function () {
    $token = loginInvoiceOwnershipUser('vendedor@misterchef.com', 'vendedor123');

    $invoicesBefore = Invoice::count();
    $detailsBefore = Detail::count();
    $auditsBefore = AuditInvoice::count();

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9002', 'CLI005'))
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permiso para facturar a este cliente.']);

    expect(Invoice::count())->toBe($invoicesBefore)
        ->and(Detail::count())->toBe($detailsBefore)
        ->and(AuditInvoice::count())->toBe($auditsBefore)
        ->and(Invoice::find('F9002'))->toBeNull();
});

it('forbids a vendor from invoicing an unassigned client', function () {
    Client::where('id_client', 'CLI008')->update(['document_employee' => null]);

    $token = loginInvoiceOwnershipUser('vendedor@misterchef.com', 'vendedor123');

    $invoicesBefore = Invoice::count();
    $detailsBefore = Detail::count();
    $auditsBefore = AuditInvoice::count();

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9003', 'CLI008'))
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permiso para facturar a este cliente.']);

    expect(Invoice::count())->toBe($invoicesBefore)
        ->and(Detail::count())->toBe($detailsBefore)
        ->and(AuditInvoice::count())->toBe($auditsBefore)
        ->and(Invoice::find('F9003'))->toBeNull();
});

it('allows the administrator to invoice any client', function () {
    Client::where('id_client', 'CLI008')->update(['document_employee' => null]);

    $token = loginInvoiceOwnershipUser('admin@misterchef.com', 'admin123');

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9004', 'CLI005'))
        ->assertCreated()
        ->assertJsonPath('invoice.id_client', 'CLI005');

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9005', 'CLI008'))
        ->assertCreated()
        ->assertJsonPath('invoice.id_client', 'CLI008');

    expect(Invoice::find('F9004'))->not->toBeNull()
        ->and(Invoice::find('F9005'))->not->toBeNull();
});

it('keeps the inactive client message for a vendor own inactive client', function () {
    Client::where('id_client', 'CLI001')->update(['status' => false]);

    $token = loginInvoiceOwnershipUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9006', 'CLI001'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No se puede crear una factura para un cliente inactivo.');

    expect(Invoice::find('F9006'))->toBeNull();
});

it('requires authentication to create an invoice', function () {
    $this->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9007', 'CLI001'))
        ->assertUnauthorized();
});

it('does not change product stock after a forbidden invoice', function () {
    $stockBefore = Product::find('P0001')->stock;

    $token = loginInvoiceOwnershipUser('vendedor@misterchef.com', 'vendedor123');

    $this->withToken($token)
        ->postJson('/api/v1/invoices', invoiceOwnershipPayload('F9008', 'CLI005'))
        ->assertForbidden();

    expect(Product::find('P0001')->stock)->toBe($stockBefore);
});
