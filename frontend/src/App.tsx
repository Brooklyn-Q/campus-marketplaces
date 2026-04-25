import React, { useEffect } from 'react';
import { HashRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
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
    <HashRouter>
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
    </HashRouter>
  );
}

function GlobalImageViewer() {
  const [src, setSrc] = React.useState<string | null>(null);

  React.useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (target.tagName === 'IMG' && target.classList.contains('viewable-image')) {
        setSrc((target as HTMLImageElement).src);
      }
    };
    document.addEventListener('click', handleClick);
    return () => document.removeEventListener('click', handleClick);
  }, []);

  if (!src) return null;

  return (
    <div 
      style={{
        position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
        background: 'rgba(0,0,0,0.85)', zIndex: 99999, 
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        backdropFilter: 'blur(4px)', cursor: 'zoom-out'
      }}
      onClick={() => setSrc(null)}
    >
      <img 
        src={src} 
        alt="Preview" 
        style={{
          maxWidth: '90%', maxHeight: '90%', 
          borderRadius: '8px', objectFit: 'contain',
          boxShadow: '0 10px 40px rgba(0,0,0,0.5)',
          animation: 'modalSlideUp 0.25s cubic-bezier(0.22, 1, 0.36, 1)'
        }} 
        onClick={(e) => e.stopPropagation()}
      />
      <button 
        style={{
          position: 'absolute', top: '20px', right: '20px', 
          background: 'rgba(255,255,255,0.2)', color: '#fff', border: 'none', 
          borderRadius: '50%', width: '40px', height: '40px', cursor: 'pointer',
          fontSize: '1.5rem', display: 'flex', alignItems: 'center', justifyContent: 'center'
        }}
        onClick={() => setSrc(null)}
      >
        ×
      </button>
    </div>
  );
}

export default function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <AppRoutes />
        <GlobalImageViewer />
      </AuthProvider>
    </ThemeProvider>
  );
}
