import { Link } from "react-router-dom";

export default function AdminDashboard() {
  return (
    <div className="admin-body">
      <div className="admin-topbar">
        <div className="admin-topbar-title">後台管理</div>

        <div className="admin-topbar-right">
          <Link className="admin-link-btn" to="/dashboard">
            回主選單
          </Link>
        </div>
      </div>

      <div className="admin-wrap">
        <div className="admin-card">
          <div className="admin-top">
            <div>
              <h1 style={{ margin: "0 0 6px 0" }}>後台管理</h1>
              <div className="admin-muted">
                文件資料、使用者、報表匯出管理
              </div>
            </div>

            <div className="admin-actions">
              <Link className="admin-btn" to="/dashboard">
                回主選單
              </Link>
            </div>
          </div>

          <div className="admin-grid">
            <Link className="admin-grid-item" to="/admin/files">
              <div className="admin-grid-title">📄 文件資料管理</div>
              <div className="admin-muted">
                查詢 / 編輯 / 取消 / 刪除 / 新增
              </div>
            </Link>

            <Link className="admin-grid-item" to="/admin/users">
              <div className="admin-grid-title">👤 使用者管理</div>
              <div className="admin-muted">
                建立帳號 / 停用啟用 / 調整權限 / 解鎖帳號
              </div>
            </Link>

            <Link className="admin-grid-item" to="/admin/report">
              <div className="admin-grid-title">📊 報表匯出</div>
              <div className="admin-muted">
                匯出 Excel / CSV / Print
              </div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}