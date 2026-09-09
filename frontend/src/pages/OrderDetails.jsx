import React, { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { downloadInvoice, getOrder } from "../api/client";
import useApp from "../context/useApp";
import { StorefrontState } from "../components/StorefrontUI";
import { applyProductFallback, productImage } from "../utils/productAssets";
import { formatCurrency } from "../utils/currency";

const statusLabel = { en_cours: "In progress", validee: "Confirmed", annulee: "Cancelled" };

export default function OrderDetails() {
  const { id } = useParams();
  const { user, token, toast } = useApp();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(Boolean(token));
  const [error, setError] = useState("");
  const [downloading, setDownloading] = useState(false);

  useEffect(() => {
    if (!token) return;
    getOrder(id, token).then(setOrder)
      .catch((requestError) => setError(requestError.status === 403 ? "You do not have access to this order." : requestError.message || "Order details could not be loaded."))
      .finally(() => setLoading(false));
  }, [id, token]);

  const handleInvoice = async () => {
    setDownloading(true);
    try { await downloadInvoice(id, token); toast("Invoice downloaded successfully", "success"); }
    catch { toast("Invoice could not be downloaded", "error"); }
    finally { setDownloading(false); }
  };

  if (!user) return <main className="order-details-page"><StorefrontState title="Sign in to view this order" message="Order details and invoices are available to the account that placed the order." actionLabel="Sign In" actionTo="/login" /></main>;
  if (loading) return <main className="order-details-page"><div className="order-detail-loading"><div className="skel" /><div className="skel" /><div className="skel" /></div></main>;
  if (error || !order) return <main className="order-details-page"><StorefrontState title="Order unavailable" message={error || "This order could not be found."} actionLabel="Back to My Orders" actionTo="/orders" /></main>;

  return (
    <main className="order-details-page">
      <Link className="product-breadcrumb" to="/orders">← Back to My Orders</Link>
      <header className="order-detail-header">
        <div><span className="store-eyebrow">Order details</span><h1>Order #{String(order.id).padStart(6, "0")}</h1><p>Placed {new Date(order.created_at).toLocaleString()}</p></div>
        <div className="order-detail-actions"><span className={`order-status is-${order.statut}`}>{statusLabel[order.statut] || order.statut}</span><button className="button button-secondary" onClick={handleInvoice} disabled={downloading}>{downloading ? "Downloading…" : "Download Invoice"}</button></div>
      </header>
      <div className="order-detail-layout">
        <section className="order-items-panel"><h2>Purchased Products</h2><div className="order-items-list">{order.details?.map((detail) => (
          <article className="order-product" key={detail.id}>
            <img src={productImage(detail.produit)} alt="" loading="lazy" decoding="async" onError={(event) => applyProductFallback(event, detail.produit)} />
            <div><strong>{detail.produit?.nom || "Product unavailable"}</strong><span>Quantity: {detail.quantite}</span><span>Unit price: {formatCurrency(detail.prix_unitaire)}</span></div>
            <b>{formatCurrency(Number(detail.prix_unitaire) * detail.quantite)}</b>
          </article>
        ))}</div></section>
        <aside className="order-info-column">
          <section className="order-info-panel"><h2>Delivery Information</h2><dl>{order.user?.nom && <><dt>Customer</dt><dd>{order.user.nom}</dd></>}<dt>Address</dt><dd>{order.delivery_address || "Not provided"}</dd><dt>Phone</dt><dd>{order.delivery_phone || "Not provided"}</dd>{order.estimated_delivery_date && <><dt>Estimated delivery</dt><dd>{new Date(order.estimated_delivery_date).toLocaleDateString()}</dd></>}</dl></section>
          <section className="order-info-panel"><h2>Order Summary</h2><dl>{order.payment_method && <><dt>Payment method</dt><dd className="capitalize">{order.payment_method.replace(/_/g, " ")}</dd></>}<dt>Status</dt><dd className={`order-status-text is-${order.statut}`}>{statusLabel[order.statut] || order.statut}</dd></dl><div className="order-grand-total"><span>Total</span><strong>{formatCurrency(order.total)}</strong></div></section>
        </aside>
      </div>
    </main>
  );
}
