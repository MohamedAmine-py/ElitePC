import React from "react";
import { Link, useNavigate } from "react-router-dom";
import useApp from "../context/useApp";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

function CartItem({ item, updateCartItem, removeFromCart }) {
  const { cartBusy } = useApp();
  const specs = [item.processor, item.graphics_card, item.ram_details, item.storage_details].filter(Boolean).slice(0, 2);

  return (
    <article className="cart-page-item">
      <Link className="cart-page-image" to={`/products/${item.id}`}><img src={productImage(item)} alt={item.nom} loading="lazy" decoding="async" onError={(event) => applyProductFallback(event, item)} /></Link>
      <div className="cart-page-product"><span>{item.categorie?.nom || "Hardware"}</span><Link to={`/products/${item.id}`}>{item.nom}</Link>{specs.length > 0 && <p>{specs.join(" · ")}</p>}<button className="cart-remove" disabled={cartBusy} onClick={() => removeFromCart(item.id)}>Remove</button></div>
      <div className="cart-unit-price"><span>Unit price</span><strong>{formatCurrency(item.prix)}</strong></div>
      <div className="cart-quantity"><span>Quantity</span><div><button disabled={cartBusy} onClick={() => updateCartItem(item.id, item.quantite - 1)} aria-label={`Decrease quantity of ${item.nom}`}>−</button><strong>{item.quantite}</strong><button disabled={cartBusy} onClick={() => updateCartItem(item.id, item.quantite + 1)} aria-label={`Increase quantity of ${item.nom}`}>+</button></div></div>
      <div className="cart-line-total"><span>Subtotal</span><strong>{formatCurrency(Number(item.prix) * item.quantite)}</strong></div>
    </article>
  );
}

export default function Cart() {
  const { cart, cartCount, cartTotal, cartLoading, cartError, refreshCart, user, setAuthOpen, updateCartItem, removeFromCart } = useApp();
  const navigate = useNavigate();
  const proceedToCheckout = () => user ? navigate("/checkout") : setAuthOpen(true);

  return (
    <main className="cart-page storefront-container">
      <header className="cart-page-header"><div><span className="store-eyebrow">Your selection</span><h1>Review Your Cart</h1><p>{cartCount} item{cartCount !== 1 ? "s" : ""} in your cart.</p></div><Link className="text-link" to="/products">← Continue shopping</Link></header>
      {cartLoading || cartError ? <div className="store-state" role="status"><p>{cartLoading ? "Loading your cart…" : cartError}</p>{cartError && <button className="button button-primary" onClick={refreshCart}>Retry</button>}</div> : cart.length === 0 ? (
        <div className="cart-empty store-state"><div className="cart-empty-icon" aria-hidden="true">□</div><h3>Your cart is empty</h3><p>Explore the catalog and add the right hardware for your next system.</p><Link className="button button-primary" to="/products">Browse the catalog</Link></div>
      ) : (
        <div className="cart-page-layout">
          <section className="cart-items" aria-label="Products in cart">{cart.map((item) => <CartItem key={item.id} item={item} updateCartItem={updateCartItem} removeFromCart={removeFromCart} />)}</section>
          <aside className="cart-summary"><span className="store-eyebrow">Summary</span><h2>Order Summary</h2><div className="summary-row"><span>Subtotal</span><strong>{formatCurrency(cartTotal)}</strong></div><div className="summary-row"><span>Shipping</span><strong>Confirmed at checkout</strong></div><div className="summary-total"><span>Estimated total</span><strong>{formatCurrency(cartTotal)}</strong></div><p className="summary-note">The final amount is recalculated by the server using current catalog prices.</p><button className="button button-primary summary-checkout" onClick={proceedToCheckout}>{user ? "Proceed to Checkout" : "Sign in to Order"} <span>→</span></button><Link className="summary-continue" to="/products">Continue shopping</Link></aside>
        </div>
      )}
    </main>
  );
}
