import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"
import { motion } from "framer-motion"

const liquidGlassButtonVariants = cva(
  "relative inline-flex items-center justify-center whitespace-nowrap rounded-2xl text-sm font-semibold ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 overflow-hidden group",
  {
    variants: {
      variant: {
        default:
          "bg-white/5 border border-white/10 text-white backdrop-blur-xl shadow-[0_8px_32px_rgba(0,0,0,0.3)] hover:shadow-[0_15px_40px_rgba(142,182,155,0.4)] hover:border-[#8EB69B]/40 hover:-translate-y-1",
        primary:
          "bg-gradient-to-br from-[#8EB69B] to-[#235347] text-[#051F20] border border-white/20 shadow-[0_8px_32px_rgba(142,182,155,0.4)] hover:shadow-[0_15px_40px_rgba(142,182,155,0.5)] hover:border-white/40 hover:-translate-y-1 font-bold",
        destructive:
          "bg-red-500/10 border border-red-500/20 text-red-100 hover:bg-red-500/20 hover:border-red-500/40 hover:-translate-y-1 shadow-[0_8px_32px_rgba(239,68,68,0.2)]",
      },
      size: {
        default: "h-11 px-6 py-2",
        sm: "h-9 rounded-xl px-4",
        lg: "h-14 rounded-[20px] px-8 text-base",
        icon: "h-11 w-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof liquidGlassButtonVariants> {
  asChild?: boolean
}

const LiquidGlassButton = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, children, ...props }, ref) => {
    const Comp = asChild ? Slot : "button"
    
    return (
      <Comp
        className={cn(liquidGlassButtonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      >
        <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-[150%] skew-x-[45deg] group-hover:transition-all group-hover:duration-1000 group-hover:translate-x-[150%]"></div>
        <span className="relative z-10 flex items-center justify-center gap-2">{children}</span>
      </Comp>
    )
  }
)
LiquidGlassButton.displayName = "LiquidGlassButton"

// Animation Wrapper for Framer Motion interactions
export const AnimatedLiquidButton = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (props, ref) => {
    return (
      <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className="inline-block">
        <LiquidGlassButton ref={ref} {...props} />
      </motion.div>
    )
  }
)

export { LiquidGlassButton, liquidGlassButtonVariants }
