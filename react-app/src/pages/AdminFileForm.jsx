import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import api from "../api/api";

const defaultAllowedStatus = [
  "SENT",
  "PICKED",
  "RECEIVED",
  "PROXY_RECEIVED",
  "COMPLETED",
  "CANCELED",
  "ERROR",
];

const emptyForm = {
  custom_id: "",
  doc_name: "",
  doc_type: "信件",
  doc_type_other: "",

  sender_user_no: "",

  sender_site_choice: "林口",
  sender_site_other: "",

  intended_receiver_user_no: "",

  picker_user_no: "",
  received_by_user_no: "",

  dest_site_choice: "林口",
  dest_site_other: "",

  receive_site_choice: "林口",
  receive_site_other: "",

  route: "",
  route_other: "",

  send_time: "",
  pick_time: "",
  receive_time: "",

  is_proxy: false,
  final_receiver_user_no: "",
  final_receive_time: "",

  status: "SENT",
};

function splitSite(site) {
  const value = String(site || "").trim();

  if (value === "") {
    return {
      choice: "林口",
      other: "",
    };
  }

  if (value === "林口" || value === "中壢") {
    return {
      choice: value,
      other: "",
    };
  }

  return {
    choice: "其他",
    other: value,
  };
}

function buildSite(choice, other) {
  if (choice === "其他") {
    return String(other || "").trim();
  }

  return choice;
}

