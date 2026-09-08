import React from "react";
import { Link } from "react-router-dom";
import BrandLogo from "./BrandLogo";

export default function Footer() {
  return (
    <footer className="footer">
      <div className="footer-inner">
        <div className="footer-grid">
          <div className="footer-brand">
            <Link className="footer-logo" to="/" aria-label="Elite PC home"><BrandLogo /></Link>
            <p>High-performance PC hardware and systems, presented in a clear, considered shopping experience.</p>
          </div>
          <div className="footer-col">
            <h3>Shop</h3>
            <Link to="/products">All products</Link>
            <Link to="/favorites">Favorites</Link>
          </div>
          <div className="footer-col">
            <h3>Elite PC</h3>
            <Link to="/about">About</Link>
            <Link to="/contact">Contact</Link>
          </div>
          <div className="footer-col">
            <h3>Your account</h3>
            <Link to="/orders">Orders</Link>
          </div>
        </div>
        <div className="footer-bottom">
          <div>© 2026 Elite PC. All rights reserved.</div>
          <span>Built for hardware that matters.</span>
        </div>
      </div>
    </footer>
  );
}
