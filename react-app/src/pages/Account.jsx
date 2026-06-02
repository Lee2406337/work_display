import { useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

export default function Account() {
  const [form, setForm] = useState({
    old_password: "",
    new_password: "",
    new_password2: "",
  });

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  function handleChange(e) {
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  async function handleSubmit(e) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      const res = await api.post("/change_password.php", form);

      if (res.data.ok) {
        setMessage(res.data.message || "密碼已更新");

        setForm({
          old_password: "",
          new_password: "",
          new_password2: "",
        });
      } else {
        setError(res.data.message || "修改失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  return (
    <div className="front-body">
      <div className="front-page">
        <h1 className="front-title">
          <span>修改密碼</span>
        </h1>

        <div className="front-box front-header-box">
          <div className="front-nav">
            <Link className="nav-btn" to="/dashboard">
              回首頁
            </Link>
          </div>
        </div>

        <div className="front-box front-main-box">
          {message && <div className="message">{message}</div>}
          {error && <div className="error">{error}</div>}

          <form className="front-form" onSubmit={handleSubmit}>
            <div className="front-form-row">
              <label>舊密碼</label>
              <input
                type="password"
                name="old_password"
                value={form.old_password}
                onChange={handleChange}
                required
              />
            </div>

            <div className="front-form-row">
              <label>新密碼</label>
              <input
                type="password"
                name="new_password"
                value={form.new_password}
                onChange={handleChange}
                required
              />
            </div>

            <div className="front-form-row">
              <label>再次輸入新密碼</label>
              <input
                type="password"
                name="new_password2"
                value={form.new_password2}
                onChange={handleChange}
                required
              />
            </div>

            <div className="front-actions">
              <button type="submit">更新密碼</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}