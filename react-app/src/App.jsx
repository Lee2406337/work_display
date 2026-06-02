import { HashRouter, Routes, Route, Navigate } from "react-router-dom";

import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Account from "./pages/Account";
import FileTransfer from "./pages/FileTransfer";
import FilesList from "./pages/FilesList";
import UsersList from "./pages/UsersList";
import CancelFile from "./pages/CancelFile";

import AdminDashboard from "./pages/AdminDashboard";
import AdminFilesManage from "./pages/AdminFilesManage";
import AdminUsersManage from "./pages/AdminUsersManage";
import AdminFileForm from "./pages/AdminFileForm";
import AdminUserForm from "./pages/AdminUserForm";
import AdminReport from "./pages/AdminReport";

import ProtectedRoute from "./components/ProtectedRoute";

export default function App() {
  return (
    <HashRouter>
      <Routes>
        <Route path="/" element={<Navigate to="/login" />} />

        <Route path="/login" element={<Login />} />

        <Route
          path="/dashboard"
          element={
            <ProtectedRoute>
              <Dashboard />
            </ProtectedRoute>
          }
        />

        <Route
          path="/account"
          element={
            <ProtectedRoute>
              <Account />
            </ProtectedRoute>
          }
        />

        <Route
          path="/transfer"
          element={
            <ProtectedRoute>
              <FileTransfer />
            </ProtectedRoute>
          }
        />

        <Route
          path="/files"
          element={
            <ProtectedRoute>
              <FilesList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/users"
          element={
            <ProtectedRoute>
              <UsersList />
            </ProtectedRoute>
          }
        />

        <Route
          path="/cancel"
          element={
            <ProtectedRoute>
              <CancelFile />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin"
          element={
            <ProtectedRoute adminOnly>
              <AdminDashboard />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/files"
          element={
            <ProtectedRoute adminOnly>
              <AdminFilesManage />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/files/add"
          element={
            <ProtectedRoute adminOnly>
              <AdminFileForm />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/files/edit/:id"
          element={
            <ProtectedRoute adminOnly>
              <AdminFileForm />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/users"
          element={
            <ProtectedRoute adminOnly>
              <AdminUsersManage />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/users/add"
          element={
            <ProtectedRoute adminOnly>
              <AdminUserForm />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/users/edit/:id"
          element={
            <ProtectedRoute adminOnly>
              <AdminUserForm />
            </ProtectedRoute>
          }
        />

        <Route
          path="/admin/report"
          element={
            <ProtectedRoute adminOnly>
              <AdminReport />
            </ProtectedRoute>
          }
        />

        <Route path="*" element={<Navigate to="/dashboard" />} />
      </Routes>
    </HashRouter>
  );
}