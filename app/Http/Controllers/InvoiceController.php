<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Detail;
use App\Models\Client;
use App\Models\Product;
use App\Models\AuditInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    // GET /api/v1/invoices
    public function index(Request $request)
    {
        $employee = $request->user();

        $query = Invoice::with(['client', 'details.product']);

        if ($employee->type === 'V') {
            $query->whereHas('client', function ($q) use ($employee) {
                $q->where('document_employee', $employee->document_employee);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json($query->get(), 200);
    }

    // GET /api/v1/invoices/{id}
    public function show(Request $request, $id)
    {
        $employee = $request->user();

        $invoice = Invoice::with(['client', 'details.product'])->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Factura no encontrada.'], 404);
        }

        if ($employee->type === 'V' && $invoice->client->document_employee !== $employee->document_employee) {
            return response()->json(['message' => 'No tienes permiso para ver esta factura.'], 403);
        }

        return response()->json($invoice, 200);
    }

    // GET /api/v1/invoices/{id}/audit
    public function audit($id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Factura no encontrada.'], 404);
        }

        $audits = AuditInvoice::with('employee')
            ->where('id_invoice', $id)
            ->orderBy('action_date', 'desc')
            ->get();

        return response()->json($audits, 200);
    }

    // POST /api/v1/invoices
    public function store(Request $request)
    {
        $request->validate([
            'id_invoice'              => 'required|string|max:5|unique:invoice,id_invoice',
            'id_client'               => 'required|string|exists:client,id_client',
            'details'                 => 'required|array|min:1',
            'details.*.id_product'    => 'required|string|exists:product,id_product',
            'details.*.amount'        => 'required|numeric|min:1',
        ]);

        $client = Client::find($request->id_client);
        if (!$client->status) {
            return response()->json([
                'message' => 'No se puede crear una factura para un cliente inactivo.',
            ], 422);
        }

        $invoice = DB::transaction(function () use ($request) {
            $invoice = Invoice::create([
                'id_invoice' => $request->id_invoice,
                'date'       => now()->toDateString(),
                'total'      => 0,
                'status'     => 'P',
                'id_client'  => $request->id_client,
            ]);

            $total = 0;
            $lineNumber = 1;

            foreach ($request->details as $item) {
                $product  = Product::find($item['id_product']);
                $subtotal = $item['amount'] * $product->selling_price;
                $total   += $subtotal;

                Detail::create([
                    'line_number' => (string) $lineNumber,
                    'amount'      => $item['amount'],
                    'subtotal'    => $subtotal,
                    'id_product'  => $item['id_product'],
                    'id_invoice'  => $invoice->id_invoice,
                ]);

                $lineNumber++;
            }

            $invoice->update(['total' => $total]);

            // Registrar auditoría
            AuditInvoice::create([
                'id_audit'          => strtoupper(Str::random(10)),
                'id_invoice'        => $invoice->id_invoice,
                'document_employee' => request()->user()->document_employee,
                'action_type'       => 'C',
                'previous_status'   => null,
                'new_status'        => 'P',
                'previous_total'    => null,
                'new_total'         => $total,
            ]);

            return $invoice;
        });

        $invoice->load(['client', 'details.product']);

        return response()->json([
            'message' => 'Factura creada correctamente.',
            'invoice' => $invoice,
        ], 201);
    }

    // PATCH /api/v1/invoices/{id}/confirm
    public function confirm(Request $request, $id)
    {
        $invoice = Invoice::with('details.product')->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Factura no encontrada.'], 404);
        }

        if ($invoice->status !== 'P') {
            return response()->json([
                'message' => 'Solo se pueden confirmar facturas en estado Pendiente.',
            ], 422);
        }

        foreach ($invoice->details as $detail) {
            if ($detail->product->stock < $detail->amount) {
                return response()->json([
                    'message' => "Stock insuficiente para '{$detail->product->product_name}'. Disponible: {$detail->product->stock}.",
                ], 422);
            }
        }

        DB::transaction(function () use ($invoice, $request) {
            $previousStatus = $invoice->status;

            foreach ($invoice->details as $detail) {
                $detail->product->decrement('stock', $detail->amount);
            }

            $invoice->update(['status' => 'C']);

            // Registrar auditoría
            AuditInvoice::create([
                'id_audit'          => strtoupper(Str::random(10)),
                'id_invoice'        => $invoice->id_invoice,
                'document_employee' => $request->user()->document_employee,
                'action_type'       => 'M',
                'previous_status'   => $previousStatus,
                'new_status'        => 'C',
                'previous_total'    => $invoice->total,
                'new_total'         => $invoice->total,
            ]);
        });

        $invoice->load(['client', 'details.product']);

        return response()->json([
            'message' => 'Factura confirmada correctamente. Stock actualizado.',
            'invoice' => $invoice,
        ], 200);
    }

    // GET /api/v1/invoices/stats
    // Estadísticas del día para el dashboard del domiciliario
    public function stats(Request $request)
    {
        $employee = $request->user();
        $today    = now()->toDateString();
 
        // Las facturas se relacionan al empleado a través del cliente
        $query = Invoice::whereRaw('DATE(date) = ?', [$today]);
 
        if ($employee->type === 'V') {
            $query->whereHas('client', function ($q) use ($employee) {
                $q->where('document_employee', $employee->document_employee);
            });
        }
 
        $invoices = $query->get();
 
        return response()->json([
            'total_pedidos'      => $invoices->count(),
            'total_ventas'       => round($invoices->sum('total'), 2),
            'clientes_visitados' => $invoices->where('status', 'C')->count(),
        ], 200);
    }

    // PATCH /api/v1/invoices/{id}/cancel
    public function cancel(Request $request, $id)
    {
        $employee = $request->user();

        if ($employee->can_modify_invoice !== 'S') {
            return response()->json([
                'message' => 'No tienes permiso para anular facturas.',
            ], 403);
        }

        $invoice = Invoice::with('details.product')->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Factura no encontrada.'], 404);
        }

        if ($invoice->status === 'A') {
            return response()->json([
                'message' => 'La factura ya se encuentra anulada.',
            ], 422);
        }

        DB::transaction(function () use ($invoice, $employee) {
            $previousStatus = $invoice->status;

            if ($invoice->status === 'C') {
                foreach ($invoice->details as $detail) {
                    $detail->product->increment('stock', $detail->amount);
                }
            }

            $invoice->update(['status' => 'A']);

            // Registrar auditoría
            AuditInvoice::create([
                'id_audit'          => strtoupper(Str::random(10)),
                'id_invoice'        => $invoice->id_invoice,
                'document_employee' => $employee->document_employee,
                'action_type'       => 'A',
                'previous_status'   => $previousStatus,
                'new_status'        => 'A',
                'previous_total'    => $invoice->total,
                'new_total'         => $invoice->total,
            ]);
        });

        $invoice->load(['client', 'details.product']);

        return response()->json([
            'message' => 'Factura anulada correctamente.',
            'invoice' => $invoice,
        ], 200);
    }
}