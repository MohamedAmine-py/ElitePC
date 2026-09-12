import { useCallback, useEffect, useRef, useState } from "react";
import { addFavorite, deleteFavorite, getFavorites } from "../api/client";

const GUEST_KEY = "elite-pc:favorites:guest";
const readGuest = () => {
  // Legacy account keys and the old ownerless key are never read or imported.
  try {
    const items = JSON.parse(localStorage.getItem(GUEST_KEY) || "[]");
    return Array.isArray(items) ? items.filter((item) => item && Number.isInteger(item.id)) : [];
  } catch { return []; }
};

const initialReads = new Map();
const loadFavorites = (token) => {
  if (!initialReads.has(token)) {
    initialReads.set(token, getFavorites(token).finally(() => initialReads.delete(token)));
  }
  return initialReads.get(token);
};

export default function useFavorites(token, toast) {
  const [state, setState] = useState(() => ({ owner: token, items: token ? [] : readGuest(), ready: !token, pending: 0, error: "" }));
  const scopeRef = useRef(null);

  useEffect(() => {
    const scope = { token, active: true, ready: false, items: [], pending: 0, error: "", queue: Promise.resolve() };
    scopeRef.current = scope;
    scope.queue = Promise.resolve().then(async () => {
      try {
        const items = token ? (await loadFavorites(token)).items : readGuest();
        if (!scope.active) return;
        scope.items = items;
      } catch {
        if (!scope.active) return;
        scope.error = "Your favorites could not be loaded. Please retry.";
      }
      if (scope.active) {
        scope.ready = true;
        setState({ owner: token, items: scope.items, ready: true, pending: scope.pending, error: scope.error });
      }
    });
    return () => { scope.active = false; };
  }, [token]);

  const runServer = useCallback((operation, success) => {
    const scope = scopeRef.current;
    if (!scope?.active || scope.token !== token) return Promise.resolve();
    scope.pending += 1;
    setState((previous) => previous.owner === token
      ? { ...previous, pending: scope.pending }
      : { owner: token, items: [], ready: false, pending: scope.pending, error: "" });
    scope.queue = scope.queue.then(async () => {
      if (!scope.active) return;
      try {
        const result = await operation();
        if (!scope.active) return;
        scope.items = result.items;
        scope.error = "";
        if (success) toast(success);
      } catch (error) {
        if (!scope.active) return;
        scope.error = Object.values(error.data?.errors || {}).flat().join(" ") || error.message || "Your favorites could not be updated.";
        toast(scope.error, "error");
      } finally {
        if (scope.active) {
          scope.ready = true;
          scope.pending -= 1;
          setState({ owner: token, items: scope.items, ready: true, pending: scope.pending, error: scope.error });
        }
      }
    });
    return scope.queue;
  }, [token, toast]);

  const refreshFavorites = useCallback(() => {
    if (token) return runServer(() => getFavorites(token));
    setState({ owner: null, items: readGuest(), ready: true, pending: 0, error: "" });
    return Promise.resolve();
  }, [token, runServer]);

  useEffect(() => {
    const refresh = () => { void refreshFavorites(); };
    const storage = (event) => { if (!token && (event.key === GUEST_KEY || event.key === null)) refresh(); };
    window.addEventListener("focus", refresh);
    window.addEventListener("storage", storage);
    return () => {
      window.removeEventListener("focus", refresh);
      window.removeEventListener("storage", storage);
    };
  }, [token, refreshFavorites]);

  const toggleFavorite = useCallback((product) => {
    if (token) {
      const scope = scopeRef.current;
      // Guard rapid clicks synchronously, and never decide add/remove from an unloaded list.
      if (!scope?.active || scope.token !== token || !scope.ready || scope.pending || scope.error) return;
      const exists = scope.items.some((item) => item.id === product.id);
      return runServer(() => exists ? deleteFavorite(product.id, token) : addFavorite(product.id, token),
        exists ? "Removed from favorites" : "Added to favorites");
    }

    const items = readGuest();
    const exists = items.some((item) => item.id === product.id);
    const updated = exists ? items.filter((item) => item.id !== product.id) : [...items, product];
    try {
      localStorage.setItem(GUEST_KEY, JSON.stringify(updated));
      setState({ owner: null, items: updated, ready: true, pending: 0, error: "" });
      toast(exists ? "Removed from favorites" : "Added to favorites", "success");
    } catch {
      toast("Your favorites could not be saved in this browser.", "error");
    }
  }, [token, runServer, toast]);

  const matching = state.owner === token;
  return {
    favorites: matching ? state.items : [],
    favoritesLoading: !matching || !state.ready,
    favoritesBusy: matching && state.pending > 0,
    favoritesError: matching ? state.error : "",
    refreshFavorites, toggleFavorite,
  };
}