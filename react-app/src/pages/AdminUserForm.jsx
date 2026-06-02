import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import api from "../api/api";

function getLocalDateTimeValue() {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 16);
}

function createEmptyForm() {
  return {
    custom_id: "",
    user_no: "",
    password: "",
    name: "",
    site_choice: "林口",
    site_other: "",
    is_active: 1,
    permission_level: 1,
    created_at: getLocalDateTimeValue(),
    deactivate_at: "",
  };
}

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

export default function AdminUserForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);

  const navigate = useNavigate();

  const [form, setForm] = useState(() => createEmptyForm());
  const [error, setError] = useState("");

  useEffect(() => {
    let ignore = false;

    if (isEdit) {
      api
        .get(`/admin_users.php?id=${id}`)
        .then((res) => {
          if (ignore) return;

          if (res.data.ok) {
            const user = res.data.user;
            const site = splitSite(user.site);

            setForm({
              custom_id: "",
              user_no: user.user_no || "",
              password: "",
              name: user.name || "",
              site_choice: site.choice,
              site_other: site.other,
              is_active: Number(user.is_active),
              permission_level: Number(user.permission_level),
              created_at: user.created_at || "",
              deactivate_at: user.deactivate_at || "",
            });
          } else {
            setError(res.data.message || "讀取失敗");
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
    const { name, value } = e.target;

    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  async function handleSubmit(e) {
    e.preventDefault();

    setError("");

    if (form.site_choice === "其他" && form.site_other.trim() === "") {
      setError("地區選擇「其他」時，請填寫其他地區");
      return;
    }

    try {
      const res = await api.post("/admin_users.php", {
        action: isEdit ? "update" : "add",
        id: isEdit ? Number(id) : undefined,
        ...form,
        is_active: Number(form.is_active),
        permission_level: Number(form.permission_level),
      });

      if (res.data.ok) {
        alert(res.data.message || "儲存成功");
        navigate("/admin/users");
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
          後台管理 / {isEdit ? "編輯使用者" : "新增使用者"}
        </div>

        <div className="admin-topbar-right">
          <Link className="admin-link-btn" to="/admin/users">
            回使用者管理
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
                {isEdit ? "編輯使用者" : "新增使用者"}
              </h1>
              <div className="admin-muted">
                {isEdit
                  ? "可修改帳號資料、權限、狀態與密碼"
                  : "建立新的系統使用者帳號"}
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

            <label>登錄帳號</label>
            <input
              name="user_no"
              value={form.user_no}
              onChange={handleChange}
              required
              placeholder="例如：B1234567"
            />

            <label>{isEdit ? "新密碼（不改可留空）" : "密碼"}</label>
            <input
              type="password"
              name="password"
              value={form.password}
              onChange={handleChange}
              required={!isEdit}
              placeholder={isEdit ? "不修改密碼可留空" : "請輸入密碼"}
            />

            <label>姓名</label>
            <input
              name="name"
              value={form.name}
              onChange={handleChange}
              required
            />

            <label>地區</label>
            <select
              name="site_choice"
              value={form.site_choice}
              onChange={handleChange}
            >
              <option value="林口">林口</option>
              <option value="中壢">中壢</option>
              <option value="其他">其他</option>
            </select>

            {form.site_choice === "其他" && (
              <>
                <label>其他地區</label>
                <input
                  name="site_other"
                  value={form.site_other}
                  onChange={handleChange}
                  placeholder="請輸入其他地區"
                />
              </>
            )}

            <label>帳號狀態</label>
            <select
              name="is_active"
              value={form.is_active}
              onChange={handleChange}
            >
              <option value={1}>啟用</option>
              <option value={0}>停用</option>
            </select>

            <label>權限等級</label>
            <select
              name="permission_level"
              value={form.permission_level}
              onChange={handleChange}
            >
              <option value={1}>一般用戶</option>
              <option value={2}>管理者</option>
            </select>

            <label>帳號建立時間</label>
            <input
              type="datetime-local"
              name="created_at"
              value={form.created_at}
              onChange={handleChange}
            />

            {Number(form.is_active) === 0 && (
              <>
                <label>帳號停用時間</label>
                <input
                  type="datetime-local"
                  name="deactivate_at"
                  value={form.deactivate_at}
                  onChange={handleChange}
                />
              </>
            )}

            <div className="admin-actions">
              <button type="submit" className="admin-btn">
                {isEdit ? "更新" : "新增"}
              </button>

              <Link className="admin-link-btn" to="/admin/users">
                取消
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}