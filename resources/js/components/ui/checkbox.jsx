import * as React from "react";

import { cn } from "@/lib/utils";

const Checkbox = React.forwardRef(({ className, onCheckedChange, ...props }, ref) => (
    <input
        ref={ref}
        type="checkbox"
        className={cn(
            "border-input text-primary focus:ring-ring data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground h-4 w-4 rounded border bg-background shadow-sm transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
            className,
        )}
        onChange={(event) => onCheckedChange?.(event.target.checked, event)}
        {...props}
    />
));
Checkbox.displayName = "Checkbox";

export { Checkbox };
