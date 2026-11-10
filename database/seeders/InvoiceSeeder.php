<?php

namespace Database\Seeders;

use App\Models\AuditInvoice;
use App\Models\Client;
use App\Models\Detail;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    private const ADMIN = '1000000001';

    /**
     * Mezcla de facturas pendientes (P), confirmadas (C) y anuladas (A).
     * Cuatro ocurren hoy para que GET /invoices/stats no devuelva ceros.
     *
     * Nota: los productos se reestablecen a su stock base en ProductSeeder,
     * por lo que el descuento de facturas confirmadas se aplica SIEMPRE aquí.
     * Ejecutar `php artisan db:seed` completo mantiene todo consistente.
     *
     * @var array<int, array{id: string, client: string, status: string, daysAgo: int, details: array<int, array{product: string, amount: int, price: float}>}>
     */
    private const INVOICES = [
        [
            'id' => 'F0001',
            'client' => 'CLI001',
            'status' => 'C',
            'daysAgo' => 0,
            'details' => [
                ['product' => 'P0001', 'amount' => 2, 'price' => 3500],
                ['product' => 'P0010', 'amount' => 1, 'price' => 6800],
            ],
        ],
        [
            'id' => 'F0002',
            'client' => 'CLI005',
            'status' => 'C',
            'daysAgo' => 0,
            'details' => [
                ['product' => 'P0004', 'amount' => 3, 'price' => 2800],
                ['product' => 'P0007', 'amount' => 1, 'price' => 7500],
            ],
        ],
        [
            'id' => 'F0003',
            'client' => 'CLI002',
            'status' => 'P',
            'daysAgo' => 0,
            'details' => [
                ['product' => 'P0011', 'amount' => 2, 'price' => 5900],
            ],
        ],
        [
            'id' => 'F0004',
            'client' => 'CLI006',
            'status' => 'A',
            'daysAgo' => 0,
            'details' => [
                ['product' => 'P0005', 'amount' => 1, 'price' => 5200],
            ],
        ],
        [
            'id' => 'F0005',
            'client' => 'CLI003',
            'status' => 'C',
            'daysAgo' => 3,
            'details' => [
                ['product' => 'P0002', 'amount' => 1, 'price' => 8900],
                ['product' => 'P0012', 'amount' => 1, 'price' => 5400],
            ],
        ],
        [
            'id' => 'F0006',
            'client' => 'CLI007',
            'status' => 'C',
            'daysAgo' => 5,
            'details' => [
                ['product' => 'P0006', 'amount' => 2, 'price' => 4100],
                ['product' => 'P0008', 'amount' => 1, 'price' => 18500],
            ],
        ],
        [
            'id' => 'F0007',
            'client' => 'CLI001',
            'status' => 'A',
            'daysAgo' => 7,
            'details' => [
                ['product' => 'P0009', 'amount' => 1, 'price' => 16900],
            ],
        ],
    ];

    private int $auditSequence = 1;

    public function run(): void
    {
        foreach (self::INVOICES as $seed) {
            $client = Client::find($seed['client']);
            $invoice = $this->seedInvoice($seed, $client);

            $this->seedDetails($seed, $invoice);
            $this->seedAudits($seed, $invoice, $client);

            if ($seed['status'] === 'C') {
                $this->decrementStock($seed);
            }
        }
    }

    /**
     * @param  array{id: string, client: string, status: string, daysAgo: int, details: array<int, array{product: string, amount: int, price: float}>}  $seed
     */
    private function seedInvoice(array $seed, ?Client $client): Invoice
    {
        $date = $seed['daysAgo'] === 0 ? now() : now()->subDays($seed['daysAgo']);
        $total = collect($seed['details'])->sum(fn (array $detail) => $detail['amount'] * $detail['price']);

        return Invoice::updateOrCreate(
            ['id_invoice' => $seed['id']],
            [
                'date' => $date,
                'total' => $total,
                'status' => $seed['status'],
                'id_client' => $client?->id_client ?? $seed['client'],
            ],
        );
    }

    /**
     * @param  array{id: string, client: string, status: string, daysAgo: int, details: array<int, array{product: string, amount: int, price: float}>}  $seed
     */
    private function seedDetails(array $seed, Invoice $invoice): void
    {
        foreach ($seed['details'] as $line => $detail) {
            Detail::updateOrCreate(
                ['line_number' => (string) ($line + 1), 'id_invoice' => $invoice->id_invoice],
                [
                    'amount' => $detail['amount'],
                    'subtotal' => $detail['amount'] * $detail['price'],
                    'id_product' => $detail['product'],
                ],
            );
        }
    }

    /**
     * @param  array{id: string, client: string, status: string, daysAgo: int, details: array<int, array{product: string, amount: int, price: float}>}  $seed
     */
    private function seedAudits(array $seed, Invoice $invoice, ?Client $client): void
    {
        $date = $invoice->date;
        $total = $invoice->total;
        $vendor = $client?->document_employee;

        $this->createAudit($invoice, $vendor, 'C', null, 'P', null, $total, $date);

        if ($seed['status'] === 'C') {
            $this->createAudit($invoice, self::ADMIN, 'M', 'P', 'C', $total, $total, $date);
        }

        if ($seed['status'] === 'A') {
            $previousStatus = $seed['previous_status'] ?? 'P';
            $this->createAudit($invoice, self::ADMIN, 'A', $previousStatus, 'A', $total, $total, $date);
        }
    }

    private function createAudit(
        Invoice $invoice,
        ?string $employee,
        string $actionType,
        ?string $previousStatus,
        string $newStatus,
        ?float $previousTotal,
        ?float $newTotal,
        $date,
    ): void {
        AuditInvoice::updateOrCreate(
            ['id_audit' => sprintf('AUD%07d', $this->auditSequence++)],
            [
                'id_invoice' => $invoice->id_invoice,
                'document_employee' => $employee ?? self::ADMIN,
                'action_type' => $actionType,
                'action_date' => $date,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
            ],
        );
    }

    /**
     * @param  array{id: string, client: string, status: string, daysAgo: int, details: array<int, array{product: string, amount: int, price: float}>}  $seed
     */
    private function decrementStock(array $seed): void
    {
        foreach ($seed['details'] as $detail) {
            $product = Product::find($detail['product']);

            $product?->decrement('stock', $detail['amount']);
        }
    }
}
