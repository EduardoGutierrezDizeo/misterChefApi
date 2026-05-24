<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/v1/products
    public function index(Request $request)
    {
        $query = Product::with('productType');

        // Filtro opcional por status: ?status=true o ?status=false
        if ($request->has('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->get();

        return response()->json($products, 200);
    }

    // GET /api/v1/products/{id}
    public function show($id)
    {
        $product = Product::with('productType')->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        return response()->json($product, 200);
    }

    // GET /api/v1/products/low-stock
    public function lowStock()
    {
        $products = Product::with('productType')
            ->whereColumn('stock', '<=', 'minimun_stock')
            ->get();

        return response()->json($products, 200);
    }

    // POST /api/v1/products
    public function store(Request $request)
    {
        $request->validate([
            'id_product'     => 'required|string|max:5|unique:product,id_product',
            'product_name'   => 'required|string|max:25',
            'stock'          => 'required|integer|min:0',
            'minimun_stock'  => 'required|integer|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'status'         => 'required|boolean',
            'id_produc_type' => 'required|string|exists:product_type,id_produc_type',
        ]);

        $product = Product::create($request->all());
        $product->load('productType');

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'product' => $product,
        ], 201);
    }

    // PUT /api/v1/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $request->validate([
            'product_name'   => 'sometimes|string|max:25',
            'minimun_stock'  => 'sometimes|integer|min:0',
            'selling_price'  => 'sometimes|numeric|min:0',
            'id_produc_type' => 'sometimes|string|exists:product_type,id_produc_type',
        ]);

        $product->update($request->only([
            'product_name',
            'minimun_stock',
            'selling_price',
            'id_produc_type',
        ]));

        $product->load('productType');

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'product' => $product,
        ], 200);
    }

    // PATCH /api/v1/products/{id}/status
    public function changeStatus(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $request->validate([
            'status' => 'required|boolean',
        ]);

        $product->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Estado del producto actualizado correctamente.',
            'product' => $product,
        ], 200);
    }
}