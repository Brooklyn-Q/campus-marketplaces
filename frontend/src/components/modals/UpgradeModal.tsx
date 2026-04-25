import React, { useState, useEffect, useCallback } from 'react';
import { usePaystackPayment } from 'react-paystack';
import { motion, AnimatePresence } from 'motion/react';
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

/* ── Paystack trigger — mounts only when config is ready ── */
function PaystackButton({ config, onVerified, onCancelled }: {
  config: { reference: string; email: string; amount: number; publicKey: string; currency: string };
  onVerified: (ref: any) => void;
  onCancelled: () => void;
}) {
  const initPayment = usePaystackPayment({
    reference: config.reference,
    email: config.email,
    amount: config.amount,
    publicKey: config.publicKey,
    currency: config.currency,
  });

  useEffect(() => {
    // Fire the Paystack popup immediately when this component mounts
    initPayment(onVerified, onCancelled);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return null;
}

/* ── Hex color helpers ── */
function hexToRgba(hex: string, alpha: number): string {
  const h = hex.replace('#', '');
  const r = parseInt(h.substring(0, 2), 16);
  const g = parseInt(h.substring(2, 4), 16);
  const b = parseInt(h.substring(4, 6), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

/* ── Card animation config by position ── */
function getCardConfig(index: number, total: number, tierColor: string) {
  const isFeatured = index === 1 && total >= 3;
  const isFirst = index === 0;
  const isLast = index === total - 1;

  return {
    rotate: isFirst ? -6 : isLast ? 6 : 0,
    yOffset: isFeatured ? -20 : 0,
    delay: index * 0.12,
    zIndex: isFeatured ? 20 : 10,
    featured: isFeatured,
    // Card surface
    bg: isFeatured
      ? `linear-gradient(135deg, ${tierColor}, ${hexToRgba(tierColor, 0.75)})`
      : hexToRgba(tierColor, 0.06),
    border: isFeatured
      ? `3px solid ${hexToRgba(tierColor, 0.6)}`
      : `1px solid ${hexToRgba(tierColor, 0.25)}`,
    shadow: isFeatured
      ? `0 25px 60px ${hexToRgba(tierColor, 0.35)}`
      : `0 0 0 1px ${hexToRgba(tierColor, 0.08)} inset`,
    // Text colors
    headingColor: isFeatured ? '#fff' : tierColor,
    priceColor: isFeatured ? '#fff' : '#fff',
    featureColor: isFeatured ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.65)',
    checkColor: isFeatured ? '#fff' : tierColor,
    // Button
    btnBg: isFeatured ? 'rgba(0,0,0,0.85)' : tierColor,
    btnText: '#fff',
    // Badge label
    badgeBg: hexToRgba(tierColor, 0.9),
    badgeText: '#fff',
  };
}

export default function UpgradeModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, checkAuth } = useAuth();
  const [tiers, setTiers] = useState<Tier[]>([]);
  const [loading, setLoading] = useState(false);
  const [paystackConfig, setPaystackConfig] = useState<any>(null);

  useEffect(() => {
    if (open) {
      setPaystackConfig(null);
      setLoading(false);
      settings.tiers().then(res => {
        if (res.tiers) setTiers(res.tiers);
      }).catch(console.error);
    }
  }, [open]);

  /* ── Paystack callbacks ── */
  const handlePaystackSuccess = useCallback((ref: any) => {
    const reference = typeof ref === 'string' ? ref : ref?.reference || ref?.trxref;
    if (!reference) {
      alert('Payment completed but no reference received. Contact support.');
      setLoading(false);
      setPaystackConfig(null);
      return;
    }
    payments.verify(reference).then(() => {
      alert('🎉 Payment verified — your account has been upgraded!');
      checkAuth();
      onClose();
    }).catch(err => {
      console.error(err);
      alert('Payment received but verification failed. Please contact support with ref: ' + reference);
    }).finally(() => {
      setLoading(false);
      setPaystackConfig(null);
    });
  }, [checkAuth, onClose]);

  const handlePaystackClose = useCallback(() => {
    setLoading(false);
    setPaystackConfig(null);
  }, []);

  /* ── Upgrade button handler ── */
  const handleUpgradeClick = async (tierName: string, price: number) => {
    if (price <= 0) return;
    if (loading) return;

    setLoading(true);
    try {
      const res = await payments.initialize(tierName as any);
      if (!res.reference) throw new Error('No reference returned');

      setPaystackConfig({
        reference: res.reference,
        email: res.email || user?.email || '',
        amount: (res.amount || price) * 100,
        publicKey: 'pk_live_ba277a24ca885b3f6299a479329bcfe265132cc2',
        currency: 'GHS',
      });
    } catch (err: any) {
      console.error('Payment init error:', err);
      alert('Could not start payment: ' + (err?.message || 'Unknown error'));
      setLoading(false);
    }
  };

  if (!open) return null;

  const currentTier = user?.seller_tier || 'basic';

  return (
    <AnimatePresence>
      <div
        onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
        style={{
          display: 'flex',
          position: 'fixed',
          inset: 0,
          background: 'rgba(0,0,0,0.92)',
          zIndex: 9999999,
          alignItems: 'flex-start',
          justifyContent: 'center',
          backdropFilter: 'blur(20px)',
          overflowY: 'auto',
          overflowX: 'hidden',
          WebkitOverflowScrolling: 'touch',
          padding: '40px 20px 80px',
        }}
      >
        <motion.div
          initial={{ opacity: 0, scale: 0.92 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.92 }}
          transition={{ type: 'spring', duration: 0.5 }}
          style={{ width: '100%', maxWidth: '1100px', padding: '2.5rem 1.5rem', position: 'relative', margin: 'auto 0' }}
        >
          {/* Close */}
          <button
            type="button"
            onClick={onClose}
            style={{
              position: 'absolute', top: 0, right: 0,
              background: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.12)',
              width: '42px', height: '42px', borderRadius: '50%',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '1.4rem', color: '#fff', cursor: 'pointer', zIndex: 10,
              transition: 'background 0.2s',
            }}
            onMouseEnter={e => { e.currentTarget.style.background = 'rgba(255,255,255,0.18)'; }}
            onMouseLeave={e => { e.currentTarget.style.background = 'rgba(255,255,255,0.08)'; }}
          >
            &times;
          </button>

          {/* Header */}
          <div style={{ textAlign: 'center', marginBottom: '2.5rem' }}>
            <motion.h2
              initial={{ opacity: 0, y: -20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 }}
              style={{ fontWeight: 900, letterSpacing: '-0.04em', fontSize: '2.2rem', marginBottom: '0.5rem', color: '#fff' }}
            >
              Upgrade Your Business
            </motion.h2>
            <motion.p
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
              style={{ fontSize: '0.95rem', color: 'rgba(255,255,255,0.45)' }}
            >
              Elevate your campus brand with verified seller tiers.
            </motion.p>
          </div>

          {/* Cards */}
          <div style={{
            display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
            gap: '1.8rem', flexWrap: 'wrap',
          }}>
            {tiers.length > 0 ? tiers.map((tier, index) => {
              const tierColor = tier.badge || '#0071e3';
              const cfg = getCardConfig(index, tiers.length, tierColor);
              const active = currentTier === tier.tier_name;

              // Build features
              const features: string[] = [
                `${tier.product_limit} Active Listings`,
                `${tier.images_per_product} Images per product`,
                tier.ads_boost ? 'Top Featured Rank' : 'Standard Rank',
              ];
              if (tier.priority) {
                features.push(`${tier.priority.charAt(0).toUpperCase() + tier.priority.slice(1)} Search Priority`);
              }
              if (tier.tier_name !== 'basic' && tier.badge) {
                features.push('Verified Seller Badge');
              }
              if (tier.benefits && tier.benefits.length > 0) {
                features.push(...tier.benefits);
              }

              return (
                <motion.div
                  key={tier.id}
                  initial={{ opacity: 0, y: 60, rotate: cfg.rotate }}
                  animate={{ opacity: 1, y: cfg.yOffset, rotate: cfg.rotate }}
                  transition={{ type: 'spring', duration: 0.55, delay: cfg.delay }}
                  whileHover={{ scale: cfg.featured ? 1.08 : 1.04 }}
                  style={{
                    position: 'relative',
                    zIndex: cfg.zIndex,
                    width: cfg.featured ? '320px' : '275px',
                    minHeight: cfg.featured ? '420px' : '380px',
                    borderRadius: cfg.featured ? '28px' : '22px',
                    border: cfg.border,
                    background: cfg.bg,
                    backdropFilter: cfg.featured ? 'none' : 'blur(14px)',
                    boxShadow: cfg.shadow,
                    padding: cfg.featured ? '3rem 2.2rem 2.2rem' : '2.2rem 1.8rem 1.8rem',
                    color: '#fff',
                    display: 'flex',
                    flexDirection: 'column',
                    flexShrink: 0,
                    transition: 'transform 0.3s ease',
                  }}
                >
                  {/* Floating "Best Deal" for featured */}
                  {cfg.featured && !active && (
                    <motion.div
                      animate={{ y: [0, -5, 0] }}
                      transition={{ repeat: Infinity, duration: 2.2, ease: 'easeInOut' }}
                      style={{
                        position: 'absolute', top: '-16px', left: '50%', transform: 'translateX(-50%)',
                        borderRadius: '999px', background: cfg.badgeBg, color: cfg.badgeText,
                        padding: '5px 18px', fontSize: '0.72rem', fontWeight: 800,
                        boxShadow: `0 4px 16px ${hexToRgba(tierColor, 0.45)}`,
                        whiteSpace: 'nowrap', letterSpacing: '0.5px', textTransform: 'uppercase',
                      }}
                    >
                      Best Deal
                    </motion.div>
                  )}

                  {/* Active badge */}
                  {active && (
                    <div style={{
                      position: 'absolute', top: '14px', right: '16px',
                      background: 'rgba(16,185,129,0.2)', border: '1px solid rgba(16,185,129,0.5)',
                      color: '#34d399', padding: '3px 12px', borderRadius: '999px',
                      fontSize: '0.68rem', fontWeight: 700, letterSpacing: '0.5px', textTransform: 'uppercase',
                    }}>
                      Active
                    </div>
                  )}

                  {/* Tier name */}
                  <div style={{
                    fontSize: cfg.featured ? '1.1rem' : '1rem', fontWeight: 700,
                    marginBottom: '0.4rem', color: cfg.headingColor, textTransform: 'capitalize',
                  }}>
                    {tier.tier_name}
                  </div>

                  {/* Price */}
                  <div style={{
                    fontSize: cfg.featured ? '3rem' : '2.2rem', fontWeight: 900,
                    marginBottom: '1.2rem', letterSpacing: '-0.04em', lineHeight: 1,
                    color: cfg.priceColor,
                  }}>
                    {tier.price > 0 ? (
                      <>₵{Number(tier.price).toFixed(0)}<span style={{ fontSize: '0.85rem', fontWeight: 600, opacity: 0.65 }}>/{tier.duration === 'forever' ? 'lifetime' : `${tier.duration}mo`}</span></>
                    ) : (
                      <>Free<span style={{ fontSize: '0.85rem', fontWeight: 600, opacity: 0.65 }}>/forever</span></>
                    )}
                  </div>

                  {/* Features */}
                  <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 1.5rem 0', flexGrow: 1, display: 'flex', flexDirection: 'column', gap: '0.55rem' }}>
                    {features.map((feat, i) => (
                      <li key={i} style={{
                        fontSize: cfg.featured ? '0.92rem' : '0.84rem',
                        display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 500,
                        color: cfg.featureColor,
                      }}>
                        <span style={{ color: cfg.checkColor, fontWeight: 800 }}>✔</span>
                        {feat}
                      </li>
                    ))}
                  </ul>

                  {/* CTA */}
                  {!active ? (
                    <button
                      type="button"
                      onClick={() => handleUpgradeClick(tier.tier_name, tier.price)}
                      disabled={loading || tier.price <= 0}
                      style={{
                        width: '100%', borderRadius: '12px', padding: '0.9rem',
                        fontWeight: 700, fontSize: '0.88rem', border: 'none',
                        cursor: (loading || tier.price <= 0) ? 'not-allowed' : 'pointer',
                        opacity: (loading || tier.price <= 0) ? 0.5 : 1,
                        background: cfg.btnBg, color: cfg.btnText,
                        transition: 'all 0.2s ease', textTransform: 'capitalize',
                      }}
                    >
                      {loading ? 'Processing...' : tier.price > 0 ? `Upgrade to ${tier.tier_name}` : 'Current Free Tier'}
                    </button>
                  ) : (
                    <div style={{
                      width: '100%', textAlign: 'center', padding: '0.9rem', borderRadius: '12px',
                      fontWeight: 700, fontSize: '0.88rem',
                      background: 'rgba(16,185,129,0.15)', color: '#34d399',
                      border: '2px solid rgba(16,185,129,0.4)',
                    }}>
                      Current Plan
                    </div>
                  )}
                </motion.div>
              );
            }) : (
              <div style={{ color: 'rgba(255,255,255,0.4)', padding: '2rem' }}>Loading account tiers...</div>
            )}
          </div>
        </motion.div>

        {/* Paystack popup trigger — only mounts when config is fully ready */}
        {paystackConfig && (
          <PaystackButton
            key={paystackConfig.reference}
            config={paystackConfig}
            onVerified={handlePaystackSuccess}
            onCancelled={handlePaystackClose}
          />
        )}
      </div>
    </AnimatePresence>
  );
}
