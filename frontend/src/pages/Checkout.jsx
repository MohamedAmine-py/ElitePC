import React, { useState } from "react";
import { Link } from "react-router-dom";
import useApp from "../context/useApp";
import { createOrder } from "../api/client";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

export default function Checkout() {
  const { cart, cartTotal, cartLoading, cartBusy, cartError, refreshCart, user, token, toast, setAuthOpen } = useApp();
  const [formData, setFormData] = useState({ payment_method: "credit_card", delivery_address: "", delivery_phone: "" });
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [createdOrder, setCreatedOrder] = useState(null);

  const handleInputChange = (event) => {
    const { name, value } = event.target;
    setFormData((current) => ({ ...current, [name]: value }));
  };

  const validate = () => {
    if (formData.delivery_address.trim().length < 10) return "The delivery address must contain at least 10 characters.";
    if (formData.delivery_phone.trim().length < 8) return "The phone number must contain at least 8 characters.";
    if (!/^[0-9+\-\s()]+$/.test(formData.delivery_phone.trim())) return "The phone number contains invalid characters.";
    return "";
  };

  const handlePlaceOrder = async (event) => {
    event.preventDefault();
    if (loading || cartLoading || cartBusy || cartError || !token) return;
    const validationError = validate();
    if (validationError) {
      setFormError(validationError);
      toast(validationError, "error");
      return;
    }

    setFormError("");
    setLoading(true);
    try {
      const order = await createOrder({
        items: cart.map((item) => ({ produit_id: item.id, quantite: item.quantite, cart_item_id: item.cart_item_id, cart_updated_at: item.cart_updated_at })),
        payment_method: formData.payment_method,
        delivery_address: formData.delivery_address.trim(),
        delivery_phone: formData.delivery_phone.trim(),
      }, token);

      if (localStorage.getItem("token") !== token) return;
      if (order?.id) {
        setCreatedOrder({ ...order, owner: token });
        void refreshCart();
        toast("Order created successfully.", "success");
      } else {
        setFormError(order?.message || "The order could not be created.");
      }
    } catch (error) {
      if (localStorage.getItem("token") !== token) return;
      const validationMessages = error.data?.errors ? Object.values(error.data.errors).flat().join(" ") : "";
      const message = validationMessages || error.message || "An error occurred while placing the order.";
      setFormError(message);
      toast(message, "error");
    } finally {
      setLoading(false);
    }
  };

  if (!user) {
    return <main className="checkout-page storefront-container"><div className="store-state"><div className="store-state-mark">ID</div><h3>Sign in to place your order</h3><p>Your cart remains saved while you sign in.</p><button className="button button-primary" onClick={() => setAuthOpen(true)}>Sign In</button></div></main>;
  }

  if (createdOrder?.owner === token) {
    return <main className="checkout-page storefront-container"><div className="checkout-success"><span aria-hidden="true">✓</span><div className="store-eyebrow">Order confirmed</div><h1>Thank you for your order.</h1><p>Order #{createdOrder.id} was created. Its confirmed total is <strong>{formatCurrency(createdOrder.total)}</strong>.</p><Link className="button button-primary" to="/orders">View My Orders</Link></div></main>;
  }

  if (cartLoading || cartError) {
    return <main className="checkout-page storefront-container"><div className="store-state" role="status"><p>{cartLoading ? "Loading your cart…" : cartError}</p>{cartError && <button className="button button-primary" onClick={refreshCart}>Retry</button>}</div></main>;
  }

  if (cart.length === 0) {
    return <main className="checkout-page storefront-container"><div className="store-state"><div className="store-state-mark">0</div><h3>Your cart is empty</h3><p>Add a product before placing an order.</p><Link className="button button-primary" to="/products">Back to the catalog</Link></div></main>;
  }

  return (
    <main className="checkout-page storefront-container">
      <header className="checkout-header"><span className="store-eyebrow">Complete your order</span><h1>Checkout</h1><p>Review your items, then enter only the information required for delivery and payment.</p></header>
      <form className="checkout-layout" onSubmit={handlePlaceOrder}>
        <div className="checkout-form-column">
          <section className="checkout-panel">
            <div className="checkout-panel-heading"><span>01</span><div><h2>Delivery</h2><p>Delivery address and contact phone</p></div></div>
            {formError && <div className="checkout-error" role="alert">{formError}</div>}
            <label className="checkout-field"><span>Delivery address</span><textarea name="delivery_address" value={formData.delivery_address} onChange={handleInputChange} minLength="10" maxLength="500" required placeholder="Full address, city, and postal code" /></label>
            <label className="checkout-field"><span>Phone number</span><input type="tel" name="delivery_phone" value={formData.delivery_phone} onChange={handleInputChange} minLength="8" maxLength="20" required placeholder="+212 600 000 000" /></label>
          </section>

          <section className="checkout-panel">
            <div className="checkout-panel-heading"><span>02</span><div><h2>Payment method</h2><p>Select one of the currently supported options</p></div></div>
            <div className="payment-options">
              {[["credit_card", "Credit card", "Payment by card"], ["paypal", "PayPal", "Payment via PayPal"], ["cash_on_delivery", "Cash on delivery", "Payment upon delivery"]].map(([value, label, description]) => (
                <label className={`payment-option ${formData.payment_method === value ? "is-selected" : ""}`} key={value}><input type="radio" name="payment_method" value={value} checked={formData.payment_method === value} onChange={handleInputChange} /><span className="payment-radio" /><span><strong>{label}</strong><small>{description}</small></span></label>
              ))}
            </div>
            <p className="payment-note">No card details are requested on this page.</p>
          </section>
        </div>

        <aside className="checkout-summary">
          <span className="store-eyebrow">Your order</span><h2>Order Summary</h2>
          <div className="checkout-items">{cart.map((item) => <div className="checkout-item" key={item.id}><img src={productImage(item)} alt="" loading="lazy" decoding="async" onError={(event) => applyProductFallback(event, item)} /><div><strong>{item.nom}</strong><span>{item.quantite} × {formatCurrency(item.prix)}</span></div><b>{formatCurrency(item.quantite * Number(item.prix))}</b></div>)}</div>
          <div className="summary-row"><span>Estimated subtotal</span><strong>{formatCurrency(cartTotal)}</strong></div>
          <div className="summary-row"><span>Shipping</span><strong>Confirmed at checkout</strong></div>
          <div className="summary-total"><span>Estimated total</span><strong>{formatCurrency(cartTotal)}</strong></div>
          <p className="summary-note">The server verifies prices and stock before creating the order.</p>
          <button type="submit" className="button button-primary checkout-submit" disabled={loading || cartBusy}>{loading ? "Creating order…" : "Place Order"} <span>→</span></button>
          <Link className="summary-continue" to="/cart">← Back to Cart</Link>
        </aside>
      </form>
    </main>
  );
}
