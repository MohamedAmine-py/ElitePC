const BASE = (import.meta.env.VITE_API_URL || "/api").replace(/\/$/, "");
export async function apiCall(method, endpoint, body = null, token = null) {
  const headers = { "Content-Type": "application/json", Accept: "application/json" };
  if (token) headers["Authorization"] = `Bearer ${token}`;
  const res = await fetch(BASE + endpoint, { method, headers, body: body ? JSON.stringify(body) : null });
  const contentType = res.headers.get("content-type") || "";
  const data = contentType.includes("application/json") ? await res.json() : null;

  if (!res.ok) {
    const error = new Error(data?.message || `Request failed with status ${res.status}`);
    error.status = res.status;
    error.data = data;
    throw error;
  }

  return data;
}
export const login = (data) => apiCall("POST", "/login", data);
export const register = (data) => apiCall("POST", "/register", data);
export const logout = (token) => apiCall("POST", "/logout", null, token);
export const getCurrentUser = (token) => apiCall("GET", "/user", null, token);
export const getProducts = () => apiCall("GET", "/products?per_page=50");
export const getProduct = (id) => apiCall("GET", `/products/${id}`);
export const getCategories = () => apiCall("GET", "/categories");
export const getFavorites = (token) => apiCall("GET", "/favorites", null, token);
export const addFavorite = (produit_id, token) => apiCall("POST", "/favorites", { produit_id }, token);
export const deleteFavorite = (id, token) => apiCall("DELETE", `/favorites/${id}`, null, token);
export const getCart = (token) => apiCall("GET", "/cart", null, token);
export const addCartItem = (produit_id, quantity, token) => apiCall("POST", "/cart/items", { produit_id, quantity }, token);
export const updateCartQuantity = (id, quantity, token) => apiCall("PATCH", `/cart/items/${id}`, { quantity }, token);
export const deleteCartItem = (id, token) => apiCall("DELETE", `/cart/items/${id}`, null, token);
export const createProduct = (data, token) => apiCall("POST", "/products", data, token);
export const updateProduct = (id, data, token) => apiCall("PUT", `/products/${id}`, data, token);
export const deleteProduct = (id, token) => apiCall("DELETE", `/products/${id}`, null, token);
export const getOrders = (token) => apiCall("GET", "/orders", null, token);
export const getOrder = (id, token) => apiCall("GET", `/orders/${id}`, null, token);
export const createOrder = (data, token) => apiCall("POST", "/orders", data, token);
export const updateOrderStatus = (id, statut, token) => apiCall("PATCH", `/orders/${id}/status`, { statut }, token);
export const getStats = (token) => apiCall("GET", "/admin/stats", null, token);
export const getUsers = (token) => apiCall("GET", "/admin/users", null, token);
export const createUser = (data, token) => apiCall("POST", "/admin/users", data, token);
export const updateUser = (id, data, token) => apiCall("PUT", `/admin/users/${id}`, data, token);
export const deleteUser = (id, token) => apiCall("DELETE", `/admin/users/${id}`, null, token);
export const deleteOrder = (id, token) => apiCall("DELETE", `/orders/${id}`, null, token);
export const downloadInvoice = async (id, token) => {
  const headers = { Accept: "application/pdf" };
  if (token) headers["Authorization"] = `Bearer ${token}`;
  const res = await fetch(`${BASE}/orders/${id}/invoice`, { headers });
  if (!res.ok) throw new Error("Failed to download invoice");
  const blob = await res.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `Invoice-${id}.pdf`;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
};
export const createCategory = (data, token) => apiCall("POST", "/categories", data, token);
export const updateCategory = (id, data, token) => apiCall("PUT", `/categories/${id}`, data, token);
export const deleteCategory = (id, token) => apiCall("DELETE", `/categories/${id}`, null, token);
export const sendSupportMessage = (message, history) => apiCall("POST", "/support/chat", { message, history });
