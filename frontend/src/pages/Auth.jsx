import React, { useState } from "react";
import { Link, Navigate, useLocation, useNavigate } from "react-router-dom";
import { login, register } from "../api/client";
import useApp from "../context/useApp";
import BrandLogo from "../components/BrandLogo";

function errorMessage(error) {
  const errors = error?.data?.errors;
  if (errors) return Object.values(errors).flat().join(" ");
  return error?.data?.message || error?.message || "We could not complete that request. Please try again.";
}

export default function Auth({ mode }) {
  const isRegister = mode === "register";
  const { user, handleLogin } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({ nom: "", email: "", mot_de_passe: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  if (user) return <Navigate to="/" replace />;

  const submit = async (event) => {
    event.preventDefault();
    setError("");
    setLoading(true);
    try {
      const payload = isRegister ? form : { email: form.email, mot_de_passe: form.mot_de_passe };
      const data = await (isRegister ? register(payload) : login(payload));
      handleLogin(data.user, data.token);
      navigate(location.state?.from || "/", { replace: true });
    } catch (requestError) {
      setError(errorMessage(requestError));
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="auth-page">
      <section className="auth-card" aria-labelledby="auth-title">
        <Link className="auth-brand" to="/" aria-label="Elite PC home">
          <BrandLogo />
        </Link>
        <span className="store-eyebrow">Customer account</span>
        <h1 id="auth-title">{isRegister ? "Create Account" : "Welcome Back"}</h1>
        <p>{isRegister ? "Create your customer account to order hardware and view purchases." : "Sign in to manage your orders and continue shopping."}</p>

        <form className="auth-form" onSubmit={submit}>
          {error && <div className="auth-error" role="alert">{error}</div>}
          {isRegister && (
            <label>
              <span>Name</span>
              <input name="nom" autoComplete="name" required value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} />
            </label>
          )}
          <label>
            <span>Email</span>
            <input name="email" type="email" autoComplete="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </label>
          <label>
            <span>Password</span>
            <input name="mot_de_passe" type="password" minLength={isRegister ? 6 : undefined} autoComplete={isRegister ? "new-password" : "current-password"} required value={form.mot_de_passe} onChange={(e) => setForm({ ...form, mot_de_passe: e.target.value })} />
          </label>
          <button className="button button-primary auth-submit" disabled={loading}>
            {loading ? "Please wait…" : isRegister ? "Create Account" : "Sign In"}
          </button>
        </form>

        <div className="auth-switch">
          {isRegister ? "Already have an account?" : "New to Elite PC?"}{" "}
          <Link to={isRegister ? "/login" : "/register"}>{isRegister ? "Sign In" : "Create Account"}</Link>
        </div>
      </section>
    </main>
  );
}
