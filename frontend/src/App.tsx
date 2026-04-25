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
  const [alt, setAlt] = React.useState<string | null>(null);

  React.useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      const isPreviewable = target.classList.contains('viewable-image') || target.classList.contains('profile-pic-previewable');
      if (target.tagName === 'IMG' && isPreviewable) {
        const img = target as HTMLImageElement;
        setSrc(img.src);
        setAlt(img.alt || '');
      }
    };
    document.addEventListener('click', handleClick);
    return () => document.removeEventListener('click', handleClick);
  }, []);

  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && src) setSrc(null);
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [src]);

  if (!src) return null;

  return (
    <div 
      style={{
        position: 'fixed', inset: 0, zIndex: 1000001, 
        background: 'rgba(0,0,0,0.88)', backdropFilter: 'blur(18px)', 
        WebkitBackdropFilter: 'blur(18px)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        cursor: 'zoom-out',
        animation: 'fadeIn 0.3s ease forwards'
      }}
      onClick={() => setSrc(null)}
    >
      <div style={{
          position: 'relative', display: 'flex', flexDirection: 'column',
          alignItems: 'center', gap: '1rem', padding: '20px',
          animation: 'zoomIn 0.35s cubic-bezier(0.19,1,0.22,1) forwards'
      }}>
        <button 
          style={{
            position: 'absolute', top: '-12px', right: '-12px', 
            background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', 
            borderRadius: '50%', width: '42px', height: '42px', cursor: 'pointer',
            fontSize: '1.4rem', display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background 0.2s'
          }}
          onClick={(e) => { e.stopPropagation(); setSrc(null); }}
          onMouseOver={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.22)')}
          onMouseOut={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.12)')}
          aria-label="Close preview"
        >
          ×
        </button>
        <img 
          src={src} 
          alt={alt || "Preview"} 
          style={{
            maxWidth: 'min(500px, 88vw)', maxHeight: '72vh', 
            borderRadius: '20px', objectFit: 'contain',
            boxShadow: '0 32px 100px rgba(0,0,0,0.55)',
            border: '1px solid rgba(255,255,255,0.08)',
            background: '#111'
          }} 
          onClick={(e) => e.stopPropagation()}
        />
        {alt && (
          <div style={{
            color: 'rgba(255,255,255,0.85)', fontWeight: 600,
            fontSize: '0.95rem', letterSpacing: '-0.01em'
          }}>
            {alt}
          </div>
        )}
      </div>
      <style>{`
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes zoomIn { from { transform: scale(0.92); } to { transform: scale(1); } }
      `}</style>
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
