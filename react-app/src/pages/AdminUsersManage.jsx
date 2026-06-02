import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

const initialFilters = {
  q: "",
  site: "",
  is_active: "",
  permission_level: "",
};

export default function AdminUsersManage() {
  const [users, setUsers] = useState([]);
  const [filters, setFilters] = useState(initialFilters);
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

  function loadUsers(currentFilters = filters) {
    setError("");

    const query = buildParams(currentFilters);

    api
      .get(`/admin_users.php${query ? `?${query}` : ""}`)
      .then((res) => {
        if (res.data.ok) {
          setUsers(res.data.data || []);
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

  useEffect(() => {
    let ignore = false;

    api
      .get("/admin_users.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          setUsers(res.data.data || []);
        } else {
          setUsers([]);
          setError(res.data.message || "讀取失敗");
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

  function handleChange(e) {
    const { name, value } = e.target;

    setFilters((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  function searchUsers(e) {
    e.preventDefault();
    loadUsers(filters);
  }

  function clearFilters() {
    setFilters(initialFilters);
    loadUsers(initialFilters);
  }

  async function toggleActive(id) {
    if (!window.confirm("確定要切換帳號狀態嗎？")) return;

    try {
      const res = await api.post("/admin_users.php", {
        action: "toggle_active",
        id,
      });

      if (res.data.ok) {
        alert("更新成功");
        loadUsers(filters);
      } else {
        alert(res.data.message || "更新失敗");
      }
    } catch {
      alert("API 連線失敗");
    }
  }

  async function changeLevel(id, permissionLevel) {
    if (!window.confirm("確定要調整這個使用者的權限嗎？")) {
      loadUsers(filters);
      return;
    }

    try {
      const res = await api.post("/admin_users.php", {
        action: "set_level",
        id,
        permission_level: Number(permissionLevel),
      });

      if (res.data.ok) {
        alert("權限更新成功");
        loadUsers(filters);
      } else {
        alert(res.data.message || "權限更新失敗");
        loadUsers(filters);
      }
    } catch {
      alert("API 連線失敗");
      loadUsers(filters);
    }
  }

  async function unlockUser(id) {
    if (!window.confirm("確定要重設這個使用者的登入次數嗎？")) return;

    try {
      const res = await api.post("/admin_users.php", {
        action: "unlock_user",
        id,
      });

      if (res.data.ok) {
        alert("解鎖成功");
        loadUsers(filters);
      } else {
        alert(res.data.message || "解鎖失敗");
      }
    } catch {
      alert("API 連線失敗");
    }
  }

  return (
    <div className="admin-body">
      <div className="admin-topbar">
        <div className="admin-topbar-title">後台管理 / 使用者管理</div>

        <div className="admin-topbar-right">
          <Link className="admin-link-btn" to="/admin">
            回後台
          </Link>

          <Link className="admin-link-btn" to="/dashboard">
            回主選單
          </Link>
        </div>
      </div>

      <div className="admin-wrap-wide">
        <div className="admin-card">
          <div className="admin-top">
            <div>
              <h1 style={{ margin: "0 0 6px 0" }}>使用者管理</h1>
              <div className="admin-muted">
                建立帳號、停用啟用、調整權限與解鎖
              </div>
            </div>

            <div className="admin-actions">
              <Link className="admin-btn" to="/admin/users/add">
                + 新增使用者
              </Link>
            </div>
          </div>

          {error && <div className="admin-err">{error}</div>}

          <form className="admin-filter-card" onSubmit={searchUsers}>
            <div className="admin-filter-row">
              <div className="admin-field">
                <label>關鍵字</label>
                <input
                  type="text"
                  name="q"
                  value={filters.q}
                  onChange={handleChange}
                  placeholder="登錄帳號 / 姓名 / 地區"
                />
              </div>

              <div className="admin-field">
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

              <div className="admin-field">
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

              <div className="admin-field">
                <label>權限</label>
                <select
                  name="permission_level"
                  value={filters.permission_level}
                  onChange={handleChange}
                >
                  <option value="">全部</option>
                  <option value="1">一般用戶</option>
                  <option value="2">管理者</option>
                </select>
              </div>
            </div>

            <div className="admin-actions">
              <button type="submit" className="admin-btn">
                查詢
              </button>

              <button type="button" className="admin-btn" onClick={clearFilters}>
                清除
              </button>
            </div>
          </form>

          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>登錄帳號</th>
                  <th>姓名</th>
                  <th>地區</th>
                  <th>帳號狀態</th>
                  <th>權限</th>
                  <th>試錯次數</th>
                  <th>建立時間</th>
                  <th>停用時間</th>
                  <th>操作</th>
                </tr>
              </thead>

              <tbody>
                {users.length === 0 && (
                  <tr>
                    <td colSpan="10" style={{ textAlign: "center" }}>
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

                    <td>
                      <span className="admin-pill">
                        {Number(user.is_active) === 1 ? "啟用" : "停用"}
                      </span>
                    </td>

                    <td>
                      <select
                        value={Number(user.permission_level)}
                        onChange={(e) => changeLevel(user.id, e.target.value)}
                      >
                        <option value={1}>一般用戶</option>
                        <option value={2}>管理者</option>
                      </select>
                    </td>

                    <td>{user.login_chance}</td>

                    <td>{user.created_at || ""}</td>

                    <td>{user.deactivate_at || "/"}</td>

                    <td>
                      <div className="admin-actions">
                        <Link
                          className="admin-link-btn"
                          to={`/admin/users/edit/${user.id}`}
                        >
                          編輯
                        </Link>

                        <button
                          type="button"
                          className="admin-btn"
                          onClick={() => toggleActive(user.id)}
                        >
                          {Number(user.is_active) === 1 ? "停用" : "啟用"}
                        </button>

                        <button
                          type="button"
                          className="admin-btn"
                          onClick={() => unlockUser(user.id)}
                        >
                          解鎖
                        </button>
                      </div>
                    </td>
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