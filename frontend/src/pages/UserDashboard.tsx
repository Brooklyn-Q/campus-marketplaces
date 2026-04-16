import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { orders } from '../services/api';

export default function UserDashboard() {
  const { user } = useAuth();
  const [buyerOrders, setBuyerOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchOrders = async () => {
      try {
        const res = await orders.list();
        setBuyerOrders(res.orders || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchOrders();
  }, []);

  const totalSpent = buyerOrders.filter(o => o.status === 'completed').reduce((acc, o) => acc + Number(o.product_price), 0);
  const itemsBought = buyerOrders.filter(o => o.status === 'completed').length;

  if (loading) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading...</div>;

  return (
    <div className="container" style={{padding: '2rem 0', maxWidth: 'none', width: '96%'}}>
      <style>{`
        .stat-card-link { text-decoration: none; color: inherit; display: block; }
        .dashboard-grid { display: grid; grid-template-columns: 300px 1fr; gap: 2rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; }
        .stat-card { padding: 1.5rem; border-radius: 20px; text-align: center; height: 100%; transition: transform 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); border: 1px solid var(--border); }
        .stat-card:hover { transform: translateY(-3px) scale(1.02); }
        .stat-val { font-size: 2.2rem; font-weight: 900; line-height: 1.1; margin-bottom: 0.25rem; letter-spacing: -0.05em; }
        .stat-label { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .profile-pic-lg { width: 110px; height: 110px; border-radius: 50% !important; object-fit: cover; display: block !important; margin: 0 auto 1.25rem !important; box-shadow: 0 8px 25px rgba(0,0,0,0.08); border: 3px solid #fff; }
        @media (max-width: 640px) { .dashboard-grid { grid-template-columns: 1fr; } }
      `}</style>
      
      <div className="dashboard-grid">
        {/* SIDEBAR */}
        <div>
          <div className="glass fade-in" style={{padding:'2rem', textAlign:'center', marginBottom:'1.5rem'}}>
            {user?.profile_pic ? (
              <img src={'/marketplace/uploads/' + user.profile_pic} className="profile-pic profile-pic-lg mb-2" alt="Profile" />
            ) : (
              <div className="profile-pic profile-pic-lg mb-2" style={{display:'flex', alignItems:'center', justifyContent:'center', background:'rgba(99,102,241,0.2)', color:'var(--primary)', fontSize:'2.5rem', fontWeight:700, margin:'0 auto'}}>
                {user?.username?.substring(0, 1).toUpperCase()}
              </div>
            )}
            <h3>{user?.username}</h3>
            <div className="mb-1">
              <span className="badge badge-blue">🛒 Buyer</span>
              {user?.verified ? <span className="badge badge-approved" style={{marginLeft:'4px'}}>✓ Verified</span> : null}
            </div>
            
            {user?.faculty && <p className="text-muted" style={{fontSize:'0.78rem', marginTop:'0.3rem'}}>🎓 {user.faculty}</p>}
            {user?.department && <p className="text-muted" style={{fontSize:'0.85rem'}}>{user.department} · L{user.level}</p>}
            {user?.hall && <p className="text-muted" style={{fontSize:'0.8rem'}}>🏠 {user.hall}</p>}
            {user?.phone && <p className="text-muted" style={{fontSize:'0.8rem'}}>📱 {user.phone}</p>}
            
            <div style={{marginTop:'1rem', display:'flex', gap:'0.5rem', flexDirection:'column'}}>
               <a href="#" className="btn btn-primary" style={{justifyContent:'center', borderRadius:'12px'}}>Edit Profile</a>
            </div>
          </div>

          <div className="glass fade-in" style={{padding:'1.5rem', marginTop:'1.5rem'}}>
            <h4 className="mb-2">📞 Contact Admin</h4>
            <p className="text-muted" style={{fontSize:'0.8rem', marginBottom:'1rem'}}>Need clarification? Chat directly with the platform administrator.</p>
            <div className="flex-column gap-1">
              <textarea placeholder="Type your question here..." className="form-control" style={{fontSize:'0.85rem', minHeight:'80px', padding:'0.75rem', borderRadius:'12px'}}></textarea>
              <button className="btn btn-primary btn-sm" style={{width:'100%', borderRadius:'10px'}}>Send Message</button>
            </div>
          </div>
        </div>

        {/* MAIN AREA */}
        <div>
          <div className="stat-grid mb-3 fade-in">
            <a href="#buyer_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--primary)'}}>{itemsBought}</div><div className="stat-label">Items Bought</div></div></a>
            <a href="#buyer_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--gold)'}}>₵{totalSpent.toFixed(2)}</div><div className="stat-label">Total Spent</div></div></a>
            <a href="#buyer_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>{buyerOrders.length}</div><div className="stat-label">Recent Orders</div></div></a>
            <a href="#buyer_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--mint)'}}>0</div><div className="stat-label">Cart Items</div></div></a>
          </div>

          <div id="buyer_orders" className="glass fade-in mt-3" style={{padding:'2rem'}}>
            <div className="flex-between mb-3">
              <h3 style={{margin:0}}>🛍️ My Orders</h3>
              {user?.has_unreviewed_orders && (
                 <span className="badge badge-rejected" style={{padding:'6px 12px', fontWeight:800}}>🔒 ACTION REQUIRED: Leave Reviews</span>
              )}
            </div>
            {buyerOrders.length > 0 ? (
              <div className="flex-column gap-1">
                {buyerOrders.map((ord: any) => (
                  <div key={ord.id} style={{background:'rgba(0,0,0,0.2)', border:'1px solid var(--border)', padding:'1rem', borderRadius:'12px'}}>
                    <div className="flex-between mb-1">
                        <strong>{ord.product_title}</strong>
                        <span className="badge" style={{background:'#0071e3', color:'#fff'}}>₵{Number(ord.product_price).toFixed(2)}</span>
                    </div>
                    <p className="text-muted" style={{fontSize:'0.85rem'}}>Seller: <strong>{ord.seller_name}</strong> &bull; {new Date(ord.created_at).toLocaleString()}</p>
                    <div style={{marginTop:'0.75rem'}}>
                      {ord.status === 'ordered' && <span className="badge badge-pending">Status: Pending (Awaiting Seller)</span>}
                      {ord.status === 'delivered' && <span className="badge badge-approved mb-1">Status: Sold (Seller Confirmed)</span>}
                      {ord.status === 'completed' && <span className="badge" style={{background:'var(--success)', color:'#fff'}}>✓ Status: Completed</span>}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
                <p className="text-muted text-center" style={{padding:'1rem'}}>You have not placed any orders yet.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
