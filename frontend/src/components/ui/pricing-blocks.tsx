"use client"

import { motion } from "motion/react"
import { Check } from "lucide-react"

export interface PricingTier {
  id: number | string
  tier_name: string
  price: number | string
  product_limit: number | string
  images_per_product: number | string
  ads_boost: number | boolean
  duration: string
  badge?: string | null
  priority?: string | null
  benefits?: string[] | string | null
}

export interface TierActionMeta {
  label?: string
  disabled?: boolean
  secondary?: boolean
}

interface PricingSimpleProps {
  tiers: PricingTier[]
  currentTier?: string
  loadingTier?: string | null
  onSelectTier?: (tier: PricingTier) => void
  showCta?: boolean
  getActionMeta?: (tier: PricingTier) => TierActionMeta
}

export function normalizeHex(input?: string | null) {
  if (!input) return null
  const value = input.trim()
  return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value) ? value : null
}

function expandHex(hex: string) {
  const normalized = hex.replace("#", "")
  if (normalized.length === 3) {
    return normalized
      .split("")
      .map((part) => part + part)
      .join("")
  }
  return normalized
}

function hexToRgb(hex: string) {
  const expanded = expandHex(hex)
  return {
    r: Number.parseInt(expanded.slice(0, 2), 16),
    g: Number.parseInt(expanded.slice(2, 4), 16),
    b: Number.parseInt(expanded.slice(4, 6), 16),
  }
}

