import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

const initialFilters = {
  q: "",
  status: "",
  site: "",
  date_from: "",
  date_to: "",
};

export default function AdminFilesManage() {
  const [files, setFiles] = useState([]);
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

  function loadFiles(currentFilters = filters) {
    setError("");

    const query = buildParams(currentFilters);

    api
      .get(`/admin_files.php${query ? `?${query}` : ""}`)
      .then((res) => {
        if (res.data.ok) {
          setFiles(res.data.data || []);
        } else {
          setFiles([]);
          setError(res.data.message || "查詢失敗");
        }
      })
      .catch(() => {
        setFiles([]);
        setError("API 連線失敗");
      });
  }

  useEffect(() => {
    let ignore = false;

    api
      .get("/admin_files.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          setFiles(res.data.data || []);
        } else {
          setFiles([]);
          setError(res.data.message || "讀取失敗");
        }
      })
      .catch(() => {
        if (ignore) return;
        setFiles([]);
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

  function searchFiles(e) {
    e.preventDefault();
    loadFiles(filters);
  }

  function clearFilters() {
    setFilters(initialFilters);
    loadFiles(initialFilters);
  }

  async function cancelFile(id) {
    if (!window.confirm("確定要取消這筆文件嗎？")) return;

    try {
      const res = await api.post("/admin_files.php", {
        action: "cancel",
        id,
      });

      if (res.data.ok) {
        alert("取消成功");
        loadFiles(filters);
      } else {
        alert(res.data.message || "取消失敗");
      }
    } catch {
      alert("API 連線失敗");
    }
  }

  async function deleteFile(id) {
    if (!window.confirm("確定要刪除這筆文件嗎？此操作無法復原。")) return;

    try {
      const res = await api.post("/admin_files.php", {
        action: "delete",
        id,
      });

      if (res.data.ok) {
        alert("刪除成功");
        loadFiles(filters);
      } else {
        alert(res.data.message || "刪除失敗");
      }
    } catch {
      alert("API 連線失敗");
    }
  }

  return (
    <div className="admin-body">
      <div className="admin-topbar">
        <div className="admin-topbar-title">後台管理 / 文件資料管理</div>

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
              <h1 style={{ margin: "0 0 6px 0" }}>文件資料管理</h1>
              <div className="admin-muted">
                查詢、編輯、取消、刪除與新增文件資料
              </div>
            </div>

            <div className="admin-actions">
              <Link className="admin-btn" to="/admin/files/add">
                + 新增文件資料
              </Link>
            </div>
          </div>

          {error && <div className="admin-err">{error}</div>}

          <form className="admin-filter-card" onSubmit={searchFiles}>
            <div className="admin-filter-row">
              <div className="admin-field">
                <label>關鍵字</label>
                <input
                  type="text"
                  name="q"
                  value={filters.q}
                  onChange={handleChange}
                  placeholder="文件 / 帳號 / 姓名"
                />
              </div>

              <div className="admin-field">
                <label>狀態</label>
                <select
                  name="status"
                  value={filters.status}
                  onChange={handleChange}
                >
                  <option value="">全部</option>
                  <option value="SENT">SENT（進行中）</option>
                  <option value="PICKED">PICKED（進行中）</option>
                  <option value="RECEIVED">RECEIVED（完成）</option>
                  <option value="PROXY_RECEIVED">PROXY_RECEIVED（完成）</option>
                  <option value="COMPLETED">COMPLETED（完成）</option>
                  <option value="CANCELED">CANCELED（取消）</option>
                  <option value="ERROR">ERROR（錯誤）</option>
                </select>
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
                <label>送件日期起</label>
                <input
                  type="date"
                  name="date_from"
                  value={filters.date_from}
                  onChange={handleChange}
                />
              </div>

              <div className="admin-field">
                <label>送件日期迄</label>
                <input
                  type="date"
                  name="date_to"
                  value={filters.date_to}
                  onChange={handleChange}
                />
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
                  <th>文件</th>
                  <th>送件人</th>
                  <th>預計簽收人</th>
                  <th>取件人</th>
                  <th>簽收人</th>
                  <th>地區</th>
                  <th>時間</th>
                  <th>狀態</th>
                  <th>操作</th>
                </tr>
              </thead>

              <tbody>
                {files.length === 0 && (
                  <tr>
                    <td colSpan="10" style={{ textAlign: "center" }}>
                      查無資料
                    </td>
                  </tr>
                )}

                {files.map((file) => (
                  <tr key={file.id}>
                    <td>{file.id}</td>

                    <td>
                      <strong>{file.doc_name}</strong>
                      <br />
                      <span className="admin-muted">
                        {file.doc_type}
                        {file.doc_type === "其他" && file.doc_type_other
                          ? ` - ${file.doc_type_other}`
                          : ""}
                      </span>
                    </td>

                    <td>
                      {file.sender_user_no} {file.sender_name}
                      <br />
                      <span className="admin-muted">{file.sender_site}</span>
                    </td>

                    <td>
                      {file.intended_receiver_user_no}{" "}
                      {file.intended_receiver_name}
                    </td>

                    <td>
                      {file.picker_user_no || "/"} {file.picker_name || ""}
                    </td>

                    <td>
                      {file.received_by_user_no || "/"}{" "}
                      {file.received_by_name || ""}
                    </td>

                    <td>
                      <div>目的：{file.dest_site || "/"}</div>
                      <div>簽收：{file.receive_site || "/"}</div>
                      <div>
                        路徑：
                        {file.route === "其他" && file.route_other
                          ? file.route_other
                          : file.route || "/"}
                      </div>
                    </td>

                    <td>
                      <div>送件：{file.send_time || "/"}</div>
                      <div>取件：{file.pick_time || "/"}</div>
                      <div>簽收：{file.receive_time || "/"}</div>
                    </td>

                    <td>
                      <span className="admin-pill">
                        {file.status_text}
                        <br />
                        <span className="admin-muted">{file.status}</span>
                      </span>
                    </td>

                    <td>
                      <div className="admin-actions">
                        <Link
                          className="admin-link-btn"
                          to={`/admin/files/edit/${file.id}`}
                        >
                          編輯
                        </Link>

                        {file.status !== "CANCELED" && (
                          <button
                            type="button"
                            className="admin-btn"
                            onClick={() => cancelFile(file.id)}
                          >
                            取消
                          </button>
                        )}

                        <button
                          type="button"
                          className="admin-btn"
                          onClick={() => deleteFile(file.id)}
                        >
                          刪除
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