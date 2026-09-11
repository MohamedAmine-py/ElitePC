import { useState, useCallback, useEffect, useRef } from "react";
import { getCurrentUser, logout as apiLogout } from "../api/client";
import AppContext from "./app-context";
import useCart from "./useCart";

const favoriteStorageKey = (user) => user?.id
  ? `elite-pc:favorites:user:${user.id}`
  : "elite-pc:favorites:guest";

const readFavorites = (user) => {
  try {
    const stored = JSON.parse(localStorage.getItem(favoriteStorageKey(user)) || "[]");
    return Array.isArray(stored) ? stored : [];
  } catch {
    return [];
  }
};

const currentUserRequests = new Map();

const validateCurrentUser = (token) => {
  if (!currentUserRequests.has(token)) {
    const request = getCurrentUser(token)
      .finally(() => currentUserRequests.delete(token));
    currentUserRequests.set(token, request);
  }

  return currentUserRequests.get(token);
};

export function AppProvider({ children }) {
  const [user, setUser] = useState(() => JSON.parse(localStorage.getItem("user") || "null"));
  const [token, setToken] = useState(() => localStorage.getItem("token") || null);
  
  const [favorites, setFavorites] = useState(() => {
    // The former global key could contain another account's data, so it is
    // intentionally discarded instead of being assigned or merged.
    localStorage.removeItem("favorites");
    return readFavorites(user);
  });
  
  const [search, setSearch] = useState("");
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [cartOpen, setCartOpen] = useState(false);
  const [authOpen, setAuthOpen] = useState(false);
  const [toasts, setToasts] = useState([]);
  const toastSequence = useRef(0);
  const toastTimers = useRef(new Map());

  useEffect(() => {
    if (!token) return;

    let active = true;
    validateCurrentUser(token)
      .then((currentUser) => {
        if (!active) return;
        setUser(currentUser);
        setFavorites(readFavorites(currentUser));
        localStorage.setItem("user", JSON.stringify(currentUser));
      })
      .catch((error) => {
        if (!active || ![401, 419].includes(error.status)) return;
        setUser(null);
        setToken(null);
        setFavorites(readFavorites(null));
        localStorage.removeItem("user");
        localStorage.removeItem("token");
      });

    return () => { active = false; };
  }, [token]);

  const toast = useCallback((msg, type = "success") => {
    const id = `${Date.now()}-${++toastSequence.current}`;
    setToasts((t) => [...t, { id, msg, type }]);
    const timer = window.setTimeout(() => {
      setToasts((t) => t.filter((x) => x.id !== id));
      toastTimers.current.delete(id);
    }, 3500);
    toastTimers.current.set(id, timer);
  }, []);

  useEffect(() => () => {
    toastTimers.current.forEach((timer) => window.clearTimeout(timer));
    toastTimers.current.clear();
  }, []);

  const cartState = useCart(token, toast);

  const handleLogin = useCallback((userData, userToken) => {
    setFavorites(readFavorites(userData));
    setUser(userData); setToken(userToken);
    localStorage.setItem("user", JSON.stringify(userData));
    localStorage.setItem("token", userToken);
    toast(`Welcome, ${userData.nom}!`);
  }, [toast]);

  const handleLogout = useCallback(async () => {
    if (token) await apiLogout(token).catch(() => {});
    setFavorites(readFavorites(null));
    setUser(null); setToken(null);
    localStorage.removeItem("user"); localStorage.removeItem("token");
    toast("Signed out successfully");
  }, [token, toast]);

  const toggleFavorite = useCallback((product) => {
    const exists = favorites.some((favorite) => favorite.id === product.id);
    const updated = exists
      ? favorites.filter((favorite) => favorite.id !== product.id)
      : [...favorites, product];

    setFavorites(updated);
    localStorage.setItem(favoriteStorageKey(user), JSON.stringify(updated));
    toast(exists ? "Removed from favorites" : "Added to favorites", "success");
  }, [favorites, toast, user]);

  return (
    <AppContext.Provider value={{
      user, token, ...cartState,
      cartOpen, setCartOpen, authOpen, setAuthOpen, toasts, toast,
      handleLogin, handleLogout,
      favorites, toggleFavorite,
      search, setSearch, selectedProduct, setSelectedProduct
    }}>
      {children}
    </AppContext.Provider>
  );
}
