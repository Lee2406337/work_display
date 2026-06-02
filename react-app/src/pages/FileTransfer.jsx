import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../api/api";

export default function FileTransfer() {
  const [tab, setTab] = useState("send");

  const [currentUser, setCurrentUser] = useState(null);
  const [users, setUsers] = useState([]);
  const [pickList, setPickList] = useState([]);
  const [receiveList, setReceiveList] = useState([]);
  const [finalList, setFinalList] = useState([]);
  const [now, setNow] = useState("");

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const [sendForm, setSendForm] = useState({
    sender_site_choice: "林口",
    sender_site_other: "",
    doc_name: "",
    doc_type: "信件",
    doc_type_other: "",
    intended_receiver_user_no: "",
    dest_site_choice: "林口",
    dest_site_other: "",
    send_time: "",
    send_remark: "",
  });

  const [pickForm, setPickForm] = useState({
    route: "林口->中壢",
    route_other: "",
    pick_time: "",
    pick_remark: "",
    pick_ids: [],
  });

  const [receiveForm, setReceiveForm] = useState({
    receive_id: "",
    receive_site_choice: "林口",
    receive_site_other: "",
    receive_time: "",
    receive_remark: "",
    is_proxy: false,
    final_receiver_user_no: "",
  });

  const [finalForm, setFinalForm] = useState({
    final_id: "",
  });

  const applyTransferData = useCallback((data) => {
  setCurrentUser(data.user);
  setUsers(data.users || []);
  setPickList(data.pickList || []);
  setReceiveList(data.receiveList || []);
  setFinalList(data.finalList || []);
  setNow(data.now || "");

  setSendForm((prev) => ({
    ...prev,
    send_time: prev.send_time || data.now || "",
  }));

  setPickForm((prev) => ({
    ...prev,
    pick_time: prev.pick_time || data.now || "",
  }));

  setReceiveForm((prev) => ({
    ...prev,
    receive_time: prev.receive_time || data.now || "",
  }));
}, []);

  function loadTransferData() {
    setMessage("");
    setError("");

    api
      .get("/transfer.php")
      .then((res) => {
        if (res.data.ok) {
          applyTransferData(res.data);
        } else {
          setError(res.data.message || "讀取資料失敗");
        }
      })
      .catch(() => {
        setError("API 連線失敗");
      });
  }

  useEffect(() => {
    let ignore = false;

    api
      .get("/transfer.php")
      .then((res) => {
        if (ignore) return;

        if (res.data.ok) {
          applyTransferData(res.data);
        } else {
          setError(res.data.message || "讀取資料失敗");
        }
      })
      .catch(() => {
        if (ignore) return;
        setError("API 連線失敗");
      });

    return () => {
      ignore = true;
    };
  }, [applyTransferData]);

  function getUserName(userNo) {
    const user = users.find((u) => u.user_no === userNo);
    return user ? user.name : "";
  }

  function handleSendChange(e) {
    const { name, value } = e.target;

    setSendForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  function handlePickChange(e) {
    const { name, value } = e.target;

    setPickForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  }

  function handleReceiveChange(e) {
    const { name, value, type, checked } = e.target;

    setReceiveForm((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }));
  }

  function togglePickId(id) {
    setPickForm((prev) => {
      const exists = prev.pick_ids.includes(id);

      return {
        ...prev,
        pick_ids: exists
          ? prev.pick_ids.filter((x) => x !== id)
          : [...prev.pick_ids, id],
      };
    });
  }

  async function submitSend(e) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      const res = await api.post("/transfer.php", {
        action: "send",
        ...sendForm,
      });

      if (res.data.ok) {
        setMessage(res.data.message || "送件完成");

        setSendForm({
          sender_site_choice: "林口",
          sender_site_other: "",
          doc_name: "",
          doc_type: "信件",
          doc_type_other: "",
          intended_receiver_user_no: "",
          dest_site_choice: "林口",
          dest_site_other: "",
          send_time: now,
          send_remark: "",
        });

        loadTransferData();
      } else {
        setError(res.data.message || "送件失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  async function submitPick(e) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      const res = await api.post("/transfer.php", {
        action: "pick",
        ...pickForm,
      });

      if (res.data.ok) {
        setMessage(res.data.message || "取件完成");

        setPickForm({
          route: "林口->中壢",
          route_other: "",
          pick_time: now,
          pick_remark: "",
          pick_ids: [],
        });

        loadTransferData();
      } else {
        setError(res.data.message || "取件失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  async function submitReceive(e) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      const res = await api.post("/transfer.php", {
        action: "receive",
        ...receiveForm,
      });

      if (res.data.ok) {
        setMessage(res.data.message || "簽收完成");

        setReceiveForm({
          receive_id: "",
          receive_site_choice: "林口",
          receive_site_other: "",
          receive_time: now,
          receive_remark: "",
          is_proxy: false,
          final_receiver_user_no: "",
        });

        loadTransferData();
      } else {
        setError(res.data.message || "簽收失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  async function submitFinalReceive(e) {
    e.preventDefault();

    setMessage("");
    setError("");

    try {
      const res = await api.post("/transfer.php", {
        action: "final_receive",
        ...finalForm,
      });

      if (res.data.ok) {
        setMessage(res.data.message || "最終簽收完成");

        setFinalForm({
          final_id: "",
        });

        loadTransferData();
      } else {
        setError(res.data.message || "最終簽收失敗");
      }
    } catch {
      setError("API 連線失敗");
    }
  }

  if (!currentUser) {
    return (
      <div className="front-body">
        <div className="front-page">
          <h1 className="front-title">
            <span>文件往來登記</span>
          </h1>

          <div className="front-box">
            <p>載入中...</p>
            {error && <div className="error">{error}</div>}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="front-body">
      <div className="front-page-wide">
        <h1 className="front-title">
          <span>文件往來登記</span>
        </h1>

        <div className="front-box front-header-box">
          <div className="front-nav">
            <Link className="nav-btn" to="/dashboard">
              回首頁
            </Link>

            <span>
              登入者：{currentUser.name}（{currentUser.user_no}）｜地區：
              {currentUser.site}
            </span>
          </div>
        </div>

        <div className="front-box front-main-box">
          {message && <div className="message">{message}</div>}
          {error && <div className="error">{error}</div>}

          <div className="front-tabs">
            <button
              type="button"
              className={tab === "send" ? "active" : ""}
              onClick={() => setTab("send")}
            >
              送件
            </button>

            <button
              type="button"
              className={tab === "pick" ? "active" : ""}
              onClick={() => setTab("pick")}
            >
              取件
            </button>

            <button
              type="button"
              className={tab === "receive" ? "active" : ""}
              onClick={() => setTab("receive")}
            >
              簽收
            </button>

            <button
              type="button"
              className={tab === "final" ? "active" : ""}
              onClick={() => setTab("final")}
            >
              最終簽收
            </button>
          </div>

          {tab === "send" && (
            <form className="front-form" onSubmit={submitSend}>
              <h2>送件</h2>

              <div className="front-form-row">
                <label>送件地區</label>
                <select
                  name="sender_site_choice"
                  value={sendForm.sender_site_choice}
                  onChange={handleSendChange}
                >
                  <option value="林口">林口</option>
                  <option value="中壢">中壢</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              {sendForm.sender_site_choice === "其他" && (
                <div className="front-form-row">
                  <label>其他送件地區</label>
                  <input
                    name="sender_site_other"
                    value={sendForm.sender_site_other}
                    onChange={handleSendChange}
                    placeholder="請輸入其他送件地區"
                  />
                </div>
              )}

              <div className="front-form-row">
                <label>文件名稱</label>
                <input
                  name="doc_name"
                  value={sendForm.doc_name}
                  onChange={handleSendChange}
                  placeholder="文件名稱"
                />
              </div>

              <div className="front-form-row">
                <label>文件類型</label>
                <select
                  name="doc_type"
                  value={sendForm.doc_type}
                  onChange={handleSendChange}
                >
                  <option value="信件">信件</option>
                  <option value="包裹">包裹</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              {sendForm.doc_type === "其他" && (
                <div className="front-form-row">
                  <label>其他文件類型</label>
                  <input
                    name="doc_type_other"
                    value={sendForm.doc_type_other}
                    onChange={handleSendChange}
                    placeholder="請輸入其他文件類型"
                  />
                </div>
              )}

              <div className="front-form-row">
                <label>簽收人</label>
                <select
                  name="intended_receiver_user_no"
                  value={sendForm.intended_receiver_user_no}
                  onChange={handleSendChange}
                >
                  <option value="">請選擇簽收人</option>
                  {users.map((u) => (
                    <option key={u.user_no} value={u.user_no}>
                      {u.user_no} {u.name}（{u.site}）
                    </option>
                  ))}
                </select>
              </div>

              <div className="front-form-row">
                <label>目的地區</label>
                <select
                  name="dest_site_choice"
                  value={sendForm.dest_site_choice}
                  onChange={handleSendChange}
                >
                  <option value="林口">林口</option>
                  <option value="中壢">中壢</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              {sendForm.dest_site_choice === "其他" && (
                <div className="front-form-row">
                  <label>其他目的地區</label>
                  <input
                    name="dest_site_other"
                    value={sendForm.dest_site_other}
                    onChange={handleSendChange}
                    placeholder="請輸入其他目的地區"
                  />
                </div>
              )}

              <div className="front-form-row">
                <label>送件時間</label>
                <input
                  type="datetime-local"
                  name="send_time"
                  value={sendForm.send_time}
                  onChange={handleSendChange}
                />
              </div>

              <div className="front-form-row">
                <label>備註</label>
                <textarea
                  name="send_remark"
                  value={sendForm.send_remark}
                  onChange={handleSendChange}
                  placeholder="可不填"
                />
              </div>

              <div className="front-actions">
                <button type="submit">送件</button>
              </div>
            </form>
          )}

          {tab === "pick" && (
            <form className="front-form" onSubmit={submitPick}>
              <h2>取件</h2>

              <div className="front-form-row">
                <label>來去地點</label>
                <select
                  name="route"
                  value={pickForm.route}
                  onChange={handlePickChange}
                >
                  <option value="林口->中壢">林口-&gt;中壢</option>
                  <option value="中壢->林口">中壢-&gt;林口</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              {pickForm.route === "其他" && (
                <div className="front-form-row">
                  <label>其他路徑</label>
                  <input
                    name="route_other"
                    value={pickForm.route_other}
                    onChange={handlePickChange}
                    placeholder="請輸入其他路徑"
                  />
                </div>
              )}

              <div className="front-form-row">
                <label>取件時間</label>
                <input
                  type="datetime-local"
                  name="pick_time"
                  value={pickForm.pick_time}
                  onChange={handlePickChange}
                />
              </div>

              <div className="front-form-row">
                <label>備註</label>
                <textarea
                  name="pick_remark"
                  value={pickForm.pick_remark}
                  onChange={handlePickChange}
                  placeholder="可不填"
                />
              </div>

              <h3>可取件文件</h3>

              <div className="front-table-wrap">
                <table className="front-table">
                  <thead>
                    <tr>
                      <th>選取</th>
                      <th>ID</th>
                      <th>文件名稱</th>
                      <th>送件人</th>
                      <th>送件地區</th>
                      <th>目的地區</th>
                      <th>送件時間</th>
                    </tr>
                  </thead>

                  <tbody>
                    {pickList.length === 0 && (
                      <tr>
                        <td colSpan="7" style={{ textAlign: "center" }}>
                          目前沒有可取件文件
                        </td>
                      </tr>
                    )}

                    {pickList.map((file) => (
                      <tr key={file.id}>
                        <td>
                          <input
                            type="checkbox"
                            checked={pickForm.pick_ids.includes(
                              Number(file.id)
                            )}
                            onChange={() => togglePickId(Number(file.id))}
                          />
                        </td>
                        <td>{file.id}</td>
                        <td>{file.doc_name}</td>
                        <td>
                          {file.sender_user_no} {file.sender_name}
                        </td>
                        <td>{file.sender_site}</td>
                        <td>{file.dest_site}</td>
                        <td>{file.send_time}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="front-actions">
                <button type="submit">取件</button>
              </div>
            </form>
          )}

          {tab === "receive" && (
            <form className="front-form" onSubmit={submitReceive}>
              <h2>簽收</h2>

              <div className="front-form-row">
                <label>選擇文件</label>
                <select
                  name="receive_id"
                  value={receiveForm.receive_id}
                  onChange={handleReceiveChange}
                >
                  <option value="">請選擇要簽收的文件</option>
                  {receiveList.map((file) => (
                    <option key={file.id} value={file.id}>
                      #{file.id} {file.doc_name}｜目的地區：{file.dest_site}
                    </option>
                  ))}
                </select>
              </div>

              <div className="front-form-row">
                <label>簽收地區</label>
                <select
                  name="receive_site_choice"
                  value={receiveForm.receive_site_choice}
                  onChange={handleReceiveChange}
                >
                  <option value="林口">林口</option>
                  <option value="中壢">中壢</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              {receiveForm.receive_site_choice === "其他" && (
                <div className="front-form-row">
                  <label>其他簽收地區</label>
                  <input
                    name="receive_site_other"
                    value={receiveForm.receive_site_other}
                    onChange={handleReceiveChange}
                    placeholder="請輸入其他簽收地區"
                  />
                </div>
              )}

              <div className="front-form-row">
                <label>簽收時間</label>
                <input
                  type="datetime-local"
                  name="receive_time"
                  value={receiveForm.receive_time}
                  onChange={handleReceiveChange}
                />
              </div>

              <div className="front-form-row">
                <label>備註</label>
                <textarea
                  name="receive_remark"
                  value={receiveForm.receive_remark}
                  onChange={handleReceiveChange}
                  placeholder="可不填"
                />
              </div>

              <label>
                <input
                  type="checkbox"
                  name="is_proxy"
                  checked={receiveForm.is_proxy}
                  onChange={handleReceiveChange}
                />{" "}
                是否代收
              </label>

              {receiveForm.is_proxy && (
                <>
                  <div className="front-form-row">
                    <label>最終簽收人</label>
                    <select
                      name="final_receiver_user_no"
                      value={receiveForm.final_receiver_user_no}
                      onChange={handleReceiveChange}
                    >
                      <option value="">請選擇最終簽收人</option>
                      {users.map((u) => (
                        <option key={u.user_no} value={u.user_no}>
                          {u.user_no} {u.name}（{u.site}）
                        </option>
                      ))}
                    </select>
                  </div>

                  {receiveForm.final_receiver_user_no && (
                    <p>
                      最終簽收人姓名：
                      {getUserName(receiveForm.final_receiver_user_no)}
                    </p>
                  )}
                </>
              )}

              <div className="front-actions">
                <button type="submit">簽收</button>
              </div>
            </form>
          )}

          {tab === "final" && (
            <form className="front-form" onSubmit={submitFinalReceive}>
              <h2>最終簽收</h2>

              <div className="front-form-row">
                <label>選擇待最終簽收文件</label>
                <select
                  name="final_id"
                  value={finalForm.final_id}
                  onChange={(e) =>
                    setFinalForm({
                      final_id: e.target.value,
                    })
                  }
                >
                  <option value="">請選擇文件</option>
                  {finalList.map((file) => (
                    <option key={file.id} value={file.id}>
                      #{file.id} {file.doc_name}｜代收人：
                      {file.received_by_name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="front-table-wrap">
                <table className="front-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>文件名稱</th>
                      <th>最終簽收人</th>
                      <th>代收人</th>
                      <th>代收時間</th>
                    </tr>
                  </thead>

                  <tbody>
                    {finalList.length === 0 && (
                      <tr>
                        <td colSpan="5" style={{ textAlign: "center" }}>
                          目前沒有待最終簽收文件
                        </td>
                      </tr>
                    )}

                    {finalList.map((file) => (
                      <tr key={file.id}>
                        <td>{file.id}</td>
                        <td>{file.doc_name}</td>
                        <td>
                          {file.final_receiver_user_no}{" "}
                          {file.final_receiver_name}
                        </td>
                        <td>
                          {file.received_by_user_no} {file.received_by_name}
                        </td>
                        <td>{file.receive_time}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="front-actions">
                <button type="submit">最終簽收</button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}