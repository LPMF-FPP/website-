"use client";

import * as React from "react";
import Link from "next/link";
import { Button } from "@dokpol/ui";
import { ThemeToggle } from "./theme-toggle";
import { MegaMenu } from "./mega-menu";

export function Header() {
  const [isScrolled, setIsScrolled] = React.useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = React.useState(false);

  React.useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 10);
    };
    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  return (
    <header
      className={`sticky top-0 z-[var(--z-sticky)] w-full border-b border-border bg-background/80 backdrop-blur-sm transition-shadow ${
        isScrolled ? "shadow-md" : ""
      }`}
    >
      <div className="container mx-auto">
        <div className="flex h-16 items-center justify-between">
          {/* Logo */}
          <Link href="/" className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 text-white font-bold text-lg">
              DS
            </div>
            <div className="hidden sm:block">
              <div className="text-lg font-bold text-foreground">Dokpol Style</div>
              <div className="text-xs text-muted-foreground">Design System</div>
            </div>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden lg:flex items-center space-x-1" aria-label="Main navigation">
            <Link href="/" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600 transition-colors">
              Beranda
            </Link>
            <MegaMenu />
            <Link href="/news" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600 transition-colors">
              Berita
            </Link>
            <Link href="/facility" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600 transition-colors">
              Faskes
            </Link>
            <Link href="/about" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600 transition-colors">
              Tentang
            </Link>
          </nav>

          {/* Actions */}
          <div className="flex items-center space-x-2">
            <ThemeToggle />
            <Button variant="primary" size="sm" className="hidden md:inline-flex">
              Login
            </Button>
            
            {/* Mobile menu toggle */}
            <button
              className="lg:hidden p-2"
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              aria-label="Toggle mobile menu"
              aria-expanded={isMobileMenuOpen}
            >
              <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {isMobileMenuOpen ? (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                )}
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Navigation */}
        {isMobileMenuOpen && (
          <nav className="lg:hidden border-t border-border py-4" aria-label="Mobile navigation">
            <div className="flex flex-col space-y-3">
              <Link href="/" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600">
                Beranda
              </Link>
              <Link href="/news" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600">
                Berita
              </Link>
              <Link href="/facility" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600">
                Faskes
              </Link>
              <Link href="/about" className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600">
                Tentang
              </Link>
              <Button variant="primary" size="sm" className="mx-3">
                Login
              </Button>
            </div>
          </nav>
        )}
      </div>
    </header>
  );
}