export function rgba(hex: string, alpha: number) {
  const { r, g, b } = hexToRgb(hex)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function isLightColor(hex: string) {
  const { r, g, b } = hexToRgb(hex)
  const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255
  return luminance >= 0.62
}

function fallbackAccent(tierName: string) {
  switch (tierName) {
    case "premium":
      return "#ff9f0a"
    case "pro":
      return "#8e8e93"
    default:
      return "#0071e3"
  }
}

export function getTierAccent(tier: PricingTier) {
  return normalizeHex(tier.badge) || fallbackAccent((tier.tier_name || "basic").toLowerCase())
}

export function getTierRank(tierName?: string | null) {
  switch ((tierName || "basic").toLowerCase()) {
    case "premium":
      return 3
    case "pro":
      return 2
    default:
      return 1
  }
}

export function getDurationLabel(duration: string) {
  const value = (duration || "").trim().toLowerCase()

  if (value === "" || value === "0" || value === "forever") return "lifetime"
  if (value === "weekly") return "week"

  if (/^\d+$/.test(value)) {
    const count = Number.parseInt(value, 10)
    return `${count} month${count === 1 ? "" : "s"}`
  }

  const monthMatch = value.match(/^(\d+)_months?$/)
  if (monthMatch) {
    const count = Number.parseInt(monthMatch[1], 10)
    return `${count} month${count === 1 ? "" : "s"}`
  }

  const weekMatch = value.match(/^(\d+)_weeks?$/)
  if (weekMatch) {
    const count = Number.parseInt(weekMatch[1], 10)
    return `${count} week${count === 1 ? "" : "s"}`
  }

  return value
}

function parseBenefits(benefits?: PricingTier["benefits"]) {
  if (Array.isArray(benefits)) {
    return benefits.filter((benefit): benefit is string => typeof benefit === "string" && benefit.trim() !== "")
  }

  if (typeof benefits === "string") {
    try {
      const decoded = JSON.parse(benefits)
      return Array.isArray(decoded)
        ? decoded.filter((benefit): benefit is string => typeof benefit === "string" && benefit.trim() !== "")
        : []
    } catch {
      return []
    }
  }

  return []
}

export function getTierFeatures(tier: PricingTier) {
  const features = [
    `${Number(tier.product_limit || 0)} active listings`,
    `${Number(tier.images_per_product || 0)} images per product`,
    tier.ads_boost ? "Ads boost included" : "Standard listing rank",
  ]

  if (tier.priority) {
    features.push(`${tier.priority.charAt(0).toUpperCase()}${tier.priority.slice(1)} search priority`)
  }

  if ((tier.tier_name || "basic").toLowerCase() !== "basic") {
    features.push("Verified seller badge styling")
  }

  for (const benefit of parseBenefits(tier.benefits)) {
    features.push(benefit)
  }

  return features
}

export function formatTierPrice(price: PricingTier["price"]) {
  const amount = Number(price || 0)
  if (!Number.isFinite(amount) || amount <= 0) {
    return "Free"
  }

  const whole = Math.abs(amount - Math.round(amount)) < 0.001
  return `GHS ${amount.toFixed(whole ? 0 : 2)}`
}

export function getTierButtonTextColor(accent: string) {
  return isLightColor(accent) ? "#111111" : "#ffffff"
}

function defaultActionMeta(
  tier: PricingTier,
  currentTier: string,
  loadingTier: string | null,
): TierActionMeta {
  const tierName = (tier.tier_name || "basic").toLowerCase()

  if (currentTier === tierName) {
    return { label: "Current Plan", disabled: true }
  }

  if (loadingTier === tierName) {
    return { label: "Processing...", disabled: true }
  }

  if (Number(tier.price || 0) <= 0) {
    return { label: "Start Free", secondary: true }
  }

  return { label: "Get Started" }
}

export default function PricingSimple({
  tiers,
  currentTier = "basic",
  loadingTier = null,
  onSelectTier,
  showCta = true,
  getActionMeta,
}: PricingSimpleProps) {
  return (
    <section className="relative flex flex-col items-center py-12">
      <div className="flex w-full flex-col items-center justify-center gap-6 md:flex-row md:flex-wrap">
        {tiers.map((tier, index) => {
          const tierName = (tier.tier_name || "basic").toLowerCase()
          const accent = getTierAccent(tier)
          const actionMeta = getActionMeta?.(tier) ?? defaultActionMeta(tier, currentTier, loadingTier)
          const isActive = currentTier === tierName
          const features = getTierFeatures(tier).slice(0, 4)

          return (
            <motion.div
              key={tier.id}
              initial={{ scale: 0.9, opacity: 0 }}
              whileInView={{ scale: 1, opacity: 1 }}
              viewport={{ once: true, amount: 0.3 }}
              transition={{ type: "spring", duration: 0.5, delay: index * 0.08 }}
              className="bg-background text-foreground flex w-80 max-w-full flex-col items-center rounded-lg border px-8 py-6 text-center shadow-sm transition-transform hover:scale-105"
              style={{
                borderColor: rgba(accent, isActive ? 0.65 : 0.28),
                boxShadow: `0 16px 36px ${rgba(accent, 0.14)}`,
              }}
            >
              <div
                className="mb-2 rounded-full px-3 py-1 text-[0.66rem] font-extrabold uppercase tracking-[0.18em]"
                style={{ background: rgba(accent, 0.12), color: accent }}
              >
                {tier.tier_name}
              </div>
              <div className="mb-2 text-4xl font-extrabold" style={{ color: accent }}>
                {formatTierPrice(tier.price)}
              </div>
              <div className="text-muted-foreground mb-4 text-sm">
                {Number(tier.price || 0) > 0 ? `Perfect for ${tier.tier_name} sellers` : "Perfect for individuals getting started"}
                <div className="mt-1 text-xs font-semibold uppercase tracking-[0.14em]">
                  {Number(tier.price || 0) > 0 ? `/${getDurationLabel(tier.duration)}` : "No payment required"}
                </div>
              </div>
              <ul className="text-muted-foreground mb-6 w-full space-y-2 text-left text-xs">
                {features.map((feature) => (
                  <li key={`${tier.id}-${feature}`} className="flex items-start gap-2">
                    <Check className="mt-[1px] h-3.5 w-3.5 shrink-0" style={{ color: accent }} />
                    <span>{feature}</span>
                  </li>
                ))}
              </ul>
              {showCta ? (
                <button
                  type="button"
                  disabled={actionMeta.disabled || !onSelectTier}
                  onClick={() => onSelectTier?.(tier)}
                  className="w-full rounded px-4 py-2 font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                  style={{
                    background: actionMeta.secondary ? "transparent" : accent,
                    color: actionMeta.secondary ? accent : getTierButtonTextColor(accent),
                    border: `1px solid ${actionMeta.secondary ? rgba(accent, 0.34) : "transparent"}`,
                  }}
                >
                  {actionMeta.label}
                </button>
              ) : (
                <div
                  className="w-full rounded px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em]"
                  style={{
                    background: rgba(accent, 0.1),
                    color: accent,
                    border: `1px solid ${rgba(accent, 0.24)}`,
                  }}
                >
                  {isActive ? "Current Plan" : "Tier Overview"}
                </div>
              )}
            </motion.div>
          )
        })}
      </div>
    </section>
  )
}
