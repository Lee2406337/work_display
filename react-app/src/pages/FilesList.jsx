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

export default function FilesList() {
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

  function handleChange(e) {
    const { name, value } = e.target;

    setFilters((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  function loadFiles(currentFilters = filters) {
    setError("");

    const query = buildParams(currentFilters);

    api
      .get(`/files.php${query ? `?${query}` : ""}`)
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

  function searchFiles(e) {
    e.preventDefault();
    loadFiles(filters);
  }

  function clearFilters() {
    setFilters(initialFilters);
    loadFiles(initialFilters);
  }

  useEffect(() => {
    let ignore = false;

    api
      .get("/files.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          setFiles(res.data.data || []);
        } else {
          setFiles([]);
          setError(res.data.message || "讀取資料失敗");
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

  return (
    <div className="front-body">
      <div className="front-page-wide">
        <h1 className="front-title">
          <span>查看登記紀錄</span>
        </h1>

        <div className="front-box front-header-box">
          <div className="front-nav">
            <Link className="nav-btn" to="/dashboard">
              回首頁
            </Link>

            <Link className="nav-btn" to="/transfer">
              去登記
            </Link>
          </div>
        </div>

        <div className="front-box front-main-box">
          {error && <div className="error">{error}</div>}

          <form className="front-filter-card" onSubmit={searchFiles}>
            <div className="front-filter-row">
              <div className="front-field">
                <label>關鍵字</label>
                <input
                  type="text"
                  name="q"
                  value={filters.q}
                  onChange={handleChange}
                  placeholder="文件名 / 登錄帳號 / 姓名"
                />
              </div>

              <div className="front-field">
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
                  <option value="PROXY_RECEIVED">
                    PROXY_RECEIVED（完成）
                  </option>
                  <option value="COMPLETED">COMPLETED（完成）</option>
                  <option value="CANCELED">CANCELED（取消）</option>
                  <option value="ERROR">ERROR（錯誤）</option>
                </select>
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

              <div className="front-field">
                <label>送件日期起</label>
                <input
                  type="date"
                  name="date_from"
                  value={filters.date_from}
                  onChange={handleChange}
                />
              </div>

              <div className="front-field">
                <label>送件日期迄</label>
                <input
                  type="date"
                  name="date_to"
                  value={filters.date_to}
                  onChange={handleChange}
                />
              </div>
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
                  <th>文件</th>
                  <th>送件人</th>
                  <th>預計簽收人</th>
                  <th>取件人</th>
                  <th>簽收人</th>
                  <th>最終簽收人</th>
                  <th>地區</th>
                  <th>時間</th>
                  <th>狀態</th>
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
                      <small>
                        {file.doc_type}
                        {file.doc_type === "其他" && file.doc_type_other
                          ? ` - ${file.doc_type_other}`
                          : ""}
                      </small>
                    </td>

                    <td>
                      {file.sender_user_no} {file.sender_name}
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
                      {file.final_receiver_user_no || "/"}{" "}
                      {file.final_receiver_name || ""}
                    </td>

                    <td>
                      <div>送件：{file.sender_site || "/"}</div>
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
                      <div>最終：{file.final_receive_time || "/"}</div>
                    </td>

                    <td>
                      {file.status_text}
                      <br />
                      <small>{file.status}</small>
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