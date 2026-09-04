<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Elite PC invoice {{ $commande->id }}</title>
    <style>
        @page { margin: 34mm 16mm 24mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17212b; background: #fff; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; line-height: 1.45; }
        .top-rule { position: fixed; top: -34mm; left: -16mm; right: -16mm; height: 4px; background: #00b8d4; }
        .footer { position: fixed; right: 0; bottom: -17mm; left: 0; padding-top: 8px; border-top: 1px solid #dce3e8; color: #687783; font-size: 8px; }
        .footer-table, .header-table, .address-table, .information-table, .totals-layout { width: 100%; border-collapse: collapse; }
        .footer-table td:last-child, .header-meta { text-align: right; }
        .header-table { margin-bottom: 23px; }
        .header-table td { vertical-align: top; }
        .logo { width: 145px; height: auto; margin-bottom: 7px; }
        .tagline { color: #687783; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        .invoice-title { margin: 0 0 5px; color: #101820; font-size: 27px; font-weight: bold; letter-spacing: -.5px; text-transform: uppercase; }
        .invoice-number { color: #008fa3; font-size: 12px; font-weight: bold; }
        .metadata { margin-top: 9px; color: #52616c; font-size: 9px; }
        .metadata-row { margin-bottom: 4px; }
        .metadata-row:last-child { margin-bottom: 0; }
        .status { display: inline-block; padding: 3px 8px; border: 1px solid #9cced5; background: #ecfafc; color: #08788a; font-size: 8px; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
        .section-label { margin-bottom: 7px; color: #008fa3; font-size: 8px; font-weight: bold; letter-spacing: 1.1px; text-transform: uppercase; }
        .address-table { margin-bottom: 23px; table-layout: fixed; }
        .address-table td { width: 50%; padding: 12px 14px; border: 1px solid #dce3e8; vertical-align: top; }
        .address-table td + td { border-left: 0; }
        .address-name { margin-bottom: 4px; color: #101820; font-size: 11px; font-weight: bold; }
        .address-lines { color: #52616c; }
        .items-table { width: 100%; margin-bottom: 18px; border-collapse: collapse; table-layout: fixed; }
        .items-table thead { display: table-header-group; }
        .items-table tr { page-break-inside: avoid; }
        .items-table th { padding: 9px 8px; border-bottom: 2px solid #00b8d4; background: #17212b; color: #fff; font-size: 8px; letter-spacing: .7px; text-align: left; text-transform: uppercase; }
        .items-table td { padding: 10px 8px; border-bottom: 1px solid #e5eaee; vertical-align: top; }
        .items-table tbody tr:nth-child(even) { background: #f7f9fa; }
        .product-name { font-weight: bold; overflow-wrap: break-word; }
        .muted { color: #71808b; }
        .center { text-align: center !important; }
        .money { text-align: right !important; white-space: nowrap; }
        .totals-layout { margin-bottom: 20px; page-break-inside: avoid; }
        .totals-layout td:first-child { width: 55%; }
        .totals-layout td:last-child { width: 45%; }
        .totals-box { width: 100%; border-collapse: collapse; }
        .totals-box td { padding: 7px 9px; border-bottom: 1px solid #dce3e8; }
        .totals-box .grand-total td { padding-top: 10px; border-top: 2px solid #17212b; border-bottom: 0; color: #101820; font-size: 14px; font-weight: bold; }
        .totals-box .grand-total td:last-child { color: #008fa3; }
        .information-table { page-break-inside: avoid; table-layout: fixed; }
        .information-table td { width: 50%; padding: 12px 14px; border: 1px solid #dce3e8; vertical-align: top; }
        .information-table td + td { border-left: 0; }
        .detail-row { margin-bottom: 5px; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-key { color: #71808b; }
        .detail-value { color: #17212b; font-weight: bold; }
        .thank-you { margin-top: 18px; padding: 10px 12px; border-left: 3px solid #00b8d4; background: #f3fafb; color: #52616c; page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        $statusLabels = ['en_cours' => 'In progress', 'validee' => 'Confirmed', 'annulee' => 'Cancelled'];
        $paymentLabels = ['credit_card' => 'Credit card', 'paypal' => 'PayPal', 'cash_on_delivery' => 'Cash on delivery'];
        $invoiceNumber = 'EP-' . str_pad((string) $commande->id, 6, '0', STR_PAD_LEFT);
        $lineSubtotal = $commande->details->sum(fn ($detail) => (float) $detail->prix_unitaire * (int) $detail->quantite);
        $storedTotal = (float) $commande->total;
        $totalsMatch = abs($lineSubtotal - $storedTotal) < 0.01;
    @endphp

    <div class="top-rule"></div>
    <div class="footer">
        <table class="footer-table"><tr><td>Elite PC - Premium computer hardware</td><td>{{ $invoiceNumber }} - Thank you for your purchase</td></tr></table>
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img class="logo" src="{{ public_path('images/elite-pc-logo-light.svg') }}" alt="Elite PC">
                <div class="tagline">Premium computer hardware</div>
            </td>
            <td class="header-meta">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-number">{{ $invoiceNumber }}</div>
                <div class="metadata">
                    <div class="metadata-row">Issued {{ $commande->created_at->format('F d, Y') }}</div>
                    <div class="metadata-row">Order #{{ $commande->id }}</div>
                    <div class="metadata-row"><span class="status">{{ $statusLabels[$commande->statut] ?? ucfirst(str_replace('_', ' ', $commande->statut)) }}</span></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="address-table">
        <tr>
            <td>
                <div class="section-label">Bill To</div>
                <div class="address-name">{{ $commande->user->nom ?? 'Customer' }}</div>
                <div class="address-lines">{{ $commande->user->email ?? 'Not provided' }}</div>
            </td>
            <td>
                <div class="section-label">Ship To</div>
                <div class="address-name">{{ $commande->user->nom ?? 'Customer' }}</div>
                <div class="address-lines">{{ $commande->delivery_address ?: 'Not provided' }}<br>{{ $commande->delivery_phone ?: 'Phone not provided' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-label">Purchased Products</div>
    <table class="items-table">
        <thead><tr><th style="width: 49%;">Product</th><th class="center" style="width: 11%;">Quantity</th><th class="money" style="width: 20%;">Unit Price</th><th class="money" style="width: 20%;">Line Total</th></tr></thead>
        <tbody>
            @foreach ($commande->details as $detail)
                <tr>
                    <td>
                        <div class="product-name">{{ $detail->produit->nom ?? 'Product unavailable' }}</div>
                        @if ($detail->produit?->brand)<div class="muted">{{ $detail->produit->brand }}</div>@endif
                    </td>
                    <td class="center">{{ $detail->quantite }}</td>
                    <td class="money">{{ number_format((float) $detail->prix_unitaire, 2) }} EUR</td>
                    <td class="money">{{ number_format((float) $detail->prix_unitaire * (int) $detail->quantite, 2) }} EUR</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-layout"><tr><td></td><td><table class="totals-box">
        @if ($totalsMatch)<tr><td>Items subtotal</td><td class="money">{{ number_format($lineSubtotal, 2) }} EUR</td></tr>@endif
        <tr class="grand-total"><td>Total</td><td class="money">{{ number_format($storedTotal, 2) }} EUR</td></tr>
    </table></td></tr></table>

    <table class="information-table">
        <tr>
            <td>
                <div class="section-label">Payment Details</div>
                <div class="detail-row"><span class="detail-key">Method: </span><span class="detail-value">{{ $paymentLabels[$commande->payment_method] ?? 'Not provided' }}</span></div>
                <div class="detail-row"><span class="detail-key">Currency: </span><span class="detail-value">EUR</span></div>
            </td>
            <td>
                <div class="section-label">Delivery Information</div>
                <div class="detail-row"><span class="detail-key">Estimated delivery: </span><span class="detail-value">{{ $commande->estimated_delivery_date ? \Carbon\Carbon::parse($commande->estimated_delivery_date)->format('F d, Y') : 'Not provided' }}</span></div>
                <div class="detail-row"><span class="detail-key">Phone: </span><span class="detail-value">{{ $commande->delivery_phone ?: 'Not provided' }}</span></div>
            </td>
        </tr>
    </table>

    <div class="thank-you">Thank you for choosing Elite PC. Please retain this invoice with your order records.</div>
</body>
</html>
