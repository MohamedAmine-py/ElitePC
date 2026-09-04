<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreOrderRequest — validates and sanitizes order creation payloads.
 *
 * Security measures:
 * - Max 50 items per order to prevent payload abuse.
 * - Quantity capped at 100 per line item.
 * - Payment method strictly whitelisted.
 * - Phone number validated with regex (only digits, +, -, spaces, parentheses).
 * - Delivery address length bounded and HTML-stripped.
 * - All produit_id values validated against the database.
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * Only authenticated users can place orders.
     * (The route is already behind auth:sanctum middleware,
     *  but this is an extra layer of defense.)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Sanitize inputs before validation runs.
     * Strips HTML tags from the delivery address to prevent stored XSS.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('delivery_address')) {
            $this->merge([
                'delivery_address' => strip_tags($this->input('delivery_address')),
            ]);
        }
    }

    /**
     * Validation rules for order creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items'              => 'required|array|min:1|max:50',
            'items.*.produit_id' => 'required|integer|exists:produits,id',
            'items.*.quantite'   => 'required|integer|min:1|max:100',
            'payment_method'     => 'required|string|in:credit_card,paypal,cash_on_delivery',
            'delivery_address'   => 'required|string|min:10|max:500',
            'delivery_phone'     => [
                'required',
                'string',
                'min:8',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],
        ];
    }

    /**
     * Custom error messages for clarity.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'             => 'Your order must contain at least one item.',
            'items.max'                  => 'An order cannot exceed 50 different items.',
            'items.*.produit_id.exists'  => 'One or more selected products do not exist.',
            'items.*.quantite.max'       => 'You cannot order more than 100 units of a single product.',
            'payment_method.in'          => 'Invalid payment method. Choose from: credit_card, paypal, or cash_on_delivery.',
            'delivery_address.min'       => 'Please provide a complete delivery address (at least 10 characters).',
            'delivery_address.max'       => 'Delivery address is too long (max 500 characters).',
            'delivery_phone.regex'       => 'Phone number can only contain digits, +, -, spaces, and parentheses.',
        ];
    }
}
