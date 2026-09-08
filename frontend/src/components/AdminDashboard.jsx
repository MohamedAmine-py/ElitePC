import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link } from "react-router-dom";
import BrandLogo from "./BrandLogo";
import { formatCurrency } from "../utils/currency";
import {
  createCategory, createProduct, createUser, deleteCategory, deleteOrder,
  deleteProduct, deleteUser, getCategories, getOrders, getProducts, getStats,
  getUsers, updateCategory, updateOrderStatus, updateProduct, updateUser,
} from "../api/client";
import "../styles/AdminDashboard.css";

const tabs = [
  ["overview", "Overview"], ["products", "Products"],
  ["categories", "Categories"], ["orders", "Orders"],
  ["users", "Users"],
];

function AdminNavIcon({ name }) {
  const paths = {
    overview: <><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /></>,
    products: <><path d="M4 7h16v13H4z" /><path d="M3 4h18v3H3z" /><path d="M9 11h6" /></>,
    categories: <><path d="m12 3 4 4-4 4-4-4 4-4Z" /><path d="m6 13 3 3-3 3-3-3 3-3Z" /><path d="m18 13 3 3-3 3-3-3 3-3Z" /></>,
    orders: <><path d="M6 3h12v18H6z" /><path d="M9 7h6M9 11h6M9 15h4" /><path d="M4 6v12M20 6v12" /></>,
    users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></>,
  };

  return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>;
}
const statusLabels = { en_cours: "In progress", validee: "Confirmed", annulee: "Cancelled" };
const blankProduct = { nom: "", prix: "", stock: "", categorie_id: "", description: "", image: "", brand: "", processor: "", graphics_card: "", ram_details: "", storage_details: "", is_custom_build: false };
const blankCategory = { nom: "", description: "" };
const blankUser = { nom: "", email: "", mot_de_passe: "", role: "client" };
const date = (value) => new Date(value).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });

function requestMessage(error) {
  const errors = error?.data?.errors;
  return errors ? Object.values(errors).flat().join(" ") : error?.data?.message || error?.message || "The request could not be completed.";
}

