import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { orders, products as productsApi } from '../services/api';

export default function SellerDashboard() {
  const { user } = useAuth();
  const [sellerOrders, setSellerOrders] = useState<any[]>([]);
  const [sellerProducts, setSellerProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [detailedView, setDetailedView] = useState(false);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [oRes, pRes] = await Promise.all([
          orders.list(),
          productsApi.myProducts()
        ]);
        setSellerOrders(oRes.orders || []);
        setSellerProducts((pRes as any).products || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const totalProducts = sellerProducts.length;
  const approvedProducts = sellerProducts.filter(p => p.status === 'approved').length;
  const pendingProducts = sellerProducts.filter(p => p.status === 'pending').length;
  const lowStock = sellerProducts.filter(p => p.quantity <= 5 && p.status === 'approved').length;
  
  const totalSold = sellerOrders.filter(o => o.status === 'completed').length;
  const revenue = sellerOrders.filter(o => o.status === 'completed').reduce((acc, o) => acc + Number(o.product_price), 0);
  const totalViews = sellerProducts.reduce((acc, p) => acc + (p.views || 0), 0);
  
  const sortProducts = [...sellerProducts].sort((a, b) => (b.views||0) - (a.views||0));
  const topProduct = sortProducts[0];

  const limit = user?.seller_tier === 'premium' ? 15 : (user?.seller_tier === 'pro' ? 5 : 2);
  const usage_pct = (totalProducts / limit) * 100;

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
        .grid-2-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } .grid-2-cols { grid-template-columns: 1fr; } }
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
              {user?.seller_tier === 'pro' && <span className="badge badge-gold">Pro</span>}
              {user?.seller_tier === 'premium' && <span className="badge badge-purple" style={{background:'#af52de', color:'#fff'}}>Premium</span>}
              {(user?.seller_tier === 'basic' || !user?.seller_tier) && <span className="badge badge-blue">Basic Seller</span>}
              {user?.verified ? <span className="badge badge-approved" style={{marginLeft:'4px'}}>✓ Verified</span> : null}
            </div>
            
            {user?.faculty && <p className="text-muted" style={{fontSize:'0.78rem', marginTop:'0.3rem'}}>🎓 {user.faculty}</p>}
            {user?.department && <p className="text-muted" style={{fontSize:'0.85rem'}}>{user.department} · L{user.level}</p>}
            {user?.hall && <p className="text-muted" style={{fontSize:'0.8rem'}}>🏠 {user.hall}</p>}
            {user?.phone && <p className="text-muted" style={{fontSize:'0.8rem'}}>📱 {user.phone}</p>}
            
            <div style={{marginTop:'1rem', display:'flex', gap:'0.5rem', flexDirection:'column'}}>
               <a href="/edit-profile" className="btn btn-primary" style={{justifyContent:'center', borderRadius:'12px'}}>Edit Profile</a>
               <button className="btn btn-outline" style={{justifyContent:'center'}}>☀️ Vacation Mode</button>
               {user?.seller_tier !== 'premium' && (
                 <button type="button" className="btn btn-gold" style={{justifyContent:'center'}}>🚀 Upgrade Account</button>
               )}
            </div>
          </div>

          <div className="glass fade-in" style={{padding:'1.5rem', marginBottom:'1.5rem', marginTop:'1.5rem'}}>
            <p className="text-muted" style={{fontSize:'0.8rem'}}>Wallet Balance</p>
            <h2 style={{color:'var(--success)', fontSize:'2rem', fontWeight:800}}>₵{Number(user?.balance || 0).toFixed(2)}</h2>
          </div>
        </div>

        {/* MAIN AREA */}
        <div>
          <div className="stat-grid mb-3 fade-in">
            <a href="#products_section" className="stat-card-link" onClick={()=>setDetailedView(true)}><div className="glass stat-card"><div className="stat-val" style={{color:'var(--primary)'}}>{totalProducts}</div><div className="stat-label">Total Products</div></div></a>
            <a href="#products_section" className="stat-card-link" onClick={()=>setDetailedView(true)}><div className="glass stat-card"><div className="stat-val" style={{color:'var(--success)'}}>{approvedProducts}</div><div className="stat-label">Active Listings</div></div></a>
            <a href="#seller_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--mint)'}}>{totalSold}</div><div className="stat-label">Items Sold</div></div></a>
            <a href="#seller_orders" className="stat-card-link"><div className="glass stat-card"><div className="stat-val" style={{color:'var(--gold)'}}>₵{revenue.toFixed(2)}</div><div className="stat-label">Total Revenue</div></div></a>
            <a href="#products_section" className="stat-card-link" onClick={()=>setDetailedView(true)}><div className="glass stat-card"><div className="stat-val" style={{color:'var(--warning)'}}>{pendingProducts}</div><div className="stat-label">Pending Approval</div></div></a>
            <a href="#analytics_section" className="stat-card-link"><div className="glass stat-card"><div className="stat-val">{totalViews}</div><div className="stat-label">Total Views</div></div></a>
          </div>

          <div className="grid-2-cols mb-3 fade-in">
            {lowStock > 0 ? (
              <div className="glass" style={{padding:'1.25rem', borderLeft:'4px solid #ff9500'}}>
                  <div style={{display:'flex', alignItems:'center', gap:'0.5rem', marginBottom:'0.3rem'}}>
                      <span style={{fontSize:'1.3rem'}}>⚠️</span>
                      <h4 style={{fontSize:'0.9rem', margin:0}}>Low Stock Alert</h4>
                  </div>
                  <p style={{fontSize:'2rem', fontWeight:800, color:'#ff9500'}}>{lowStock}</p>
                  <p className="text-muted" style={{fontSize:'0.78rem'}}>products with ≤5 units</p>
              </div>
            ) : (
              <div className="glass" style={{padding:'1.25rem', borderLeft:'4px solid var(--success)'}}>
                  <div style={{display:'flex', alignItems:'center', gap:'0.5rem', marginBottom:'0.3rem'}}>
                      <span style={{fontSize:'1.3rem'}}>✅</span>
                      <h4 style={{fontSize:'0.9rem', margin:0}}>Stock Status</h4>
                  </div>
                  <p style={{fontSize:'0.85rem', color:'var(--success)', fontWeight:600}}>All products well stocked</p>
              </div>
            )}

            <div className="glass" style={{padding:'1.25rem', borderLeft:'4px solid var(--primary)'}}>
                <div style={{display:'flex', alignItems:'center', gap:'0.5rem', marginBottom:'0.3rem'}}>
                    <span style={{fontSize:'1.3rem'}}>🏆</span>
                    <h4 style={{fontSize:'0.9rem', margin:0}}>Top Product</h4>
                </div>
                {topProduct ? (
                  <>
                    <p style={{fontSize:'0.95rem', fontWeight:700, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'}}>{topProduct.title}</p>
                    <p className="text-muted" style={{fontSize:'0.78rem'}}>👁 {topProduct.views} views · Stock: {topProduct.quantity}</p>
                  </>
                ) : (
                  <p className="text-muted" style={{fontSize:'0.85rem'}}>No products yet</p>
                )}
            </div>
          </div>

          <div id="analytics_section" className="glass fade-in" style={{padding:'1.5rem', marginBottom:'1.5rem'}}>
            <h4 className="mb-2">📊 Weekly Performance</h4>
            <div style={{height:'150px', background:'rgba(0,0,0,0.02)', borderRadius:'12px', border:'1px dashed var(--border)', display:'flex', alignItems:'center', justifyContent:'center'}}>
                <span className="text-muted" style={{fontSize:'0.85rem'}}>Chart data functionally migrated to backend API</span>
            </div>
          </div>

          <div id="products_section" className="fade-in mb-3">
            <div className="glass" style={{padding:'2rem', borderRadius:'24px', position:'relative', overflow:'hidden', background:'linear-gradient(135deg, rgba(0,113,227,0.1) 0%, rgba(20,20,20,0) 100%)', border:'1px solid rgba(0,113,227,0.2)'}}>
              <div className="products_section_summary" style={{display:'flex', justifyContent:'space-between', alignItems:'center', flexWrap:'wrap', gap:'1rem'}}>
                  <div>
                      <div style={{display:'flex', alignItems:'center', gap:'12px', marginBottom:'0.5rem'}}>
                          <span style={{fontSize:'1.8rem'}}>📦</span>
                          <h3 style={{margin:0, fontSize:'1.5rem', fontWeight:800}}>My Inventory</h3>
                      </div>
                      <p className="text-muted" style={{fontSize:'0.9rem'}}>You have <strong>{totalProducts}</strong> products listed on the marketplace.</p>
                      <div style={{marginTop:'1.25rem', display:'flex', gap:'0.5rem', flexWrap:'wrap'}}>
                          <button className="btn btn-primary" onClick={() => setDetailedView(!detailedView)} style={{padding:'0.75rem 1.5rem', borderRadius:'12px', fontWeight:700}}>Manage Products</button>
                          <a href="/add-product" className="btn btn-outline" style={{padding:'0.75rem 1.5rem', borderRadius:'12px', fontWeight:700}}>+ New Product</a>
                          <button className="btn btn-outline" style={{padding:'0.75rem 1.5rem', borderRadius:'12px', fontWeight:700, color:'var(--primary)', borderColor:'var(--primary)'}}>🔗 Share My Shop</button>
                      </div>
                  </div>
                  
                  <div className="inventory-slots-card" style={{textAlign:'right'}}>
                      <div style={{padding:'1.25rem', background:'rgba(255,255,255,0.05)', borderRadius:'20px', border:'1px solid rgba(255,255,255,0.1)', display:'inline-block'}}>
                          <p className="text-muted" style={{fontSize:'0.75rem', marginBottom:'0.2rem'}}>Inventory Slots</p>
                          <div style={{fontSize:'1.4rem', fontWeight:900, color:'var(--text-main)', lineHeight:1}}>{totalProducts} / {limit}</div>
                          <div style={{width:'100px', height:'6px', background:'rgba(0,0,0,0.1)', borderRadius:'3px', marginTop:'8px', overflow:'hidden'}}>
                              <div style={{width: `${Math.min(100, usage_pct)}%`, height:'100%', background:'var(--primary)'}}></div>
                          </div>
                      </div>
                  </div>
              </div>
            </div>

            {detailedView && (
              <div id="detailed-product-grid" className="glass mt-2" style={{padding:'2rem', borderRadius:'24px', animation:'slideDown 0.4s cubic-bezier(0.165, 0.84, 0.44, 1)'}}>
                  <div className="flex-between mb-4" style={{display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'1.5rem'}}>
                      <h4 style={{fontSize:'1.2rem', margin:0}}>Product Catalog</h4>
                      <button className="btn btn-outline btn-sm" onClick={() => setDetailedView(false)}>Close View</button>
                  </div>

                  {sellerProducts.length > 0 ? (
                      <div className="product-grid" style={{display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(200px, 1fr))', gap:'1.25rem'}}>
                          {sellerProducts.map(p => (
                              <div key={p.id} className="glass product-card" style={{display:'flex', flexDirection:'column', background:'var(--bg)', border:'1px solid var(--border)', borderRadius:'22px', overflow:'hidden', minHeight:'400px', position:'relative'}}>
                                  <div className="product-img-wrap" style={{aspectRatio:'1/1', position:'relative', overflow:'hidden'}}>
                                      {p.images?.[0] ? (
                                          <img src={'/marketplace/uploads/' + p.images[0].url} className="product-img" style={{width:'100%', height:'100%', objectFit:'cover'}} alt={p.title} />
                                      ) : (
                                          <div style={{width:'100%', height:'100%', display:'flex', alignItems:'center', justifyContent:'center', background:'rgba(0,0,0,0.05)', color:'var(--text-muted)', fontSize:'0.75rem'}}>No Image</div>
                                      )}
                                      
                                      <div style={{position:'absolute', top:'12px', left:'12px', display:'flex', flexDirection:'column', gap:'6px'}}>
                                          <span className="badge" style={{background: p.status === 'approved' ? '#34c759' : (p.status==='pending' ? '#ff9500' : '#0071e3'), color:'#fff', border:'none', fontSize:'0.65rem', padding:'4px 10px', fontWeight:700, backdropFilter:'blur(10px)'}}>
                                              {(p.status||'').toUpperCase()}
                                          </span>
                                      </div>
                                  </div>
                                  
                                  <div className="product-body" style={{padding:'1.25rem', flexGrow:1, display:'flex', flexDirection:'column'}}>
                                      <h4 style={{fontSize:'1.05rem', fontWeight:700, marginBottom:'0.4rem'}}>{p.title}</h4>
                                      <div style={{display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:'1rem'}}>
                                          <p style={{fontSize:'1.25rem', fontWeight:800, color:'var(--primary)', margin:0}}>₵{Number(p.price).toFixed(2)}</p>
                                          <span className="text-muted" style={{fontSize:'0.75rem', fontWeight:600}}>Qty: {p.quantity}</span>
                                      </div>

                                      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:'0.6rem', marginTop:'auto'}}>
                                          {p.status === 'approved' && (
                                            <>
                                              <button className="btn btn-outline btn-sm" style={{fontSize:'0.75rem', padding:'0.6rem', justifyContent:'center', color:'#ff3b30', borderColor:'rgba(255,59,48,0.1)'}}>Sold Out</button>
                                              <button className="btn btn-outline btn-sm" style={{fontSize:'0.75rem', padding:'0.6rem', justifyContent:'center'}}>Pause</button>
                                              <button className="btn btn-primary btn-sm" style={{gridColumn:'span 2', fontSize:'0.8rem', padding:'0.6rem', justifyContent:'center', borderRadius:'12px'}}>📸 Flyer / Promo</button>
                                              <button className="btn btn-gold btn-sm" style={{gridColumn:'span 2', fontSize:'0.8rem', padding:'0.6rem', justifyContent:'center', borderRadius:'12px'}}>⚡ Boost Item</button>
                                            </>
                                          )}
                                          <button className="btn btn-outline btn-sm" style={{gridColumn:'span 2', fontSize:'0.7rem', padding:'0.4rem', color:'var(--text-muted)', justifyContent:'center', borderStyle:'dashed', marginTop:'0.5rem', opacity:0.6}}>Remove listing</button>
                                      </div>
                                  </div>
                              </div>
                          ))}
                      </div>
                  ) : (
                      <p className="text-center text-muted" style={{padding:'4rem'}}>No products found.</p>
                  )}
              </div>
            )}
          </div>

          <div id="seller_orders" className="glass fade-in" style={{marginBottom:'2rem', padding:'2rem'}}>
            <h3 className="mb-3">📦 Order Management</h3>
            {sellerOrders.length > 0 ? (
                <div className="flex-column gap-1">
                    {sellerOrders.map(ord => (
                    <div key={ord.id} style={{background:'rgba(0,0,0,0.2)', border:'1px solid var(--border)', padding:'1rem', borderRadius:'12px', marginBottom:'1rem'}}>
                        <div className="flex-between mb-1" style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                            <strong>#ORDER-{ord.id} &bull; {ord.product_title}</strong>
                            <span className="badge" style={{background:'#0071e3', color:'#fff'}}>₵{Number(ord.product_price).toFixed(2)}</span>
                        </div>
                        <p className="text-muted" style={{fontSize:'0.85rem', marginBottom:'0.75rem'}}>Buyer: <strong>{ord.buyer_name}</strong> &bull; Date: {new Date(ord.created_at).toLocaleString()}</p>
                        
                        <div>
                            {ord.status === 'ordered' && <><span className="badge badge-pending mb-1">Status: Pending</span><br/><a className="btn btn-success btn-sm mt-1">Confirm Item Sold</a> <a className="btn btn-outline btn-sm mt-1">💬 Message</a></>}
                            {ord.status === 'delivered' && <><span className="badge badge-approved">Status: Sold (Seller Confirmed)</span><br/><a className="btn btn-outline btn-sm mt-1">💬 Message</a></>}
                            {ord.status === 'completed' && <><span className="badge" style={{background:'var(--success)', color:'#fff'}}>✓ Status: Completed</span><br/><a className="btn btn-outline btn-sm mt-1">💬 History</a></>}
                        </div>
                    </div>
                    ))}
                </div>
            ) : (
                <p className="text-muted text-center" style={{padding:'1rem'}}>No orders to manage.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
