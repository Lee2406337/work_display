import { useState } from "react";
import { Link } from "react-router-dom";

const REPORT_BASE_URL = "https://b1229011webp2026.infinityfree.me/admin_report.php";

export default function AdminReport() {
  const [report, setReport] = useState("files");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  function buildReportUrl(format) {
    const params = new URLSearchParams();

    params.set("report", report);
    params.set("format", format);

    if (report === "files") {
      if (dateFrom !== "") {
        params.set("date_from", dateFrom);
      }

      if (dateTo !== "") {
        params.set("date_to", dateTo);
      }
    }

    return `${REPORT_BASE_URL}?${params.toString()}`;
  }

  function openReport(format) {
    const url = buildReportUrl(format);

    if (format === "print") {
      window.open(url, "_blank");
      return;
    }

    window.location.href = url;
  }

  return (
    <div className="admin-body">
      <div className="admin-topbar">
        <div className="admin-topbar-title">後台管理 / 報表匯出</div>

        <div className="admin-topbar-right">
          <Link className="admin-link-btn" to="/admin">
            回後台
          </Link>

          <Link className="admin-link-btn" to="/dashboard">
            回主選單
          </Link>
        </div>
      </div>

      <div className="admin-wrap">
        <div className="admin-card">
          <div className="admin-top">
            <div>
              <h1 style={{ margin: "0 0 6px 0" }}>報表匯出</h1>
              <div className="admin-muted">
                匯出文件資料紀錄或人員資料，可選 Excel、CSV、列印格式
              </div>
            </div>
          </div>

          <form className="admin-form">
            <label>報表類型</label>
            <select value={report} onChange={(e) => setReport(e.target.value)}>
              <option value="files">文件資料紀錄</option>
              <option value="users">人員資料</option>
            </select>

            {report === "files" && (
              <>
                <label>送件日期起</label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />

                <label>送件日期迄</label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />

                <div className="admin-muted">
                  日期篩選只套用在「文件資料紀錄」，依送件時間篩選。
                </div>
              </>
            )}

            {report === "users" && (
              <div className="admin-muted">人員資料報表不使用日期篩選。</div>
            )}

            <div className="admin-actions">
              <button
                type="button"
                className="admin-btn"
                onClick={() => openReport("excel")}
              >
                匯出 Excel
              </button>

              <button
                type="button"
                className="admin-btn"
                onClick={() => openReport("csv")}
              >
                匯出 CSV
              </button>

              <button
                type="button"
                className="admin-btn"
                onClick={() => openReport("print")}
              >
                列印報表
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}