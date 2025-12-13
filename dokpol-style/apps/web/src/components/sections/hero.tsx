import { Button } from "@dokpol/ui";
import Link from "next/link";

export function Hero() {
  return (
    <section className="relative bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 text-white overflow-hidden">
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-10">
        <div className="absolute inset-0" style={{
          backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
        }} />
      </div>

      <div className="container mx-auto relative z-10">
        <div className="flex flex-col items-center justify-center min-h-[600px] text-center py-20">
          {/* Hero Content */}
          <div className="max-w-4xl">
            <div className="inline-flex items-center bg-white/10 backdrop-blur-sm rounded-full px-4 py-2 mb-6">
              <span className="text-sm font-medium">🎨 Production-Ready Design System</span>
            </div>
            
            <h1 className="text-5xl md:text-6xl lg:text-7xl font-bold mb-6 leading-tight">
              Dokpol Style
              <span className="block text-accent-400">Design System</span>
            </h1>
            
            <p className="text-xl md:text-2xl text-primary-100 mb-10 max-w-2xl mx-auto">
              Sistem desain komprehensif dengan dukungan tema Light/Dark, aksesibilitas WCAG 2.2 AA, dan performa tinggi.
            </p>

            {/* CTA Buttons */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button variant="secondary" size="lg" asChild>
                <Link href="/facility">
                  Cari Faskes
                  <svg className="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </Link>
              </Button>
              <Button variant="outline" size="lg" className="bg-white/10 border-white text-white hover:bg-white hover:text-primary-600" asChild>
                <Link href="/news">
                  Berita Terbaru
                  <svg className="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </Link>
              </Button>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-8 mt-16 max-w-3xl mx-auto">
              <div>
                <div className="text-4xl font-bold text-accent-400">150+</div>
                <div className="text-sm text-primary-200 mt-1">Komponen UI</div>
              </div>
              <div>
                <div className="text-4xl font-bold text-accent-400">98%</div>
                <div className="text-sm text-primary-200 mt-1">Aksesibilitas</div>
              </div>
              <div>
                <div className="text-4xl font-bold text-accent-400">< 2.5s</div>
                <div className="text-sm text-primary-200 mt-1">LCP Score</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
