import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { leaderboard } from '../services/api';

export default function Leaderboard() {
  const [leaders, setLeaders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchBoard = async () => {
      try {
        const res = await leaderboard.get();
        setLeaders(res.leaderboard || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchBoard();
  }, []);

  const assetUrl = (path: string | undefined | null) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    if (path.startsWith('uploads/')) {
      const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/marketplace/backend/api';
      const backendRoot = apiBase.replace(/\/api\/?$/, '');
      return `${backendRoot}/../${path}`;
    }
    return path.startsWith('/') ? path : `/${path}`;
  };

  const colors = ['#FFD700', '#C0C0C0', '#CD7F32']; // Gold, Silver, Bronze

  if (loading) return <div className="container" style={{padding:'4rem 0', textAlign:'center'}}>Loading Rankings...</div>;

  return (
    <div className="container fade-in" style={{maxWidth:'960px', padding:'3rem 5%'}}>
      <div style={{textAlign:'center', marginBottom:'4rem'}}>
        <span style={{background:'rgba(0,113,227,0.1)', color:'var(--primary)', padding:'0.5rem 1.2rem', borderRadius:'20px', fontSize:'0.85rem', fontWeight:700, textTransform:'uppercase', letterSpacing:'1px', marginBottom:'1.5rem', display:'inline-block'}}>Ranking</span>
        <h1 style={{fontSize:'3.5rem', fontWeight:900, letterSpacing:'-0.05em', color:'var(--text-main)', lineHeight:1.1, marginBottom:'1rem'}}>Seller Leaderboard</h1>
        <p style={{color:'var(--text-muted)', fontSize:'1.2rem', maxWidth:'600px', margin:'0 auto', fontWeight:500}}>Recognizing our most dedicated campus sellers. Activity and sales drive the rankings.</p>
      </div>

      <div className="glass" style={{borderRadius:'28px', overflow:'hidden', border:'1px solid rgba(255,255,255,0.1)', boxShadow:'0 20px 40px rgba(0,0,0,0.08)'}}>
        {/* Header Grid */}
        <div style={{display:'grid', gridTemplateColumns:'80px 1fr 100px 180px', gap:'1.5rem', padding:'1.25rem 2rem', alignItems:'center', borderBottom:'2px solid var(--border)', background:'rgba(0,0,0,0.02)', fontWeight:800, color:'var(--text-muted)', textTransform:'uppercase', fontSize:'0.75rem', letterSpacing:'1.5px'}}>
            <div style={{textAlign:'center'}}>Rank</div>
            <div>Seller Details</div>
            <div style={{textAlign:'center'}}>Deals</div>
            <div style={{textAlign:'right'}}>Performance</div>
        </div>

        {leaders.length > 0 ? (
          leaders.map((l, index) => {
            const isTop3 = index < 3;
            const rankEmoji = ['🥇','🥈','🥉'][index] || null;
            const rankColor = isTop3 ? colors[index] : 'var(--text-muted)';
            
            return (
              <Link key={l.id} to={`/chat?user=${l.id}`} style={{display:'grid', gridTemplateColumns:'80px 1fr 100px 180px', gap:'1.5rem', padding:'1.25rem 2rem', alignItems:'center', borderBottom:'1px solid rgba(128,128,128,0.06)', textDecoration:'none', color:'inherit', cursor:'pointer', transition:'all 0.2s ease'}}>
                {/* Rank Column */}
                <div style={{textAlign:'center'}}>
                  {rankEmoji ? (
                    <span style={{fontSize:'1.8rem', filter:'drop-shadow(0 4px 8px rgba(0,0,0,0.1))'}}>{rankEmoji}</span>
                  ) : (
                    <span style={{fontSize:'1.1rem', fontWeight:800, color:'var(--text-muted)', opacity:0.6}}>#{index+1}</span>
                  )}
                </div>
                
                {/* User Info Column */}
                <div style={{display:'flex', alignItems:'center', gap:'1.25rem'}}>
                  <div style={{position:'relative'}}>
                    {l.profile_pic ? (
                      <img src={assetUrl('uploads/' + l.profile_pic)} alt="" style={{width:'56px', height:'56px', borderRadius:'18px', objectFit:'cover', border:`2px solid ${isTop3 ? rankColor : 'rgba(128,128,128,0.1)'}`, boxShadow:'0 8px 16px rgba(0,0,0,0.05)'}} />
                    ) : (
                      <div style={{width:'56px', height:'56px', borderRadius:'18px', background:'linear-gradient(135deg, rgba(0,113,227,0.1), rgba(0,113,227,0.05))', color:'var(--primary)', display:'flex', alignItems:'center', justifyContent:'center', fontWeight:800, fontSize:'1.4rem', border:`2px solid ${isTop3 ? rankColor : 'rgba(128,128,128,0.1)'}`}}>
                        {l.username.substring(0,1).toUpperCase()}
                      </div>
                    )}
                    {l.verified === 1 && (
                      <div style={{position:'absolute', bottom:'-4px', right:'-4px', background:'var(--success)', color:'#fff', width:'20px', height:'20px', borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'0.7rem', border:'2px solid #fff', boxShadow:'0 2px 4px rgba(0,0,0,0.1)'}}>✓</div>
                    )}
                  </div>
                  <div style={{overflow:'hidden'}}>
                     <h4 style={{fontSize:'1rem', fontWeight:700, color:'var(--text-main)', margin:0, display:'flex', alignItems:'center', gap:'6px'}}>
                        {l.username}
                     </h4>
                     <p style={{color:'var(--text-muted)', fontSize:'0.75rem', marginTop:'1px', whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis', fontWeight:500}}>
                        {l.department || 'Verified Seller'}
                     </p>
                  </div>
                </div>

                {/* Statistics Column */}
                <div style={{textAlign:'center'}}>
                  <span style={{fontSize:'1.1rem', fontWeight:800, color:'var(--text-main)', letterSpacing:'-0.4px'}}>{l.sales_today > 0 ? l.sales_today : l.lifetime_sales}</span>
                  <div style={{fontSize:'0.55rem', color:'var(--text-muted)', textTransform:'uppercase', fontWeight:700, letterSpacing:'0.4px', marginTop:'-1px'}}>
                    {l.sales_today > 0 ? 'Sold Today' : 'Sold Total'}
                  </div>
                </div>

                {/* Badge Column */}
                <div style={{textAlign:'right'}}>
                  {l.sales_today > 0 ? (
                    <div style={{display:'inline-flex', alignItems:'center', gap:'4px', background:'rgba(0, 113, 227, 0.1)', color:'var(--primary)', padding:'0.4rem 0.8rem', borderRadius:'10px', fontSize:'0.75rem', fontWeight:800, border:'1px solid rgba(0,113,227,0.2)'}}>
                      <span>🚀</span> Trending
                    </div>
                  ) : l.lifetime_sales > 10 ? (
                    <div style={{display:'inline-flex', alignItems:'center', gap:'4px', background:'rgba(250, 204, 21, 0.1)', color:'#ca8a04', padding:'0.4rem 0.8rem', borderRadius:'10px', fontSize:'0.75rem', fontWeight:800, border:'1px solid rgba(250,204,21,0.2)'}}>
                      <span>💎</span> Power Seller
                    </div>
                  ) : (
                    <div style={{display:'inline-flex', alignItems:'center', gap:'4px', background:'rgba(128, 128, 128, 0.08)', color:'var(--text-muted)', padding:'0.4rem 0.8rem', borderRadius:'10px', fontSize:'0.75rem', fontWeight:700, border:'1px solid rgba(128,128,128,0.15)'}}>
                      Growing
                    </div>
                  )}
                </div>
              </Link>
            )
          })
        ) : (
          <div style={{padding:'4rem', textAlign:'center', color:'var(--text-muted)'}}>
            <span style={{fontSize:'3rem', display:'block', marginBottom:'1rem'}}>🏆</span>
            <p style={{fontSize:'1.1rem', fontWeight:600}}>The leaderboard is warming up...</p>
            <p style={{fontSize:'0.9rem', opacity:0.7}}>Be the first to list and sell to claim your spot!</p>
          </div>
        )}
      </div>
    </div>
  );
}
