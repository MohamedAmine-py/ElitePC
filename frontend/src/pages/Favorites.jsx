import React from "react";
import { Link } from "react-router-dom";
import useApp from "../context/useApp";
import ProductCard from "../components/ProductCard";

export default function Favorites() {
  const { favorites, favoritesLoading, favoritesBusy, favoritesError, refreshFavorites } = useApp();

  return (
    <main className="favorites-page storefront-container">
      <header className="favorites-header">
        <div><span className="store-eyebrow">Your selection</span><h1>Saved Hardware</h1><p>Find the hardware you have saved for later.</p></div>
        <span className="result-count">{favorites.length} product{favorites.length !== 1 ? "s" : ""}</span>
      </header>

      {favoritesLoading || favoritesError ? (
        <div className="store-state" role="status">
          <p>{favoritesLoading ? "Loading your favorites…" : favoritesError}</p>
          {favoritesError && <button className="button button-primary" disabled={favoritesBusy} onClick={refreshFavorites}>Retry</button>}
        </div>
      ) : favorites.length === 0 ? (
        <div className="favorites-empty store-state">
          <div className="wishlist-heart" aria-hidden="true">♡</div>
          <h3>Your selection is empty</h3>
          <p>Use the heart on any product to save it here for easy access.</p>
          <Link to="/products" className="button button-primary">Browse products</Link>
        </div>
      ) : (
        <div className="grid product-grid favorites-grid">
          {favorites.map((p, index) => (
            <ProductCard key={p.id} product={p} index={index} />
          ))}
        </div>
      )}
    </main>
  );
}
