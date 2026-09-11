<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    private function only(Request $request, array $keys): void
    {
        if (array_diff(array_keys($request->all()), $keys)) {
            throw ValidationException::withMessages(['request' => 'Unexpected cart parameter.']);
        }
    }

    public function index(Request $request)
    {
        $this->only($request, []);

        return response()->json($this->cart->read($request->user()));
    }

    public function store(Request $request)
    {
        $this->only($request, ['produit_id', 'quantity']);
        $data = $request->validate(['produit_id' => 'required|integer|min:1', 'quantity' => 'required']);

        return response()->json($this->cart->add($request->user(), (int) $data['produit_id'], $data['quantity']));
    }

    public function update(Request $request, int $product)
    {
        $this->only($request, ['quantity']);
        $data = $request->validate(['quantity' => 'required']);

        return response()->json($this->cart->update($request->user(), $product, $data['quantity']));
    }

    public function destroy(Request $request, int $product)
    {
        $this->only($request, []);

        return response()->json($this->cart->remove($request->user(), $product));
    }
}
