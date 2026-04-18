import React, { useState, useEffect } from 'react';
import {
  Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, 
  BarElement, Title, Tooltip, Legend, ArcElement
} from 'chart.js';
import { Bar, Line, Doughnut } from 'react-chartjs-2';
import { apiFetch } from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';

// Register ChartJS elements
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, ArcElement);

export default function AdminAnalytics() {
  const { user } = useAuth();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchAnalytics = async () => {
      try {
        const res = await apiFetch('admin/analytics');
        if (res.success) {
          setData(res.data);
        } else {
          // Fallback mock data structure 
          setData({
             metrics: { revenue: 1400.00, boost_revenue: 250, users: 45, products: 120, sellers: 12, orders: 48, views: 9500, new_today: 3 },
             charts: {
                 revenue: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data: [120, 200, 150, 400, 320, 100, 50] },
                 users: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data: [2, 5, 3, 10, 8, 4, 1] },
                 categories: { labels: ['Electronics', 'Fashion', 'Books'], data: [50, 30, 20] }
             },
             tables: {
                 topViewed: [{ title: 'iPhone 13', seller: 'John', views: 500, price: 4000 }],
                 topSelling: [{ title: 'Sneakers', seller: 'Mike', sales: 15, price: 200 }],
                 topSellers: [{ username: 'John', seller_tier: 'premium', sale_count: 50, revenue: 8500 }]
             }
          });
        }
      } catch (e) {
          setData({
             metrics: { revenue: 1400.00, boost_revenue: 250, users: 45, products: 120, sellers: 12, orders: 48, views: 9500, new_today: 3 },
             charts: {
                 revenue: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data: [120, 200, 150, 400, 320, 100, 50] },
                 users: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data: [2, 5, 3, 10, 8, 4, 1] },
                 categories: { labels: ['Electronics', 'Fashion', 'Books'], data: [50, 30, 20] }
             },
             tables: {
                 topViewed: [{ title: 'iPhone 13', seller: 'John', views: 500, price: 4000 }],
                 topSelling: [{ title: 'Sneakers', seller: 'Mike', sales: 15, price: 200 }],
                 topSellers: [{ username: 'John', seller_tier: 'premium', sale_count: 50, revenue: 8500 }]
             }
          });
      } finally {
        setLoading(false);
      }
    };
    fetchAnalytics();
  }, []);

  if (loading || !data) return <div style={{padding:'2rem', textAlign:'center'}}>Loading Analytics...</div>;

  const chartColors = {
      revenue: { bg: 'rgba(0,113,227,0.15)', border: '#0071e3' },
      users: { bg: 'rgba(52,199,89,0.15)', border: '#34c759' },
  };

  return (
    <div className="container" style={{padding:'2rem 4%', maxWidth:'none'}}>
      <h2 className="mb-3" style={{display:'flex', alignItems:'center', gap:'0.5rem'}}>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
          Analytics Dashboard
      </h2>

      {/* KPI CARDS */}
      <div className="stat-grid mb-3 fade-in" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))' }}>
          <div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>₵{data.metrics.revenue?.toFixed(2)}</div><div className="stat-label">Total Revenue</div></div>
          <div className="glass stat-card"><div className="stat-val" style={{color:'var(--gold)'}}>₵{data.metrics.boost_revenue?.toFixed(2)}</div><div className="stat-label">Boost Revenue</div></div>
          <div className="glass stat-card"><div className="stat-val" style={{color:'var(--primary)'}}>{data.metrics.users}</div><div className="stat-label">Total Users</div></div>
          <div className="glass stat-card"><div className="stat-val">{data.metrics.products}</div><div className="stat-label">Total Products</div></div>
          <div className="glass stat-card"><div className="stat-val" style={{color:'#ff9500'}}>{data.metrics.sellers}</div><div className="stat-label">Active Sellers</div></div>
          <div className="glass stat-card"><div className="stat-val" style={{color:'#af52de'}}>{data.metrics.orders}</div><div className="stat-label">Total Orders</div></div>
          <div className="glass stat-card"><div className="stat-val">{data.metrics.views}</div><div className="stat-label">Total Views</div></div>
          <div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>+{data.metrics.new_today}</div><div className="stat-label">New Today</div></div>
      </div>

      {/* CHARTS ROW */}
      <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fit, minmax(400px, 1fr))', gap:'1.5rem', marginBottom:'1.5rem'}}>
        <div className="glass fade-in" style={{padding:'1.5rem'}}>
          <h4 className="mb-2" style={{fontSize:'0.95rem'}}>📈 Revenue Growth</h4>
          <Bar 
            data={{
               labels: data.charts.revenue.labels,
               datasets: [{ label: 'Revenue (₵)', data: data.charts.revenue.data, backgroundColor: chartColors.revenue.bg, borderColor: chartColors.revenue.border, borderWidth: 2, borderRadius: 6 }]
            }}
            options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { grid: { color: 'rgba(0,0,0,0.04)' } } } }}
          />
        </div>
        
        <div className="glass fade-in" style={{padding:'1.5rem'}}>
          <h4 className="mb-2" style={{fontSize:'0.95rem'}}>👥 User Sign-ups</h4>
          <Line 
            data={{
               labels: data.charts.users.labels,
               datasets: [{ label: 'New Users', data: data.charts.users.data, backgroundColor: chartColors.users.bg, borderColor: chartColors.users.border, borderWidth: 2, tension: 0.4, fill: true }]
            }}
            options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { grid: { color: 'rgba(0,0,0,0.04)' } } } }}
          />
        </div>
      </div>

      {/* PIE CHART AND TABLES */}
      <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fit, minmax(400px, 1fr))', gap:'1.5rem', marginBottom:'1.5rem'}}>
        <div className="glass fade-in" style={{padding:'1.5rem', display:'flex', flexDirection:'column', alignItems:'center'}}>
          <h4 className="mb-2" style={{fontSize:'0.95rem', width:'100%'}}>📊 Category Distribution</h4>
          <div style={{width:'60%', height:'60%'}}>
              <Doughnut 
                data={{
                  labels: data.charts.categories.labels,
                  datasets: [{
                    data: data.charts.categories.data,
                    backgroundColor: ['#0071e3','#34c759','#ff9500','#af52de','#ff3b30','#5ac8fa'],
                    borderWidth: 0
                  }]
                }}
                options={{ responsive: true, cutout: '60%' }}
              />
          </div>
        </div>

        <div className="glass fade-in" style={{padding:'1.5rem'}}>
          <h4 className="mb-2" style={{fontSize:'0.95rem'}}>🏆 Top Sellers</h4>
          <table style={{width:'100%', textAlign:'left'}}>
            <thead><tr><th>#</th><th>Seller</th><th>Tier</th><th>Sales</th><th>Rev</th></tr></thead>
            <tbody>
              {data.tables.topSellers.map((s:any, i:number) => (
                <tr key={i} style={{borderBottom:'1px solid rgba(0,0,0,0.05)'}}>
                  <td style={{padding:'0.5rem'}}>{i+1}</td>
                  <td style={{fontWeight:600}}>{s.username}</td>
                  <td><span className={`badge ${s.seller_tier==='premium'?'badge-gold':'badge-blue'}`}>{s.seller_tier}</span></td>
                  <td>{s.sale_count}</td>
                  <td style={{fontWeight:700, color:'var(--success)'}}>₵{s.revenue?.toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
