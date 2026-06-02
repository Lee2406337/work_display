import { useEffect, useState } from "react";
import { Navigate } from "react-router-dom";
import api from "../api/api";

export default function ProtectedRoute({
  children,
  adminOnly = false,
}) {
  const [loading, setLoading] = useState(true);
  const [allowed, setAllowed] = useState(false);
  const [redirectTo, setRedirectTo] = useState("/login");

  useEffect(() => {
    let ignore = false;

    api
      .get("/me.php")
      .then((res) => {
        if (ignore) return;

        if (!res.data.ok) {
          setAllowed(false);
          setRedirectTo("/login");
          setLoading(false);
          return;
        }

        const user = res.data.user;
        const permissionLevel = Number(user.permission_level || 1);

        if (adminOnly && permissionLevel < 2) {
          setAllowed(false);
          setRedirectTo("/dashboard");
          setLoading(false);
          return;
        }

        setAllowed(true);
        setLoading(false);
      })
      .catch(() => {
        if (ignore) return;

        setAllowed(false);
        setRedirectTo("/login");
        setLoading(false);
      });

    return () => {
      ignore = true;
    };
  }, [adminOnly]);

  if (loading) {
    return <div style={{ padding: "40px" }}>載入中...</div>;
  }

  if (!allowed) {
    return <Navigate to={redirectTo} replace />;
  }

  return children;
}