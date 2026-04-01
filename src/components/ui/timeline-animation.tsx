"use client";
import React from "react";
import { motion, useInView, Variants } from "framer-motion";
import { cn } from "@/lib/utils";

interface TimelineContentProps {
  children: React.ReactNode;
  animationNum?: number;
  timelineRef: React.RefObject<HTMLDivElement | null>;
  customVariants?: Variants;
  className?: string;
  as?: React.ElementType;
}

export const TimelineContent = ({
  children,
  animationNum = 0,
  timelineRef,
  customVariants,
  className,
  as: Component = "div",
}: TimelineContentProps) => {
  const isInView = useInView(timelineRef, { once: true, margin: "-10%" });

  const defaultVariants: Variants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.5, delay: animationNum * 0.1 } },
  };

  const variants = customVariants || defaultVariants;

  return (
    <Component
      as={motion.div}
      initial="hidden"
      animate={isInView ? "visible" : "hidden"}
      variants={variants}
      custom={animationNum}
      className={cn(className)}
    >
      {children}
    </Component>
  );
};
