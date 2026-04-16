"use client";

import { useId, useState, useEffect } from "react";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { MoonIcon, SunIcon } from "lucide-react";

const SwitchToggleThemeDemo = () => {
  const id = useId();
  const [isDark, setIsDark] = useState(true);

  useEffect(() => {
    // Initial load
    if (document.documentElement.classList.contains('dark-mode')) {
        setIsDark(true);
    } else {
        setIsDark(false);
    }
  }, []);

  const handleToggle = (checked: boolean) => {
    setIsDark(checked);
    if (checked) {
        document.documentElement.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
    }
  };

  return (
    <div className="group inline-flex items-center gap-2">
      <span
        id={`${id}-light`}
        className={cn(
          "cursor-pointer text-left text-sm font-medium",
          isDark && "text-foreground/50 text-gray-500",
        )}
        style={{ color: isDark ? 'var(--text-muted)' : 'var(--text-main)', fontSize: '0.8rem' }}
        aria-controls={id}
        onClick={() => handleToggle(false)}
      >
        <SunIcon className="size-4" aria-hidden="true" />
      </span>

      <Switch
        id={id}
        checked={isDark}
        onCheckedChange={handleToggle}
        aria-labelledby={`${id}-light ${id}-dark`}
        aria-label="Toggle between dark and light mode"
      />

      <span
        id={`${id}-dark`}
        className={cn(
          "cursor-pointer text-right text-sm font-medium",
          isDark || "text-foreground/50 text-gray-500",
        )}
        style={{ color: !isDark ? 'var(--text-muted)' : 'var(--text-main)', fontSize: '0.8rem' }}
        aria-controls={id}
        onClick={() => handleToggle(true)}
      >
        <MoonIcon className="size-4" aria-hidden="true" />
      </span>
    </div>
  );
};

export default SwitchToggleThemeDemo;
