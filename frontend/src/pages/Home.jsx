import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { getCategories, getProducts } from "../api/client";
import ProductCard from "../components/ProductCard";
import { ProductGridSkeleton, SectionHeading, StorefrontState } from "../components/StorefrontUI";
import { applyProductFallback, categoryImage } from "../utils/productAssets";

export default function Home() {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    Promise.all([getProducts(), getCategories()])
      .then(([productResponse, categoryResponse]) => {
        setProducts(productResponse.data || []);
        setCategories(Array.isArray(categoryResponse) ? categoryResponse : []);
      })
      .catch(() => setError("The catalog could not be loaded right now."))
      .finally(() => setLoading(false));
  }, []);

  const featured = products.slice(0, 4);
  const heroImage = "/products/nova-strike-esports.webp";
  const promoImage = "/categories/gamer-pcs.webp";

  return (
    <>
      <section className="hero" style={{ "--hero-image": `url("${heroImage}")` }}>
        <div className="hero-inner animate-item storefront-container">
          <div className="hero-tag">Elite PC • High-performance hardware</div>
          <h1 className="hero-title">
            Built for <em>Performance</em>
          </h1>
          <p className="hero-desc">
            From high-end components to complete systems, discover hardware built
            for gaming, content creation, and demanding workloads.
          </p>
          <div className="hero-actions">
            <Link to="/products" className="button button-primary">View the catalog <span>→</span></Link>
            <Link to="/about" className="button button-secondary">Discover Elite PC</Link>
          </div>
        </div>
      </section>

      <main className="home-content storefront-container">
        <section className="home-section category-section">
          <SectionHeading eyebrow="Catalogue" title="Browse by Category" />
          {loading ? (
            <div className="category-grid category-grid-loading">
              {Array.from({ length: 5 }, (_, index) => <div className="skel category-card" key={index} />)}
            </div>
          ) : categories.length > 0 ? (
            <div className="category-grid">
              {categories.map((category) => {
                const representative = { categorie: category };
                const image = categoryImage(category);
                return <Link className="category-card" to="/products" key={category.id}>
                  <img src={image} alt="" loading="lazy" decoding="async" onError={(event) => applyProductFallback(event, representative)} />
                  <span className="category-card-shade" />
                  <span className="category-name">{category.nom}</span>
                  <span className="category-arrow" aria-hidden="true">↗</span>
                </Link>
              })}
            </div>
          ) : null}
        </section>

        <section className="home-section" id="products">
          <SectionHeading
            eyebrow="Current selection"
            title="Featured Hardware & Rigs"
            actionLabel="View all"
            actionTo="/products"
          />

        {loading ? (
          <ProductGridSkeleton />
        ) : error ? (
          <StorefrontState title="Catalog unavailable" message={error} actionLabel="Open the catalog" actionTo="/products" />
        ) : featured.length === 0 ? (
          <StorefrontState title="No products available" message="The catalog does not contain any products yet." />
        ) : (
          <div className="grid product-grid">
            {featured.map((p, index) => (
              <ProductCard key={p.id} product={p} index={index} />
            ))}
          </div>
        )}

        </section>

        <section className="promo-panel">
          <div className="promo-copy">
            <span className="store-eyebrow">Your next system</span>
            <h2>Performance starts with the right components.</h2>
            <p>Explore available systems, components, and peripherals, compare their real specifications, and choose the setup that fits your needs.</p>
            <div className="promo-actions">
              <Link className="button button-primary" to="/products">Explore hardware</Link>
              <Link className="text-link" to="/contact">Need help?</Link>
            </div>
          </div>
          <div className="promo-image" style={{ backgroundImage: `url("${promoImage}")` }} role="img" aria-label="Elite PC computer hardware" />
        </section>

        <section className="benefits" aria-label="Store benefits">
          <article><span className="benefit-icon">01</span><h3>Technical information</h3><p>Available specifications are presented clearly for every product.</p></article>
          <article><span className="benefit-icon">02</span><h3>Visible stock</h3><p>Displayed availability comes directly from the Elite PC catalog.</p></article>
          <article><span className="benefit-icon">03</span><h3>Simple shopping</h3><p>Favorites, cart, and orders remain accessible through consistent navigation.</p></article>
          <article><span className="benefit-icon">04</span><h3>Advice within reach</h3><p>Support and the Elite AI assistant are available throughout the store.</p></article>
        </section>
      </main>
    </>
  );
}
