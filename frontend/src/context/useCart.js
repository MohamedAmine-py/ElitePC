import { useCallback, useEffect, useRef, useState } from "react";
import { addCartItem, deleteCartItem, getCart, updateCartQuantity } from "../api/client";

const GUEST_KEY = "elite-pc:cart:guest";
const readGuest = () => {
  // The old, ownerless "cart" key is deliberately left untouched and never imported.
  try {
    const items = JSON.parse(localStorage.getItem(GUEST_KEY) || "[]");
    return Array.isArray(items) ? items.filter((item) => item && Number.isInteger(item.id) && Number.isInteger(item.quantite) && item.quantite > 0) : [];
  } catch { return []; }
};

const initialReads = new Map();
const loadCart = (token) => {
  if (!initialReads.has(token)) {
    initialReads.set(token, getCart(token).finally(() => initialReads.delete(token)));
  }
  return initialReads.get(token);
};

export default function useCart(token, toast) {
  const [state, setState] = useState(() => ({ owner: token, items: token ? [] : readGuest(), ready: !token, pending: 0, error: "" }));
  const scopeRef = useRef(null);

  useEffect(() => {
    const scope = { token, active: true, queue: Promise.resolve() };
    scopeRef.current = scope;
    scope.queue = Promise.resolve().then(async () => {
      try {
        const items = token ? (await loadCart(token)).items : readGuest();
        if (scope.active) setState((previous) => ({ owner: token, items, ready: true, pending: previous.owner === token ? previous.pending : 0, error: "" }));
      } catch {
        if (scope.active) setState((previous) => ({ owner: token, items: [], ready: true, pending: previous.owner === token ? previous.pending : 0, error: "Your cart could not be loaded. Please retry." }));
      }
    });
    return () => { scope.active = false; };
  }, [token]);

  // Serialize requests in this browser; never replay a failed incremental add.
  // Server-side user locks serialize other tabs/devices. Old account responses are ignored.
  const runServer = useCallback((operation, success) => {
    const scope = scopeRef.current;
    if (!scope?.active || scope.token !== token) return Promise.resolve();
    setState((previous) => previous.owner === token ? { ...previous, pending: previous.pending + 1 } : { owner: token, items: [], ready: false, pending: 1, error: "" });
    scope.queue = scope.queue.then(async () => {
      if (!scope.active) return;
      try {
        const result = await operation();
        if (!scope.active) return;
        setState((previous) => ({ ...previous, owner: token, items: result.items, ready: true, error: "" }));
        if (success) toast(success);
      } catch (error) {
        if (!scope.active) return;
        const message = Object.values(error.data?.errors || {}).flat().join(" ") || error.message || "Your cart could not be updated.";
        setState((previous) => ({ ...previous, ready: true, error: message }));
        toast(message, "error");
      } finally {
        if (scope.active) setState((previous) => ({ ...previous, pending: Math.max(0, previous.pending - 1) }));
      }
    });
    return scope.queue;
  }, [token, toast]);

  const refreshCart = useCallback(() => {
    if (token) return runServer(() => getCart(token));
    setState({ owner: null, items: readGuest(), ready: true, pending: 0, error: "" });
    return Promise.resolve();
  }, [token, runServer]);

  useEffect(() => {
    const refresh = () => { void refreshCart(); };
    const storage = (event) => { if (!token && event.key === GUEST_KEY) refresh(); };
    window.addEventListener("focus", refresh);
    window.addEventListener("storage", storage);
    return () => {
      window.removeEventListener("focus", refresh);
      window.removeEventListener("storage", storage);
    };
  }, [token, refreshCart]);

  const changeGuest = useCallback((operation) => {
    const items = operation(readGuest());
    localStorage.setItem(GUEST_KEY, JSON.stringify(items));
    setState({ owner: null, items, ready: true, pending: 0, error: "" });
  }, []);

  const addToCart = useCallback((product) => {
    if (token) return runServer(() => addCartItem(product.id, 1, token), `${product.nom} added to cart`);
    changeGuest((items) => items.some((item) => item.id === product.id)
      ? items.map((item) => item.id === product.id ? { ...item, quantite: item.quantite + 1 } : item)
      : [...items, { ...product, quantite: 1 }]);
    toast(`${product.nom} added to cart`);
  }, [token, runServer, changeGuest, toast]);

  const removeFromCart = useCallback((id) => {
    if (token) return runServer(() => deleteCartItem(id, token));
    changeGuest((items) => items.filter((item) => item.id !== id));
  }, [token, runServer, changeGuest]);

  const updateCartItem = useCallback((id, quantity) => {
    if (quantity < 1) return removeFromCart(id);
    if (token) return runServer(() => updateCartQuantity(id, quantity, token));
    changeGuest((items) => items.map((item) => item.id === id ? { ...item, quantite: quantity } : item));
  }, [token, runServer, changeGuest, removeFromCart]);

  const matching = state.owner === token;
  const cart = matching ? state.items : [];
  return {
    cart, cartCount: cart.reduce((sum, item) => sum + item.quantite, 0),
    cartTotal: cart.reduce((sum, item) => sum + Number(item.prix) * item.quantite, 0),
    cartLoading: !matching || !state.ready, cartBusy: matching && state.pending > 0,
    cartError: matching ? state.error : "", refreshCart, addToCart, removeFromCart, updateCartItem,
  };
}
