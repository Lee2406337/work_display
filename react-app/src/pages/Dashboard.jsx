import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import api from "../api/api";

export default function Dashboard() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  useEffect(() => {
    api
      .get("/me.php")
      .then((res) => {
        if (res.data.ok) {
          setUser(res.data.user);
        } else {
          navigate("/login", { replace: true });
        }
      })
      .catch(() => {
        navigate("/login", { replace: true });
      });
  }, [navigate]);

  if (!user) {
    return (
      <div className="front-body">
        <div className="front-page">
          <h1 className="front-title">
            <span>文件往來系統</span>
          </h1>

          <div className="front-box">
            <p>載入中...</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="front-body">
      <div className="front-page">
        <h1 className="front-title">
          <span>文件往來系統</span>
        </h1>

        <div className="front-box">
          <div className="front-user-bar">
            <p>
              歡迎登入：{user.user_no} {user.name} ｜ 地區：{user.site}
            </p>

            <Link className="user-btn" to="/account">
              修改密碼
            </Link>

            <Link className="user-btn" to="/cancel">
              我想抽單
            </Link>

            <button
              type="button"
              className="user-btn"
              onClick={async () => {
                await api.post("/logout.php");
                navigate("/login", { replace: true });
              }}
            >
              登出
            </button>
          </div>

          <div className="front-menu">
            <Link to="/transfer">文件往來登記</Link>

            <Link to="/files">查看登記紀錄</Link>

            <Link to="/users">使用者資料查詢</Link>

            {Number(user.permission_level) >= 2 && (
              <Link to="/admin">後台管理</Link>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}