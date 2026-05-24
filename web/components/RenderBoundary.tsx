"use client";

import { Component, type ReactNode } from "react";

/** Catches render exceptions in a subtree so a bad parse can't blank the surrounding UI. */
export class RenderBoundary extends Component<{ fallback: ReactNode; children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch() { /* swallow — the fallback renders instead */ }
  render() { return this.state.failed ? this.props.fallback : this.props.children; }
}
