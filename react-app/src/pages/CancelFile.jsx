import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

export default function CancelFile() {
  const [files, setFiles] = useState([]);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  function reloadFiles() {
    api
      .get("/cancel_file.php")
      .then((res) => {
        if (res.data.ok) {
          setFiles(res.data.data || []);
        } else {
          setError(res.data.message || "讀取抽單資料失敗");
        }
      })
      .catch(() => {
        setError("API 連線失敗");
      });
  }

  useEffect(() => {
    let ignore = false;

    api
      .get("/cancel_file.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          setFiles(res.data.data || []);
        } else {
          setError(res.data.message || "讀取抽單資料失敗");
        }
      })
      .catch(() => {
        if (ignore) return;
        setError("API 連線失敗");
      });

    return () => {
      ignore = true;
    };
  }, []);

  async function cancelFile(id) {
    if (!window.confirm("確定要取消這筆文件嗎？")) {
      return;
    }

    setMessage("");
    setError("");

    try {
      const res = await api.post("/cancel_file.php", {
        id,
      });

      if (res.data.ok) {
        setMessage(res.data.message || "抽單成功");
        reloadFiles();
      } else {
        setError(res.data.message || "抽單失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  return (
    <div className="front-body">
      <div className="front-page-wide">
        <h1 className="front-title">
          <span>我想抽單</span>
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

          <p className="small">
            只有「尚未被取件」的文件可以取消。已被取件的文件會顯示，但不能取消。
          </p>

          <div className="front-table-wrap">
            <table className="front-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>文件</th>
                  <th>送件人</th>
                  <th>預計簽收人</th>
                  <th>目的地區</th>
                  <th>送件時間</th>
                  <th>取件時間</th>
                  <th>狀態</th>
                  <th>操作</th>
                </tr>
              </thead>

              <tbody>
                {files.length === 0 && (
                  <tr>
                    <td colSpan="9" style={{ textAlign: "center" }}>
                      目前沒有可顯示的抽單資料
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
                      <br />
                      <small>{file.sender_site}</small>
                    </td>

                    <td>
                      {file.intended_receiver_user_no}{" "}
                      {file.intended_receiver_name}
                    </td>

                    <td>{file.dest_site}</td>

                    <td>{file.send_time}</td>

                    <td>{file.pick_time || "/"}</td>

                    <td>{file.status_text}</td>

                    <td>
                      {file.can_cancel ? (
                        <button
                          type="button"
                          onClick={() => cancelFile(file.id)}
                        >
                          取消
                        </button>
                      ) : (
                        <span className="small">不可取消</span>
                      )}
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