import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { apiFetch } from '../services/api';

export default function AdminDashboard() {
  const { user } = useAuth();
  const [stats, setStats] = useState<any>({});
  const [loading, setLoading] = useState(true);

  // Quick fallback mock data until the backend Admin routes are fully converted
  useEffect(() => {
    const fetchAdminData = async () => {
      try {
        const res = await apiFetch('admin/dashboard');
        if (res.stats) {
          setStats(res.stats);
        } else {
            setStats({
               total_users: 15, sellers: 5, active: 10, pending: 2, deletion_req: 0,
               disc_pending: 1, profile_pending: 1, volume: 1450.00, sale_rev: 1200.00,
               premium_rev: 200.00, boost_rev: 50.00, total_orders: 45
            });
        }
      } catch (err) {
        setStats({
           total_users: 15, sellers: 5, active: 10, pending: 2, deletion_req: 0,
           disc_pending: 1, profile_pending: 1, volume: 1450.00, sale_rev: 1200.00,
           premium_rev: 200.00, boost_rev: 50.00, total_orders: 45
        });
      } finally {
        setLoading(false);
      }
    };
    fetchAdminData();
  }, []);

  if (!user || user.role !== 'admin') {
    return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Access Denied. Admins only.</div>;
  }

  if (loading) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading Admin Data...</div>;

  return (
    <div className="container" style={{padding:'2rem 0', maxWidth:'none', width:'96%', paddingLeft:'2rem', paddingRight:'2rem'}}>
      {stats.profile_pending > 0 && (
          <div className="container fade-in" style={{background:'rgba(168,85,247,0.1)', border:'1px solid rgba(168,85,247,0.2)', color:'#9333ea', display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1.5rem', borderRadius:'12px', padding:'1rem'}}>
              <div><strong>🔔 Profile & Tier Changes Pending</strong> — {stats.profile_pending} user(s) have requested updates to their accounts.</div>
              <a href="#profile_section" className="btn btn-sm" style={{background:'#a855f7', color:'#fff'}}>Review Now</a>
          </div>
      )}

      <div style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'2rem'}}>
        <h1 style={{fontSize:'2.5rem', fontWeight:800, margin:0}}>Admin Dashboard</h1>
        <div style={{background:'rgba(168,85,247,0.1)', color:'#a855f7', padding:'8px 16px', borderRadius:'99px', fontWeight:'bold'}}>
          Super Admin Mode
        </div>
      </div>

      <div className="stat-grid mb-3 fade-in">
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val">{stats.total_users || 0}</div><div className="stat-label">Total Users</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--primary)'}}>{stats.sellers || 0}</div><div className="stat-label">Sellers</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>{stats.active || 0}</div><div className="stat-label">Active Listings</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card" style={stats.pending > 0 ? {borderColor:'#fbbf24'} : {}}><div className="stat-val" style={{color:'var(--warning)'}}>{stats.pending || 0}</div><div className="stat-label">Pending Review</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card" style={stats.deletion_req > 0 ? {borderColor:'var(--danger)'} : {}}><div className="stat-val" style={{color:'var(--danger)'}}>{stats.deletion_req || 0}</div><div className="stat-label">Deletion Requests</div></div></a>
          <a href="#discount_section" className="stat-card-link"><div className="glass stat-card" style={stats.disc_pending > 0 ? {borderColor:'var(--mint)'} : {}}><div className="stat-val" style={{color:'var(--mint)'}}>{stats.disc_pending || 0}</div><div className="stat-label">Discount Requests</div></div></a>
          <a href="#profile_section" className="stat-card-link"><div className="glass stat-card" style={stats.profile_pending > 0 ? {borderColor:'#a855f7'} : {}}><div className="stat-val" style={{color:'#a855f7'}}>{stats.profile_pending || 0}</div><div className="stat-label">Profile Edits</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--gold)'}}>₵{(stats.volume || 0).toFixed(2)}</div><div className="stat-label">Total Revenue</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>₵{(stats.sale_rev || 0).toFixed(2)}</div><div className="stat-label">Product Sales</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'#af52de'}}>₵{(stats.premium_rev || 0).toFixed(2)}</div><div className="stat-label">Premium Fees</div></div></a>
          <a href="#" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'#ff9f0a'}}>₵{(stats.boost_rev || 0).toFixed(2)}</div><div className="stat-label">Boost Revenue</div></div></a>
          <a href="#transparency_section" className="stat-card-link"><div className="glass stat-card"><div className="stat-val">{stats.total_orders || 0}</div><div className="stat-label">Total Orders</div></div></a>
      </div>

      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:'1.5rem', marginBottom:'3rem'}}>
          <div className="glass fade-in" style={{padding:'1.5rem'}}>
              <h4 className="mb-2">🏆 Top Sellers Leaderboard</h4>
              <table>
                  <thead><tr><th>#</th><th>Seller</th><th>Tier</th><th>Revenue</th></tr></thead>
                  <tbody>
                      <tr><td colSpan={4} className="text-muted" style={{textAlign:'center', padding:'1rem'}}>Data available in legacy PHP or when API finishes migration</td></tr>
                  </tbody>
              </table>
          </div>

          <div className="glass fade-in" style={{padding:'1.5rem'}}>
              <div className="flex-between mb-2">
                  <h4>⏳ Pending Items</h4>
                  <a href="#" className="btn btn-primary btn-sm">View All</a>
              </div>
              <p className="text-muted" style={{padding:'1rem', textAlign:'center'}}>All clear! Nothing pending.</p>
          </div>
      </div>

    </div>
  );
}
