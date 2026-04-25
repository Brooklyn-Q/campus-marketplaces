import React, { useState, useEffect } from 'react';
import { usePaystackPayment } from 'react-paystack';
import { settings, payments } from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';

interface Tier {
  id: number;
  tier_name: string;
  price: number;
  product_limit: number;
  images_per_product: number;
  ads_boost: number;
  duration: string;
  badge: string;
  priority: string;
}

export default function UpgradeModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, checkAuth } = useAuth();
  const [tiers, setTiers] = useState<Tier[]>([]);
  const [loading, setLoading] = useState(false);
  const [paystackConfig, setPaystackConfig] = useState<any>(null);

  const initializePayment = usePaystackPayment(paystackConfig || { publicKey: 'dummy' });

  useEffect(() => {
    if (open) {
      settings.tiers().then(res => {
        if (res.tiers) setTiers(res.tiers);
      }).catch(console.error);
    }
  }, [open]);

  useEffect(() => {
    if (paystackConfig) {
      const onSuccess = (ref: any) => {
        console.log('Payment successful. Verifying...', ref);
        payments.verify(ref.reference).then(() => {
          alert('Payment verified and account upgraded successfully!');
          checkAuth(); // Refresh user data
          onClose();
        }).catch(err => {
          console.error(err);
          alert('Failed to verify payment on our servers. Please contact support.');
        }).finally(() => {
          setLoading(false);
          setPaystackConfig(null);
        });
      };

      const onClosed = () => {
        console.log('Payment window closed.');
        setLoading(false);
        setPaystackConfig(null);
      };

      initializePayment(onSuccess, onClosed);
    }
  }, [paystackConfig]);

  if (!open) return null;

  const handleUpgradeClick = async (tierName: string, price: number) => {
    if (price === 0) {
      // Free tier logic (usually downgrading or requesting)
      alert(`Requesting transition to ${tierName} tier...`);
      return;
    }

    setLoading(true);
    try {
      const res = await payments.initialize(tierName as any);
      if (res.reference) {
        setPaystackConfig({
          reference: res.reference,
          email: user?.email || 'user@example.com',
          amount: res.amount * 100, // Paystack requires amount in pesewas/kobo
          publicKey: (import.meta as any).env.VITE_PAYSTACK_PUBLIC_KEY || 'pk_test_placeholder',
          currency: 'GHS',
        });
      } else {
        throw new Error('Failed to initialize payment on server');
      }
    } catch (err) {
      console.error(err);
      alert('Could not start payment process.');
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay open" style={{display:'flex', position:'fixed', inset:0, background:'rgba(0,0,0,0.85)', zIndex:9999999, alignItems:'center', justifyContent:'center', backdropFilter:'blur(15px)', overflowY:'auto', WebkitOverflowScrolling:'touch', padding:'20px'}}>
      <div className="glass upgrade-modal-content fade-in" style={{width:'100%', maxWidth:'1000px', padding:'2.5rem', borderRadius:'32px', position:'relative', background:'var(--bg)', border:'1px solid var(--border)', color:'var(--text-main)', boxShadow:'var(--shadow-lg)'}}>
        <button type="button" onClick={onClose} style={{position:'absolute', top:'15px', right:'15px', background:'rgba(255,255,255,0.1)', border:'none', width:'40px', height:'40px', borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center', fontSize:'1.5rem', color:'var(--text-main)', cursor:'pointer', zIndex:10}}>&times;</button>
        
        <div style={{textAlign:'center', marginBottom:'2rem', paddingTop:'0.5rem'}}>
          <h2 style={{fontWeight:800, letterSpacing:'-0.03em', fontSize:'2rem', marginBottom:'0.5rem'}}>Upgrade Your Business</h2>
          <p className="text-muted" style={{fontSize:'0.9rem'}}>Elevate your campus brand with premium verified tiers.</p>
        </div>
        
        <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fit, minmax(280px, 1fr))', gap:'1.5rem', width:'100%'}}>
          {tiers.length > 0 ? tiers.map(tier => {
            const active = (user?.seller_tier || 'basic') === tier.tier_name;
            const is_popular = tier.tier_name === 'pro';
            const baseColor = tier.tier_name === 'basic' ? '#0071e3' : (tier.tier_name === 'pro' ? '#8e8e93' : '#ff9f0a');
            const bgHover = is_popular ? 'rgba(0,113,227,0.04)' : 'rgba(0,0,0,0.02)';

            return (
              <div key={tier.id} className="tier-card" style={{padding:'2rem', borderRadius:'28px', display:'flex', flexDirection:'column', background:bgHover, border:`1px solid ${active ? 'var(--primary)' : 'var(--border)'}`, position:'relative'}}>
                {active && <span className="badge badge-blue" style={{position:'absolute', top:'20px', right:'20px'}}>Active Plan</span>}
                {is_popular && !active && <span className="badge badge-gold" style={{position:'absolute', top:'20px', right:'20px'}}>Best Value</span>}
                
                <h3 style={{textTransform:'capitalize', marginBottom:'0.4rem', fontWeight:800, fontSize:'1.5rem'}}>{tier.tier_name}</h3>
                <div style={{fontSize:'2.8rem', fontWeight:900, marginBottom:'1.5rem', color:'var(--primary)', letterSpacing:'-0.05em'}}>
                  ₵{Number(tier.price).toFixed(0)}<span style={{fontSize:'0.9rem', fontWeight:600, color:'var(--text-muted)', marginLeft:'6px'}}>/ {tier.duration === 'forever' ? 'lifetime' : 'period'}</span>
                </div>
                
                <ul style={{listStyle:'none', padding:0, marginBottom:'1.5rem', flexGrow:1}}>
                  <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                    <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> {tier.product_limit} Active Listings
                  </li>
                  <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                    <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> {tier.images_per_product} Images per product
                  </li>
                  <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                    <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> {tier.ads_boost ? 'Top Featured Rank' : 'Standard Rank'}
                  </li>
                  <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                    <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> {tier.priority.charAt(0).toUpperCase() + tier.priority.slice(1)} Search Priority
                  </li>
                  {tier.tier_name !== 'basic' && (
                    <>
                      <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                        <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> {tier.badge.charAt(0).toUpperCase() + tier.badge.slice(1)} Badge Verified
                      </li>
                      <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                        <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> AI Recommendations Boost
                      </li>
                    </>
                  )}
                  {tier.tier_name === 'premium' && (
                    <>
                      <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                        <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> 24/7 Priority Admin Support
                      </li>
                      <li style={{marginBottom:'0.8rem', fontSize:'0.92rem', display:'flex', gap:'10px', fontWeight:500}}>
                        <span style={{color:'var(--primary)', fontWeight:800}}>✓</span> Zero Sales Commission
                      </li>
                    </>
                  )}
                </ul>
                
                {!active ? (
                  <button 
                    type="button" 
                    onClick={() => handleUpgradeClick(tier.tier_name, tier.price)} 
                    disabled={loading}
                    className={`btn ${is_popular ? 'btn-primary' : 'btn-outline'}`} 
                    style={{width:'100%', borderRadius:'14px', padding:'1.2rem', fontWeight:800, textTransform:'uppercase', letterSpacing:'0.5px', boxShadow: is_popular ? '0 10px 20px rgba(0,113,227,0.1)' : 'none', opacity: loading ? 0.6 : 1, cursor: loading ? 'not-allowed' : 'pointer'}}
                  >
                    {loading && paystackConfig ? 'Processing...' : `Upgrade to ${tier.tier_name}`}
                  </button>
                ) : (
                  <div style={{display:'flex', flexDirection:'column', gap:'0.5rem'}}>
                    <div style={{width:'100%', textAlign:'center', background:'rgba(0,113,227,0.08)', padding:'1rem', borderRadius:'14px', fontWeight:800, color:'var(--primary)', border:'2px solid var(--primary)'}}>Active Plan</div>
                  </div>
                )}
              </div>
            );
          }) : (
            <div style={{width:'100%', textAlign:'center', color:'var(--text-muted)', padding:'2rem'}}>Loading account tiers...</div>
          )}
        </div>
      </div>
    </div>
  );
}