export default function AdminFileForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);

  const navigate = useNavigate();

  const [form, setForm] = useState(emptyForm);
  const [users, setUsers] = useState([]);
  const [allowedStatus, setAllowedStatus] = useState(defaultAllowedStatus);
  const [error, setError] = useState("");

  useEffect(() => {
    let ignore = false;

    if (isEdit) {
      api
        .get(`/admin_files.php?id=${id}`)
        .then((res) => {
          if (ignore) return;

          if (res.data.ok) {
            const file = res.data.file;

            const senderSite = splitSite(file.sender_site);
            const destSite = splitSite(file.dest_site);
            const receiveSite = splitSite(file.receive_site);

            setUsers(res.data.users || []);
            setAllowedStatus(res.data.allowedStatus || defaultAllowedStatus);

            setForm({
              custom_id: "",

              doc_name: file.doc_name || "",
              doc_type: file.doc_type || "信件",
              doc_type_other: file.doc_type_other || "",

              sender_user_no: file.sender_user_no || "",

              sender_site_choice: senderSite.choice,
              sender_site_other: senderSite.other,

              intended_receiver_user_no: file.intended_receiver_user_no || "",

              picker_user_no: file.picker_user_no || "",
              received_by_user_no: file.received_by_user_no || "",

              dest_site_choice: destSite.choice,
              dest_site_other: destSite.other,

              receive_site_choice: receiveSite.choice,
              receive_site_other: receiveSite.other,

              route: file.route || "",
              route_other: file.route_other || "",

              send_time: file.send_time || "",
              pick_time: file.pick_time || "",
              receive_time: file.receive_time || "",

              is_proxy: Number(file.is_proxy) === 1,
              final_receiver_user_no: file.final_receiver_user_no || "",
              final_receive_time: file.final_receive_time || "",

              status: file.status || "SENT",
            });
          } else {
            setError(res.data.message || "讀取失敗");
          }
        })
        .catch(() => {
          if (ignore) return;
          setError("API 連線失敗");
        });
    } else {
      api
        .get("/transfer.php")
        .then((res) => {
          if (ignore) return;

          if (res.data.ok) {
            setUsers(res.data.users || []);

            setForm((prev) => ({
              ...prev,
              send_time: res.data.now || "",
              pick_time: res.data.now || "",
              receive_time: res.data.now || "",
              final_receive_time: res.data.now || "",
            }));
          } else {
            setError(res.data.message || "讀取資料失敗");
          }
        })
        .catch(() => {
          if (ignore) return;
          setError("API 連線失敗");
        });
    }

    return () => {
      ignore = true;
    };
  }, [id, isEdit]);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }));
  }

  function handleSenderChange(e) {
    const userNo = e.target.value;
    const user = users.find((u) => u.user_no === userNo);

    const senderSite = splitSite(user ? user.site : "");

    setForm((prev) => ({
      ...prev,
      sender_user_no: userNo,
      sender_site_choice: senderSite.choice,
      sender_site_other: senderSite.other,
    }));
  }

  async function handleSubmit(e) {
    e.preventDefault();

    setError("");

    const senderSite = buildSite(
      form.sender_site_choice,
      form.sender_site_other
    );

    const destSite = buildSite(form.dest_site_choice, form.dest_site_other);

    const receiveSite = buildSite(
      form.receive_site_choice,
      form.receive_site_other
    );

    if (form.sender_site_choice === "其他" && senderSite === "") {
      setError("送件地區選擇「其他」時，請填寫其他地區");
      return;
    }

    if (form.dest_site_choice === "其他" && destSite === "") {
      setError("目的地區選擇「其他」時，請填寫其他地區");
      return;
    }

    if (form.receive_site_choice === "其他" && receiveSite === "") {
      setError("簽收地區選擇「其他」時，請填寫其他地區");
      return;
    }

    const payload = {
      action: isEdit ? "update" : "add",
      id: isEdit ? Number(id) : undefined,

      custom_id: form.custom_id,

      doc_name: form.doc_name,
      doc_type: form.doc_type,
      doc_type_other: form.doc_type_other,

      sender_user_no: form.sender_user_no,
      sender_site: senderSite,

      intended_receiver_user_no: form.intended_receiver_user_no,

      picker_user_no: form.picker_user_no,
      received_by_user_no: form.received_by_user_no,

      dest_site: destSite,
      receive_site: receiveSite,

      route: form.route,
      route_other: form.route_other,

      send_time: form.send_time,
      pick_time: form.pick_time,
      receive_time: form.receive_time,

      is_proxy: form.is_proxy,
      final_receiver_user_no: form.final_receiver_user_no,
      final_receive_time: form.final_receive_time,

      status: form.status,
    };

    try {
      const res = await api.post("/admin_files.php", payload);

      if (res.data.ok) {
        alert(res.data.message || "儲存成功");
        navigate("/admin/files");
      } else {
        setError(res.data.message || "儲存失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  return (
    <div className="admin-body">
      <div className="admin-topbar">
        <div className="admin-topbar-title">
          後台管理 / {isEdit ? "編輯文件資料" : "新增文件資料"}
        </div>

        <div className="admin-topbar-right">
          <Link className="admin-link-btn" to="/admin/files">
            回文件資料管理
          </Link>

          <Link className="admin-link-btn" to="/admin">
            回後台
          </Link>
        </div>
      </div>

      <div className="admin-wrap">
        <div className="admin-card">
          <div className="admin-top">
            <div>
              <h1 style={{ margin: "0 0 6px 0" }}>
                {isEdit ? "編輯文件資料" : "新增文件資料"}
              </h1>
              <div className="admin-muted">
                可建立或修改文件流轉紀錄與狀態
              </div>
            </div>
          </div>

          {error && <div className="admin-err">{error}</div>}

          <form className="admin-form" onSubmit={handleSubmit}>
            {!isEdit && (
              <>
                <label>ID（可留空自動生成）</label>
                <input
                  name="custom_id"
                  value={form.custom_id}
                  onChange={handleChange}
                  placeholder="可留空"
                />
              </>
            )}

            <label>文件名稱</label>
            <input
              name="doc_name"
              value={form.doc_name}
              onChange={handleChange}
              required
            />

            <label>文件類型</label>
            <select
              name="doc_type"
              value={form.doc_type}
              onChange={handleChange}
            >
              <option value="信件">信件</option>
              <option value="包裹">包裹</option>
              <option value="其他">其他</option>
            </select>

            {form.doc_type === "其他" && (
              <>
                <label>其他文件類型</label>
                <input
                  name="doc_type_other"
                  value={form.doc_type_other}
                  onChange={handleChange}
                  placeholder="請輸入其他文件類型"
                />
              </>
            )}

            <label>送件人</label>
            <select
              name="sender_user_no"
              value={form.sender_user_no}
              onChange={handleSenderChange}
              required
            >
              <option value="">請選擇送件人</option>
              {users.map((u) => (
                <option key={u.user_no} value={u.user_no}>
                  {u.user_no} {u.name}（{u.site}）
                </option>
              ))}
            </select>

            <label>送件地區</label>
            <select
              name="sender_site_choice"
              value={form.sender_site_choice}
              onChange={handleChange}
              required
            >
              <option value="林口">林口</option>
              <option value="中壢">中壢</option>
              <option value="其他">其他</option>
            </select>

            {form.sender_site_choice === "其他" && (
              <>
                <label>其他送件地區</label>
                <input
                  name="sender_site_other"
                  value={form.sender_site_other}
                  onChange={handleChange}
                  placeholder="請輸入其他送件地區"
                />
              </>
            )}

            <label>預計簽收人</label>
            <select
              name="intended_receiver_user_no"
              value={form.intended_receiver_user_no}
              onChange={handleChange}
              required
            >
              <option value="">請選擇預計簽收人</option>
              {users.map((u) => (
                <option key={u.user_no} value={u.user_no}>
                  {u.user_no} {u.name}（{u.site}）
                </option>
              ))}
            </select>

            <label>取件人</label>
            <select
              name="picker_user_no"
              value={form.picker_user_no}
              onChange={handleChange}
            >
              <option value="">可不選</option>
              {users.map((u) => (
                <option key={u.user_no} value={u.user_no}>
                  {u.user_no} {u.name}（{u.site}）
                </option>
              ))}
            </select>

            <label>簽收人</label>
            <select
              name="received_by_user_no"
              value={form.received_by_user_no}
              onChange={handleChange}
            >
              <option value="">可不選</option>
              {users.map((u) => (
                <option key={u.user_no} value={u.user_no}>
                  {u.user_no} {u.name}（{u.site}）
                </option>
              ))}
            </select>

            <label>目的地區</label>
            <select
              name="dest_site_choice"
              value={form.dest_site_choice}
              onChange={handleChange}
              required
            >
              <option value="林口">林口</option>
              <option value="中壢">中壢</option>
              <option value="其他">其他</option>
            </select>

            {form.dest_site_choice === "其他" && (
              <>
                <label>其他目的地區</label>
                <input
                  name="dest_site_other"
                  value={form.dest_site_other}
                  onChange={handleChange}
                  placeholder="請輸入其他目的地區"
                />
              </>
            )}

            <label>簽收地區</label>
            <select
              name="receive_site_choice"
              value={form.receive_site_choice}
              onChange={handleChange}
            >
              <option value="林口">林口</option>
              <option value="中壢">中壢</option>
              <option value="其他">其他</option>
            </select>

            {form.receive_site_choice === "其他" && (
              <>
                <label>其他簽收地區</label>
                <input
                  name="receive_site_other"
                  value={form.receive_site_other}
                  onChange={handleChange}
                  placeholder="請輸入其他簽收地區"
                />
              </>
            )}

            <label>路徑</label>
            <select name="route" value={form.route} onChange={handleChange}>
              <option value="">可不選</option>
              <option value="林口->中壢">林口-&gt;中壢</option>
              <option value="中壢->林口">中壢-&gt;林口</option>
              <option value="其他">其他</option>
            </select>

            {form.route === "其他" && (
              <>
                <label>其他路徑</label>
                <input
                  name="route_other"
                  value={form.route_other}
                  onChange={handleChange}
                  placeholder="請輸入其他路徑"
                />
              </>
            )}

            <label>送件時間</label>
            <input
              type="datetime-local"
              name="send_time"
              value={form.send_time}
              onChange={handleChange}
            />

            <label>取件時間</label>
            <input
              type="datetime-local"
              name="pick_time"
              value={form.pick_time}
              onChange={handleChange}
            />

            <label>簽收時間</label>
            <input
              type="datetime-local"
              name="receive_time"
              value={form.receive_time}
              onChange={handleChange}
            />

            <label>
              <input
                type="checkbox"
                name="is_proxy"
                checked={form.is_proxy}
                onChange={handleChange}
              />{" "}
              是否代收
            </label>

            {form.is_proxy && (
              <>
                <label>最終簽收人</label>
                <select
                  name="final_receiver_user_no"
                  value={form.final_receiver_user_no}
                  onChange={handleChange}
                >
                  <option value="">請選擇最終簽收人</option>
                  {users.map((u) => (
                    <option key={u.user_no} value={u.user_no}>
                      {u.user_no} {u.name}（{u.site}）
                    </option>
                  ))}
                </select>

                <label>最終簽收時間</label>
                <input
                  type="datetime-local"
                  name="final_receive_time"
                  value={form.final_receive_time}
                  onChange={handleChange}
                />
              </>
            )}

            <label>狀態</label>
            <select name="status" value={form.status} onChange={handleChange}>
              {allowedStatus.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>

            <div className="admin-actions">
              <button type="submit" className="admin-btn">
                {isEdit ? "更新" : "新增"}
              </button>

              <Link className="admin-link-btn" to="/admin/files">
                取消
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}