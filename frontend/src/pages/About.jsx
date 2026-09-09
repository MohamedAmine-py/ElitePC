import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { getCategories, getProducts } from "../api/client";
import { BrandMark } from "../components/BrandLogo";
import "../styles/About.css";

const principles = [
  {
    number: "01",
    title: "Performance first",
    text: "Computers, components, and peripherals selected for gaming, content creation, and demanding workloads.",
  },
  {
    number: "02",
    title: "Useful information",
    text: "Prices, availability, and technical specifications are presented clearly to make every comparison easier.",
  },
  {
    number: "03",
    title: "For PC enthusiasts",
    text: "A direct, readable experience for customers who want to understand the hardware they choose.",
  },
];

const hardwarePoints = [
  ["Curated hardware", "Products focused on gaming, workstation, and performance use."],
  ["Clear specifications", "Important technical information, without unnecessary clutter."],
  ["Real availability", "Current catalog stock to help you see what is available."],
  ["Performance focused", "Systems and components for demanding workloads and gaming."],
];

const openAssistant = () => window.dispatchEvent(new Event("elitepc:open-assistant"));

export default function About() {
  const [counts, setCounts] = useState(null);

  useEffect(() => {
    let active = true;
    Promise.all([getProducts(), getCategories()])
      .then(([products, categories]) => {
        if (!active) return;
        setCounts({
          products: Number.isInteger(products?.total) ? products.total : null,
          categories: Array.isArray(categories) ? categories.length : null,
        });
      })
      .catch(() => {
        // Qualitative labels remain useful when catalog counts are unavailable.
      });
    return () => { active = false; };
  }, []);

  return (
    <main className="simple-page about-page">
      <header className="about-editorial about-hero">
        <div className="about-copy">
          <span className="store-eyebrow">About Elite PC</span>
          <h1>Hardware that matters,<br /><em>presented clearly.</em></h1>
          <p>
            Elite PC brings high-performance systems and components together in a
            store designed to make choosing hardware simpler and more precise.
          </p>
          <Link className="button button-primary" to="/products">Explore the catalog</Link>
        </div>
        <figure className="about-image about-hero-image">
          <img src="/about-hardware.webp" alt="Black workstation tower with carefully arranged components and restrained red lighting" width="1536" height="1024" fetchPriority="high" decoding="async" />
          <figcaption>Systems. Components. Possibilities.</figcaption>
        </figure>
      </header>

      <section className="about-principles" aria-label="Elite PC principles">
        {principles.map((principle) => (
          <article className="simple-info-card" key={principle.number}>
            <span className="simple-card-number">{principle.number}</span>
            <h2>{principle.title}</h2>
            <p>{principle.text}</p>
          </article>
        ))}
      </section>

      <section className="about-editorial about-why" aria-labelledby="about-why-title">
        <figure className="about-image">
          <img src="/about-components.webp" alt="Close-up of motherboard heatsinks, memory, and cooling inside a black PC" width="1536" height="1024" loading="lazy" decoding="async" />
          <figcaption>A closer look at what powers your PC.</figcaption>
        </figure>
        <div className="about-copy">
          <span className="store-eyebrow">Why Elite PC</span>
          <h2 id="about-why-title">Built around<br />the hardware.</h2>
          <p>Choosing a PC starts with understanding what is inside. Elite PC puts specifications, availability, and intended use together so you can compare your options with clarity.</p>
          <dl className="about-hardware-points">
            {hardwarePoints.map(([title, text]) => (
              <div key={title}><dt>{title}</dt><dd>{text}</dd></div>
            ))}
          </dl>
        </div>
      </section>

      <section className="about-facts" aria-label="The Elite PC catalog">
        <div><strong>{counts?.products ?? "Explore"}</strong><span>Products</span></div>
        <div><strong>{counts?.categories ?? "Discover"}</strong><span>Categories</span></div>
        <div><strong>Live stock</strong><span>Catalog availability</span></div>
        <div><strong>Elite AI</strong><span>Shopping assistance</span></div>
      </section>

      <section className="about-editorial about-assistant" aria-labelledby="about-ai-title">
        <div className="about-copy">
          <span className="store-eyebrow">A question before you choose?</span>
          <h2 id="about-ai-title">Elite AI helps you compare the available catalog.</h2>
          <p>Ask about products, compare hardware, or explore compatibility. Elite AI uses the current catalog to help you understand your options before you choose.</p>
          <div className="about-actions">
            <button className="button button-primary" type="button" onClick={openAssistant}>Ask Elite AI</button>
            <Link className="button button-secondary" to="/contact">View help options</Link>
          </div>
        </div>
        <div className="about-ai-visual" aria-label="Elite AI hardware shopping assistance">
          <div className="about-ai-brand"><BrandMark /><span>Elite AI</span><p>Hardware shopping assistant</p></div>
          <ul><li>Product questions</li><li>Hardware comparisons</li><li>Compatibility guidance</li></ul>
        </div>
      </section>

      <section className="about-final" aria-labelledby="about-final-title">
        <span className="store-eyebrow">Build your next system</span>
        <h2 id="about-final-title">Ready to find<br />the right hardware?</h2>
        <p>Explore the catalog, compare the details, and find hardware that fits your next project.</p>
        <div className="about-actions">
          <Link className="button button-primary" to="/products">Explore the catalog</Link>
          <button className="button button-secondary" type="button" onClick={openAssistant}>Ask Elite AI</button>
        </div>
      </section>
    </main>
  );
}
