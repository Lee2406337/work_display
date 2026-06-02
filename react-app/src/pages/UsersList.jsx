import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

const initialFilters = {
  q: "",
  site: "",
  is_active: "",
};

export default function UsersList() {
  const [users, setUsers] = useState([]);
  const [filters, setFilters] = useState(initialFilters);
  const [currentPermission, setCurrentPermission] = useState(1);
  const [error, setError] = useState("");

  function buildParams(currentFilters) {
    const params = new URLSearchParams();

    Object.entries(currentFilters).forEach(([key, value]) => {
      if (value !== "") {
        params.set(key, value);
      }
    });

    return params.toString();
  }

  function handleChange(e) {
    const { name, value } = e.target;

    setFilters((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  function loadUsers(currentFilters = filters) {
    setError("");

    const query = buildParams(currentFilters);

    api
      .get(`/users.php${query ? `?${query}` : ""}`)
      .then((res) => {
        if (res.data.ok) {
          setUsers(res.data.data || []);
          setCurrentPermission(Number(res.data.current_permission || 1));
        } else {
          setUsers([]);
          setError(res.data.message || "查詢失敗");
        }
      })
      .catch(() => {
        setUsers([]);
        setError("API 連線失敗");
      });
  }

  function searchUsers(e) {
    e.preventDefault();
    loadUsers(filters);
  }

  function clearFilters() {
    setFilters(initialFilters);
    loadUsers(initialFilters);
  }

  useEffect(() => {
    let ignore = false;

    api
      .get("/users.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          setUsers(res.data.data || []);
          setCurrentPermission(Number(res.data.current_permission || 1));
        } else {
          setUsers([]);
          setError(res.data.message || "讀取資料失敗");
        }
      })
      .catch(() => {
        if (ignore) return;
        setUsers([]);
        setError("API 連線失敗");
      });

    return () => {
      ignore = true;
    };
  }, []);

  return (
    <div className="front-body">
      <div className="front-page-wide">
        <h1 className="front-title">
          <span>使用者資料查詢</span>
        </h1>

        <div className="front-box front-header-box">
          <div className="front-nav">
            <Link className="nav-btn" to="/dashboard">
              回首頁
            </Link>
          </div>
        </div>

        <div className="front-box front-main-box">
          {error && <div className="error">{error}</div>}

          <form className="front-filter-card" onSubmit={searchUsers}>
            <div className="front-filter-row">
              <div className="front-field">
                <label>關鍵字</label>
                <input
                  type="text"
                  name="q"
                  value={filters.q}
                  onChange={handleChange}
                  placeholder="登錄帳號 / 姓名 / 地區"
                />
              </div>

              <div className="front-field">
                <label>地區</label>
                <select
                  name="site"
                  value={filters.site}
                  onChange={handleChange}
                >
                  <option value="">全部</option>
                  <option value="林口">林口</option>
                  <option value="中壢">中壢</option>
                </select>
              </div>

              {currentPermission >= 2 && (
                <div className="front-field">
                  <label>帳號狀態</label>
                  <select
                    name="is_active"
                    value={filters.is_active}
                    onChange={handleChange}
                  >
                    <option value="">全部</option>
                    <option value="1">啟用</option>
                    <option value="0">停用</option>
                  </select>
                </div>
              )}
            </div>

            <div className="front-actions">
              <button type="submit">查詢</button>

              <button type="button" onClick={clearFilters}>
                清除
              </button>
            </div>
          </form>

          <div className="front-table-wrap">
            <table className="front-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>登錄帳號</th>
                  <th>姓名</th>
                  <th>地區</th>
                  <th>帳號狀態</th>
                  <th>權限</th>
                  <th>建立時間</th>
                  <th>停用時間</th>
                </tr>
              </thead>

              <tbody>
                {users.length === 0 && (
                  <tr>
                    <td colSpan="8" style={{ textAlign: "center" }}>
                      查無資料
                    </td>
                  </tr>
                )}

                {users.map((user) => (
                  <tr key={user.id}>
                    <td>{user.id}</td>

                    <td>{user.user_no}</td>

                    <td>{user.name}</td>

                    <td>{user.site || "/"}</td>

                    <td>{user.is_active_text}</td>

                    <td>{user.permission_text}</td>

                    <td>{user.created_at || "/"}</td>

                    <td>{user.deactivate_at || "/"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}