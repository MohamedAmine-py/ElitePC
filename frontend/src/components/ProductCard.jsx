import React from "react";
import { useNavigate } from "react-router-dom";
import useApp from "../context/useApp";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

// SVG Icons
const IconHeartOutline = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>;
const IconHeartFilled = () => <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>;
const IconCart = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>;

export default function ProductCard({ product, index }) {
  const { favorites, favoritesLoading, favoritesBusy, favoritesError, toggleFavorite, addToCart } = useApp();
  const navigate = useNavigate();
  const p = product;
  const isOut = p.stock === 0;
  const img = productImage(p);
  const isFav = favorites.some((f) => f.id === p.id);

  return (
    <div
      className="card animate-item"
      style={{ animationDelay: `${index * 0.05}s` }}
      onClick={() => navigate(`/products/${p.id}`)}
    >
      <div className="card-img">
        <img
          src={img}
          alt={p.nom}
          loading="lazy"
          decoding="async"
          onError={(event) => applyProductFallback(event, p)}
        />
        {isOut && <div className="card-badge badge-out">Out of stock</div>}
        {!isOut && p.stock <= 5 && (
          <div className="card-badge badge-limited">Low stock</div>
        )}
        <button
          className="card-favorite"
          disabled={favoritesLoading || favoritesBusy || Boolean(favoritesError)}
          aria-pressed={isFav}
          onClick={(e) => {
            e.stopPropagation();
            toggleFavorite(p);
          }}
          data-favorite={isFav}
          title={isFav ? "Remove from favorites" : "Add to favorites"}
        >
          {isFav ? <IconHeartFilled /> : <IconHeartOutline />}
        </button>
      </div>
      <div className="card-body">
        <div className="card-cat">
          {p.categorie?.nom || "Hardware"}
        </div>
        <div className="card-name">{p.nom}</div>
        <div className="card-desc">
          {p.description || "High-performance PC hardware"}
        </div>

        {(p.processor || p.graphics_card || p.ram_details) && (
          <div className="spec-pills">
            {p.processor && <span className="spec-pill">{p.processor}</span>}
            {p.graphics_card && (
              <span className="spec-pill">{p.graphics_card}</span>
            )}
            {p.ram_details && <span className="spec-pill">{p.ram_details}</span>}
          </div>
        )}

        <div className="card-foot">
          <div>
            <div className="card-price">{formatCurrency(p.prix)}</div>
            <div className={`card-stock ${isOut ? "is-out" : p.stock <= 5 ? "is-low" : ""}`}>
              {isOut ? "Out of stock" : `${p.stock} in stock`}
            </div>
          </div>
          <button
            className="card-add"
            disabled={isOut}
            title={isOut ? "Out of stock" : "Add to cart"}
            onClick={(e) => {
              e.stopPropagation();
              addToCart(p);
            }}
          >
            <IconCart />
          </button>
        </div>
      </div>
    </div>
  );
}
