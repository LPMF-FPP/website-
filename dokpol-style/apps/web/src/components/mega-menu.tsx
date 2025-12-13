"use client";

import * as React from "react";
import Link from "next/link";

export function MegaMenu() {
  const [isOpen, setIsOpen] = React.useState(false);

  return (
    <div
      className="relative"
      onMouseEnter={() => setIsOpen(true)}
      onMouseLeave={() => setIsOpen(false)}
    >
      <button
        className="px-3 py-2 text-sm font-medium text-foreground hover:text-primary-600 transition-colors flex items-center"
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        Informasi
        <svg className="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute top-full left-0 w-screen max-w-4xl mt-2 bg-background border border-border rounded-lg shadow-lg p-6">
          <div className="grid grid-cols-3 gap-6">
            {/* Column 1 */}
            <div>
              <h3 className="font-semibold text-foreground mb-3">Tentang Kami</h3>
              <ul className="space-y-2">
                <li>
                  <Link href="/about/vision" className="text-sm text-muted-foreground hover:text-primary-600">
                    Visi & Misi
                  </Link>
                </li>
                <li>
                  <Link href="/about/structure" className="text-sm text-muted-foreground hover:text-primary-600">
                    Struktur Organisasi
                  </Link>
                </li>
                <li>
                  <Link href="/about/history" className="text-sm text-muted-foreground hover:text-primary-600">
                    Sejarah
                  </Link>
                </li>
              </ul>
            </div>

            {/* Column 2 */}
            <div>
              <h3 className="font-semibold text-foreground mb-3">Layanan</h3>
              <ul className="space-y-2">
                <li>
                  <Link href="/services/medical" className="text-sm text-muted-foreground hover:text-primary-600">
                    Pelayanan Medis
                  </Link>
                </li>
                <li>
                  <Link href="/services/lab" className="text-sm text-muted-foreground hover:text-primary-600">
                    Laboratorium
                  </Link>
                </li>
                <li>
                  <Link href="/services/consultation" className="text-sm text-muted-foreground hover:text-primary-600">
                    Konsultasi
                  </Link>
                </li>
              </ul>
            </div>

            {/* Column 3 */}
            <div>
              <h3 className="font-semibold text-foreground mb-3">Sub Satuan Kerja</h3>
              <ul className="space-y-2">
                <li>
                  <Link href="/units/biddokkes" className="text-sm text-muted-foreground hover:text-primary-600">
                    Biddokkes
                  </Link>
                </li>
                <li>
                  <Link href="/units/hospitals" className="text-sm text-muted-foreground hover:text-primary-600">
                    Rumah Sakit
                  </Link>
                </li>
                <li>
                  <Link href="/units/clinics" className="text-sm text-muted-foreground hover:text-primary-600">
                    Klinik
                  </Link>
                </li>
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
