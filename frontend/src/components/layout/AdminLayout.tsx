import React, { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { Menu, X, BarChart2, Users, Shield, MessageSquare, List, Activity, Megaphone, Settings, LogOut } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';

export default function AdminLayout() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const { user } = useAuth();

  const toggleMenu = () => setMobileMenuOpen(!mobileMenuOpen);

  const navLinkStyle = ({ isActive }: { isActive: boolean }) => ({
    color: isActive ? 'var(--text-main)' : 'var(--text-muted)',
    background: isActive ? 'rgba(0,0,0,0.05)' : 'transparent',
    fontWeight: 600,
    fontSize: '0.95rem',
    padding: '0.5rem 0.8rem',
    borderRadius: '10px',
    transition: 'all 0.2s',
    textDecoration: 'none',
    whiteSpace: 'nowrap' as const,
    flexShrink: 0,
    display: 'flex',
    alignItems: 'center',
    gap: '6px'
  });

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <nav style={{
        position: 'sticky', top: 0, zIndex: 9999, 
        backdropFilter: 'saturate(180%) blur(24px)', 
        WebkitBackdropFilter: 'saturate(180%) blur(24px)', 
        background: 'rgba(255,255,255,0.85)', 
        borderBottom: '1px solid var(--border)', 
        transition: 'all 0.3s ease', 
        padding: '0 4%'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: '58px', width: '100%' }}>
          
          {/* Logo / Brand */}
          <NavLink to="/admin" style={{ fontSize: '1.05rem', fontWeight: 800, color: 'var(--text-main)', textDecoration: 'none', letterSpacing: '-0.04em', display: 'flex', alignItems: 'center', gap: '6px', flexShrink: 0 }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
            </svg>
            Admin Panel
          </NavLink>

          {/* Desktop Links */}
          <div className={`nav-links ${mobileMenuOpen ? 'open' : ''}`} style={{
            display: mobileMenuOpen ? 'flex' : 'none', 
            gap: '0.8rem', alignItems: 'center', justifyContent: 'center', flex: 1, minWidth: 0,
            ...(window.innerWidth <= 768 ? {
              position: 'fixed', top: '58px', left: 0, right: 0, flexDirection: 'column', 
              background: 'rgba(255,255,255,0.95)', padding: '1rem 0', alignItems: 'stretch'
            } : { display: 'flex' })
          }}>
            <NavLink to="/admin" end style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <Activity size={16} /> Dashboard
            </NavLink>
            <NavLink to="/admin/users" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <Users size={16} /> Users
            </NavLink>
            <NavLink to="/admin/products" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <Shield size={16} /> Moderation
            </NavLink>
            <NavLink to="/admin/messages" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <MessageSquare size={16} /> Messages
            </NavLink>
            <NavLink to="/admin/audit" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <List size={16} /> Audit Log
            </NavLink>
            <NavLink to="/admin/analytics" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <BarChart2 size={16} /> Analytics
            </NavLink>
            <NavLink to="/admin/ads" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <Megaphone size={16} /> Ads
            </NavLink>
            <NavLink to="/admin/settings" style={navLinkStyle} onClick={() => setMobileMenuOpen(false)}>
              <Settings size={16} /> Settings
            </NavLink>
            <NavLink to="/dashboard" style={{
              background: '#0071e3', color: '#fff', fontWeight: 700, fontSize: '0.95rem', 
              padding: '0.5rem 1.2rem', borderRadius: '980px', textDecoration: 'none', 
              display: 'flex', alignItems: 'center', gap: '6px'
            }}>
              <LogOut size={16} /> Exit
            </NavLink>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexShrink: 0 }}>
            {/* Removed missing ThemeToggle import reference */}
            <button className="mobile-toggle" onClick={toggleMenu} style={{ color: 'var(--text-main)', cursor: 'pointer', background: 'none', border: 'none', padding: '6px', borderRadius: '8px', display: window.innerWidth > 768 ? 'none' : 'block' }}>
              {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>
      </nav>
      
      {/* Page Content area */}
      <div style={{ flex: 1, position: 'relative' }}>
        <Outlet />
      </div>
    </div>
  );
}