export default function AdminDashboard({ token, user, onToast }) {
  const [tab, setTab] = useState("overview");
  const [stats, setStats] = useState(null);
  const [users, setUsers] = useState([]);
  const [orders, setOrders] = useState([]);
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState("");
  const [modal, setModal] = useState(null);
  const [editing, setEditing] = useState(null);
  const [productForm, setProductForm] = useState(blankProduct);
  const [categoryForm, setCategoryForm] = useState(blankCategory);
  const [userForm, setUserForm] = useState(blankUser);
  const [actionLoading, setActionLoading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [expandedOrder, setExpandedOrder] = useState(null);
  const loadedToken = useRef(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const [statsResponse, usersResponse, ordersResponse, productsResponse, categoriesResponse] = await Promise.all([
        getStats(token), getUsers(token), getOrders(token), getProducts(), getCategories(),
      ]);
      setStats(statsResponse);
      setUsers(Array.isArray(usersResponse) ? usersResponse : []);
      setOrders(Array.isArray(ordersResponse) ? ordersResponse : []);
      setProducts(productsResponse?.data || []);
      setCategories(Array.isArray(categoriesResponse) ? categoriesResponse : []);
    } catch (error) {
      setLoadError(requestMessage(error));
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    if (loadedToken.current === token) return;
    loadedToken.current = token;
    const request = Promise.resolve().then(loadData);
    return () => { void request; };
  }, [loadData, token]);

  const pageMeta = useMemo(() => ({
    overview: ["Dashboard", "A current overview of the Elite PC store."],
    products: ["Products", "Manage catalog information, pricing, stock, and specifications."],
    categories: ["Categories", "Organize the product catalog using the existing categories."],
    orders: ["Orders", "Review customer orders and manage their current status."],
    users: ["Users", "Manage customer and administrator accounts."],
  })[tab], [tab]);

  const refresh = async (resource) => {
    if (resource === "product") {
      const [productResponse, statsResponse] = await Promise.all([getProducts(), getStats(token)]);
      setProducts(productResponse?.data || []);
      setStats(statsResponse);
    } else if (resource === "category") {
      const [categoriesResponse, productResponse, statsResponse] = await Promise.all([getCategories(), getProducts(), getStats(token)]);
      setCategories(Array.isArray(categoriesResponse) ? categoriesResponse : []);
      setProducts(productResponse?.data || []);
      setStats(statsResponse);
    } else if (resource === "user") {
      const [usersResponse, ordersResponse, statsResponse] = await Promise.all([getUsers(token), getOrders(token), getStats(token)]);
      setUsers(Array.isArray(usersResponse) ? usersResponse : []);
      setOrders(Array.isArray(ordersResponse) ? ordersResponse : []);
      setStats(statsResponse);
    } else if (resource === "order") {
      const [ordersResponse, statsResponse, productResponse] = await Promise.all([getOrders(token), getStats(token), getProducts()]);
      setOrders(Array.isArray(ordersResponse) ? ordersResponse : []);
      setStats(statsResponse);
      setProducts(productResponse?.data || []);
    }
  };

  const mutate = async (operation, success, resource, after) => {
    setActionLoading(true);
    try {
      await operation();
      if (after) after();
      await refresh(resource);
      onToast(success);
    } catch (error) {
      onToast(requestMessage(error), "error");
    } finally {
      setActionLoading(false);
      setDeletingId(null);
    }
  };

  const openProduct = (product = null) => {
    setEditing(product);
    setProductForm(product ? { ...blankProduct, ...product, categorie_id: product.categorie_id || "", is_custom_build: Boolean(product.is_custom_build) } : { ...blankProduct, categorie_id: categories[0]?.id || "" });
    setModal("product");
  };
  const openCategory = (category = null) => { setEditing(category); setCategoryForm(category ? { nom: category.nom, description: category.description || "" } : blankCategory); setModal("category"); };
  const openUser = (account = null) => { setEditing(account); setUserForm(account ? { nom: account.nom, email: account.email, mot_de_passe: "", role: account.role } : blankUser); setModal("user"); };

  const saveProduct = () => mutate(
    () => editing ? updateProduct(editing.id, productForm, token) : createProduct(productForm, token),
    editing ? "Product updated" : "Product created", "product", () => setModal(null),
  );
  const saveCategory = () => mutate(
    () => editing ? updateCategory(editing.id, categoryForm, token) : createCategory(categoryForm, token),
    editing ? "Category updated" : "Category created", "category", () => setModal(null),
  );
  const saveUser = () => {
    const payload = { ...userForm };
    if (editing && !payload.mot_de_passe) delete payload.mot_de_passe;
    return mutate(() => editing ? updateUser(editing.id, payload, token) : createUser(payload, token), editing ? "User updated" : "User created", "user", () => setModal(null));
  };
  const remove = (kind, id) => {
    const labels = { product: "product", category: "category", order: "order", user: "user" };
    const warnings = { category: "Deleting this category may affect related products.", order: "Deleting an order is permanent. Existing stock-safety rules will be applied." };
    if (!window.confirm(`${warnings[kind] || `Delete this ${labels[kind]}?`}${warnings[kind] ? " Continue?" : ""}`)) return;
    const calls = { product: deleteProduct, category: deleteCategory, order: deleteOrder, user: deleteUser };
    setDeletingId(`${kind}-${id}`);
    mutate(() => calls[kind](id, token), `${labels[kind][0].toUpperCase()}${labels[kind].slice(1)} deleted`, kind);
  };
  const changeOrderStatus = (id, statut) => mutate(() => updateOrderStatus(id, statut, token), "Order status updated", "order");

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-brand"><BrandLogo /><span>Admin</span></div>
        <nav aria-label="Admin navigation">{tabs.map(([id, label]) => <button key={id} className={tab === id ? "is-active" : ""} onClick={() => setTab(id)}><span><AdminNavIcon name={id} /></span>{label}</button>)}</nav>
        <Link className="admin-store-link" to="/">← Return to storefront</Link>
      </aside>

      <section className="admin-workspace">
        <div className="admin-topbar"><div><strong>{user.nom}</strong><span>{user.email}</span></div><span className="admin-role">Admin</span></div>
        <header className="admin-page-heading"><div><span>Elite PC management</span><h1>{pageMeta[0]}</h1><p>{pageMeta[1]}</p></div>{tab !== "overview" && <span className="admin-count">{({ products, categories, orders, users })[tab].length} records</span>}</header>

        {loading && !stats ? <AdminState title="Loading Admin" message="Retrieving current store data…" />
          : loadError ? <AdminState title="Admin data unavailable" message={loadError} action="Retry" onAction={loadData} />
          : <>
            {tab === "overview" && <Overview stats={stats} orders={orders} />}
            {tab === "products" && <Products products={products} onAdd={() => openProduct()} onEdit={openProduct} onDelete={(id) => remove("product", id)} deletingId={deletingId} />}
            {tab === "categories" && <Categories categories={categories} onAdd={() => openCategory()} onEdit={openCategory} onDelete={(id) => remove("category", id)} deletingId={deletingId} />}
            {tab === "orders" && <Orders orders={orders} expandedOrder={expandedOrder} setExpandedOrder={setExpandedOrder} onStatus={changeOrderStatus} onDelete={(id) => remove("order", id)} deletingId={deletingId} actionLoading={actionLoading} />}
            {tab === "users" && <Users users={users} onAdd={() => openUser()} onEdit={openUser} onDelete={(id) => remove("user", id)} deletingId={deletingId} />}
          </>}
      </section>

      {modal === "product" && <ProductModal editing={editing} form={productForm} setForm={setProductForm} categories={categories} onClose={() => setModal(null)} onSave={saveProduct} loading={actionLoading} />}
      {modal === "category" && <CategoryModal editing={editing} form={categoryForm} setForm={setCategoryForm} onClose={() => setModal(null)} onSave={saveCategory} loading={actionLoading} />}
      {modal === "user" && <UserModal editing={editing} form={userForm} setForm={setUserForm} onClose={() => setModal(null)} onSave={saveUser} loading={actionLoading} />}
    </div>
  );
}

