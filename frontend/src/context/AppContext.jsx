import { useState, useCallback, useEffect, useRef } from "react";
import { getCurrentUser, logout as apiLogout } from "../api/client";
import AppContext from "./app-context";
import useCart from "./useCart";
import useFavorites from "./useFavorites";

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
        localStorage.setItem("user", JSON.stringify(currentUser));
      })
      .catch((error) => {
        if (!active || ![401, 419].includes(error.status)) return;
        setUser(null);
        setToken(null);
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
  const favoriteState = useFavorites(token, toast);

  const handleLogin = useCallback((userData, userToken) => {
    setUser(userData); setToken(userToken);
    localStorage.setItem("user", JSON.stringify(userData));
    localStorage.setItem("token", userToken);
    toast(`Welcome, ${userData.nom}!`);
  }, [toast]);

  const handleLogout = useCallback(async () => {
    if (token) await apiLogout(token).catch(() => {});
    setUser(null); setToken(null);
    localStorage.removeItem("user"); localStorage.removeItem("token");
    toast("Signed out successfully");
  }, [token, toast]);

  return (
    <AppContext.Provider value={{
      user, token, ...cartState,
      cartOpen, setCartOpen, authOpen, setAuthOpen, toasts, toast,
      handleLogin, handleLogout,
      ...favoriteState,
      search, setSearch, selectedProduct, setSelectedProduct
    }}>
      {children}
    </AppContext.Provider>
  );
}
