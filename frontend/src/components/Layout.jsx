import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import useApp from "../context/useApp";
import Navbar from "./Navbar";
import Footer from "./Footer";
import SupportChat from "./SupportChat";
import ProductModal from "./ProductModal";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";
import { login as apiLogin, register as apiRegister } from "../api/client";

// Icon Cart for Drawer Empty View
const IconCart = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>;

export default function Layout({ children }) {
  const {
    user,
    cart,
    cartCount,
    cartTotal,
    cartLoading,
    cartError,
    cartBusy,
    refreshCart,
    cartOpen,
    setCartOpen,
    authOpen,
    setAuthOpen,
    toasts,
    handleLogin,
    handleLogout,
    updateCartItem,
    removeFromCart,
    addToCart,
    selectedProduct,
    setSelectedProduct
  } = useApp();

  const navigate = useNavigate();

  // Auth Modal States
  const [authMode, setAuthMode] = useState("login");
  const [authForm, setAuthForm] = useState({ nom: "", email: "", mot_de_passe: "" });
  const [authErr, setAuthErr] = useState("");
  const [authLoading, setAuthLoading] = useState(false);

  async function handleAuthSubmit() {
    setAuthErr("");
    setAuthLoading(true);
    const fn = authMode === "login" ? apiLogin : apiRegister;
    try {
      const data = await fn(authForm);
      setAuthLoading(false);
      if (data.token) {
        handleLogin(data.user, data.token);
        setAuthOpen(false);
        setAuthForm({ nom: "", email: "", mot_de_passe: "" });
      } else {
        setAuthErr(data.errors ? Object.values(data.errors).flat().join(" ") : data.message || "Something went wrong.");
      }
    } catch {
      setAuthLoading(false);
      setAuthErr("Could not connect to the server.");
    }
  }

  async function handleCheckout() {
    if (!user) {
      setCartOpen(false);
      setAuthOpen(true);
      return;
    }
    // Navigate to checkout page instead of placing order directly
    setCartOpen(false);
    navigate("/checkout");
  }

  return (
    <>
      <Navbar />

      <div className="page">
        {children}
      </div>

      <Footer />

      {/* CART DRAWER */}
      {cartOpen && (
        <>
          <div className="overlay" onClick={() => setCartOpen(false)} />
          <div className="drawer">
            <div className="drawer-hd">
              <div>
                <div className="drawer-title">My Cart</div>
                <div style={{ fontSize: 12, color: "var(--text3)", marginTop: 2 }}>
                  {cartCount} item{cartCount !== 1 ? "s" : ""}
                </div>
              </div>
              <button className="drawer-x" onClick={() => setCartOpen(false)}>✕</button>
            </div>
            <div className="drawer-body">
              {cartLoading || cartError ? <div className="empty" role="status"><p>{cartLoading ? "Loading your cart…" : cartError}</p>{cartError && <button onClick={refreshCart}>Retry</button>}</div> : cart.length === 0 ? (
                <div className="empty">
                  <div className="empty-icon" style={{ opacity: 0.1 }}>
                    <IconCart width={64} height={64} />
                  </div>
                  <h3>Your cart is empty</h3>
                  <p>Add products to get started</p>
                </div>
              ) : (
                cart.map((item) => (
                  <div key={item.id} className="cart-item">
                    <div className="cart-thumb">
                      <img
                        src={productImage(item)}
                        alt={item.nom}
                        loading="lazy"
                        decoding="async"
                        onError={(event) => applyProductFallback(event, item)}
                      />
                    </div>
                    <div className="cart-info">
                      <div className="cart-name">{item.nom}</div>
                      <div className="cart-price">
                        {formatCurrency(item.prix * item.quantite)}
                      </div>
                      <div className="cart-ctrls">
                        <button
                          className="qty-btn"
                          disabled={cartBusy}
                          onClick={() => updateCartItem(item.id, item.quantite - 1)}
                        >
                          −
                        </button>
                        <span className="qty-n">{item.quantite}</span>
                        <button
                          className="qty-btn"
                          disabled={cartBusy}
                          onClick={() => updateCartItem(item.id, item.quantite + 1)}
                        >
                          +
                        </button>
                        <button
                          className="del-btn"
                          disabled={cartBusy}
                          onClick={() => removeFromCart(item.id)}
                        >
                          ✕
                        </button>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
            {cart.length > 0 && (
              <div className="drawer-ft">
                <div className="cart-total-row">
                  <span className="cart-total-lbl">Total</span>
                  <span className="cart-total-val">{formatCurrency(cartTotal)}</span>
                </div>
                <button
                  className="checkout-btn"
                  disabled={cartLoading || cartBusy || Boolean(cartError)}
                  onClick={handleCheckout}
                >
                  {user
                    ? "Proceed to Checkout"
                    : "Sign in to Order"}
                </button>
              </div>
            )}
          </div>
        </>
      )}

      {/* AUTH MODAL */}
      {authOpen && (
        <div className="modal-ov" onClick={() => setAuthOpen(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            {user ? (
              <>
                <div className="modal-hd">
                  <div className="modal-title">My Account</div>
                  <button className="modal-x" onClick={() => setAuthOpen(false)}>✕</button>
                </div>
                <div className="modal-body">
                  <div style={{ textAlign: "center", padding: "16px 0 24px" }}>
                    <div
                      style={{
                        width: 64,
                        height: 64,
                        borderRadius: 16,
                        background: "var(--accent)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        fontSize: 28,
                        fontWeight: 900,
                        color: "#fff",
                        margin: "0 auto 16px",
                      }}
                    >
                      {user.nom[0].toUpperCase()}
                    </div>
                    <div
                      style={{
                        fontSize: 18,
                        fontWeight: 800,
                        marginBottom: 4,
                        color: "var(--text)",
                      }}
                    >
                      {user.nom}
                    </div>
                    <div style={{ fontSize: 13, color: "var(--text2)" }}>
                      {user.email}
                    </div>
                    {user.role === "admin" && (
                      <div
                        style={{
                          fontSize: 11,
                          fontWeight: 800,
                          color: "var(--accent)",
                          letterSpacing: ".1em",
                          textTransform: "uppercase",
                          marginTop: 8,
                        }}
                      >
                        Administrator
                      </div>
                    )}
                  </div>
                  <button
                    onClick={() => {
                      handleLogout();
                      setAuthOpen(false);
                    }}
                    style={{
                      width: "100%",
                      padding: 12,
                      borderRadius: 10,
                      border: "1.5px solid #fecaca",
                      background: "#fee2e2",
                      color: "#ef4444",
                      fontFamily: "Inter,sans-serif",
                      fontSize: 14,
                      fontWeight: 800,
                      cursor: "pointer",
                    }}
                  >
                    Sign Out
                  </button>
                </div>
              </>
            ) : (
              <>
                <div className="modal-hd">
                  <div className="modal-title">
                    {authMode === "login" ? "Sign In" : "Create Account"}
                  </div>
                  <button className="modal-x" onClick={() => setAuthOpen(false)}>✕</button>
                </div>
                <div className="modal-body">
                  {authErr && <div className="err-msg">{authErr}</div>}
                  {authMode === "register" && (
                    <div className="f-group">
                      <label className="f-label">Full name</label>
                      <input
                        className="f-input"
                        placeholder="Your name"
                        value={authForm.nom}
                        onChange={(e) =>
                          setAuthForm({ ...authForm, nom: e.target.value })
                        }
                      />
                    </div>
                  )}
                  <div className="f-group">
                    <label className="f-label">Email</label>
                    <input
                      className="f-input"
                      type="email"
                      placeholder="vous@example.com"
                      value={authForm.email}
                      onChange={(e) =>
                        setAuthForm({ ...authForm, email: e.target.value })
                      }
                    />
                  </div>
                  <div className="f-group">
                    <label className="f-label">Password</label>
                    <input
                      className="f-input"
                      type="password"
                      placeholder="••••••••"
                      value={authForm.mot_de_passe}
                      onChange={(e) =>
                        setAuthForm({ ...authForm, mot_de_passe: e.target.value })
                      }
                    />
                  </div>
                  <button
                    className="submit-btn"
                    onClick={handleAuthSubmit}
                    disabled={authLoading}
                  >
                    {authLoading
                      ? "Please wait..."
                      : authMode === "login"
                      ? "Sign In"
                      : "Create Account"}
                  </button>
                  <div className="switch-mode">
                    {authMode === "login" ? "New to Elite PC? " : "Already have an account? "}
                    <span
                      onClick={() => {
                        setAuthMode(authMode === "login" ? "register" : "login");
                        setAuthErr("");
                      }}
                    >
                      {authMode === "login" ? "Create Account" : "Sign In"}
                    </span>
                  </div>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* PRODUCT DETAIL QUICK-VIEW MODAL */}
      {selectedProduct && (
        <ProductModal
          product={selectedProduct}
          onClose={() => setSelectedProduct(null)}
          onAddToCart={addToCart}
        />
      )}

      {/* TOASTS NOTIFICATIONS */}
      <div className="toasts">
        {toasts.map((t) => (
          <div key={t.id} className={`toast toast-${t.type}`}>
            <span>
              {t.type === "success" ? (
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              ) : (
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
              )}
            </span>
            {t.msg}
          </div>
        ))}
      </div>

      {/* Persistent AI Chat Assistant */}
      <SupportChat />
    </>
  );
}
