import { useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../api/api";

export default function Login() {
  const navigate = useNavigate();

  const [userNo, setUserNo] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [capsLock, setCapsLock] = useState(false);

  async function handleLogin(e) {
    e.preventDefault();

    setError("");

    try {
      const res = await api.post("/login.php", {
        user_no: userNo,
        password: password,
      });

      if (res.data.ok) {
        navigate("/dashboard");
      } else {
        setError(res.data.message || "登入失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  function checkCaps(e) {
    if (e.getModifierState) {
      setCapsLock(e.getModifierState("CapsLock"));
    }
  }

  return (
    <div className="login-page">
      <h1 className="front-title login-title">
        <span>文件往來系統登入</span>
      </h1>

      <div className="front-box login-box">
        <form onSubmit={handleLogin}>
          {error && <div className="error">{error}</div>}

          <div>
            <label>帳號：</label>
            <input
              type="text"
              value={userNo}
              onChange={(e) => setUserNo(e.target.value)}
              required
            />
          </div>

          <div>
            <label>
              密碼：
              {capsLock && (
                <span className="caps-warning">Caps Lock 已開啟</span>
              )}
            </label>

            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              onKeyDown={checkCaps}
              onKeyUp={checkCaps}
              onFocus={checkCaps}
              required
            />
          </div>

          <button type="submit">登入</button>
        </form>
      </div>
    </div>
  );
}