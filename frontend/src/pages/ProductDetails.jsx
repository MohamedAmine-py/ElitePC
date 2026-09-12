import React, { useEffect, useMemo, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { getProduct } from "../api/client";
import useApp from "../context/useApp";
import { StorefrontState } from "../components/StorefrontUI";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

const HeartIcon = ({ filled }) => (
  <svg width="19" height="19" viewBox="0 0 24 24" fill={filled ? "currentColor" : "none"} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></svg>
);

export default function ProductDetails() {
  const { id } = useParams();
  const { favorites, favoritesLoading, favoritesBusy, favoritesError, toggleFavorite, addToCart } = useApp();
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    getProduct(id)
      .then(setProduct)
      .catch((requestError) => setError(requestError.status === 404 ? "This product no longer exists." : "This product could not be loaded."))
      .finally(() => setLoading(false));
  }, [id]);

  const specs = useMemo(() => product ? [
    ["Brand", product.brand],
    ["Processor", product.processor],
    ["Graphics card", product.graphics_card],
    ["Memory", product.ram_details],
    ["Storage", product.storage_details],
    ["Type", product.is_custom_build ? "Custom build" : null],
  ].filter(([, value]) => value) : [], [product]);

  if (loading) {
    return <main className="product-detail-page storefront-container"><div className="product-detail-skeleton"><div className="skel" /><div><div className="skel" /><div className="skel" /><div className="skel" /></div></div></main>;
  }

  if (error || !product) {
    return <main className="product-detail-page storefront-container"><StorefrontState title="Product unavailable" message={error || "This product is not available."} actionLabel="Back to the catalog" actionTo="/products" /></main>;
  }

  const isOut = product.stock === 0;
  const isFavorite = favorites.some((favorite) => favorite.id === product.id);

  return (
    <main className="product-detail-page storefront-container">
      <nav className="product-breadcrumb" aria-label="Breadcrumb"><Link to="/products">Catalog</Link><span>/</span><span>{product.categorie?.nom || "Hardware"}</span></nav>
      <section className="product-detail-hero">
        <div className="product-detail-media">
          <img src={productImage(product)} alt={product.nom} decoding="async" onError={(event) => applyProductFallback(event, product)} />
          <span className={`detail-stock-badge ${isOut ? "is-out" : product.stock <= 5 ? "is-low" : ""}`}>{isOut ? "Out of stock" : `${product.stock} in stock`}</span>
        </div>
        <div className="product-detail-summary">
          <span className="store-eyebrow">{product.categorie?.nom || "Hardware"}</span>
          <h1>{product.nom}</h1>
          {product.description && <p className="product-detail-description">{product.description}</p>}
          {specs.length > 0 && <div className="detail-spec-preview">{specs.slice(0, 3).map(([label, value]) => <div key={label}><span>{label}</span><strong>{value}</strong></div>)}</div>}
          <div className="product-detail-price"><span>Price</span><strong>{formatCurrency(product.prix)}</strong></div>
          <div className="product-detail-actions">
            <button className="button button-primary detail-cart-button" disabled={isOut} onClick={() => addToCart(product)}>{isOut ? "Unavailable" : "Add to Cart"}</button>
            <button className={`detail-favorite-button ${isFavorite ? "is-active" : ""}`} disabled={favoritesLoading || favoritesBusy || Boolean(favoritesError)} aria-pressed={isFavorite} onClick={() => toggleFavorite(product)} aria-label={isFavorite ? "Remove from favorites" : "Add to favorites"}><HeartIcon filled={isFavorite} /><span>{isFavorite ? "In your favorites" : "Add to favorites"}</span></button>
          </div>
        </div>
      </section>

      <section className="product-details-content">
        <div>
          <span className="store-eyebrow">Overview</span>
          <h2>Product details</h2>
          <p>{product.description || "No detailed description is available for this product."}</p>
        </div>
        <div className="full-specifications">
          <div className="full-specifications-heading"><span className="store-eyebrow">Available data</span><h2>Specifications</h2></div>
          {specs.length > 0 ? specs.map(([label, value]) => <div className="specification-row" key={label}><span>{label}</span><strong>{value}</strong></div>) : <p className="specifications-empty">No additional technical specifications are available.</p>}
        </div>
      </section>
    </main>
  );
}
