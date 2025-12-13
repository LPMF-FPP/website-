"use client";

import { Card, CardContent, Badge, Button } from "@dokpol/ui";
import { formatRelativeTime } from "@dokpol/ui";
import Link from "next/link";

const newsItems = [
  {
    id: 1,
    title: "Program Vaksinasi Nasional Mencapai Target 80%",
    excerpt: "Program vaksinasi nasional telah berhasil mencapai target coverage 80% untuk kategori prioritas pertama...",
    category: "Kesehatan",
    publishedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000), // 2 days ago
    image: "https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?w=800&h=500&fit=crop",
    views: 1234,
  },
  {
    id: 2,
    title: "Peluncuran Rumah Sakit Modern di Jakarta Timur",
    excerpt: "Fasilitas kesehatan terbaru dilengkapi dengan teknologi medis canggih dan tenaga profesional bersertifikat...",
    category: "Infrastruktur",
    publishedAt: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000), // 5 days ago
    image: "https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=800&h=500&fit=crop",
    views: 2456,
  },
  {
    id: 3,
    title: "Workshop Pelatihan Tenaga Medis Profesional",
    excerpt: "Pelatihan intensif untuk meningkatkan kompetensi dan sertifikasi tenaga medis di seluruh Indonesia...",
    category: "Pendidikan",
    publishedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000), // 7 days ago
    image: "https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&h=500&fit=crop",
    views: 987,
  },
];

export function NewsGrid() {
  return (
    <section className="py-20 bg-muted/50">
      <div className="container mx-auto">
        <div className="flex justify-between items-end mb-12">
          <div>
            <h2 className="text-4xl font-bold text-foreground mb-4">
              Berita Terbaru
            </h2>
            <p className="text-lg text-muted-foreground">
              Update dan informasi terkini seputar layanan kesehatan
            </p>
          </div>
          <Button variant="outline" asChild className="hidden md:inline-flex">
            <Link href="/news">
              Lihat Semua
              <svg className="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </Button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {newsItems.map((news) => (
            <Link key={news.id} href={`/news/${news.id}`}>
              <Card className="group hover:shadow-xl transition-all duration-300 h-full overflow-hidden">
                {/* Image */}
                <div className="relative h-48 overflow-hidden bg-neutral-200 dark:bg-neutral-800">
                  <div
                    className="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-400 opacity-20 group-hover:opacity-30 transition-opacity"
                  />
                  <div className="absolute inset-0 flex items-center justify-center">
                    <svg className="h-16 w-16 text-neutral-300 dark:text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                </div>

                <CardContent className="p-6">
                  {/* Category & Date */}
                  <div className="flex items-center justify-between mb-3">
                    <Badge variant="default" className="bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                      {news.category}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {formatRelativeTime(news.publishedAt)}
                    </span>
                  </div>

                  {/* Title */}
                  <h3 className="text-xl font-bold text-foreground mb-3 group-hover:text-primary-600 transition-colors line-clamp-2">
                    {news.title}
                  </h3>

                  {/* Excerpt */}
                  <p className="text-sm text-muted-foreground mb-4 line-clamp-3">
                    {news.excerpt}
                  </p>

                  {/* Footer */}
                  <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <div className="flex items-center">
                      <svg className="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      <span>{news.views.toLocaleString()}</span>
                    </div>
                    <span className="text-primary-600 font-medium group-hover:underline">
                      Baca selengkapnya →
                    </span>
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>

        <div className="text-center mt-8 md:hidden">
          <Button variant="outline" asChild>
            <Link href="/news">
              Lihat Semua Berita
              <svg className="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </Button>
        </div>
      </div>
    </section>
  );
}
