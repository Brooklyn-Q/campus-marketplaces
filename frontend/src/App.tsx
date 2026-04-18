import React, { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import Layout from './components/layout/Layout';
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import Product from './pages/Product';
import Dashboard from './pages/Dashboard';
import Chat from './pages/Chat';
import AddProduct from './pages/AddProduct';
import EditProfile from './pages/EditProfile';
import AdminDashboard from './pages/AdminDashboard';
import Leaderboard from './pages/Leaderboard';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { ThemeProvider } from './contexts/ThemeContext';
import AdminLayout from './components/layout/AdminLayout';
import AdminAnalytics from './pages/admin/AdminAnalytics';
import AdminUsers from './pages/admin/AdminUsers';
import AdminAds from './pages/admin/AdminAds';

function PageLoader() {
  return (
    <div className="container" style={{ padding: '4rem 0', textAlign: 'center' }}>
      Loading...
    </div>
  );
}

function ScrollToTop() {
  const { pathname, search } = useLocation();

  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
  }, [pathname, search]);

  return null;
}

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { loading, isLoggedIn } = useAuth();

  if (loading) return <PageLoader />;
  if (!isLoggedIn) return <Navigate to="/login" replace />;

  return <>{children}</>;
}

function RequireAdmin({ children }: { children: React.ReactNode }) {
  const { loading, isLoggedIn, isAdmin } = useAuth();

  if (loading) return <PageLoader />;
  if (!isLoggedIn) return <Navigate to="/login" replace />;
  if (!isAdmin) return <Navigate to="/dashboard" replace />;

  return <>{children}</>;
}

function GuestOnly({ children }: { children: React.ReactNode }) {
  const { loading, isLoggedIn, isAdmin } = useAuth();

  if (loading) return <PageLoader />;
  if (isLoggedIn) {
    return <Navigate to={isAdmin ? "/admin" : "/dashboard"} replace />;
  }

  return <>{children}</>;
}

function AppRoutes() {
  return (
    <BrowserRouter>
      <ScrollToTop />
      <Routes>
        {/* Main Storefront Layout */}
        <Route element={<Layout />}>
          <Route path="/" element={<Home />} />
          <Route path="/leaderboard" element={<Leaderboard />} />
          <Route path="/product/:id" element={<Product />} />
          <Route path="/login" element={<GuestOnly><Login /></GuestOnly>} />
          <Route path="/register" element={<GuestOnly><Register /></GuestOnly>} />
          <Route path="/dashboard" element={<RequireAuth><Dashboard /></RequireAuth>} />
          <Route path="/chat" element={<RequireAuth><Chat /></RequireAuth>} />
          <Route path="/add-product" element={<RequireAuth><AddProduct /></RequireAuth>} />
          <Route path="/edit-profile" element={<RequireAuth><EditProfile /></RequireAuth>} />
        </Route>

        {/* Dedicated Admin Layout */}
        <Route element={<RequireAdmin><AdminLayout /></RequireAdmin>}>
           <Route path="/admin" element={<AdminDashboard />} />
           <Route path="/admin/analytics" element={<AdminAnalytics />} />
           <Route path="/admin/ads" element={<AdminAds />} />
           <Route path="/admin/users" element={<AdminUsers />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </ThemeProvider>
  );
}
