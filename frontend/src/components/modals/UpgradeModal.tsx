import React, { useState, useEffect } from 'react';
import { usePaystackPayment } from 'react-paystack';
import { motion } from 'motion/react';
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
  benefits?: string[];
}

/* ── Paystack trigger sub-component ── */
function PaystackTrigger({ config, onSuccess, onClosed }: {
  config: any;
  onSuccess: (ref: any) => void;
  onClosed: () => void;
}) {
  const initializePayment = usePaystackPayment(config);

  useEffect(() => {
    initializePayment(onSuccess, onClosed);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}

/* ── Card visual config per position ── */
const cardStyles: Record<number, {
  rotate: number;
  y: number;
  delay: number;
  z: number;
  scale: number;
  featured: boolean;
  gradient: string;
  borderColor: string;
  labelColor: string;
  textColor: string;
  btnBg: string;
  btnText: string;
}> = {
  0: {
    rotate: -6, y: 0, delay: 0, z: 10, scale: 1, featured: false,
    gradient: 'rgba(0,0,0,0.4)',
    borderColor: 'rgba(255,105,180,0.3)',
    labelColor: '#ff6fb1',
    textColor: '#fff',
    btnBg: '#ff6fb1',
    btnText: '#111',
  },
  1: {
    rotate: 0, y: -20, delay: 0.15, z: 20, scale: 1.1, featured: true,
    gradient: 'linear-gradient(to bottom, #ff6fb1, #ff3a95)',
    borderColor: 'rgba(255,105,180,0.5)',
    labelColor: '#1a1a1a',
    textColor: '#1a1a1a',
    btnBg: '#111',
    btnText: '#fff',
  },
  2: {
    rotate: 6, y: 0, delay: 0.1, z: 10, scale: 1, featured: false,
    gradient: 'rgba(0,0,0,0.4)',
    borderColor: 'rgba(255,105,180,0.3)',
    labelColor: '#ff6fb1',
    textColor: '#fff',
    btnBg: '#ff6fb1',
    btnText: '#111',
  },
};

export default function UpgradeModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, checkAuth } = useAuth();
  const [tiers, setTiers] = useState<Tier[]>([]);
  const [loading, setLoading] = useState(false);
  const [paystackConfig, setPaystackConfig] = useState<any>(null);

  useEffect(() => {
    if (open) {
      settings.tiers().then(res => {
        if (res.tiers) setTiers(res.tiers);
      }).catch(console.error);
    }
  }, [open]);

  /* ── Payment callbacks ── */
  const handleSuccess = (ref: any) => {
    payments.verify(ref.reference).then(() => {
      alert('🎉 Payment verified and account upgraded successfully!');
      checkAuth();
      onClose();
    }).catch(err => {
      console.error(err);
      alert('Failed to verify payment. Please contact support.');
    }).finally(() => {
      setLoading(false);
      setPaystackConfig(null);
    });
  };

  const handleClosed = () => {
    setLoading(false);
    setPaystackConfig(null);
  };

  const handleUpgradeClick = async (tierName: string, price: number) => {
    if (price === 0) {
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
          amount: res.amount * 100,
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

  if (!open) return null;

  const currentTier = user?.seller_tier || 'basic';

  return (
    <div
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      style={{
        display: 'flex',
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.92)',
        zIndex: 9999999,
        alignItems: 'center',
        justifyContent: 'center',
        backdropFilter: 'blur(20px)',
        overflowY: 'auto',
        WebkitOverflowScrolling: 'touch',
        padding: '20px',
      }}
    >
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ type: 'spring', duration: 0.5 }}
        style={{
          width: '100%',
          maxWidth: '1100px',
          padding: '3rem 2rem',
          position: 'relative',
        }}
      >
        {/* Close Button */}
        <button
          type="button"
          onClick={onClose}
          style={{
            position: 'absolute',
            top: '0',
            right: '0',
            background: 'rgba(255,255,255,0.1)',
            border: '1px solid rgba(255,255,255,0.15)',
            width: '44px',
            height: '44px',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '1.5rem',
            color: '#fff',
            cursor: 'pointer',
            zIndex: 10,
            transition: 'all 0.2s',
          }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.2)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.1)'; }}
        >
          &times;
        </button>

        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '3rem' }}>
          <motion.h2
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            style={{
              fontWeight: 900,
              letterSpacing: '-0.04em',
              fontSize: '2.4rem',
              marginBottom: '0.6rem',
              color: '#fff',
              lineHeight: 1.1,
            }}
          >
            Upgrade Your Business
          </motion.h2>
          <motion.p
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            style={{ fontSize: '1rem', color: 'rgba(255,255,255,0.5)' }}
          >
            Elevate your campus brand with premium verified tiers.
          </motion.p>
        </div>

        {/* Pricing Cards */}
        <div style={{
          display: 'flex',
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '2rem',
          flexWrap: 'wrap',
        }}>
          {tiers.length > 0 ? tiers.map((tier, index) => {
            const style = cardStyles[index] || cardStyles[0];
            const active = currentTier === tier.tier_name;

            // Build the features list dynamically
            const features: string[] = [
              `${tier.product_limit} Active Listings`,
              `${tier.images_per_product} Images per product`,
              tier.ads_boost ? 'Top Featured Rank' : 'Standard Rank',
              `${tier.priority ? tier.priority.charAt(0).toUpperCase() + tier.priority.slice(1) : 'Normal'} Search Priority`,
            ];

            if (tier.tier_name !== 'basic' && tier.badge) {
              features.push(`${tier.badge.charAt(0).toUpperCase() + tier.badge.slice(1)} Badge Verified`);
            }

            // Append admin-defined custom benefits
            if (tier.benefits && tier.benefits.length > 0) {
              features.push(...tier.benefits);
            }

            return (
              <motion.div
                key={tier.id}
                initial={{ opacity: 0, y: 60, rotate: style.rotate }}
                animate={{
                  opacity: 1,
                  y: style.y,
                  rotate: style.rotate,
                }}
                transition={{ type: 'spring', duration: 0.5 + style.delay, delay: style.delay }}
                style={{
                  position: 'relative',
                  zIndex: style.z,
                  width: style.featured ? '320px' : '280px',
                  borderRadius: style.featured ? '24px' : '20px',
                  border: style.featured
                    ? `4px solid ${style.borderColor}`
                    : `1px solid ${style.borderColor}`,
                  background: style.gradient,
                  backdropFilter: style.featured ? 'none' : 'blur(12px)',
                  boxShadow: style.featured
                    ? '0 25px 60px rgba(255,58,149,0.3)'
                    : '0 0 0 1px rgba(255,105,180,0.08) inset',
                  padding: style.featured ? '3.5rem 2.5rem' : '2.5rem 2rem',
                  color: style.textColor,
                  transition: 'transform 0.3s ease',
                  cursor: 'default',
                  flexShrink: 0,
                }}
                whileHover={{ scale: style.featured ? 1.12 : 1.05 }}
              >
                {/* "Best Deal" floating badge for the featured card */}
                {style.featured && !active && (
                  <motion.div
                    animate={{ y: [10, 6, 10] }}
                    transition={{ repeat: Infinity, duration: 2, ease: 'easeInOut' }}
                    style={{
                      position: 'absolute',
                      top: '-24px',
                      left: '50%',
                      transform: 'translateX(-50%)',
                      borderRadius: '999px',
                      border: '1px solid rgba(0,0,0,0.2)',
                      background: '#ff6fb1',
                      padding: '4px 20px',
                      fontSize: '0.75rem',
                      fontWeight: 800,
                      color: '#1a1a1a',
                      boxShadow: '0 4px 12px rgba(255,105,180,0.4)',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    Best Deal
                  </motion.div>
                )}

                {/* Active badge */}
                {active && (
                  <div style={{
                    position: 'absolute',
                    top: style.featured ? '16px' : '14px',
                    right: style.featured ? '20px' : '16px',
                    background: 'rgba(16,185,129,0.2)',
                    border: '1px solid rgba(16,185,129,0.5)',
                    color: '#10b981',
                    padding: '3px 12px',
                    borderRadius: '999px',
                    fontSize: '0.7rem',
                    fontWeight: 700,
                    letterSpacing: '0.5px',
                    textTransform: 'uppercase',
                  }}>
                    Active
                  </div>
                )}

                {/* Tier Name */}
                <div style={{
                  fontSize: style.featured ? '1.15rem' : '1.05rem',
                  fontWeight: 700,
                  marginBottom: '0.5rem',
                  color: style.featured ? style.labelColor : style.labelColor,
                  textTransform: 'capitalize',
                }}>
                  {tier.tier_name}
                </div>

                {/* Price */}
                <div style={{
                  fontSize: style.featured ? '3.2rem' : '2rem',
                  fontWeight: 900,
                  marginBottom: '1.5rem',
                  letterSpacing: '-0.04em',
                  lineHeight: 1,
                  color: style.featured ? '#1a1a1a' : '#fff',
                }}>
                  {tier.price > 0 ? (
                    <>₵{Number(tier.price).toFixed(0)}<span style={{ fontSize: '0.9rem', fontWeight: 600, opacity: 0.7 }}>/{tier.duration === 'forever' ? 'lifetime' : `${tier.duration}mo`}</span></>
                  ) : (
                    <>Free<span style={{ fontSize: '0.9rem', fontWeight: 600, opacity: 0.7 }}>/forever</span></>
                  )}
                </div>

                {/* Features */}
                <ul style={{
                  listStyle: 'none',
                  padding: 0,
                  margin: '0 0 1.5rem 0',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.6rem',
                }}>
                  {features.map((feat, i) => (
                    <li key={i} style={{
                      fontSize: style.featured ? '0.95rem' : '0.85rem',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '8px',
                      fontWeight: 500,
                      color: style.featured ? 'rgba(26,26,26,0.85)' : 'rgba(255,255,255,0.7)',
                    }}>
                      <span style={{ color: style.featured ? '#059669' : '#34d399', marginRight: '2px' }}>✔</span>
                      {feat}
                    </li>
                  ))}
                </ul>

                {/* CTA Button */}
                {!active ? (
                  <button
                    type="button"
                    onClick={() => handleUpgradeClick(tier.tier_name, tier.price)}
                    disabled={loading}
                    style={{
                      width: '100%',
                      borderRadius: '10px',
                      padding: '0.85rem',
                      fontWeight: 700,
                      fontSize: '0.9rem',
                      border: 'none',
                      cursor: loading ? 'not-allowed' : 'pointer',
                      opacity: loading ? 0.6 : 1,
                      background: style.btnBg,
                      color: style.btnText,
                      transition: 'all 0.2s ease',
                      textTransform: 'capitalize',
                    }}
                    onMouseEnter={(e) => { e.currentTarget.style.opacity = '0.85'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.opacity = loading ? '0.6' : '1'; }}
                  >
                    {loading && paystackConfig ? 'Processing...' : (tier.price > 0 ? `Upgrade to ${tier.tier_name}` : `Stay on ${tier.tier_name}`)}
                  </button>
                ) : (
                  <div style={{
                    width: '100%',
                    textAlign: 'center',
                    background: 'rgba(16,185,129,0.15)',
                    padding: '0.85rem',
                    borderRadius: '10px',
                    fontWeight: 700,
                    color: '#10b981',
                    border: '2px solid rgba(16,185,129,0.4)',
                    fontSize: '0.9rem',
                  }}>
                    Current Plan
                  </div>
                )}
              </motion.div>
            );
          }) : (
            <div style={{ color: 'rgba(255,255,255,0.5)', padding: '2rem' }}>
              Loading account tiers...
            </div>
          )}
        </div>
      </motion.div>

      {/* Paystack popup trigger */}
      {paystackConfig && (
        <PaystackTrigger config={paystackConfig} onSuccess={handleSuccess} onClosed={handleClosed} />
      )}
    </div>
  );
}
