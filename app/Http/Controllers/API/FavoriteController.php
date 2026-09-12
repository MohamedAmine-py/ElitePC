<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FavoriteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    public function __construct(private FavoriteService $favorites) {}

    private function only(Request $request, array $keys): void
    {
        if (array_diff(array_keys($request->all()), $keys)) {
            throw ValidationException::withMessages(['request' => 'Unexpected favorite parameter.']);
        }
    }

    public function index(Request $request)
    {
        $this->only($request, []);

        return response()->json($this->favorites->read($request->user()));
    }

    public function store(Request $request)
    {
        $this->only($request, ['produit_id']);
        $data = $request->validate(['produit_id' => 'required|integer|min:1']);

        return response()->json($this->favorites->add($request->user(), (int) $data['produit_id']));
    }

    public function destroy(Request $request, int $product)
    {
        $this->only($request, []);

        return response()->json($this->favorites->remove($request->user(), $product));
    }
}
