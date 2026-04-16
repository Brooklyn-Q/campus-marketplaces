import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"
import { motion } from "framer-motion"

const metalButtonVariants = cva(
  "relative inline-flex items-center justify-center whitespace-nowrap rounded-2xl text-sm font-semibold ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 overflow-hidden group",
  {
    variants: {
      variant: {
        default:
          "bg-[#0B2B26] border border-[#163832] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.1),_0_2px_4px_rgba(0,0,0,0.5)] hover:shadow-[0_8px_32px_rgba(35,83,71,0.4)] hover:border-[#235347] hover:-translate-y-1",
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
    VariantProps<typeof metalButtonVariants> {
  asChild?: boolean
}

const MetalButton = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : "button"
    return (
      <Comp
        className={cn(metalButtonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    )
  }
)
MetalButton.displayName = "MetalButton"

export const AnimatedMetalButton = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (props, ref) => {
    return (
      <motion.div whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className="inline-block">
        <MetalButton ref={ref} {...props} />
      </motion.div>
    )
  }
)

export { MetalButton, metalButtonVariants }
