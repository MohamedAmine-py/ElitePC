import React from "react";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

export default function ProductModal({ product, onClose, onAddToCart }) {
  if (!product) return null;

  const isOut = product.stock === 0;
  const isLimited = !isOut && product.stock <= 5;
  const specs = [
    { label: "Processor", value: product.processor },
    { label: "Graphics Card", value: product.graphics_card },
    { label: "Memory (RAM)", value: product.ram_details },
    { label: "Storage", value: product.storage_details },
    { label: "Brand", value: product.brand },
    { label: "Category", value: product.categorie?.nom || "Hardware" },
    { label: "Availability", value: isOut ? "Out of Stock" : `${product.stock} Units Available` },
    { label: "Warranty", value: "3 Years Elite PC Care" },
  ].filter((spec) => spec.value);

  return (
    <div className="quick-view-overlay" onClick={onClose}>
      <section className="quick-view" onClick={(event) => event.stopPropagation()} aria-modal="true" role="dialog" aria-label={product.nom}>
        <div className="quick-view-media">
          <img src={productImage(product)} alt={product.nom} onError={(event) => applyProductFallback(event, product)} decoding="async" />
          {isOut && <span className="quick-view-badge is-out">Sold out</span>}
          {isLimited && <span className="quick-view-badge is-limited">Limited stock</span>}
        </div>

        <div className="quick-view-content">
          <header className="quick-view-header">
            <span>{product.categorie?.nom || "Hardware"}</span>
            <button type="button" onClick={onClose} aria-label="Close quick view">×</button>
          </header>
          <h2>{product.nom}</h2>
          <p className="quick-view-description">{product.description || "High-performance PC hardware component."}</p>

          <div className="quick-view-specs">
            <h3>Technical specifications</h3>
            <dl>
              {specs.map((spec) => (
                <div key={spec.label}>
                  <dt>{spec.label}</dt>
                  <dd>{spec.value}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div className="quick-view-price">
            <span>Price</span>
            <strong>{formatCurrency(product.prix)}</strong>
          </div>
          <button
            className="quick-view-add"
            disabled={isOut}
            onClick={() => {
              onAddToCart(product);
              onClose();
            }}
          >
            {isOut ? "Out of stock" : "Add to cart →"}
          </button>
        </div>
      </section>
    </div>
  );
}
