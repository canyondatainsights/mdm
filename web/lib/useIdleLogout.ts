import { useEffect, useRef } from "react";

/**
 * Invoke `onIdle` after `minutes` with no user activity (mouse/key/scroll/touch). Only armed while
 * `enabled` is true. The latest `onIdle` is always used without re-binding listeners each render.
 */
export function useIdleLogout(onIdle: () => void, minutes = 30, enabled = true): void {
  const cb = useRef(onIdle);
  cb.current = onIdle;

  useEffect(() => {
    if (!enabled) return;
    const ms = minutes * 60 * 1000;
    let timer: ReturnType<typeof setTimeout>;
    const reset = () => {
      clearTimeout(timer);
      timer = setTimeout(() => cb.current(), ms);
    };
    const events = ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"];
    events.forEach((e) => window.addEventListener(e, reset, { passive: true }));
    reset();
    return () => {
      clearTimeout(timer);
      events.forEach((e) => window.removeEventListener(e, reset));
    };
  }, [minutes, enabled]);
}