function orderTrend(orders, days = 7) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return Array.from({ length: days }, (_, index) => {
    const day = new Date(today);
    day.setDate(today.getDate() - (days - index - 1));
    const nextDay = new Date(day);
    nextDay.setDate(day.getDate() + 1);
    const count = orders.filter((order) => {
      const placedAt = new Date(order.created_at);
      return placedAt >= day && placedAt < nextDay;
    }).length;

    return {
      key: day.toISOString().slice(0, 10),
      label: day.toLocaleDateString(undefined, { weekday: "short" }),
      dateLabel: day.toLocaleDateString(undefined, { month: "short", day: "numeric" }),
      count,
    };
  });
}

function Overview({ stats, orders }) {
  const cards = [["Clients", stats?.total_users], ["Products", stats?.total_produits], ["Orders", stats?.total_commandes], ["In progress", stats?.commandes_en_cours], ["Confirmed revenue", formatCurrency(stats?.revenus_total)]];
  const trend = orderTrend(orders);
  const maxOrders = Math.max(1, ...trend.map((point) => point.count));
  const periodTotal = trend.reduce((sum, point) => sum + point.count, 0);

  return <div className="admin-overview">
    <div className="admin-stat-grid">{cards.map(([label, value], index) => <article className="admin-stat" key={label}><span>0{index + 1}</span><strong>{value ?? 0}</strong><p>{label}</p></article>)}</div>
    <section className="admin-trend-panel" aria-labelledby="admin-order-trend-title">
      <header><div><span>Order activity</span><h2 id="admin-order-trend-title">Orders — last 7 days</h2><p>All customer orders, grouped by placement date.</p></div><strong>{periodTotal}<small>orders</small></strong></header>
      <div className="admin-bar-chart" role="img" aria-label={`${periodTotal} orders placed over the last seven days`}>
        {trend.map((point) => <div className="admin-bar-column" key={point.key} title={`${point.dateLabel}: ${point.count} order${point.count === 1 ? "" : "s"}`}>
          <span className="admin-bar-value">{point.count}</span>
          <div className="admin-bar-track"><span style={{ height: `${(point.count / maxOrders) * 100}%` }} /></div>
          <span className="admin-bar-day">{point.label}<small>{point.dateLabel}</small></span>
        </div>)}
      </div>
    </section>
    <Panel title="Recent Orders"><AdminTable headers={["Order", "Customer", "Total", "Status", "Date"]}>{(stats?.recent_orders || []).map((order) => <tr key={order.id}><td className="admin-id">#{order.id}</td><td>{order.user?.nom || "—"}</td><td>{formatCurrency(order.total)}</td><td><Status value={order.statut} /></td><td>{date(order.created_at)}</td></tr>)}</AdminTable></Panel>
  </div>;
}

function Products({ products, onAdd, onEdit, onDelete, deletingId }) {
  return <Management title={`${products.length} products`} action="New Product" onAction={onAdd}><AdminTable headers={["Product", "Category", "Price", "Stock", "Actions"]}>{products.map((product) => <tr key={product.id}><td><strong>{product.nom}</strong>{product.brand && <small>{product.brand}</small>}</td><td>{product.categorie?.nom || "—"}</td><td className="admin-price">{formatCurrency(product.prix)}</td><td><Stock value={product.stock} /></td><td><Actions onEdit={() => onEdit(product)} onDelete={() => onDelete(product.id)} deleting={deletingId === `product-${product.id}`} /></td></tr>)}</AdminTable></Management>;
}

function Categories({ categories, onAdd, onEdit, onDelete, deletingId }) {
  return <Management title={`${categories.length} categories`} action="New Category" onAction={onAdd}><AdminTable headers={["ID", "Category", "Description", "Products", "Actions"]}>{categories.map((category) => <tr key={category.id}><td className="admin-id">#{category.id}</td><td><strong>{category.nom}</strong></td><td className="admin-description">{category.description || "—"}</td><td>{category.produits_count ?? 0}</td><td><Actions onEdit={() => onEdit(category)} onDelete={() => onDelete(category.id)} deleting={deletingId === `category-${category.id}`} /></td></tr>)}</AdminTable></Management>;
}

function Orders({ orders, expandedOrder, setExpandedOrder, onStatus, onDelete, deletingId, actionLoading }) {
  if (!orders.length) return <AdminState title="No orders" message="Customer orders will appear here." />;
  return <Panel><AdminTable headers={["Order", "Customer", "Total", "Items", "Status", "Date", "Actions"]}>{orders.flatMap((order) => {
    const expanded = expandedOrder === order.id;
    return [<tr key={order.id}><td className="admin-id">#{order.id}</td><td><strong>{order.user?.nom || "—"}</strong><small>{order.user?.email}</small></td><td className="admin-price">{formatCurrency(order.total)}</td><td>{order.details?.length || 0}</td><td><select className={`admin-status-select is-${order.statut}`} value={order.statut} disabled={actionLoading} onChange={(event) => onStatus(order.id, event.target.value)}><option value="en_cours">In progress</option><option value="validee">Confirmed</option><option value="annulee">Cancelled</option></select></td><td>{date(order.created_at)}</td><td><div className="admin-actions"><button onClick={() => setExpandedOrder(expanded ? null : order.id)}>{expanded ? "Close" : "Details"}</button><button className="is-danger" onClick={() => onDelete(order.id)} disabled={deletingId === `order-${order.id}`}>{deletingId === `order-${order.id}` ? "…" : "Delete"}</button></div></td></tr>, expanded && <tr className="admin-order-detail-row" key={`${order.id}-details`}><td colSpan="7"><OrderDetail order={order} /></td></tr>];
  })}</AdminTable></Panel>;
}

function OrderDetail({ order }) {
  return <div className="admin-order-detail"><div><h3>Order items</h3>{order.details?.map((detail) => <div className="admin-order-line" key={detail.id}><span>{detail.produit?.nom || "Product unavailable"} × {detail.quantite}</span><strong>{formatCurrency(Number(detail.prix_unitaire) * detail.quantite)}</strong></div>)}</div><dl><dt>Delivery address</dt><dd>{order.delivery_address || "—"}</dd><dt>Phone</dt><dd>{order.delivery_phone || "—"}</dd><dt>Payment method</dt><dd>{order.payment_method?.replace(/_/g, " ") || "—"}</dd><dt>Order total</dt><dd>{formatCurrency(order.total)}</dd></dl></div>;
}

function Users({ users, onAdd, onEdit, onDelete, deletingId }) {
  return <Management title={`${users.length} users`} action="New User" onAction={onAdd}><AdminTable headers={["Name", "Email", "Role", "Orders", "Joined", "Actions"]}>{users.map((account) => <tr key={account.id}><td><strong>{account.nom}</strong></td><td>{account.email}</td><td><span className={`admin-role-badge is-${account.role}`}>{account.role}</span></td><td>{account.commandes_count || 0}</td><td>{date(account.created_at)}</td><td><Actions onEdit={() => onEdit(account)} onDelete={() => onDelete(account.id)} deleting={deletingId === `user-${account.id}`} /></td></tr>)}</AdminTable></Management>;
}

function Management({ title, action, onAction, children }) { return <div><div className="admin-management-bar"><strong>{title}</strong><button className="admin-primary" onClick={onAction}>+ {action}</button></div><Panel>{children}</Panel></div>; }
function Panel({ title, children }) { return <section className="admin-panel">{title && <div className="admin-panel-title"><h2>{title}</h2></div>}{children}</section>; }
function Status({ value }) { return <span className={`admin-status is-${value}`}>{statusLabels[value] || value}</span>; }
function Stock({ value }) { return <span className={`admin-stock ${value === 0 ? "is-out" : value <= 5 ? "is-low" : ""}`}>{value}</span>; }
function Actions({ onEdit, onDelete, deleting }) { return <div className="admin-actions"><button onClick={onEdit}>Edit</button><button className="is-danger" onClick={onDelete} disabled={deleting}>{deleting ? "…" : "Delete"}</button></div>; }

function AdminTable({ headers, children }) { return <div className="admin-table-wrap"><table className="admin-table"><thead><tr>{headers.map((header) => <th key={header}>{header}</th>)}</tr></thead><tbody>{children}</tbody></table></div>; }
function AdminState({ title, message, action, onAction }) { return <div className="admin-state"><span>EP</span><h2>{title}</h2><p>{message}</p>{action && <button className="admin-secondary" onClick={onAction}>{action}</button>}</div>; }

function Modal({ title, children, onClose, onSave, loading, saveText }) {
  return <div className="admin-modal-overlay" onMouseDown={(event) => event.target === event.currentTarget && onClose()}><section className="admin-modal" role="dialog" aria-modal="true" aria-label={title}><header><div><span>Elite PC Admin</span><h2>{title}</h2></div><button aria-label="Close" onClick={onClose}>×</button></header><div className="admin-modal-body">{children}</div><footer><button className="admin-secondary" onClick={onClose}>Cancel</button><button className="admin-primary" onClick={onSave} disabled={loading}>{loading ? "Saving…" : saveText}</button></footer></section></div>;
}
function Field({ label, children }) { return <label className="admin-field"><span>{label}</span>{children}</label>; }
function Input({ form, setForm, name, type = "text", required = false }) { return <input type={type} required={required} value={form[name]} onChange={(event) => setForm({ ...form, [name]: event.target.value })} />; }

function ProductModal({ editing, form, setForm, categories, onClose, onSave, loading }) {
  return <Modal title={editing ? "Edit Product" : "New Product"} onClose={onClose} onSave={onSave} loading={loading} saveText={editing ? "Save Changes" : "Create Product"}><div className="admin-form-grid"><Field label="Product name"><Input form={form} setForm={setForm} name="nom" required /></Field><Field label="Category"><select value={form.categorie_id} onChange={(event) => setForm({ ...form, categorie_id: event.target.value })}><option value="">Select category</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.nom}</option>)}</select></Field><Field label="Price (USD)"><Input form={form} setForm={setForm} name="prix" type="number" required /></Field><Field label="Stock"><Input form={form} setForm={setForm} name="stock" type="number" required /></Field><Field label="Brand"><Input form={form} setForm={setForm} name="brand" /></Field><Field label="Processor"><Input form={form} setForm={setForm} name="processor" /></Field><Field label="Graphics card"><Input form={form} setForm={setForm} name="graphics_card" /></Field><Field label="RAM"><Input form={form} setForm={setForm} name="ram_details" /></Field><Field label="Storage"><Input form={form} setForm={setForm} name="storage_details" /></Field><Field label="Image URL"><Input form={form} setForm={setForm} name="image" /></Field><Field label="Description"><textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} rows="4" /></Field><label className="admin-checkbox"><input type="checkbox" checked={form.is_custom_build} onChange={(event) => setForm({ ...form, is_custom_build: event.target.checked })} /><span>Custom build</span></label></div></Modal>;
}
function CategoryModal({ editing, form, setForm, onClose, onSave, loading }) { return <Modal title={editing ? "Edit Category" : "New Category"} onClose={onClose} onSave={onSave} loading={loading} saveText={editing ? "Save Changes" : "Create Category"}><div className="admin-form-grid"><Field label="Category name"><Input form={form} setForm={setForm} name="nom" required /></Field><Field label="Description"><textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} rows="4" /></Field></div></Modal>; }
function UserModal({ editing, form, setForm, onClose, onSave, loading }) { return <Modal title={editing ? "Edit User" : "New User"} onClose={onClose} onSave={onSave} loading={loading} saveText={editing ? "Save Changes" : "Create User"}><div className="admin-form-grid"><Field label="Name"><Input form={form} setForm={setForm} name="nom" required /></Field><Field label="Email"><Input form={form} setForm={setForm} name="email" type="email" required /></Field><Field label={editing ? "Password (leave blank to keep current)" : "Password"}><Input form={form} setForm={setForm} name="mot_de_passe" type="password" required={!editing} /></Field><Field label="Role"><select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}><option value="client">Client</option><option value="admin">Admin</option></select></Field></div></Modal>; }
